# Services de la couche Application

Les services orchestrent la logique métier : ils appellent l'API Blizzard, agrègent les données et renvoient des résultats structurés. Ils n'ont pas accès direct à `Request` ni à la couche Http.

---

## `AccountScoreService`

Calcule et met en cache le score de compte multi-personnages d'un utilisateur connecté.

**Constantes**

| Constante | Valeur | Description |
|---|---|---|
| `BATCH_SIZE` | `1` | Nombre de personnages traités par batch |
| `CACHE_TTL` | `86400` | Durée de cache du résultat (24 h) |
| `PROGRESS_TTL` | `3600` | Durée de cache de la progression en cours (1 h) |

**Dépendances injectées** : `CharacterProfileService`, `UserCharacterService`

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `getOrCompute()` | `array{status, data?, progress?}` | Retourne le score depuis le cache ou le recalcule. `status` peut être `ready`, `computing` ou `unauthenticated` ; `data` est un `AccountScoreResult` avec son `score`. |
| `invalidate()` | `void` | Supprime le cache du score pour l'utilisateur courant. |

---

## `AdminService`

Pilote les opérations d'administration : imports et synchronisation du socle, purge du magasin de référence, caches, maintenance, Discord. Les deux seules commandes qu'il sait lancer sont `app:wow-data-import` et `app:wow-reference-sync`, toujours par un `RunImportJob` sur la queue `imports`.

**Méthodes**

| Méthode | Description |
|---|---|
| `startImport` | Met un import en file et rend son `jobId` : tout ou une sélection d'étapes, incrémental ou forcé. Le verrou « un import à la fois » est pris ici, au clic, et non quand le worker prend le job : entre les deux, un second onglet aurait le temps d'en lancer un autre. Lève `ImportAlreadyRunningException`. |
| `startReferenceSync` | Met une synchronisation du socle en file, toutes tables ou une seule. Elle prend le même verrou qu'un import : un socle qui change sous un import produirait des résultats incohérents. |
| `steerImport` | Pose un ordre — pause ou abandon — que le job lit à la frontière de tranche suivante. Rien n'est interrompu de l'extérieur, et c'est ce qui garantit qu'un abandon ne tombe jamais au milieu d'une écriture. |
| `resumeImport` | Lève l'ordre de pause : le job repart de lui-même. |
| `getImportJobStatus` | L'avancement d'un import, plus les lignes de journal apparues depuis le curseur annoncé par le panneau, pour ne pas retélécharger tout le journal à chaque seconde. |
| `currentImportJobId` | L'import en cours, que le panneau l'ait lancé ou non. |
| `purgeReferenceFiles` | Retire des fichiers du magasin de référence. Refusée pendant un import, qui lirait le magasin au moment même où la purge le balaie. |
| `clearCaches` | Vide les caches applicatifs. |
| `toggleMaintenance`, `isInMaintenance` | Mode maintenance. |
| `sendDiscordEmbed` | Envoie un embed sur le webhook Discord configuré. |

L'auteur d'une action (`$actor`) la suit : il part avec le job au lancement d'un import, qui l'inscrit dans l'historique comme déclencheur, et il est consigné par `AdminAudit` pour le pilotage et la purge. Le détail du pilotage d'un import est dans [Orchestration de l'import](11-import.md).

---

## `CharacterFavoriteService`

Les personnages favoris d'un utilisateur, rangés par `bnet_user_id` dans la table `character_favorites`. Un utilisateur en a au plus trois (`MAX_FAVORITES`) : au-delà, l'ajout lève `FavoriteLimitReachedException`.

| Méthode | Description |
|---|---|
| `getFavoritesForUser` | Les favoris dans leur ordre de rang, puis d'ajout. |
| `addFavorite` | Ajoute un favori en dernière position. Idempotent : un personnage déjà en favori est rendu tel quel, sans compter contre la limite. |
| `removeFavorite` | Retire un favori. Retirer un personnage absent ne fait rien. |

Royaume et nom sont ramenés en minuscules à l'écriture comme à la lecture, pour que `Hyjal/Arthas` et `hyjal/arthas` désignent le même favori.

---

## `CharacterProfileService`

Récupère et assemble le profil complet d'un personnage depuis l'API Blizzard.

Son seul point d'entrée, `getProfile(string $realm, string $name)`, rend un `CharacterProfileDTO` complet, score compris. Le résumé du personnage est lu d'abord, puis les douze endpoints du profil partent en parallèle par le trait `FetchesProfileEndpoints`. Chaque réponse est confiée à l'agrégateur de sa dimension, et le service assemble le tout. Le trajet complet d'une donnée est décrit dans [Agrégateurs](03-aggregators.md).

Chaque endpoint interrogé est lu par son objet de réponse (`Blizzard/Responses/Profile/`, voir [Infrastructure](05-infrastructure.md)) : le service ne manipule aucun tableau brut.

Les noms français des raids du tier courant viennent de `data/wow/journal-instance/{id}`, mis en cache une semaine sous `raid_journal_instance:{id}`. Le cache garde la réponse décodée et non l'objet, pour qu'un changement de classe n'invalide pas son contenu.

---

## `CharacterSeoService`

Génère les métadonnées SEO et les sitemaps pour les pages personnage et les pages statiques.

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getHomeMeta` | — | `array<string, string\|null>` | Méta pour la page d'accueil. |
| `getStaticPageMeta` | `string $page` | `array<string, string\|null>` | Méta pour une page statique (`faq`, `privacy`, `cgu`). |
| `buildCharacterMeta` | `CharacterProfileDTO $profile, string $realm, string $name` | `array<string, string\|null>` | Méta OG + Twitter d'un personnage, construites sur le profil déjà chargé par la page : aucun appel à l'API en plus. |
| `buildNotFoundCharacterMeta` | `string $realm, string $name` | `array<string, string\|null>` | Méta de la page d'un personnage introuvable, servie avec le 404. |
| `generateSitemapIndex` | — | `string` | Génère le sitemap index XML. |
| `generatePagesSitemap` | — | `string` | Génère le sitemap des pages statiques. |

---

## `CharacterTaskService`

Gère les tâches personnalisées associées aux personnages d'un utilisateur (tâches quotidiennes, hebdomadaires, mensuelles).

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getTasksForUser` | `string $bnetUserId` | `Collection<int, CharacterTask>` | Liste toutes les tâches de l'utilisateur. |
| `createTask` | `string $bnetUserId, array $data` | `CharacterTask` | Crée une nouvelle tâche. |
| `toggleTask` | `int $taskId, string $bnetUserId` | `CharacterTask` | Bascule l'état complété/non complété. |
| `deleteTask` | `int $taskId, string $bnetUserId` | `void` | Supprime une tâche (vérifie la propriété). |
| `resetTask` | `int $taskId, string $bnetUserId` | `CharacterTask` | Remet la tâche à l'état non complété. |

---

## `CrossCharacterService`

Orchestre le calcul de progression agrégée sur tous les personnages d'un compte Battle.net.

**Constantes**

| Constante | Valeur |
|---|---|
| `CACHE_TTL_HOURS` | `24` |
| `MAX_RETRIES` | `3` |
| `RETRY_BASE_DELAY_S` | `5` |

**Méthodes publiques**

| Méthode | Retour | Description |
|---|---|---|
| `compute(string $battleTag)` | `array{status, data?, characterCount?, jobId?}` | Déclenche ou retourne le calcul cross-personnage. Si déjà calculé, retourne `ready`. Sinon, dispatche un `ComputeCrossCharacterJob` et retourne `computing` + `jobId`. Le BattleTag, lu en session par le contrôleur, est l'étiquette du calcul dans la page Santé : une chaîne vide lève `MissingBattleTagException` au lieu de mettre en file un calcul anonyme. |
| `getJobStatus` | `array{status}` | Lit l'état d'un job cross-personnage depuis le cache. |
| `getStoredData` | `?array{data, character_count}` | Retourne les données déjà calculées, **telles qu'elles sont stockées** — ancien format compris. |
| `mergeCurrentCharacter` | `CharacterProfileDTO` | Fusionne les données du personnage courant dans une progression cross-personnage. |
| `fetchAndMergeCharacters` | `list<array<string, scalar\|null>>, CrossCharacterProgress, ?string` | Récupère et fusionne tous les personnages (utilisé depuis le job). Un personnage sans royaume ou sans nom textuel est passé. |

Chaque endpoint d'un personnage est lu par son objet de réponse — `CompletedQuestsResponse`, `CharacterAchievementsResponse`, `CharacterReputationsResponse`, `CharacterProfessionsResponse` — et le tout forme un `FetchedCharacterProgress`. Un 404 ou un endpoint qui échoue après ses quatre tentatives donne une réponse vide : la tolérance aux personnages supprimés ou renommés est fonctionnelle. Un 401, en revanche, arrête tout le calcul par `ExpiredBlizzardTokenException`, sans nouvelle tentative : le jeton a expiré, typiquement sur une relance tardive depuis les jobs échoués, et tous les autres endpoints répondraient de même. Le traiter comme une réponse vide enregistrerait un compte sans progression par-dessus le précédent. Seuls le royaume et le nom des personnages partent dans la file du job.

La colonne `jsonb` `cross_character_data.data` est une frontière : son type, `StoredCrossCharacterData`, est déclaré sur le modèle `CrossCharacterData`. Elle est servie telle quelle au front, et relue par `CrossCharacterProgress::fromStored()` quand il faut y fusionner le personnage consulté.

---

## `DatabaseQueryService`

Les requêtes des pages publiques de la base de données : montures, hauts-faits, quêtes, mascottes, décorations, garde-robe, métiers et recettes. C'est la source des props des pages Inertia servies par `DatabaseController`.

Chaque section rend ses entrées actives (`is_active`), avec la liste de ses sous-catégories et leur compte pour la barre latérale. Selon la section, les sous-catégories sont des catégories de collection, des extensions, des emplacements de garde-robe ou des métiers. Les sections volumineuses — hauts-faits, quêtes, garde-robe, recettes — sont paginées et filtrables par recherche : 50 entrées par page par défaut, jamais plus de 100, quoi que demande la requête. Les petites sections sont servies entières.

`counts()` et `subcategories()` alimentent la barre latérale de toutes les pages de la base ; `subcategories()` rend `null` pour une section inconnue. `cachedCounts()` et `cachedSubcategories()` les gardent une heure en cache (`database_sidebar_counts`, `database_sidebar_subcategories`), car une quinzaine de requêtes d'agrégation à chaque navigation coûterait pour des données qui changent rarement. `forgetSidebar()` oublie les deux clés. L'arbitrage de la taxonomie l'appelle, pour qu'une réaffectation se voie tout de suite, et `ImportPipeline` à la clôture de chaque import, abouti ou annulé : la barre latérale suit le catalogue dès qu'il a changé, au lieu d'attendre l'expiration du cache. Les libellés français des emplacements de garde-robe vivent ici, dans `SLOT_LABELS`.

---

## `DatabaseSeoService`

Calcule les métadonnées SEO pour chaque section de la base de données.

**Méthodes**

| Méthode | Paramètres | Retour |
|---|---|---|
| `getIndexMeta` | — | `array<string, string\|null>` |
| `getMountsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getAchievementsMeta` | `?string $expansionSlug` | `?array<string, string\|null>` |
| `getQuestsMeta` | `?string $expansionSlug` | `?array<string, string\|null>` |
| `getPetsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getDecorsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getAppearancesMeta` | `?string $slotSlug` | `?array<string, string\|null>` |
| `getProfessionsMeta` | `?string $professionSlug` | `?array<string, string\|null>` |
| `getSitemapUrls` | — | `array<array{url, label, lastmod}>` |
| `generateSitemap` | — | `string` (XML) |

---

## `UserCharacterService`

Gère l'authentification OAuth2 Battle.net et récupère la liste des personnages du compte.

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `isAuthenticated()` | `bool` | Vérifie si l'utilisateur a une session Battle.net active. |
| `logout()` | `void` | Déconnecte l'utilisateur, et oublie la liste de ses personnages. |
| `ownsCharacter(string $realmSlug, string $name)` | `bool` | Dit si le personnage appartient au compte connecté, sans tenir compte de la casse. La liste du compte est lue une fois par session, sans les avatars, puis gardée en session : une fiche ne coûte pas un appel à Blizzard par visite. Un visiteur, ou un compte illisible, rend `false`. |
| `getUserCharacters()` | `list<AccountCharacter>` | Retourne la liste des personnages avec avatars, classe, race, faction. |
| `getClassIcons()` | `array<int, string>` | Icône de chacune des treize classes, par identifiant. |

---

## `PlayableNameService`

Traduit en français une classe et une spécialisation désignées par leur nom anglais : « Chevalier de la mort · Sang ».

Le besoin vient des classements JcJ. Blizzard y nomme les brackets par spécialisation avec un slug anglais (`shuffle-deathknight-blood`) et n'expose aucun identifiant, donc rien qui se localise directement. Le service lit les index des classes et des spécialisations jouables en anglais et en français, les rapproche par identifiant, et indexe le résultat sur le nom anglais mis sous la forme exacte des slugs de Blizzard. Cela fait quatre appels, mis en cache trente jours par région sous `wow_playable_names:{region}`, et aucune table de correspondance à maintenir dans le dépôt.

Il rend `null` pour un nom qu'il ne sait pas résoudre. `PvpBracketClassifier` retombe alors sur le slug mis en forme. Un index injoignable donne une carte vide plutôt qu'une erreur : l'onglet JcJ et les classements restent affichables, en anglais.

---

## `PvpProfileService`

Le PvP d'un personnage, lu à la volée : aucune table, aucun import. Il est chargé paresseusement par l'onglet PvP, pour ne rien coûter aux personnages qui n'en font pas.

| Méthode | Retour | Description |
|---|---|---|
| `getForCharacter(string $realm, string $name)` | `PvpProfile\|null` | Résumé, brackets joués et paliers, normalisés par `PvpProgressAggregator`. |

Le résumé donne la liste des brackets joués, interrogés ensuite en parallèle. Les paliers rencontrés sont résolus par leur nom et leur media, puis mis en cache un par un sous `pvp_tier:{id}` (trente jours, forme `{name, icon_url}`) : deux personnages du même rang ne coûtent qu'un appel. Le résultat complet est mis en cache quinze minutes sous `pvp_profile:{realm}:{name}`, absence de PvP comprise.

---

## `PvpLeaderboardService`

Les classements PvP officiels de la saison, servis en direct par l'API.

| Méthode | Retour | Description |
|---|---|---|
| `availableBrackets()` | `list<{key, label, brackets}>` | Brackets classés de la saison, regroupés par mode, « Toutes spés » en tête. |
| `leaderboard(string $bracket, int $page = 1, ?string $search = null)` | `LeaderboardPage` | Une page de cinquante lignes, filtrée sur le nom ou le royaume. Un bracket inconnu retombe sur `3v3`. |

L'index des classements est mis en cache un jour sous `pvp_leaderboard_index:{saison}`. Un classement complet pèse plusieurs Mo : seules les colonnes affichées (`LeaderboardRow`) sont gardées, une heure, sous `pvp_leaderboard:{saison}:{bracket}`.

---

## La santé vue du panneau (`app/Application/Health/`)

Ce que `/admin/health` affiche, section par section. C'est un outil de diagnostic, pas un tableau de bord de métriques : tout est lu à l'ouverture de la page, rien n'est historisé.

**Le serveur juge, la page affiche.** Chaque mesure porte un `HealthStatus` — `ok`, `warning`, `critical` ou `unavailable` — et, en cas d'anomalie, un libellé qui la nomme. Une anomalie n'est jamais laissée à l'interprétation d'un chiffre. `unavailable` n'est pas un degré de gravité : il signifie que la mesure elle-même n'a pas pu être faite.

**`HealthReport`** assemble les cinq sections — services, quota, queue, volumétries, erreurs récentes — et calcule chacune sous garde. Une base injoignable rend les volumétries `unavailable` sans faire tomber la page, et c'est ce qui permet au diagnostic de fonctionner justement quand quelque chose est en panne.

**`ServiceProbes`** vérifie PostgreSQL par un `select 1` et chaque connexion Redis nommée dans `database.redis` par un `ping`, chacune isolément. La limite est connue et assumée : la session et le limiteur de débit vivent sur Redis, donc un Redis entièrement arrêté empêche la page d'être servie. C'est alors `/up`, hors session, qui répond. Les sondes couvrent le reste : un index injoignable, ou une base qui ne répond plus sous une application encore debout.

**`BlizzardQuota`** situe le quota consommé sur l'heure glissante (`HourlyBudgetGuard::usedInWindow()`, sans second compteur) face aux trois plafonds : le quota publié par Blizzard, la limite que le client s'impose, et le plafond que les imports se réservent. Le statut est une fonction pure. Il passe à `warning` au-delà de `NEAR_CEILING_RATIO` (0,9) du plafond des imports, car un import lancé à ce moment-là passerait l'essentiel de son temps à attendre, et à `critical` à la limite appliquée.

**`QueueState`** compte les jobs de la queue `imports` en attente, différés et pris par le worker, et désigne l'import en cours par `CurrentImport`. Les différés sont comptés à part : un import qui attend la libération du quota se redispatche avec un délai, et le confondre avec un job en souffrance ferait croire à un worker arrêté. Une queue qui n'est pas sur Redis ne se mesure pas.

Elle liste aussi les jobs eux-mêmes : `running`, les jobs pris par le worker (sorted set `queues:imports:reserved`, dont le score est l'expiration de la réservation, d'où une prise à score − `retry_after`), et `waiting`, les jobs en attente dans l'ordre de la file (liste `queues:imports`, depuis leur `createdAt`). La source est la file Redis elle-même et non un registre tenu par les jobs : un job tué par une erreur fatale ou un redémarrage du worker disparaît avec la file, sans ligne fantôme. Chaque charge utile est lue par **`QueuedJob`**, qui ne prend que l'étiquette publique `described`, à défaut le nom court de la classe (`displayName`), et `createdAt` — jamais `data`, où un job peut porter le jeton Blizzard d'un joueur. Une charge utile illisible lève `UnreadableQueuedJobException`, que `QueueState` attrape : le job est écarté de la liste avec un avertissement, sans faire tomber la page qui sert justement à diagnostiquer la file.

**`FailedJobs`** liste les jobs abandonnés par le worker, du plus récent au plus ancien, avec la première ligne de leur exception. Un job qui se décrit (`DescribedJob`) y est nommé par son étiquette publique, les autres par leur classe. La trace complète reste en base. Deux actions sont possibles, journalisées avec leur auteur :

- **relancer** passe par `queue:retry`, qui remet le job dans sa queue. C'est le worker qui le rejoue, jamais la requête HTTP. La relance est **refusée tant qu'un import tourne**, car un `RunImportJob` relancé à ce moment-là violerait la règle d'un seul import à la fois, et le job relancé ne sait pas prendre le verrou ;
- **supprimer** retire le job sans le rejouer.

Un uuid inconnu lève `FailedJobNotFoundException`, typiquement parce qu'un autre onglet a déjà traité le job. Un job dont la charge utile ne se relit plus lève `FailedJobNotRetryableException` : `queue:retry` doit désérialiser le job pour recalculer son `retryUntil`, ce qui échoue quand sa classe a été renommée ou supprimée depuis l'échec. Le job reste alors dans la liste, où seule la suppression peut encore le traiter.

**`CatalogueVolumetry`** compte chaque table par un `COUNT` direct, sans cache : la plus grosse, la garde-robe, compte autour de 49 000 lignes, ce qu'un `COUNT` PostgreSQL lit sans peine. Les tables du catalogue suivent `ImportStage::catalogue()`, si bien qu'une entité ajoutée à la chaîne d'import apparaît ici d'elle-même. Elles portent en plus la part de lignes actives et, pour celles qui ont une colonne `icon_url`, la part de lignes sans icône. Chaque table donne un `TableVolume` : une table du catalogue vide y est `critical`, alors qu'une table applicative vide ne signale rien.

**`ApplicationErrorLog`** rend les dernières erreurs du journal que tient `DatabaseErrorHandler`. La source est fixée côté serveur : la page ne désigne ni fichier ni canal, et ne devient donc jamais un lecteur de journaux paramétrable.
