# Couche Infrastructure

Adaptateurs techniques : client API Blizzard et ses garde-fous de quota, importers, socle de référence DB2, taxonomie des collections, mappings d'extensions, journalisation. Cette couche isole les détails d'implémentation du domaine métier. Aucun import ne lit de fichier du disque au moment de l'import : le socle DB2 est chargé en base par `app:wow-reference-sync`, et le rangement des collections vient d'un instantané versionné.

---

## API Blizzard (`app/Infrastructure/Blizzard/`)

### `BlizzardApiClient`

Client HTTP vers l'API Blizzard (OAuth2 client credentials). Gère automatiquement l'obtention et le renouvellement du token d'accès.

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getAccessToken()` | — | `string` | Retourne le token d'accès, le renouvelle si expiré. |
| `get()` | `string $endpoint, array<string, string> $query` | `array<array-key, mixed>` | Requête GET synchrone avec token application, réponse décodée brute. Ceux qui mettent en cache des données plutôt que des objets (journal d'instance, arbre de talents) la gardent telle quelle ; les autres appelants l'enveloppent aussitôt dans un `ResponsePayload`. C'est l'une des frontières de `mixed-boundaries.txt`. |
| `getResponse()` | `string $endpoint, array<string, string> $query` | `ResponsePayload` | La porte typée : la même requête, lue par un `ResponsePayload`. C'est le chemin par défaut. |
| `getIfModifiedSince()` | `string $endpoint, ?string $lastModified, array<string, string> $query` | `?ResponsePayload` | Revalidation d'un index par `If-Modified-Since`. Rend `null` sur un 304, qui veut dire *inchangé* et non *échoué* : le corps vide d'un 304 n'atteint jamais le décodage JSON. |
| `getWithUserToken()` | `string $endpoint, string $userToken, array<string, string> $query` | `ResponsePayload` | Requête GET avec token utilisateur (OAuth2 user flow). |
| `getAsync()` | `string $endpoint, array $query` | `PromiseInterface` | Requête GET asynchrone (Guzzle promise). |
| `currentBuild()` | — | `?string` | Le build WoW servi par l'API, lu dans l'en-tête `battlenet-namespace` (voir `BlizzardNamespace`). Une sonde ne part que si aucune réponse n'en a encore livré un. |
| `lastSeenBuild()`, `lastModifiedSeen()` | — | `?string` | Le dernier build et le dernier `Last-Modified` vus passer, que l'import retient pour sa prochaine revalidation (`ImportBuildGate`). |
| `getRegion()` | — | `string` | Région configurée (ex. : `eu`). |
| `getCurrentMythicSeasonId()`, `getCurrentPvpSeasonId()` | — | `int` | Saison Mythique+ ou JcJ courante. `0` hors saison, valeur d'absence attendue par les appelants. |
| `getClient()`, `getBaseOptions()` | — | `Client`, `array{headers, query}` | Le client Guzzle sous-jacent, et les en-têtes et paramètres de base d'une requête statique. Plus aucun appelant dans l'application : seuls leurs tests les exercent. |

---

### `BlizzardNamespace`

Lecture de l'en-tête `battlenet-namespace`, renvoyé par Blizzard sur chaque réponse. Les namespaces versionnés ont la forme `static-12.1.0_68914-eu`, dont le segment central identifie le build WoW servi. Un namespace sans build — `profile-eu` — n'en rend aucun plutôt qu'une valeur approchante.

C'est le levier le moins coûteux du pipeline d'import : les données `static` ne changent qu'au patch, donc comparer ce build à celui du dernier import rend la plupart des réimports inutiles.

---

### `ImportBuildGate`

Décide s'il y a lieu de réimporter une entité, en comparant le build servi par l'API à celui du dernier import réussi.

| Méthode | Retour | Description |
|---|---|---|
| `isUpToDate(string $entity, ?string $currentBuild)` | `bool` | Faux si l'entité n'a jamais été importée, si son build diffère, ou si le build courant est inconnu — dans le doute on importe. |
| `remember(string $entity, string $build, ?string $lastModified)` | `void` | Enregistre le build d'un import réussi. |
| `lastModifiedFor(string $entity)` | `string\|null` | Date à renvoyer en `If-Modified-Since` à la prochaine revalidation. |

L'état vit dans `wow_import_states`, **en base et non en cache** : c'est un état d'import, il doit survivre à un `cache:clear`. Il est tenu **par entité et non globalement**, parce qu'un patch peut ne toucher que les recettes et que réimporter les 22 000 apparences pour autant serait absurde.

---

### `BlizzardBatchImporter`

Façade qui délègue chaque type d'import à l'importeur spécialisé correspondant.

**Méthodes**

| Méthode | Description |
|---|---|
| `importAchievements()` | Lance l'import des hauts-faits. |
| `importQuests(array $areaExpansionMap, array $questExpansionMap, array $questFactionMap, array $zoneFactionMap)` | Lance l'import des quêtes avec les cartes d'extension et de faction tirées du socle. |
| `importMounts()` | Lance l'import des montures. |
| `importPets()` | Lance l'import des mascottes. |
| `importDecor()` | Lance l'import des décorations. |
| `importProfessions(array $recipeFactionMap)` | Lance l'import des professions et recettes. |
| `importAppearanceChunk(bool $full, int $offset, int $timeBoxSeconds, ?int $limit, ?Closure $stopRequested)` | Une tranche bornée dans le temps du balayage de la garde-robe, qui rend un `AppearanceImportProgress` pour que l'appelant reprenne où elle s'est arrêtée. |
| `tagMirrorQuestFactions(array $reputationFactionMap)` | Identifie et tague les quêtes miroirs Alliance/Horde. |
| `tagMirrorRecipeFactions()` | Identifie et tague les recettes miroirs Alliance/Horde. |

---

### Trait `ImportsFromBlizzardApi`

Mutualisé par les importeurs spécialisés. Fournit les mécanismes de requêtes asynchrones avec reprise sur erreur.

`fetchBatchAsync()` maintient une **concurrence constante** : dès qu'une requête se termine, la suivante part, au lieu d'attendre le traînard d'un lot. Les promesses sont produites par un générateur, donc créées au fur et à mesure et non toutes d'avance — c'est ce qui borne la mémoire sur les gros balayages.

La régulation par seconde n'est pas de son ressort : elle appartient à `RateLimitingMiddleware`, seul endroit où elle est correcte. Le nombre de requêtes en vol se règle par `BLIZZARD_IMPORT_CONCURRENCY`, 20 par défaut.

Une réponse 429 fait redescendre la concurrence de moitié à chaque tentative, plancher à 5, et le journal le dit. Un 304 est compté comme inchangé, jamais comme un échec.

**Constantes**

| Constante | Valeur | Description |
|---|---|---|
| `RATE_LIMIT_WAIT_S` | `10` | Attente en secondes lors d'un 429. |
| `MAX_RETRIES` | `5` | Tentatives max par requête. |
| `DEFAULT_CONCURRENCY` | `20` | Requêtes en vol, à défaut de `BLIZZARD_IMPORT_CONCURRENCY`. |
| `MAX_RATE_LIMIT_RETRIES` | `3` | Retry max sur 429. |
| `MAX_SERVER_ERROR_RETRIES` | `1` | Retry max sur 5xx. |

**Méthodes protégées**

| Méthode | Description |
|---|---|
| `fetchWithRetry(string $endpoint, int $attempt)` | Requête avec retry automatique. Retourne un `?ResponsePayload`, `null` sur 404 ou abandon. |
| `fetchBatchAsync(array $endpoints, ?int $concurrency)` | Exécute un batch de requêtes en parallèle. Retourne `array<key, ResponsePayload\|null>` ; une réponse qui n'est pas un objet JSON est traitée comme un JSON invalide. |

Les importers lisent chaque réponse par les accesseurs typés de `ResponsePayload` : un champ absent prend sa valeur par défaut, un champ du mauvais type lève une exception de contrat plutôt que de produire une ligne fausse.

---

### Le quota Blizzard : deux garde-fous

Blizzard limite les appels à la seconde et publie un quota de 36 000 requêtes par heure. L'application tient deux garde-fous distincts, qui ne se remplacent pas.

| | `RateLimitingMiddleware` | `HourlyBudgetGuard` |
|---|---|---|
| Échelle | La seconde | L'heure glissante |
| Limite | 80 requêtes | 34 000 requêtes, et un plafond réservé plus bas pour les imports |
| Où vit l'état | En mémoire du processus | Dans Redis, sur un index à lui |
| Ce qu'il fait | Retient la requête jusqu'à ce qu'une place se libère | Compte chaque requête ; dit à l'import combien de temps attendre |
| Qui il protège | Chaque processus contre une rafale | Le quota partagé entre le site et les imports |

`BlizzardServiceProvider` les câble ensemble : le client Blizzard est un singleton, dont la pile Guzzle porte le middleware, qui porte le budget. Toute requête qui passe par `BlizzardApiClient`, synchrone ou asynchrone, site ou import, est donc régulée à la seconde **et** comptée à l'heure, sans que l'appelant ait à y penser.

La fenêtre d'une seconde vit dans la mémoire du processus : elle régule chaque processus, pas leur somme. C'est le budget horaire, en Redis, qui voit la somme — le worker et le site s'additionnent dans le même compteur.

Le budget ne bloque rien de lui-même : c'est un compteur qu'on interroge. Les imports le consultent par `secondsUntilAvailable()` avant chaque passe, avec le plafond réservé `BLIZZARD_IMPORT_HOURLY_CEILING` (30 000 par défaut, `services.blizzard.import_hourly_ceiling`). Au-delà, l'import se met en attente au lieu d'appeler. La différence avec la limite de 34 000 est la marge laissée au trafic du site : un import complet ne doit jamais rendre une fiche personnage inaccessible.

**Tout nouvel appel massif à l'API passe par ces deux mécanismes** : par `BlizzardApiClient`, pour être régulé et compté, et par une consultation de `secondsUntilAvailable()` avec le plafond réservé avant chaque lot, pour attendre plutôt que d'entamer la marge du site. `ImportPipeline` et `AppearanceImporter` montrent la forme.

---

### `RateLimitingMiddleware`

Middleware Guzzle qui limite les requêtes à 80/seconde en insérant des pauses (`BACKOFF_US = 50 000 µs`). C'est le point de passage unique de tous les appels Blizzard, site et imports confondus, et c'est là que chaque requête est comptée au budget horaire.

---

### `HourlyBudgetGuard`

Second garde-fou, complémentaire du précédent : là où le middleware régule la seconde, celui-ci tient une fenêtre glissante d'une heure sur le quota Blizzard de 36 000 requêtes, porté par `PUBLISHED_HOURLY_QUOTA`. `HOURLY_LIMIT` est fixé à 34 000, marge de sécurité comprise.

Un compteur par minute dans Redis, incrémenté par `INCRBY` et détruit par son propre TTL. Consommer ne lit rien : deux processus qui comptent en même temps — le worker et une requête du site — s'additionnent au lieu de s'écraser. La lecture se fait par un `MGET` des soixante clés de la fenêtre, et n'est nécessaire qu'au moment de décider d'attendre.

| Méthode | Retour | Description |
|---|---|---|
| `consume(int $count)` | `void` | Compte des requêtes. Un lot coûte une opération, pas une par requête. |
| `secondsUntilAvailable(int $count, ?int $ceiling)` | `int` | Secondes à attendre avant de pouvoir consommer `$count` sous le plafond. Les imports passent un plafond réservé, inférieur à `HOURLY_LIMIT`, pour laisser de la marge au trafic du site. |
| `usedInWindow()` | `int` | Appels comptés sur l'heure glissante. |

**Le budget a son propre index Redis**, distinct de celui du cache. Ce n'est pas cosmétique : `cache:clear` émet un `FLUSHDB`, et remettre ce compteur à zéro autoriserait un import à consommer un second quota dans la même heure réelle, alors que Blizzard, lui, ne réinitialise rien.

---

### `ExpansionTierMatcher`

Utilitaire statique qui mappe les noms de tier de réputation français/anglais à un ID d'extension (0–11).

**Méthode**

```
ExpansionTierMatcher::match(string $name): ?int
```

---

### `SearchIdWindow`

La grille de fenêtres d'identifiants, partagée par tous les balayages de recherche. Une fenêtre est un index `w` désignant l'intervalle `[w × 1000, w × 1000 + 999]` ; mille identifiants tenant toujours sous la page maximale de l'API, une fenêtre ne pagine jamais. La grille est fixe, ce qui permet de désigner une fenêtre par un entier et donc de reprendre un balayage interrompu — et de réutiliser le même offset d'une passe à la suivante.

| Méthode | Retour | Description |
|---|---|---|
| `countFor(int $highestId)` | `int` | Nombre de fenêtres couvrant les identifiants jusqu'à celui-ci. |
| `holding(int $id)` | `int` | Index de la fenêtre dont l'intervalle contient cet identifiant. |
| `query(int $window)` | `string` | Paramètres de recherche de la fenêtre, à concaténer à un endpoint. |

Un identifiant ou un index négatif lève `InvalidArgumentException` : c'est un appelant qui s'est trompé, pas une donnée à corriger silencieusement.

Le trait `SweepsIdWindows` porte la boucle commune aux balayages — construire les endpoints, lancer le lot, réduire chaque réponse puis la libérer aussitôt. Une réponse de recherche porte toutes les locales quelle que soit celle demandée, soit jusqu'à 1,2 Mo par fenêtre : garder les corps décodés d'un lot entier multiplierait le pic mémoire par le nombre de fenêtres.

---

### `ItemSearchSweep`

Balayage du catalogue d'items sur cette grille. Un document de recherche d'item porte déjà nom, qualité, media et apparences, là où le détail unitaire demandait un appel par apparence.

| Méthode | Retour | Description |
|---|---|---|
| `windowCountFor(int $highestId)` | `int` | Nombre de fenêtres couvrant le catalogue jusqu'à cet identifiant. |
| `highestItemId()` | `?int` | Borne du balayage, lue sur une recherche triée par identifiant décroissant. |
| `sweepItems(array $windows, callable $onDocument)` | `void` | Balaie des fenêtres de `data/wow/search/item` et remet chaque document à l'appelant. |
| `sweepItemMedia(array $windows)` | `array<int, MediaSearchDocument>` | Icônes des items de ces fenêtres, indexées par identifiant de media. |

L'appelant choisit combien de fenêtres il traite d'un coup : c'est ce nombre qui fixe le pic mémoire (`services.blizzard.appearance_window_batch`, 5 par défaut).

---

### `MediaSearchSweep`

Balayage des media sur la même grille : une fenêtre rend l'icône de tout ce que l'espace du tag contient dans son intervalle, là où le media unitaire demanderait un appel par entrée.

```
sweep(array $windows, MediaSearchTag $tag): array<int, MediaSearchDocument>
```

`MediaSearchTag` énumère les espaces filtrables — `Item`, `Achievement`. Chacun est son propre espace d'identifiants : un media d'item porte l'identifiant de l'item, un media de haut fait celui du haut fait, et rien ne garantit qu'un identifiant désigne la même chose d'un tag à l'autre.

---

### `CollectionSearchSweep`

Balayage des montures et des décorations sur la même grille.

| Méthode | Retour | Description |
|---|---|---|
| `sweepMounts(array $windows)` | `array<int, MountSearchDocument>` | Montures de ces fenêtres, indexées par identifiant. |
| `sweepDecors(array $windows)` | `array<int, DecorSearchDocument>` | Décorations de ces fenêtres, indexées par identifiant. |

Ces deux recherches portent le même contenu que le détail unitaire correspondant : quatre fenêtres rendent les 1 669 montures et vingt-huit les 2 124 décorations, contre 3 793 appels de détail. Les mascottes n'ont pas d'équivalent — `data/wow/search/pet` répond 404 — et passent donc par leur détail, un appel par mascotte.

Contrairement au balayage d'items, les documents sont petits et les fenêtres peu nombreuses : le résultat tient en mémoire d'un bloc et n'a pas à être remis au fil de l'eau.

---

### `RenderedIconProbe`

```
servedUrls(array $urls): list<string>
```

Contrôle qu'une icône composée est bien servie par le CDN de rendu, et ne rend que celles qui répondent.

Les icônes de montures sont les seules que l'application compose elle-même, à partir du `SpellIconFileDataID` du socle. Or le CDN ne publie pas tous les identifiants de fichier du client : **70 montures sur 1 659 répondent 403**, et aucune variante de taille, de région ou d'icône active ne répond à leur place. Une URL qui échoue est **pire qu'une absence d'URL** — le front rend son gabarit de repli sur un `null`, et une image brisée sur un lien mort.

Ce CDN n'est pas l'API Blizzard et ne consomme pas son quota, mais le contrôle reste borné : les URL en double ne sont demandées qu'une fois, par lots de cinquante, et `MountImporter` ne soumet que celles qui ne figurent pas déjà en base. Une URL déjà écrite a déjà passé le contrôle : en régime stable, une passe n'envoie aucune requête.

---

### Hiérarchie des hauts faits

`AchievementCategorySweep` récupère l'arborescence complète : l'index des catégories, puis le détail de chacune — cent soixante-dix appels aujourd'hui.

```
fetchTaxonomy(): ?AchievementTaxonomy
```

**C'est tout ou rien.** Une catégorie manquante, c'est un pan entier du catalogue absent du lot, que le balayage des lignes périmées supprimerait ensuite : un échec rend `null` et l'importer abandonne sans toucher au catalogue. Les catégories de guilde vivent dans une autre liste de l'index et ne sont pas demandées.

`AchievementTaxonomy` en déduit le rangement, et c'est une fonction pure de la liste des `AchievementCategoryDocument` :

| Méthode | Retour | Description |
|---|---|---|
| `fromCategories(array $categories)` | `self` | Construit la taxonomie (statique). |
| `placements()` | `list<AchievementPlacement>` | Un `AchievementPlacement` par haut fait : `id`, `name`, `categoryName`, `expansionId`. |
| `unrankedCategories()` | `array<string, int>` | Catégories dont rien ne date les hauts faits, et combien chacune en porte. |
| `staleDatingSignals()` | `list<string>` | Signes que la datation par la catégorie se périme. |

**La catégorie racine nomme, la sous-catégorie date.** L'extension est celle du premier ancêtre daté, la racine exclue — aucune racine ne porte de nom d'extension. L'appariement passe par `ExpansionTierMatcher`, sans second mécanisme concurrent.

Ces catégories suivent la sortie du contenu et non la géographie : « Reprise des Chants éternels », situé en Quel'Thalas, est rangé sous Midnight et non sous Royaumes de l'Est. **Une extension ne se déduit donc jamais d'un nom de lieu.** Le garde-fou est posé dans la taxonomie elle-même : une sous-catégorie à nom de continent — `Kalimdor`, `Royaumes de l'Est`, `Outreterre`, `Norfendre` — qui reçoit un haut fait d'identifiant supérieur ou égal à 40 000 sort dans `staleDatingSignals()`, et l'import le signale. Ces quatre catégories sont les seaux Classic de l'onglet Quêtes, et n'ont jamais reçu de contenu récent.

Ce que rien ne date tombe dans `ExpansionId::UNCLASSIFIED`, pas dans l'extension 0, et figure au rapport d'import : un rangement qu'on ignore doit se lire, jamais se découvrir par un tri devenu faux. Un haut fait listé sous deux catégories est arbitré par un ordre total — le rangement daté d'abord, puis la plus petite catégorie — pour qu'un import le range toujours au même endroit.

---

### Lecture typée (`ResponsePayload`)

Tout JSON venu de l'extérieur — réponse de l'API Blizzard, export curé de SimpleArmory — est enveloppé dans un `ResponsePayload` dès son décodage, et n'en sort que typé. C'est là que le `mixed` de `json_decode` s'arrête.

| Lecture | Absent | Mauvais type |
|---|---|---|
| `requiredString`, `requiredInt`, `requiredFloat`, `requiredObject` | `MissingFieldException` | `UnexpectedFieldTypeException` |
| `optionalString`, `optionalInt`, `optionalFloat`, `optionalBool`, `optionalObject` | `null` | `UnexpectedFieldTypeException` |
| `objectList`, `intList` | liste vide | `UnexpectedFieldTypeException`, index compris dans le chemin |
| `stringMap`, `objectMap` | table vide | `UnexpectedFieldTypeException` ; une clé non entière est refusée |
| `lenientInt` | `null` | `null` ; accepte tout ce qu'accepte `is_numeric` |
| `lenientString` | `null` | `null` |

Les tables indexées par identifiant (`stringMap`, `objectMap`, et `entries()` pour une table imbriquée) acceptent une liste au même titre qu'un objet : un objet JSON aux clés `"0"`, `"1"`… revient en liste une fois décodé. Les lectures tolérantes ne servent qu'où le code d'origine tolérait déjà, et où un test fixe cette tolérance ; partout ailleurs, un écart au contrat lève. Les messages nomment le chemin complet du champ (`subcats.0.items.3.ID`) et la source. `isEmpty()` distingue une réponse vide — un endpoint en 404 — d'une réponse lacunaire.

Les deux exceptions de lecture descendent de `BlizzardContractException`, classe abstraite qui ne sert qu'à les attraper ensemble : un appelant qui veut traiter « l'amont n'a pas tenu son contrat », quelle qu'en soit la forme, attrape la mère. C'est ce que fait `SimpleArmoryTaxonomyReader`, qui la traduit en `TaxonomySourceUnavailableException::malformed()`. Partout ailleurs, elle remonte : une réponse qui trahit son contrat est une erreur à voir, pas une donnée à deviner.

**Où le `mixed` entre, et où il s'arrête.** Une valeur de forme inconnue n'a le droit d'entrer que dans les fichiers listés par `mixed-boundaries.txt`, chacun avec son motif, et `make mixed-check` fait échouer la CI si un autre fichier écrit `mixed`. Côté Blizzard, il y en a quatre : `BlizzardApiClient`, dont `get()` rend le corps décodé ; `ResponsePayload`, qui le tient et n'en rend que des valeurs typées ; `UnexpectedFieldTypeException`, qui transporte la valeur fautive, quel que soit son type ; le trait `ImportsFromBlizzardApi`, où la raison du rejet d'une promesse Guzzle arrive sans type et devient un `Throwable` dès sa réception. Tout le reste du code ne voit que des objets de réponse ou des types précis.

### Documents de recherche (`Blizzard/Responses/`)

Lecture typée des documents rendus par les endpoints de recherche, construite sur `ResponsePayload`. Le `mixed` sorti du décodage JSON s'arrête là.

| Classe | Champs exposés |
|---|---|
| `ItemSearchDocument` | `id`, `nameFr`, `quality` (OverallQualityID numérique), `mediaId`, `categoryFr`, `appearanceIds` |
| `MediaSearchDocument` | `id`, `iconUrl`, `fileDataId` |
| `AchievementCategoryDocument` | `id`, `name`, `parentId`, `achievements` (identifiant → nom) |
| `AchievementDocument` | `id`, `points`, `faction` |
| `MountSearchDocument` | `id`, `nameFr`, `sourceType` |
| `DecorSearchDocument` | `id`, `nameFr`, `itemId` |
| `PetDocument` | `id`, `nameFr`, `iconUrl`, `creatureId`, `sourceType` |

Le nom français tombe sur le nom anglais quand la locale française manque. Un media sans asset `icon` est un cas normal, traité par un repli côté appelant, pas une réponse invalide.

Une catégorie porte ses propres hauts faits **et** des sous-catégories qui portent les leurs : une racine n'est pas un simple conteneur, et « Quêtes » en compte trente-quatre en propre. `AchievementDocument` est réduit aux deux champs que la hiérarchie ne porte pas — les points, et la faction lue dans `requirements.faction.type` : ce sont les seules raisons d'appeler le détail d'un haut fait.

Les trois documents de collection sont réduits de la même façon. `MountSearchDocument` ignore délibérément la faction que le document porte : aucune colonne ne l'accueille. `PetDocument` lit un détail, pas une recherche, donc son nom est du texte et non une carte de locales — et il porte l'icône en clair, ce qu'aucun autre endpoint de collection ne fait.

`TrimmedText::firstNonEmpty(?string ...$candidates)` rend la première valeur utilisable parmi plusieurs, débarrassée de son remplissage. Deux besoins s'y rejoignent : le français manque parfois là où l'américain est rempli, et certains libellés de l'API traînent un CRLF — le haut fait 13503 en est le cas connu.


### Réponses du profil de personnage (`Blizzard/Responses/Profile/`)

Un objet par endpoint que la fiche personnage consomme, construit sur `ResponsePayload`. `CharacterProfileService` les reçoit par `BlizzardApiClient::getResponse()` pour les appels synchrones et par `fetchPayloadsAsync()` du trait `FetchesProfileEndpoints` pour les appels parallèles, qui construit un `ResponsePayload` par réponse ; un 404, une erreur définitive ou un abandon après les tentatives donnent une réponse vide.

| Classe | Endpoint | Champs exposés |
|---|---|---|
| `CharacterSummaryResponse` | `profile/wow/character/{realm}/{name}` | `name`, `realmName`, `raceName`, `classId`, `className`, `level`, `equippedItemLevel`, `factionName`, `guildName` |
| `CharacterMediaResponse` | `…/character-media` | `avatarUrl` : le second asset (médaillon), sinon le premier |
| `CompletedQuestsResponse` | `…/quests/completed` | `questIds` |
| `CharacterAchievementsResponse` | `…/achievements` | `completedAchievementIds` (horodatage de complétion présent), `totalPoints` |
| `CharacterMountsResponse` | `…/collections/mounts` | `mountIds` |
| `CharacterPetsResponse` | `…/collections/pets` | `speciesIds` : l'espèce, que le catalogue référence, pas l'exemplaire |
| `CharacterDecorResponse` | `…/collections/decor` | `decorIds` |
| `CharacterTransmogsResponse` | `…/collections/transmogs` | `appearanceIds`, aplatis sur tous les emplacements |
| `CharacterEquipmentResponse` | `…/equipment` | `items`, et `equippedItemIds()` pour aller chercher les icônes |
| `EquippedItemEntry` | un objet porté | `slotType`, `slotName`, `itemId`, `name`, `qualityType`, `itemLevel` |
| `CharacterReputationsResponse` | `…/reputations` | `standings` |
| `ReputationStanding` | une réputation | `factionId`, `factionName`, `standingName`, `tier`, `value`, `max`, `raw`, `renownLevel` |
| `CharacterProfessionsResponse` | `…/professions` | `professions`, principaux puis secondaires |
| `CharacterProfession` | un métier | `professionId` (obligatoire), `professionName`, `skillPoints`, `maxSkillPoints`, `tiers` |
| `ProfessionTier` | un palier de métier | `id`, `name`, `skillPoints`, `maxSkillPoints`, `knownRecipeIds` |
| `CharacterRaidsResponse` | `…/encounters/raids` | `instancesOf(int $expansionId)`, nul si l'extension est absente, et `instanceIdsOf(int $expansionId)` |
| `RaidInstance` | une instance de raid | `id`, `name`, `modes` |
| `RaidMode` | une difficulté | `difficultyType`, `completedCount`, `totalCount`, `encounters` |
| `RaidEncounterProgress` | un boss vaincu | `id`, `name`, `lastKillTimestamp` |
| `JournalInstanceResponse` | `data/wow/journal-instance/{id}` | `name`, `encounterNames` (identifiant → nom français) |
| `MythicKeystoneSeasonResponse` | `…/mythic-keystone-profile/season/{id}` | `seasonId`, `rating`, `ratingColor`, `bestRuns` |
| `MythicRun` | une entrée de `best_runs` | donjon, niveau, durée, horodatage, dans les temps, cote et cote de donjon avec leurs couleurs, `members` |
| `MythicRunMember` | un membre de `members` | `name`, `realmName`, `specializationName`, `equippedItemLevel` |
| `RatingColor` | la `color` d'une cote | `r`, `g`, `b`, `a`, et `toArray()` pour la prop transmise au front |

Un personnage supprimé, renommé ou jamais joué rend des réponses lacunaires, et un endpoint en 404 rend une réponse vide : presque tout y est donc optionnel, et les valeurs de repli (`''`, `0`) sont posées par le service. Seules les références qui structurent une entrée sont obligatoires — la `mount` d'une monture collectée, les quatre canaux d'une couleur, le `profession` d'un métier et l'identifiant d'une recette connue. Un champ présent mais du mauvais type lève `UnexpectedFieldTypeException`, sauf l'identifiant d'une apparence, lu par `lenientInt()` qui accepte aussi une chaîne numérique comme le faisait le code d'origine.

Les agrégateurs de progression reçoivent ces objets, jamais la réponse décodée.

### Media d'un objet ou d'un sort

`MediaIconResponse` lit `data/wow/media/item/{id}` et `data/wow/media/spell/{id}`, qui ont la même forme, et n'en garde que `iconUrl` : la valeur du premier asset `icon` qui en porte une. Il sert aux icônes d'équipement de la fiche et aux icônes de sorts de l'onglet talents.

### Talents (`Blizzard/Responses/Talent/`)

| Classe | Endpoint | Champs exposés |
|---|---|---|
| `CharacterSpecializationsResponse` | `profile/wow/character/{realm}/{name}/specializations` | `activeSpecializationId`, `activeLoadout` |
| `TalentLoadout` | un loadout | `classTalents`, `specTalents`, `heroTalents` (listes de `SelectedTalent`), `heroTreeId` |
| `SelectedTalent` | un nœud retenu | `nodeId`, `rank`, `talentId` (le talent choisi sur un nœud à choix) |
| `PlayableSpecializationResponse` | `data/wow/playable-specialization/{id}` | `talentTreeId`, extrait du lien `spec_talent_tree.key.href` |
| `TalentTreeResponse` | `data/wow/talent-tree/{treeId}/playable-specialization/{specId}` | `className`, `specName`, `specId`, `classNodes`, `specNodes`, `heroTrees`, et `spellIds()` |
| `HeroTalentTree` | un arbre héroïque | `id`, `name`, `specializationIds`, `nodes` |
| `TalentNode` | un nœud | `id`, `type`, `ranks`, `lockedBy`, `unlocks`, `displayCol`, `displayRow` |
| `TalentRank` | un rang | `tooltip` (nœud simple), `choices` (nœud à choix) |
| `TalentTooltip` | un tooltip ou une option | `talentId`, `talentName`, `spellId` |

Le loadout actif est le premier loadout actif de la spécialisation active. Une spécialisation peut apparaître plusieurs fois dans la réponse, et la recherche continue tant qu'aucun loadout actif n'est trouvé. Un tooltip vide compte comme absent : le rang n'affiche alors rien. `TalentTreeResponse::spellIds()` rend les sorts de tous les rangs et de toutes les options, arbres héroïques compris, sans doublon et dans l'ordre de lecture : ce sont ceux dont on va chercher l'icône.

Les dépendances entre nœuds (`locked_by`, `unlocks`) sont lues par `ResponsePayload::intList()`, qui exige des entiers.

### PvP (`Blizzard/Responses/Pvp/`)

| Classe | Endpoint | Champs exposés |
|---|---|---|
| `PvpSummaryResponse` | `profile/wow/character/{realm}/{name}/pvp-summary` | `isEmpty`, `honorLevel`, `honorableKills`, `battlegroundStatistics`, `bracketSlugs` (lus dans les liens `pvp-bracket/{slug}`, sans doublon) |
| `PvpBracketResponse` | `…/pvp-bracket/{slug}` | `isEmpty`, `seasonId`, `rating`, `seasonStatistics`, `weeklyStatistics`, `tierId`, `specializationName` |
| `PvpMatchStatistics` | un bloc `*_match_statistics` | `played`, `won`, `lost` |
| `PvpTierResponse` | `data/wow/pvp-tier/{id}` | `name` ; l'icône vient de `MediaIconResponse` sur `data/wow/media/pvp-tier/{id}` |
| `PvpLeaderboardIndexResponse` | `data/wow/pvp-season/{id}/pvp-leaderboard/index` | `slugs` |
| `PvpLeaderboardResponse` | `data/wow/pvp-season/{id}/pvp-leaderboard/{slug}` | `entries` |
| `PvpLeaderboardEntry` | une ligne de classement | `rank`, `characterName`, `realmSlug`, `factionType`, `rating`, `statistics` |

Un bracket qu'on n'a pas pu lire rend une réponse vide, signalée par `isEmpty` plutôt que par une exception. Les compteurs (rating, rang, honneur, parties) sont lus par `lenientInt()` et valent zéro à défaut : l'ancien code les lisait par `is_numeric`, et une chaîne numérique passe toujours.

### Index de saison

`SeasonIndexResponse` lit `mythic-keystone/season/index` et `pvp-season/index`, servis à l'identique. `currentSeasonId` est nul entre deux saisons, ce qui est un cas normal ; une `current_season` présente sans `id` lève `MissingFieldException`. `BlizzardApiClient::getCurrentMythicSeasonId()` et `getCurrentPvpSeasonId()` en rendent `0` à défaut de saison.

---

### Importeurs spécialisés (`Blizzard/Importers/`)

Chaque importeur lit les données sources, les transforme et les sauvegarde via `upsert`.

| Classe | Source principale | Modèle cible |
|---|---|---|
| `AchievementImporter` | hiérarchie `achievement-category` + détail de chaque haut fait + balayage des media | `WowAchievement` |
| `MountImporter` | `mount/index` + balayage `search/mount` + taxonomie curée + socle pour le sort et l'icône | `WowMount` |
| `PetImporter` | `pet/index` + détail de chaque mascotte + taxonomie curée | `WowPet` |
| `DecorImporter` | `decor/index` + balayage `search/decor` + balayage des media d'items + taxonomie curée | `WowDecor` |
| `QuestImporter` | API Blizzard (liste par zone) + DB2 area/quest maps | `WowQuest` |
| `ProfessionImporter` | `skill_line_ability.csv` + API Blizzard | `WowProfession`, `WowRecipe` |

Pour les trois collections, le partage d'autorité est explicite : **l'API tranche l'existence**, la **taxonomie curée tranche le rangement**. Une entrée que l'API ignore n'entre pas au catalogue ; une entrée que la taxonomie ne range pas entre avec le type de source de l'API en valeur d'attente, et figure au rapport d'entrées à arbitrer. Voir [Taxonomie des collections](#taxonomie-des-collections-appinfrastructuretaxonomy).

**L'index est obligatoire, l'enrichissement ne l'est pas.** Un index manquant ferait disparaître du lot des entrées que le balayage des lignes périmées supprimerait ensuite : l'import s'interrompt sans toucher au catalogue. Une fenêtre de recherche ou un détail manquant, en revanche, laisse la ligne avec ce qu'elle avait. Une ligne identique à celle déjà en base n'est pas réécrite.

**Les montures sont le seul cas où le socle de référence est indispensable.** L'API n'expose l'icône d'une monture nulle part — `data/wow/media/mount/{id}` répond 404, `search/media?tags=mount` rend zéro résultat, et l'espace de media des sorts est creux —, ni le sort source qui porte son lien Wowhead. `Mount.SourceSpellID` puis `SpellMisc.SpellIconFileDataID` sont le seul chemin, et ils couvrent 1 686 montures sur 1 689.

**Les mascottes sont le seul cas sans endpoint de recherche.** `data/wow/search/pet` répond 404 : leur identité vient du détail, un appel par mascotte. C'est sans regret, ce détail étant le seul des trois à porter l'icône en clair, avec l'identifiant de créature du lien Wowhead.

**Le marqueur d'obtention des décorations est de la curation, jamais de l'API.** L'API atteste qu'une décoration existe, jamais qu'un joueur peut encore l'obtenir : un événement de pré-lancement clos ou une promotion retirée laissent une entrée hors d'atteinte, qui compterait au dénominateur et rendrait le 100 % inatteignable. Il vit donc dans la taxonomie, colonne `obtainable`. Les montures et les mascottes ne le lisent pas : leurs fichiers curés le portent, mais aucun import ne l'a jamais appliqué sur ces deux entités.

---

### `AppearanceImporter`

La garde-robe se construit par balayage du catalogue d'items, sans aucun appel unitaire par apparence. Un document de recherche d'item porte déjà le nom, la qualité, le media, la classe d'objet et les apparences liées : quelques centaines de fenêtres remplacent les 22 000 requêtes de l'ancien pipeline.

Deux autorités, jamais mélangées. Les 18 index de slots de l'`Item Appearance API` disent ce qui est collectionnable ; le balayage dit ce que chaque apparence contient. Une apparence portée par un item mais absente des index n'entre pas au catalogue. Si un seul de ces index ne répond pas, l'import s'interrompt sans rien supprimer — un index partiel effacerait tout un slot. Même posture si la borne du balayage est illisible : une plage devinée manquerait les identifiants les plus hauts, donc le contenu le plus récent.

**Deux passes sur la même grille de fenêtres**, et c'est l'unité de reprise portée par `AppearanceImportProgress` : les `n` premières fenêtres balaient les items, les `n` suivantes les media. Les lignes en base servent d'accumulateur d'une fenêtre à l'autre, ce qui évite de porter quoi que ce soit d'une passe à la suivante.

L'item représentatif d'une apparence est choisi par un ordre total : **meilleure qualité, puis plus petit identifiant d'item**. Le départage par identifiant n'est pas cosmétique — un critère dépendant de l'ordre de parcours ne rendrait pas le même représentant après une reprise. Un changement de représentant remet l'icône à nul, ce qui suffit à la faire reprendre par la passe media ; hors `--full`, cette passe ne vise que les lignes sans icône.

Une ligne identique à ce qui est déjà en base n'est pas réécrite, sans quoi chaque passe toucherait les 22 000 lignes et le mode incrémental ne voudrait plus rien dire.

---

## Taxonomie des collections (`app/Infrastructure/Taxonomy/`)

Le rangement des montures, mascottes et décorations — catégorie de niveau 1, source de niveau 2 — est de la curation éditoriale que ni l'API Blizzard ni les DB2 ne portent. L'API n'expose qu'un vocabulaire de douze valeurs de `source.type`, là où la curation compte 170 sources pour les seules montures. Cette taxonomie est donc **notre donnée**, et le dépôt en porte la trace : l'instantané `database/data/collection_taxonomy.csv` est la seule source dont l'amorçage dépend, et il se charge sans réseau ni tiers. SimpleArmory reste l'amont d'où vient la curation d'un nouveau patch, tiré à la main, jamais par un import.

Elle vit dans la table `wow_collection_taxonomy`, hors de la famille `wow_ref_*` pour qu'aucun traitement balayant les tables de référence DB2 ne puisse vider la curation.

### `CollectionEntity`

Énumération des trois collections curées. La valeur de chaque cas (`mount`, `pet`, `decor`) est le discriminant stocké en base : la changer invaliderait la taxonomie existante.

| Méthode | Description |
|---|---|
| `fromOption(string $name): self` | Résout l'entité d'une option de commande, insensible à la casse. Lève `InvalidArgumentException` en listant les entités connues. |
| `simpleArmoryFile(): string` | Nom du fichier curé dont cette collection s'amorce. |

### `TaxonomyEntry`

Objet de valeur immuable portant le rangement d'une entrée : `?string $category` et `?string $source`, en anglais brut, traduits à l'affichage par les onglets de collection. Les deux peuvent être nuls — c'est une entrée rangée nulle part **en connaissance de cause**, à ne pas confondre avec l'absence d'entrée, qui est à arbitrer.

### `CollectionTaxonomySnapshot`

Instantané versionné du rangement curé, `database/data/collection_taxonomy.csv` par défaut.

| Méthode | Description |
|---|---|
| `path(): string` | Chemin visé, celui du dépôt à défaut de surcharge. |
| `read(): array<string, array<int, TaxonomyEntry>>` | Entrées groupées par entité puis indexées par identifiant. |
| `entriesFor(CollectionEntity): array<int, TaxonomyEntry>` | La tranche d'une seule collection. |
| `write(array $entries): int` | Écrit l'instantané, entités dans leur ordre de déclaration et entrées par identifiant. |

La lecture est stricte : en-tête inattendu, entité inconnue, identifiant non entier ou marqueur d'obtention autre que `true`/`false` lèvent `TaxonomySnapshotMalformedException`. Un instantané absent ou vide lève `TaxonomySourceUnavailableException`. Un fichier versionné qui reçoit des arbitrages manuels mérite un refus net plutôt qu'une ligne muette en base.

Il est exporté **depuis la base** et non reconstruit depuis les fichiers curés : ceux-ci marquent hors d'atteinte 301 montures et 131 mascottes que la base garde délibérément obtenables, et réamorcer depuis eux changerait deux dénominateurs. Le format est un CSV de cinq colonnes plutôt qu'un JSON, pour qu'un arbitrage manuel se relise et se diffe ligne à ligne.

### `CollectionTaxonomyLoader`

Amorçage et enrichissement de la table.

| Méthode | Description |
|---|---|
| `load(CollectionEntity): array{read: int, inserted: int, skipped: int}` | Charge la collection depuis l'instantané. |
| `loadEntries(CollectionEntity, array<int, TaxonomyEntry>): array{read, inserted, skipped}` | Charge les entrées qu'on lui tend — c'est par là qu'un tirage amont fait entrer la curation d'un patch. |

Le chargement est strictement additif : `insertOrIgnore` laisse en place toute ligne connue, si bien que la première exécution amorce et que les suivantes n'ajoutent que les entrées d'un nouveau patch. C'est ce qui fait survivre un arbitrage manuel.

### `SimpleArmoryClient`

Frontière [simplearmory.com](https://simplearmory.com), amont de la curation.

**Méthode** : `fetch(CollectionEntity $collectionEntity): int` — télécharge le document curé sur le disque `reference` et rend sa taille en octets. Une réponse refusée ou vide lève `TaxonomyUpstreamUnreachableException` et ne laisse rien derrière elle.

Elle n'est traversée que par un tirage manuel, une fois par patch : l'amont indisponible n'empêche aucun import. Base et délai se règlent par `services.simplearmory`.

### `SimpleArmoryTaxonomyReader`

**Méthode** : `entriesFor(CollectionEntity $collectionEntity): array<int, TaxonomyEntry>`

Lit un document curé depuis le disque `reference` et n'en tire que le rangement. Une entrée non sortie ou sans identifiant utilisable est ignorée ; un identifiant listé sous plusieurs catégories garde sa **dernière** occurrence, ce qu'a fait chaque import jusqu'ici, donc ce qui a produit le rangement en place.

Chaque catégorie est lue par un `ResponsePayload`, avec trois tolérances délibérées : une catégorie qui n'est pas un objet est ignorée, un libellé qui n'est pas du texte vaut « rangé nulle part » (`lenientString`), un identifiant se lit par `lenientInt`. Au-delà, un export mal formé — des sous-catégories qui ne sont pas une liste, un drapeau `notObtainable` qui n'est pas un booléen — lève `TaxonomySourceUnavailableException::malformed()`, dont le message nomme le fichier, la catégorie et le chemin du champ.

### `CollectionTaxonomyReader`

**Méthode** : `for(CollectionEntity $collectionEntity): array<int, TaxonomyEntry>`

Charge la taxonomie d'une collection d'un seul coup, indexée par identifiant Blizzard. Quelques milliers de lignes de deux libellés coûtent moins qu'une requête par entrée pendant l'import.

### `ApiSourceTypeVocabulary`

Conversion du vocabulaire de source de l'API vers celui de la taxonomie, comme **valeur d'attente** pour une entrée non encore rangée — jamais comme remplacement d'une source curée.

| Méthode | Description |
|---|---|
| `toPendingSource(?string $sourceType): ?string` | Convertit l'un des onze types de l'API ; rend `null` pour un type inconnu plutôt que d'inventer un libellé. |
| `pendingSources(): list<string>` | Les onze libellés produits. |

Les onze libellés existent déjà dans les dictionnaires de traduction des trois onglets de collection, ce qu'un test vérifie. N'en ajouter un douzième qu'en l'ajoutant aussi côté front, sans quoi il s'afficherait en anglais.

### `TaxonomySourceUnavailableException`

Levée quand la source de l'amorçage est absente, illisible ou vide. Un amorçage qui ne trouve rien et se tait laisserait croire la taxonomie à jour alors qu'elle est restée vide. `malformed()` couvre l'export présent mais mal formé, et garde l'exception de contrat d'origine comme cause.

### `TaxonomySnapshotMalformedException`

Levée quand l'instantané est là mais que sa forme est fausse : en-tête, nombre de colonnes, entité, identifiant ou marqueur d'obtention.

### `TaxonomyUpstreamUnreachableException`

Levée quand l'amont curé refuse le téléchargement ou répond vide. Un tirage amont étant hors de tout chemin d'import, elle n'arrête jamais un import.

### L'arbitrage vu du panneau (`app/Application/Taxonomy/`)

Cinq classes de la couche Application portent ce que `/admin/taxonomy` affiche et écrit. Sans écran, le rapport d'entrées à arbitrer restait un fichier de log que personne ne lisait, et le rangement se dégradait patch après patch.

**`PendingTaxonomyEntries`** rend les entrées de catalogue que la taxonomie ne range pas. Rien n'est stocké : l'absence de ligne de taxonomie pour une ligne de catalogue *est* le rapport. La règle vit ici et non dans la commande, parce que trois appelants la partagent — la commande de rapport, l'écran d'arbitrage et le compteur du tableau de bord. `forEntity()` accepte une recherche par nom ou par identifiant, `counts()` rend le couple « en attente / au catalogue » par collection, `total()` la somme.

**`TaxonomyVocabulary`** rend les catégories et les sources qu'une collection emploie réellement, lues en base et non figées dans le code : ce vocabulaire est de la curation, il grossit d'un patch à l'autre, et une liste codée en dur aurait divergé dès le premier arbitrage. Elle propose l'existant à la saisie sans l'imposer — une valeur neuve reste saisissable, c'est ainsi qu'une catégorie entre.

**`TaxonomyArbitration`** écrit le rangement et remet l'instantané en phase dans la foulée. Trois points la gouvernent.

L'écriture passe par le constructeur de requêtes : la clé primaire de `WowCollectionTaxonomy` est composite, et un `save()` sur une instance chargée ne saurait pas la retrouver. Une entrée déjà curée est corrigée, une entrée inconnue est créée, et **le drapeau d'obtention trouvé en place n'est jamais touché** — le panneau range, il ne statue pas sur l'existence d'une monture.

Un libellé vide est un rangement nul et non une chaîne vide : c'est la distinction que l'instantané et les importers lisent, et c'est ce qui permet de **ranger une entrée nulle part en connaissance de cause**. Sans ce geste, une entrée qui n'a vraiment aucune catégorie reviendrait à arbitrer pour toujours.

**L'export suit immédiatement l'écriture**, et c'est la réponse au seul vrai piège de cet écran : un arbitrage n'existe qu'en base, et un environnement reconstruit depuis le dépôt le perdrait. En exportant dans la même foulée, la dérive est nulle par construction et il ne reste qu'un geste humain, le commit du fichier. Un export qui échoue — dépôt monté en lecture seule — ne défait pas l'arbitrage acquis et ne fait pas tomber la requête : il est journalisé, et l'écran dit que l'instantané a dérivé.

**En production, cet export n'a pas lieu.** Le fichier y vit dans l'image et ne peut pas être commité : le réécrire effacerait la seule référence commitée, et l'écran dirait « en phase » alors que le dépôt n'a pas l'arbitrage. La clé `services.taxonomy.export_after_arbitration` (`TAXONOMY_EXPORT_AFTER_ARBITRATION`) vaut par défaut `false` quand `APP_ENV` vaut `production`, et `true` ailleurs. En production, un arbitrage revient au dépôt par le téléchargement de l'instantané.

**`TaxonomySnapshotExporter`** regénère l'instantané depuis la base et dit s'il en est encore le reflet. La comparaison de `state()` porte sur **le contenu et non sur un nombre de lignes** : une correction de libellé ne change aucun décompte et ferait passer pour en phase un fichier périmé. Un instantané absent ou illisible se lit comme vide, pour que l'écran puisse signaler la dérive plutôt que tomber en erreur. `app:collection-taxonomy-export` délègue à cette classe, il n'y a donc qu'une implémentation de l'export.

**La dérive est orientée**, parce que le remède en dépend. `state()` compte les entrées du fichier absentes de la base (`missing_in_base`), qui se rechargent, les entrées de la base absentes du fichier (`missing_in_file`), et celles que les deux côtés tiennent différemment (`differing`), qui se téléchargent pour être commitées. L'écran ne conseillait autrefois que l'export, qui va dans l'autre sens : face à une base de production jamais amorcée, il aurait écrasé le fichier curé par une base vide, et seul son refus d'écrire un instantané vide l'en a empêché. `download()` rend l'instantané régénéré depuis la base, sans toucher au fichier en place, par `CollectionTaxonomySnapshot::render()`.

**`TaxonomySnapshotMerge`** recharge en base la curation versionnée des trois collections, depuis le bouton de l'écran : c'est le remède d'une base en retard sur le fichier. La fusion est additive, comme celle que font les étapes d'import avant d'importer une collection, et elle est tracée sur la piste d'audit.

**Une garde côté tests.** L'instantané est un fichier versionné, et tout ce qui écrit en base peut demander son réexport. `tests/Pest.php` redirige donc `CollectionTaxonomySnapshot` vers un chemin temporaire pour toute la suite `Feature`, et `VersionedSnapshotIsLeftAloneTest` tient cette garde. Sans elle, un test réécrit les milliers de lignes de curation du dépôt avec ses quelques lignes de doublure — c'est arrivé.

---

## Mappings (`app/Infrastructure/Mappings/`)

### `FrozenAreaExpansionMap`

Carte `zone → extension` figée dans `database/data/area_expansion_map.json`, versionnée avec le dépôt.

**Méthode** : `load(): array<int, int>` (statique)

Elle survit au socle de référence, et ce n'est pas un oubli : `AreaTable` ne porte pas l'extension d'une zone. Cette carte a été générée une fois en croisant `AreaTable`, `Map` et `ContentTuning`, corrections manuelles comprises, et ce croisement n'est pas reproductible depuis les seules colonnes du socle. Comme l'instantané de la taxonomie, elle vit dans `database/data/`, donc dans le dépôt.

---

## Journalisation (`app/Infrastructure/Logging/`)

### `DatabaseErrorHandler`

Handler Monolog qui tient le journal des erreurs consulté par la page de santé. Il est branché sur le canal `errors`, que `config/logging.php` ajoute toujours à la pile `stack`, quelle que soit la valeur de `LOG_STACK`. Il reçoit donc tout ce qui atteint le niveau erreur : les `Log::error` des contrôleurs et des jobs, qui rattrapent l'essentiel des échecs, comme les exceptions que rien n'a rattrapées. Le canal a son propre niveau, si bien que la production le reçoit même avec `LOG_LEVEL=warning`.

Chaque entrée garde son niveau, son message (tronqué à `MESSAGE_LENGTH`), la classe de l'exception et l'endroit où elle a été levée. Le journal est borné à `RETAINED_ENTRIES` entrées, élaguées à chaque écriture.

**Un échec d'écriture est avalé, sans rien journaliser à son tour.** La journalisation ne doit jamais faire tomber la requête qui journalisait, ni se rappeler elle-même en boucle quand la base est justement ce qui est en panne. Une erreur journalisée dans une transaction ensuite annulée disparaît avec elle.

### `AdminAudit`

La piste « qui a déclenché quoi » des actions du panneau : pilotage d'un import, purge du magasin de référence, arbitrage de la taxonomie, relance ou suppression d'un job échoué. Elle écrit sur le canal `audit` (`storage/logs/audit.log`, rotation quotidienne, un an), au niveau information et toujours écrit. Ces traces passaient auparavant par le journal applicatif, que la production tient en `warning` : elles y disparaissaient. Chaque entrée porte l'action, son contexte et l'administrateur.

La trace suit une action déjà faite. **Un échec de son écriture ne fait donc pas échouer l'action** : il part dans le journal des erreurs, que la page de santé affiche, avec de quoi reconstituer la trace perdue. Sinon, un fichier d'audit inaccessible ferait répondre en erreur une purge ou un rechargement pourtant réalisés.

---

## Couverture de documentation (`app/Infrastructure/Documentation/`)

Outillage de la commande `docs:coverage`, décrite dans [Commandes Artisan](09-commands.md).

### `DocumentationCoverage`

Fonction pure : une liste de noms de classes, le texte des pages et une liste d'exclusions entrent, un rapport sort. Aucun accès disque — le parcours des répertoires appartient à la commande, ce qui rend le calcul testable sans monter d'arborescence.

Une classe est reconnue quand son nom court ouvre une portion entre backticks. La correspondance porte sur le jeton entier, sans quoi `ImportStageRunner` satisferait `ImportStage` et la mesure ne voudrait plus rien dire.

### `CoverageReport`

Objet en lecture seule portant le résultat : les noms complets des classes manquantes, le nombre de documentées, la taille du périmètre et le nombre d'exclusions.

| Méthode | Retour | Description |
|---|---|---|
| `percentage()` | `float` | Part documentée du périmètre. Un périmètre vide vaut 100 %, l'absence de classe à décrire n'étant pas un échec. |
| `missingByLayer()` | `array<string, list<string>>` | Classes manquantes groupées par couche, pour une sortie directement exploitable comme liste de travail. |

---

## Mesure du CRAP (`app/Infrastructure/Coverage/`)

Outillage de la commande `coverage:crap`, décrite dans [Commandes Artisan](09-commands.md). Le CRAP croise complexité et couverture, méthode par méthode : `complexité² × (1 − couverture)³ + complexité`. Une méthode de complexité 5 ou moins ne dépasse jamais 30, même sans aucun test, alors qu'une méthode complexe peu couverte ressort. C'est le signal qui remplace le pourcentage de lignes par fichier.

### `CloverReader`

Fonction pure : le contenu d'un rapport clover de PHPUnit entre, une liste de `MethodRisk` sort. Aucun accès disque.

Le CRAP est **lu tel que PHPUnit l'a calculé**, jamais recalculé. La couverture affichée, elle, se déduit : le clover range ses lignes au niveau du fichier et non de la classe, les instructions d'une méthode sont donc celles qui suivent sa ligne `type="method"` jusqu'à la méthode suivante. Une méthode sans instruction, comme un constructeur à promotion de propriétés, compte comme couverte dès qu'elle a été appelée.

Un document qui n'est pas du XML, un XML dont la racine n'est pas `<coverage>`, ou une méthode sans attribut `crap` ou `complexity` lèvent `UnreadableCloverException`. Un attribut absent n'est jamais lu comme zéro : une méthode silencieusement notée à 0 passerait sous tous les seuils.

### `MethodRisk`

Objet en lecture seule : classe, méthode, complexité cyclomatique, couverture en pourcentage et CRAP.

### `CrapReport`

Rapport en lecture seule construit par `CrapReport::of()` à partir des méthodes mesurées et d'un seuil. Il garde les méthodes qui **dépassent** le seuil, triées de la pire à la moins grave, et le nombre de méthodes mesurées. Le seuil est exclusif, et un seuil inférieur à 1 est refusé.

| Méthode | Retour | Description |
|---|---|---|
| `exceedsThreshold()` | `bool` | Vrai dès qu'une méthode dépasse le seuil. |

---

## Périmètre de mutation (`app/Infrastructure/Mutation/`)

Outillage de la commande `mutation:scope`, décrite dans [Commandes Artisan](09-commands.md), qui dit à `scripts/mutate.sh` quoi muter et contre quels tests.

### `MutationPerimeter`

Lecture du contenu de `mutation-perimeter.txt` : un chemin par ligne, espaces de bord retirés, lignes vides et commentaires en `#` ignorés, doublons fusionnés. Fonction pure, sans accès disque.

### `MutationScope`

Deux fonctions pures.

`dedicatedTests()` rend les tests dédiés d'une classe : les fichiers sous `tests/` dont le nom commence par celui de la classe et finit par `Test.php`. Chaque classe du périmètre est mutée contre eux seuls. La correspondance par préfixe est voulue, puisque `ScoreCalculatorEdgeCasesTest.php` protège `ScoreCalculator`. Elle retient parfois un test de trop (`ImportStageRunnerTest.php` pour `ImportStage`), ce qui coûte un peu de temps mais ne perd jamais un test.

`select()` choisit, parmi les fichiers du périmètre, ceux qu'une branche concerne : ceux qu'elle modifie, ceux dont elle modifie ou supprime un test dédié, et ceux qu'elle vient de déclarer dans le périmètre. La sélection sort triée et sans doublon.

### `GitCommandFailedException`

Levée quand une commande Git lancée par `mutation:scope` échoue, typiquement sur une base inconnue. Le message reprend la commande et la sortie d'erreur de Git.

---

## Socle de référence DB2 (`app/Infrastructure/Reference/`)

Les correspondances que l'API Blizzard n'expose sur aucun endpoint — extension d'une quête, faction d'une zone, renom d'une réputation — viennent des tables DB2 publiées par wago.tools. Elles sont chargées une fois par patch dans des tables `wow_ref_*` par la commande [`app:wow-reference-sync`](09-commands.md), au lieu d'être reparsées depuis le disque à chaque import.

### `ReferenceCatalog`

Déclare les huit tables DB2 retenues et, pour chacune, les seules colonnes utiles : `Faction`, `ContentTuning`, `AreaTable`, `QuestV2CliTask`, `SkillLineAbility`, `CurrencyTypes`, `Mount`, `SpellMisc`.

`SpellMisc` est la plus lourde du socle — 417 583 lignes pour 45 Mo — et n'est retenue que pour deux colonnes, `SpellID` et `SpellIconFileDataID`. C'est le prix de l'icône des montures, que l'API n'expose nulle part.

Les noms de colonnes sources appartiennent à un build donné et changent d'un patch à l'autre. Blizzard a par exemple scindé les masques de race en deux moitiés — `RaceMask` est devenu `RaceMasks_0` et `RaceMasks_1` — le jour où les identifiants de race ont dépassé la largeur d'origine. Une colonne déclarée ici mais absente de la source fait échouer la synchronisation, ce qui est le comportement recherché : c'est le seul moment où un renommage se voit.

### `ReferenceTable`, `ReferenceColumn`, `ReferenceColumnType`

Descripteurs en lecture seule, sans dépendance au framework. `ReferenceTable` porte le nom de la table DB2, son slug, la locale à demander et ses colonnes ; elle en dérive le nom de la table PostgreSQL (`wow_ref_` + slug) et le nom du fichier stocké (slug + build). `ReferenceColumnType` distingue les colonnes numériques des colonnes textuelles, seule information dont le projecteur a besoin pour décider si une cellule vide vaut `NULL` ou chaîne vide.

### `Db2CsvProjector`

Réduit un CSV DB2 aux colonnes déclarées, dans l'ordre de la table cible, et rend un générateur de lignes déjà encodées. Les colonnes sont repérées par nom, un réordonnancement de la source est donc sans effet, et une colonne absente lève `MissingColumnException`.

### `CopyText`

Encodage d'une ligne au format texte de `COPY`. Ce format sépare par tabulation, marque le nul par `\N` et n'accorde aucun sens aux guillemets ni aux virgules : seuls la barre oblique inverse et les caractères de mise en page sont neutralisés, la barre oblique en premier pour ne pas ré-échapper les échappements produits ensuite.

`NULL_MARKER_SQL` existe parce que `copyFromArray()` recopie le marqueur dans la clause `NULL AS '…'` sans l'échapper : une barre oblique simple y est consommée par l'analyseur SQL, et PostgreSQL finit par chercher un nul écrit `N`.

### Le socle vu du panneau (`app/Application/Reference/`)

Six classes de la couche Application portent ce que `/admin/reference` affiche : deux pour l'état des tables, quatre pour l'inventaire des fichiers téléchargés.

**`ReferenceInventory`** rend, pour chacune des huit tables, ce qu'elle pèse, quand elle a été chargée et sous quel build, et l'écart avec le chargement précédent. Les lignes sont **comptées sur la table**, pas relues de l'inventaire : ce dernier dit ce qu'un chargement a écrit, la table dit ce qui reste, et les deux divergent dès qu'on touche à la base autrement que par la commande de synchronisation.

Trois signaux en sortent : une table vide, une table restée sur un build antérieur, et une table **dégarnie**. Ce dernier mérite son seuil écrit. La commande de synchronisation refuse déjà d'écrire une source tombée sous la moitié du chargement précédent ; `VolumeShrink::ALERT_RATIO` vaut **0,9**, et signale donc tout chargement qui a retenu moins de neuf dixièmes de son prédécesseur. Entre les deux valeurs, le chargement aboutit sans que rien ne le dise : c'est exactement l'angle mort que la page comble, et c'est le signal le plus utile de l'écran — un patch qui renomme une colonne se manifeste par une table qui se vide, pas par une erreur. `VolumeShrink` porte ce seuil pour tout le projet : l'historique des imports lit la chute de volumétrie d'une entité au même seuil.

**`LiveReferenceBuild`** lit le build que wago sert. C'est bien **le build de wago et non celui de l'API Blizzard** : le socle vient des tables DB2, et les deux numéros ne coïncident pas — au 20 septembre 2026, wago servait `12.1.0.69875` quand l'API Blizzard servait `12.1.0_68914`. Comparer le socle au build Blizzard signalerait un écart permanent qui n'existe pas.

La lecture et son cache d'une heure vivent depuis la détection de patch dans `UpstreamBuildProbe`, commun aux deux amonts, qui retient en plus la dernière valeur connue en base pour survivre au vidage de cache que `/admin/tools` sait déclencher. Ce qui reste propre à cette classe est son contrat : `null` dès que wago n'a pas répondu, **y compris quand un build plus ancien reste connu** — la page se rend sans comparaison plutôt que de situer le socle contre une valeur périmée.

**`ReferenceBuildState`** dit sur quel build le socle se trouve et combien de ses tables sont restées en arrière, pour le bandeau du tableau de bord. L'état se lit sur l'inventaire des chargements et non sur `wow_import_states` : une synchronisation lancée depuis cette page ne passe pas par le pipeline d'import et n'y écrit rien.

Le lancement depuis le panneau passe par `AdminService::startReferenceSync()`, qui prend **le même verrou que les imports**. Les deux sens du refus en découlent : on ne synchronise pas sous un import, et on n'importe pas sous une synchronisation — un socle qui change sous un import produirait des résultats incohérents.

#### Le magasin vu du panneau

Quatre classes de plus portent l'inventaire des fichiers téléchargés et leur purge. `storage/app/blizzard/` avait accumulé 66 Mo de reliquats parce que rien ne les affichait ; c'est cette dérive que l'écran empêche de recommencer sur `storage/app/wow-reference/`.

**`ReferenceFileState`** énumère ce qu'un fichier vaut encore : `live`, `obsolete`, `taxonomy`, `orphan`. `isSweepable()` dit ce qu'un nettoyage en lot a le droit d'emporter — les obsolètes et les orphelins, jamais les deux autres.

**`ReferenceFileClassifier`** applique la règle, et rien d'autre : aucune lecture de disque ni de base, ce qui la rend vérifiable sans rien monter. Deux points la gouvernent.

**« En service » veut dire chargé, pas publié par wago.** Le fichier en service d'une table est celui de son **dernier chargement selon `downloaded_at`**, donc celui dont le chargement a produit ce qui est dans la table. Le classement ne dépend d'aucun appel sortant et reste juste quand wago est injoignable. Deux chargements enregistrés dans la même seconde sont départagés par le build, pour que l'ordre de lecture de la base ne décide de rien.

**Les trois instantanés de taxonomie forment une famille à part.** `mounts.json`, `pets.json` et `decors.json` sont déposés sur ce disque par `SimpleArmoryClient`, qui écrit directement et ne crée aucune entrée d'inventaire. Par le seul critère « présent sur disque et absent de l'inventaire » ils passeraient pour des orphelins purgeables, ce qui serait faux. Ils sont reconnus par `CollectionEntity::simpleArmoryFile()`, la source de vérité qui existait déjà : les inventorier aurait demandé de rendre `build` et `row_count` nullables pour des fichiers qui n'ont ni build WoW ni table cible.

Une ligne d'inventaire dont le fichier a disparu du disque sort à part, signalée plutôt qu'affichée comme si de rien n'était.

**`ReferenceFileInventory`** est la moitié à effets : elle lit le disque et l'inventaire, délègue le classement, et rend les totaux — ce que le magasin occupe, et ce qu'un balayage libérerait. `filenames()` donne la seule liste qu'une requête a le droit de désigner.

**`ReferencePurge`** retire les fichiers désignés et les lignes d'inventaire correspondantes, et rend ce qui a réellement été libéré. Un fichier déjà parti ne compte pas dans le chiffre : le panneau en annonce un, et il doit être celui qu'on retrouve sur le disque. La piste d'audit part dans le journal applicatif, comme celle des ordres donnés à un import.

La purge prend **le même verrou que les imports et la synchronisation**, pour la raison qui les lie déjà : une synchronisation écrit dans ce répertoire au moment même où la purge le balaie.

### `ReferenceLoader`

Remplace le contenu d'une table de référence par `COPY`, cinq mille lignes à la fois, sur une connexion `Pdo\Pgsql`. `TRUNCATE` étant transactionnel sur PostgreSQL, un chargement qui casse en cours de route laisse la table telle qu'elle était, sans passer par une table de transit.

### `ReferenceStore`

Magasin des fichiers téléchargés, sur le disque `reference` (`storage/app/wow-reference/`) : les CSV du socle, et les documents curés d'un tirage amont. Le nom de fichier d'une table porte le build — deux synchronisations d'un même build écrivent le même fichier, deux builds différents en laissent deux.

| Méthode | Retour | Description |
|---|---|---|
| `put(ReferenceTable, string $build, string $contents)` | `int` | Écrit le fichier et rend sa taille. |
| `read(ReferenceTable, string $build)` | `resource` | Flux de lecture, `RuntimeException` si le fichier est illisible. |
| `sizes()` | `array<string, int>` | Le contenu de la racine, avec le poids de chaque fichier. |
| `delete(string $filename)` | `void` | Supprime un fichier déjà résolu par le serveur. |

Les tailles viennent du système de fichiers et jamais du contenu : `spell_misc` pèse 45 Mo par build, et l'inventaire d'un écran n'a aucune raison de le charger en mémoire. Le magasin étant plat, ce qui serait rangé sous un sous-répertoire n'est pas à lui et sort du listing.

Le disque porte `throw` (`config/filesystems.php`), contrairement au disque `local` : `sizes()` attrape le cas du **répertoire absent**, qui est l'état d'une installation neuve, et rend une liste vide plutôt que de faire tomber la page qui vient le lire. La suppression, elle, est idempotente par le contrat de Flysystem — un fichier déjà parti n'est pas une erreur, ce que deux onglets purgeant la même sélection rencontrent pour de bon.

### `WagoClient`

Frontière wago.tools. `liveBuild()` lit la version LIVE sur `/api/builds`, `fetch()` télécharge une table sur `/db2/{table}/csv`. Le produit est épinglé sur `wow` dans les deux cas : sans lui, wago sert son dernier build tous produits confondus, souvent un PTR dont la localisation française est incomplète.

### `RaceMask`

Lecture de la faction dans un masque de race DB2.

| Méthode | Retour | Description |
|---|---|---|
| `combine(?int $low, ?int $high)` | `?int` | Recompose le masque complet à partir de ses deux moitiés. |
| `faction(?int $low, ?int $high)` | `?string` | `Alliance`, `Horde`, ou `null` quand le masque ne tranche pas. |

Blizzard a scindé ces masques en deux moitiés de 32 bits le jour où les identifiants de race ont dépassé la largeur d'origine : `FiltRaceMasks_0` / `_1`, `RaceMasks_0` / `_1`, `ReputationRaceMasks0_0` / `_1`. La première porte les bits de poids faible, la seconde ceux de poids fort, et leur recomposition rend exactement les masques complets d'avant la scission.

**Une moitié lue seule ne veut rien dire** : c'est un entier quelconque, souvent négatif, dont l'analyse par bits produit une faction plausible et fausse. Les deux moitiés sont donc toujours exigées ensemble.

---

### `ReferenceMaps`

Les correspondances que l'import tire du socle.

| Méthode | Retour | Source |
|---|---|---|
| `questExpansions()` | `array<int, int>` | `wow_ref_quest_v2_cli_task` joint à `wow_ref_content_tuning` |
| `questFactions()` | `array<int, string>` | `wow_ref_quest_v2_cli_task`, masque de race |
| `recipeFactions()` | `array<int, string>` | `wow_ref_skill_line_ability`, masque de race |
| `zoneFactions()` | `array<int, string>` | `wow_ref_area_table`, `FactionGroupMask` : 2 pour l'Alliance, 4 pour la Horde |
| `mountSpells()` | `array<int, int>` | `wow_ref_mount`, `SourceSpellID` |
| `mountIcons()` | `array<int, string>` | `wow_ref_mount` joint à `wow_ref_spell_misc` par le sort source |

Les cartes sont construites une fois en début de passe et gardées en mémoire : quelques dizaines de milliers d'entiers ne pèsent rien, là où un aller-retour SQL par quête coûterait la passe entière. Une quête sans titre est ignorée partout, comme le faisait la lecture du CSV : elle n'entre au catalogue sous aucune forme.

Les recettes sont indexées par `SkillLineAbility.ID`, qui est bien l'identifiant de recette que l'API retourne.

L'icône d'une monture se compose à partir de `SpellIconFileDataID` sur le gabarit `https://render.worldofwarcraft.com/{région}/icons/56/{fileDataId}.jpg`, celui-là même que l'API sert pour les mascottes et les hauts faits. Un sort porte parfois plusieurs lignes `SpellMisc`, une par difficulté : la plus petite tranche l'égalité pour que deux imports rendent la même icône.

---

### `FactionReference`

Tout ce que le socle sait des réputations, servi à l'import comme à l'exécution.

| Méthode | Retour | Description |
|---|---|---|
| `expansions()` | `array<int, int>` | Extension d'une réputation, par remontée de la hiérarchie des factions parentes. |
| `names()` | `array<int, string>` | Nom localisé d'une réputation. |
| `maxRenownLevels()` | `array<int, int>` | Renom maximal, via `RenownCurrencyID` et `MaxQty`. |
| `accountWideIds()` | `array<int, true>` | Réputations valables sur tout le compte : renom, amitié, ou extension ≥ Dragonflight. |
| `factions()` | `array<int, string>` | Camp des réputations exclusives à une faction. |

Une réputation est exclusive quand elle porte un plafond pour un camp et pas pour l'autre. Son camp se lit alors par recoupement avec le masque de race de Hurlevent, pris comme référence Alliance : Blizzard ne nomme les camps nulle part.

Les réputations que l'endpoint `/reputations` ne retourne jamais sont exclues — parangon, saisons de gouffres et de traque, entrées `DEPRECATED`, `[DNT]` et `JOUEUR` — sans quoi elles compteraient au dénominateur et rendraient le 100 % inatteignable.

Les lectures sont mémorisées pour la durée de l'instance : l'agrégateur de progression des réputations interroge cinq de ces cartes à chaque profil de personnage.

---

### `ReferenceValue`

Rétrécissement des valeurs qui sortent du socle : `int()`, `nullableInt()` et `string()`. Le constructeur de requêtes rend des objets aux propriétés non typées, et ce `mixed` est converti dès la ligne qui le reçoit plutôt que de traverser le code.

---

### Exceptions

Toutes descendent de `ReferenceSyncException`, ce qui permet à la commande de rattraper la famille entière et de rendre un message plutôt qu'une pile.

| Exception | Levée quand |
|---|---|
| `BuildUnavailableException` | wago ne rend pas de version pour le produit configuré. |
| `DownloadFailedException` | Téléchargement refusé, ou corps vide. |
| `MalformedSourceException` | Fichier servi sans ligne d'en-tête. |
| `MissingColumnException` | Une colonne déclarée a disparu de la source. |
| `TruncatedSourceException` | Volumétrie effondrée sous la moitié du dernier chargement. |
| `UnknownTableException` | `--table` désigne une table absente du catalogue. |
