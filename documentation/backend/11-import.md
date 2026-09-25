# Orchestration de l'import

Un import complet enchaîne huit étapes dans un ordre explicite, publie en permanence où il en est, ce qu'il consomme et pourquoi il attend, et reprend là où il s'est arrêté si le worker redémarre.

Deux façons de le lancer, un seul déroulé : la commande `app:wow-data-import` et le job `RunImportJob` appellent le même pipeline. Seule la façon d'attendre diffère — la commande dort, le job relâche le worker et se re-dispatch.

---

## La chaîne

### `ImportStage`

Les étapes, dans leur ordre d'exécution.

| Étape | Valeur | Dépend de | Tables comptées |
|---|---|---|---|
| Socle de référence | `reference` | — | — |
| Hauts faits | `achievements` | — | `wow_achievements` |
| Quêtes | `quests` | socle | `wow_quests` |
| Métiers | `professions` | socle | `wow_professions`, `wow_recipes` |
| Montures | `mounts` | socle | `wow_mounts` |
| Mascottes | `pets` | — | `wow_pets` |
| Décorations | `decor` | — | `wow_decors` |
| Garde-robe | `appearances` | — | `wow_appearances` |

Le socle de référence ouvre la chaîne : les quêtes, les métiers et les montures tirent de `wow_ref_*` ce que l'API n'expose pas, et un import parti d'une base vide sans lui sortirait faux sans rien signaler. Ce n'est pas une entité de catalogue, d'où l'absence de table comptée : `wow_ref_*` est chargée par `COPY` dans des tables sans horodatages.

L'ordre est vérifié contre les dépendances déclarées plutôt que simplement écrit — une dépendance ajoutée à contresens de la chaîne fait tomber le test.

`ImportStage::requested($type)` traduit l'option `--type` : `all` rend la chaîne entière, un nom d'étape rend cette étape seule, et une liste séparée par des virgules — `quests,mounts` — rend cette sélection. Une étape nommée seule ne tire pas le socle derrière elle, pour qu'un réimport ciblé reste ce que l'exploitant a demandé.

Une sélection est rendue **dans l'ordre de la chaîne**, jamais dans celui où elle a été écrite : les dépendances entre étapes ne se négocient pas à la saisie. Un nom inconnu invalide toute la sélection plutôt que d'être ignoré — importer six entités sur sept sans le dire serait le pire des deux.

`ImportStage::catalogue()` rend les sept entités de catalogue, soit la chaîne sans le socle : c'est ce que le panneau liste et ce qu'il accepte de recevoir. `estimatedApiCalls()` donne l'ordre de grandeur d'un import forcé de l'étape, relevé sur les imports complets de septembre 2026 — des mesures, pas des promesses, qui servent à décider entre incrémental et forcé.

### `ImportInventory`

L'état des entités importables tel que `/admin/imports` le montre : ce que chacune pèse en base, la date et le build du dernier import réussi, et l'ordre de grandeur de ce qu'un import forcé coûterait. Les métiers pèsent sur deux tables, dont les lignes s'additionnent — l'exploitant réimporte une entité, pas une table.

Aucun appel à l'API Blizzard : comparer au build courant demanderait une requête sortante à chaque ouverture de page, et c'est le sujet de la détection de patch, décrite plus bas, avec son propre cache.

### `ImportPipeline`

- `begin(string $jobId, array $stages, bool $force, string $trigger)` — ouvre le suivi, une étape par entité demandée, marque ignorées celles que `StageFreshness` tient déjà pour l'amont de leur famille, et ouvre l'entrée d'historique au nom du déclencheur.
- `advance(ImportRun $run, bool $full, ?int $limit)` — exécute **une passe** de la première étape non aboutie, retient l'étape au build si elle a abouti, puis republie l'import.
- `isDone(ImportRun $run)` — plus aucune étape à faire.

Une passe et une seule par appel : c'est ce qui permet au job de rendre la main entre deux étapes sans que le déroulé diffère de celui de la commande.

### `ImportStageRunner` et `ImportStageResult`

Exécute une passe d'une étape et rend ce qu'elle a fait. **Une étape qui échoue est rapportée, jamais propagée** : l'échec d'une entité n'emporte pas les suivantes.

`ImportStageResult` porte l'étape mise à jour et l'attente sur laquelle elle bute. L'attente est rendue à l'appelant plutôt que subie sur place, pour qu'un job puisse relâcher le worker là où une commande préfère dormir.

Seule la garde-robe rend la main avant d'avoir fini : son offset désigne la fenêtre où reprendre, et la passe suivante repart de là.

**Les trois collections fusionnent la curation versionnée avant d'importer.** Les importers de montures, mascottes et décorations rangent chaque entrée d'après la taxonomie en base. Une taxonomie vide ferait passer tout le catalogue « en attente d'arbitrage », sans que rien ne le signale : c'est ce qui s'est produit en production, où l'instantané n'avait jamais été chargé. Avant chacune de ces étapes, le runner fusionne donc en base l'instantané versionné de la collection, par `CollectionTaxonomyLoader::load()`. La fusion est additive : un arbitrage fait en base n'est jamais réécrit, et une correction commitée d'une entrée déjà présente n'écrase pas la valeur de production. Le journal dit combien d'entrées elle a apportées. Si la collection reste sans taxonomie, l'étape **échoue avec sa raison**, et l'importer n'est pas appelé. La curation commitée arrive ainsi en production au premier import qui suit un déploiement, sans commande à lancer.

---

## L'état publié

### `ImportRun`

L'état complet d'un import, immuable et sérialisable de bout en bout : ce que le suivi publie, ce que l'endpoint de progression rend, et ce que le rapport final archive.

| Membre | Rôle |
|---|---|
| `status()` | État global : l'interruption si l'administrateur a repris la main, sinon l'agrégat des étapes |
| `currentStage()` | Étape en cours |
| `fraction()` | Part faite, chaque étape pesant autant que les autres |
| `elapsedSeconds()`, `etaSeconds()` | Temps écoulé, temps restant estimé |
| `summary()` | Rapport lisible, affiché par le panneau et archivable |

**Un échec d'étape ne clôt pas l'import** : il n'est visible qu'une fois toutes les étapes terminées, faute de quoi le front cesserait de suivre un import qui tourne.

### `ImportRunState`

L'état d'un import entier, distinct du statut d'une étape : `pending`, `running`, `paused`, `completed`, `failed`, `cancelled`.

Les quatre premiers se déduisent des étapes. `paused` et `cancelled` ne sont atteignables par aucun enchaînement d'étapes — seulement par une décision humaine — et c'est ce qui les distingue : `ImportRun` les porte dans un champ à part, `interruption`, accompagné de son horodatage, et le constructeur refuse tout autre état dans ce champ.

**`paused` n'est pas terminal**, et c'est ce qui fait que le panneau continue de suivre l'import et peut en proposer la reprise. Les étapes, elles, gardent leur propre statut : l'étape abandonnée au moment d'une annulation reste `running` dans le rapport, où elle est décrite comme abandonnée en cours — c'est ce qu'elle était réellement.

`etaSeconds()` extrapole des durées réellement mesurées — moyenne des étapes faites, multipliée par le nombre d'étapes restantes — et rend `null` tant que rien n'est fait, plutôt qu'un chiffre inventé. L'estimation se rafraîchit à l'étape et non à la seconde : sur une chaîne aux étapes très inégales, extrapoler à la seconde enfle tant qu'une étape longue n'aboutit pas, puis tombe à zéro alors qu'il reste tout à faire.

### `ImportStep` et `ImportStepStatus`

Ce qu'une étape a fait : son état, ses lignes, ses appels API, son temps, et son avancement quand elle se compte en fenêtres. Chaque passe rend une nouvelle étape qui cumule la précédente.

`ImportStepStatus` vaut `pending`, `running`, `completed`, `failed` ou `skipped`. **`skipped` est terminal sans être un échec** : c'est la porte de build qui a constaté qu'il n'y avait rien à refaire.

### `RowTally` et `RowTallyCounter`

Lignes créées, mises à jour et supprimées par une étape, **mesurées sur la base plutôt que rapportées par les importers**.

Les sept importers écrivent par `upsert()` Eloquent, qui entretient `created_at` et `updated_at`, et sautent les lignes inchangées avant d'écrire : une ligne touchée est donc une ligne réellement modifiée. Le décompte se déduit de quatre nombres — lignes touchées, lignes nées, cardinalité avant, cardinalité après — et `RowTally::fromCounts()` est une fonction pure testée comme telle. Un état impossible, plus de lignes créées que touchées, lève plutôt que de rendre un chiffre faux.

Aucune étape n'écrit dans la table d'une autre : deux étapes qui se suivent dans la même seconde ne se disputent jamais une ligne, malgré des horodatages `timestamp(0)`.

### `ImportProgressStore`

Publie et relit l'avancement sous `admin_import:{jobId}`. La valeur stockée garde `status` et `output` — ce que le panneau d'administration lit déjà — et range l'état structuré à côté : le front continue de fonctionner sans une ligne de changement.

`payload()` sert `GET /api/admin/import/{jobId}` : étape en cours, avancement, budget horaire consommé, temps écoulé, temps restant estimé, attente en cours et détail par étape. Un job suivi par le chemin des commandes simples est rendu tel qu'il l'a toujours été.

**Ce magasin n'est pas l'autorité de reprise.** Il vit dans le cache, que le bouton « Vider les caches » efface : la reprise s'appuie sur la charge du job en file et sur `ImportBuildGate`, toutes deux durables.

### `ImportLog` et `ImportLogSlice`

Le journal d'un import : une ligne horodatée par événement — démarrage, étape sautée par la porte de build, étape démarrée, rapport d'étape, échec, attente, rapport final. C'est le pipeline qui écrit, personne d'autre.

Le stockage est une **liste Redis**, `import_log:{jobId}`, parce que le contrat de suivi est « curseur plus delta » : le panneau annonce sa position et ne reçoit que la suite, au lieu de retélécharger le journal entier chaque seconde. `since($jobId, $cursor)` rend un `ImportLogSlice` — les lignes depuis cette position, et la position à présenter au prochain appel.

Le curseur rendu est **toujours la longueur du journal**, jamais la position demandée augmentée du nombre de lignes lues : un lecteur en avance sur le journal se retrouve recalé dessus plutôt que de s'en éloigner. Un curseur négatif lit depuis la première ligne.

| Rétention | Valeur | Pourquoi |
|---|---|---|
| `ImportLog::TTL_S` | 24 h | Relire au matin l'import lancé la veille au soir. Au-delà, c'est l'historique en base qui porte la mémoire, et le rapport plutôt que le détail. |

L'expiration est posée à la création de la clé, pas à chaque ajout : un import bavard ne doit pas faire vivre son journal bien au-delà du jour.

`note()` horodate la ligne à l'écriture — c'est ce qui distingue un import qui progresse lentement d'un import qui ne bouge plus — et c'est le journal qui porte ce format, pas chacun de ceux qui y écrivent.

### `ImportLogOutput`

Détourne la sortie d'une commande Artisan vers le journal d'un import, en se présentant comme un `OutputInterface` de Symfony.

C'est ce qui donne au **chemin générique** de `RunImportJob` — toute commande qui n'est pas l'import orchestré — le même suivi en direct que lui : la commande écrit comme elle l'a toujours fait, et le panneau relit ses lignes au fil de l'eau par le même contrat « curseur plus delta », au lieu d'attendre un bloc de sortie publié à la fin. Une commande peut composer une ligne en plusieurs écritures : la ligne n'est journalisée qu'à sa fin, et les lignes vides dont une commande aère sa sortie sont écartées.

Le chemin générique rend par ailleurs le verrou quoi qu'il arrive, et seulement s'il le détient : `CurrentImport::release()` ne supprime le pointeur que s'il nomme ce job, sans quoi une commande lancée hors du panneau effacerait le pointeur d'un import parti depuis.

### `CurrentImport`

Désigne l'import en cours, sous `import_current`. C'est ce qui permet à un panneau ouvert après coup, rafraîchi, ou ouvert dans un second onglet, de raccrocher un suivi qu'il n'a pas lancé. `ImportPipeline::begin()` le pose, la fin du dernier étage l'efface.

**Le serveur fait autorité, pas la mémoire du navigateur** : un import démarré depuis un autre poste ou en ligne de commande se retrouve de la même façon. Le pointeur expire seul au bout de 24 h, pour qu'un worker tué en cours d'import ne laisse pas le panneau suivre indéfiniment un job qui ne rendra plus rien.

Ce pointeur est aussi le **verrou « un import à la fois »**. `tryMark()` le pose en un aller-retour atomique (`SET … NX`), et c'est `AdminService::startImport()` qui le prend — au lancement, pas quand le worker ramasse le job : entre le clic et la prise du job, un second onglet aurait tout le temps d'en lancer un autre. Un lancement refusé lève `ImportAlreadyRunningException`, qui porte lequel tourne et depuis quand, et le contrôleur le rend en 409. Si la mise en file échoue, le verrou est relâché — un verrou posé sur un import qui n'a jamais démarré bloquerait le panneau jusqu'à son expiration.

### L'index Redis des imports

Le journal et le pointeur vivent sur la connexion `imports`, index 5, **séparée du cache**. La raison est la même que pour le compteur de budget : `cache:clear` émet un `FLUSHDB`, et le panneau sait déclencher un vidage de cache depuis `/admin/tools`. Un administrateur qui vide les caches pendant un import n'a aucune raison de perdre le journal qu'il est en train de lire.

En test, l'index est 14 et la suite le vide avant chaque test — un journal qui fuirait d'un test à l'autre décalerait les curseurs du suivant.

---

## Reprendre la main sur un import

Un import en cours se met en pause, se reprend et s'annule depuis `/admin/imports`. Le principe tient en une phrase : **le contrôleur pose un ordre, le job le lit et décide**. Ni `kill` de processus, ni exception jetée dans un travail en train d'écrire — c'est ce qui garantit qu'une interruption tombe entre deux écritures et jamais au milieu de l'une d'elles.

### `ImportControl`, `ImportSignal` et `ImportRequest`

`ImportControl` porte le drapeau, sous `import_control:{jobId}`, sur l'index Redis des imports comme le journal et le pointeur : couper un import ne doit pas dépendre de ce qu'un autre écran du panneau vient de faire.

`ImportSignal` n'a que deux valeurs, `pause` et `cancel`, parce que ce sont les deux seuls ordres qui s'écrivent : **reprendre consiste à effacer l'ordre posé**, pas à en poser un troisième. Un import qui porterait à la fois « en pause » et « reprends » serait un état impossible à trancher.

`ImportRequest` est l'ordre en attente : le signal, son demandeur, son horodatage. Le demandeur voyage avec l'ordre parce que c'est le job qui journalise, à la frontière suivante, et non le contrôleur qui a reçu le clic. Un drapeau illisible se lit comme aucun ordre : la pire réaction à une clé abîmée serait de laisser un import en pause sans moyen de le reprendre.

### Les deux frontières

| Frontière | Où | Délai pour l'atteindre |
|---|---|---|
| Entre deux passes | `RunImportJob::handle()`, avant `advance()` | La fin de l'étape en cours |
| Entre deux fenêtres | La boucle de `AppearanceImporter::importChunk()` | Une fenêtre de balayage |

La garde-robe est la seule étape assez longue pour qu'attendre sa fin rende une pause inutile : elle reçoit donc une fermeture d'arrêt, consultée à chaque fenêtre au même titre que son time-box, et rend la main sur le même chemin. Ce chemin **précède `deleteRowsOutsideCatalog`**, ce qui rend structurellement impossible qu'une passe interrompue ampute le catalogue. Les six autres étapes s'arrêtent à leur propre fin, où leur balayage de suppression a déjà eu lieu sur un traitement complet.

### La pause est une attente, pas un arrêt du chaînage

Tant que le drapeau `pause` est posé, `RunImportJob` se redispatche toutes les 5 secondes **sans exécuter de passe**. C'est délibéré : la charge du job — les étapes demandées, le mode — reste dans la file, là où la reprise la trouve déjà, et aucun état durable supplémentaire n'est à écrire ni à expirer.

La pause est republiée à chaque battement, pour que l'écran sache depuis quand elle dure, mais **journalisée une seule fois**, à la transition : une ligne toutes les cinq secondes noierait le journal qu'on a mis l'import en pause pour lire.

### L'abandon

Une pause laissée plus d'une heure (`ImportRequest::ABANDON_AFTER_S`) est tenue pour abandonnée : le job la clôt comme une annulation, le journal le dit, et le verrou « un import à la fois » est relâché. Sans cela, un aller-retour distrait confisquerait le panneau jusqu'à l'expiration du pointeur, soit une journée entière. C'est le job qui décide, pas un planificateur — le dépôt n'en a aucun, et un planificateur muet est indiscernable d'un planificateur en panne.

`payload()` expose `interrupted_for` et `abandoned_in`, de quoi afficher depuis quand la pause dure et combien de temps il lui reste.

### `ImportNotRunningException`

**Seul l'import désigné par le pointeur se pilote.** Un identifiant venu de la requête ne doit jamais pouvoir poser un ordre sur autre chose : `AdminService` compare au pointeur et lève sinon, ce que le contrôleur rend en 409. Le cas courant est l'onglet resté ouvert sur un import terminé depuis, d'où un refus qui le dit.

Qui a interrompu quoi part dans deux journaux : celui de l'import, lisible à l'écran mais qui vit 24 h, et le journal applicatif, qui porte la piste d'audit au-delà.

---

## Les attentes

### `ImportWait`, `ImportWaitReason`, `ImportWaitReporter`

Une attente silencieuse est indistinguable d'un blocage. Quatre raisons couvrent tout ce qui met un import en pause :

| Raison | Origine |
|---|---|
| `hourly_budget` | Plafond horaire réservé aux imports atteint |
| `rate_limit_backoff` | Recul après un 429 |
| `batch` | Lot de requêtes en vol |
| `paused` | Pause demandée depuis le panneau |

`ImportWaitReporter` est le service partagé — unique exemplaire tenu par le conteneur — par lequel le client API fait remonter les deux dernières. Le pipeline lui désigne le job suivi le temps d'une passe ; hors import suivi, appel en ligne de commande ou trafic du site, il ne suit rien et ne publie rien.

Le point d'instrumentation est `ImportsFromBlizzardApi::fetchBatchAsync()`, que traversent tous les balayages : un lot en vol y publie sa taille, un recul son délai. Sept importers n'ont ainsi rien à connaître du suivi.

---

## Les appels consommés

Les appels d'une étape sont la différence du total monotone de `HourlyBudgetGuard` entre son début et sa fin. Ce total est incrémenté dans le même aller-retour Redis que le compteur par minute, au seul point de comptage de tout appel Blizzard — `RateLimitingMiddleware`. Pas de second mécanisme, et un chiffre exact là où la différence de `usedInWindow()` aurait été fausse dès qu'une minute sort de la fenêtre glissante pendant l'étape.

---

## Reprise et redémarrage

Chaque passe rendue est un job de file neuf : un worker arrêté entre deux passes reprend immédiatement, et les étapes déjà abouties sont ignorées par la porte de build.

Un worker tué **pendant** une passe est un autre cas : le job reste réservé, et la file ne le rend qu'au bout de `retry_after` (2 000 s). L'import n'est pas perdu — il repart à l'étape qui n'a pas abouti — mais il reste figé jusque-là. C'est pourquoi le conteneur du worker se voit accorder un délai d'arrêt large : `queue:work` termine sa passe sur SIGTERM, encore faut-il lui en laisser le temps.

`retry_after` doit rester supérieur au `timeout` du job, sans quoi une passe encore en vol serait rejouée en parallèle — du trafic Blizzard en double sur un quota. L'invariant a son test dans `tests/Feature/Config/QueueConfigTest.php`.

---

## L'historique des imports

Chaque passage de la chaîne laisse une entrée persistante, qu'il ait été lancé depuis le panneau ou depuis la console. La synchronisation autonome du socle n'y figure pas : elle a déjà son propre historique dans `wow_reference_downloads`, qui garde chaque chargement avec son build et son nombre de lignes. Le socle apparaît dans l'historique quand il fait partie de la chaîne.

### `ImportHistory`

Écrit l'historique. `ImportPipeline::begin()` ouvre l'entrée **dès le lancement**, avec le déclencheur, le mode et une ligne par étape. Un import dont le worker meurt en route n'atteint jamais sa clôture, et il doit quand même figurer dans l'historique, dans l'état réel où il a été laissé. La clôture, qu'elle vienne de la fin des étapes ou d'une annulation, reporte le statut, le quota consommé et, étape par étape, les lignes créées, mises à jour et supprimées, les appels et la durée. Le rapport est repris tel que l'orchestration l'a produit, sans être recalculé.

La clôture relève aussi **le volume de chaque étape sur ses tables** (`rows_after`) : ce que le catalogue pèse réellement après l'import, que l'étape ait tourné, été sautée ou échoué. C'est ce volume qu'on compare d'un import au suivant. Le socle n'en a pas, ses tables n'étant pas celles d'une étape de catalogue.

Le déclencheur est l'identifiant Battle.net de l'administrateur, transmis par `AdminService::startImport()` au job, qui le passe d'une passe à l'autre, ou `console` pour `app:wow-data-import` lancée en ligne de commande.

**Deux rétentions distinctes.** Le rapport est gardé `RETENTION_MONTHS` mois, soit douze, un cycle complet de patchs. Le projet n'a pas de planificateur : la purge se fait à l'ouverture d'un nouvel import, seule porte par laquelle l'historique grossit. Le journal détaillé, lui, garde sa rétention d'une journée dans Redis. L'historique l'affiche tant qu'il vit, puis indique qu'il a expiré.

### `ImportHistoryReader`

Lit l'historique pour le panneau : une page chronologique, le détail d'un import, la comparaison de deux imports.

**Une chute de volumétrie se juge entité par entité**, contre le dernier import qui a mesuré la même entité. Un import partiel ne dit rien des tables qu'il n'a pas touchées, et les comparer à zéro inventerait des chutes. Le seuil est `VolumeShrink::ALERT_RATIO`, le même que celui du socle de référence : un seul seuil, nommé une fois.

Un import resté « en cours » alors que le panneau n'en suit plus aucun, ou en suit un autre, est rendu comme **abandonné** : son worker est mort en route, et il n'atteindra jamais sa clôture. C'est ce qui empêche de le confondre avec un import complet, ou de le croire encore en train de tourner.

La comparaison remet les deux imports dans l'ordre chronologique, quel que soit l'ordre dans lequel on les a désignés, et ne porte que sur les entités qu'ils ont en commun.

### `VolumeShrink`

Le seuil de chute de volume, partagé par le socle de référence et l'historique : perdre un dixième de ses lignes ou plus d'un chargement au suivant. Une table qui était vide ne peut pas avoir fondu.

## Détection de patch

Le tableau de bord `/admin` ouvre sur l'écart entre ce que les amonts servent et ce que WowPlanet a importé. La détection est automatique, le déclenchement ne l'est pas : le jour où un patch change un format, un import automatique appauvrirait le catalogue sans prévenir, et les garde-fous empêchent sa destruction, pas sa dégradation silencieuse. Aucun planificateur n'est introduit — le dépôt n'en a aucun, et comme le lancement reste humain, un patch découvert à l'ouverture du panneau l'est exactement au moment où l'on peut agir dessus.

### Deux familles de builds, jamais comparables entre elles

Le catalogue vient de l'API Blizzard, qui sert son build dans l'en-tête `battlenet-namespace` : `12.1.0_68914`. Le socle vient des tables DB2 de wago, qui sert `12.1.0.69875` — séparateurs et valeurs différents, et wago prend de l'avance. Rapprocher les deux numéros signalerait un écart permanent qui n'existe pas.

Chaque famille se compare donc au sien, et à lui seul. C'est aussi pourquoi l'état du socle se lit sur l'inventaire de ses chargements et non sur `wow_import_states` : une synchronisation lancée depuis `/admin/reference` ne passe pas par le pipeline et n'écrit rien dans cette table, qui ignorerait alors un socle pourtant à jour.

### `UpstreamSource`, `UpstreamBuild`, `UpstreamBuildProbe`

`UpstreamSource` énumère les deux amonts et porte leur libellé et leur clé de cache. `UpstreamBuild` est ce qu'on sait de l'un d'eux : un build, la date à laquelle il a été lu, et si la lecture a abouti. Ses deux constructeurs nommés rendent inconstructible l'état « aboutie mais sans build », qui n'existe pas.

`UpstreamBuildProbe` tient **deux mémoires distinctes**, et c'est le cœur du sujet :

- le **cache Redis**, une heure, évite une requête sortante à chaque ouverture de page pour une valeur qui change au rythme des patchs ;
- la **table `wow_upstream_builds`** retient la dernière valeur lue, avec sa date. C'est elle qui permet d'afficher quelque chose quand un amont ne répond plus, et de le dater honnêtement. En base et non en cache, précisément parce que le panneau sait vider les caches : perdre cette valeur au moment où elle sert serait le contraire de ce qu'on en attend.

Un échec n'écrit jamais le cache — la vue suivante retente — et ne touche ni au build retenu ni à sa date : il ne dit rien de l'amont, seulement qu'on n'a pas pu lui parler. Seule la colonne `outcome` passe à `unreachable`.

`LiveReferenceBuild` délègue à cette sonde et garde son contrat propre : `null` dès que wago n'a pas répondu, **y compris quand un build plus ancien reste connu**. Situer le socle contre une valeur périmée le dirait à jour sur la foi d'un appel raté.

### `StageFreshness`

L'unique endroit qui sait qu'une étape se juge contre l'amont de sa famille : le socle contre wago, via `ReferenceBuildState`, tout le reste contre Blizzard, via `ImportBuildGate`.

Cette classe est appelée par le bandeau **et** par `ImportPipeline::begin()`. Ils ne peuvent donc pas diverger, et c'est ce qui rend le bouton de mise à jour honnête : sans elle, l'étape du socle était retenue sur le build Blizzard du moment, et un socle que wago avait dépassé était signalé en retard par l'écran puis sauté par l'import qu'on venait de lancer.

Les builds entrent en paramètre plutôt que d'être lus là : les lire coûte deux requêtes sortantes, qui n'ont leur place ni dans une boucle d'import ni dans le rendu d'une page. `begin()` n'interroge wago que si le socle figure parmi les étapes demandées.

### `ReferenceBuildState`

Sur quel build le socle se trouve, et combien de ses tables sont restées en arrière, lus sur `wow_reference_downloads`. Une table jamais chargée compte comme en retard, au même titre qu'une table restée sur un build antérieur. Sans build wago à quoi comparer, rien n'est signalé : un amont muet ne doit pas faire passer tout le socle pour périmé.

### `BuildStatus`

Compose la charge `buildStatus` que le tableau de bord reçoit en **prop différée** : les deux amonts avec leur dernière lecture, une entrée par étape de la chaîne dans l'ordre de celle-ci, la liste des étapes en retard, et deux drapeaux.

Le socle porte en plus une `note` que les autres entités n'ont pas : il est la seule entité que son build ne décrit pas entièrement. Partiellement rechargé, son dernier chargement est au build servi alors qu'une table est restée derrière, et la ligne afficherait deux builds identiques sous une alerte de retard. La note dit combien de tables sont concernées, le détail restant sur `/admin/reference`.

Chaque entrée porte un `state` en quatre valeurs exclusives — `current`, `stale`, `never`, `unknown` — plutôt que des booléens orthogonaux. `unknown` dit que l'amont de cette entité n'a pas pu être lu, et c'est ce qui empêche structurellement l'écran d'annoncer « à jour » sur la foi d'un appel raté. Le build de l'amont est répété sur chaque ligne bien qu'il soit déductible : deux familles de numéros cohabitent à l'écran, et rien à rapprocher côté client signifie aucune occasion de les croiser.

Les deux drapeaux se lisent ensemble et ne disent pas la même chose. `is_conclusive` vaut vrai quand les deux amonts ont répondu ; `is_up_to_date` exige en plus que rien ne soit en retard. Un amont muet interdit donc la seconde affirmation quel que soit l'état de la base. `behind` liste les étapes `stale` et `never` — une entité jamais importée est en retard au sens de l'exploitant — mais jamais les `unknown` : on ne propose pas de réimporter ce qu'on n'a pas pu situer.

### Le lancement depuis le bandeau

`POST /api/admin/import` accepte le socle au même titre que les entités de catalogue, sa liste blanche étant `ImportStage::chain()` et non `catalogue()`. Un bouton « tout mettre à jour » envoie donc `behind` tel quel, en mode **incrémental** : `ImportStage::requested()` rétablit l'ordre de la chaîne, le socle part en tête, et les dépendances sont respectées par construction. Forcer relancerait les appels des entités déjà à jour pour rien, la porte de build faisant désormais le bon travail sur chaque famille.

`POST /api/admin/build-check` rouvre les amonts sans attendre l'expiration du cache. Elle ne rend pas la comparaison : c'est le rechargement de la prop différée qui la sert, et un seul sérialiseur pour les deux chemins ne peut pas diverger de lui-même.
