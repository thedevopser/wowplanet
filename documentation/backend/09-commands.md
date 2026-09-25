# Commandes Artisan

Les commandes s'exécutent en local, `php artisan <commande>`, qui attaque la même base que le conteneur. Passer par le conteneur quand on veut son état, typiquement ses caches :
```bash
docker compose exec app php artisan <commande>
```

---

## `app:wow-data-import`

Enchaîne les huit étapes d'un import complet, du socle de référence à la garde-robe, et rend un rapport par étape : lignes créées, mises à jour et supprimées, appels API consommés, durée. Écrit par `upsert` — une ligne absente du catalogue servi par l'API est supprimée par le balayage de rebut de chaque importer, jamais par une troncature.

**Signature** : `app:wow-data-import {--type=all} {--force} {--full} {--limit=}` — **Classe** : `WowDataImportCommand`

| Option | Rôle |
|---|---|
| `--type` | `all` (défaut) ou une étape seule : `reference`, `achievements`, `quests`, `professions`, `mounts`, `pets`, `decor`, `appearances` |
| `--force` | Réimporte même si le build WoW n'a pas changé depuis le dernier import |
| `--full` | Garde-robe : rafraîchit toutes les icônes au lieu des seules manquantes |
| `--limit` | Borne le nombre de fenêtres balayées par passe, pour un smoke-test sans consommer le quota |

**Ordre des opérations** et détail du pipeline : voir [Orchestration de l'import](11-import.md). Les étapes déjà à jour pour le build courant sont ignorées, une étape qui échoue n'interrompt pas les suivantes, et la commande sort en échec si l'une d'elles a échoué.

Le panneau d'administration lance exactement la même chaîne via `RunImportJob`, qui rend la main entre deux étapes au lieu de dormir.

---

## `app:generate-favicons`

Génère tous les formats de favicon à partir de `public/images/logo.png`.

**Signature** : `app:generate-favicons` — **Classe** : `GenerateFaviconsCommand`

À lancer à la main quand le logo change ; les fichiers produits sont versionnés dans `public/`.

**Fichiers générés dans `public/`**

| Fichier | Taille |
|---|---|
| `favicon-16x16.png` | 16×16 |
| `favicon-32x32.png` | 32×32 |
| `apple-touch-icon.png` | 180×180 |
| `mstile-150x150.png` | 150×150 |
| `android-chrome-192x192.png` | 192×192 |
| `android-chrome-512x512.png` | 512×512 |
| `favicon.ico` | Multi-taille (16+32+48) |

Supporte les sources PNG, JPEG et WebP. Utilise l'extension GD de PHP.

---

## `app:ping-search-engines`

Notifie les moteurs de recherche de l'existence du sitemap.

**Signature** : `app:ping-search-engines` — **Classe** : `PingSearchEnginesCommand`

À lancer à la main après une mise en production qui change le plan du site. Le sitemap visé est `APP_URL/sitemap.xml`.

- **Bing** : ping via `https://www.bing.com/ping?sitemap=<url>`
- **Google** : affiche les instructions de soumission manuelle (Google a supprimé le ping automatique en 2023)

---

## `app:wow-reference-sync`

Charge les tables de référence DB2 depuis wago.tools dans les tables `wow_ref_*`.

**Signature** : `app:wow-reference-sync {--table=}` — **Classe** : `WowReferenceSyncCommand`

Elle lit le build LIVE, télécharge les huit tables du `ReferenceCatalog`, les écrit dans le magasin `storage/app/wow-reference/` puis les charge par `COPY`. La sortie rend la volumétrie table par table, avec l'écart au chargement précédent.

`SpellMisc` pèse à elle seule 45 Mo pour 417 583 lignes, contre une quarantaine de milliers pour les six premières : c'est le prix de l'icône des montures, que l'API n'expose sur aucun endpoint.

Rien n'est écrit en base avant que **tous** les téléchargements ne soient acquis, et le chargement tient dans une seule transaction. Un téléchargement refusé, un fichier sans en-tête, une colonne disparue ou une volumétrie effondrée sous la moitié du dernier chargement interrompent la commande sans toucher au socle existant : un socle à moitié chargé est pire qu'un socle périmé.

`--table` restreint la synchronisation à une seule table DB2, désignée par son nom chez wago.

Le détail des classes est dans [Couche Infrastructure](05-infrastructure.md).

---

## `app:collection-taxonomy-sync`

Charge la taxonomie curée des montures, mascottes et décorations dans `wow_collection_taxonomy`.

**Signature** : `app:collection-taxonomy-sync {--entity=} {--upstream}` — **Classe** : `CollectionTaxonomySyncCommand`

Deux chemins, parce que les deux besoins sont distincts. Par défaut la commande lit l'instantané versionné `database/data/collection_taxonomy.csv` : reconstruire la taxonomie de zéro ne demande alors ni réseau ni tiers. Avec `--upstream` elle tire la curation fraîche de SimpleArmory, seul amont à ranger une monture ou une décoration, puis réexporte l'instantané pour que le dépôt garde la trace de ce que le patch a apporté — le fichier produit est à commiter.

Le chargement est additif dans les deux cas : **aucune valeur déjà en base n'est réécrite**, de sorte qu'un arbitrage manuel survit à autant de rafraîchissements qu'on voudra.

`--upstream` refuse une taxonomie vide. C'est un chemin d'enrichissement, et l'appliquer à une base nue ferait entrer une curation que l'instantané contredit délibérément sur plusieurs centaines d'entrées — les fichiers curés marquent hors d'atteinte 301 montures et 131 mascottes que la base garde obtenables.

Les collections demandées sont chargées dans une seule transaction : une source manquante laisse la taxonomie exactement dans l'état où elle était, plutôt qu'à moitié amorcée. La sortie rend, par collection, le nombre d'entrées lues, le total en base et le nombre d'ajouts.

`--entity` restreint la synchronisation à une collection (`mount`, `pet` ou `decor`).

Elle n'est pas dans le chemin d'un import : aucun import ne contacte simplearmory.com, et un import complet réussit avec l'accès à ce domaine coupé.

---

## `app:collection-taxonomy-export`

Écrit l'instantané versionné de la taxonomie depuis la base.

**Signature** : `app:collection-taxonomy-export {--path=}` — **Classe** : `CollectionTaxonomyExportCommand`

C'est le pendant de `app:collection-taxonomy-sync` : celle-ci charge l'instantané en base, celle-là le regénère. On l'exécute après un arbitrage manuel, et `--upstream` l'appelle lui-même après un tirage. Le fichier produit se commite — sans quoi la curation ne vit que dans une base et se perd au prochain environnement.

Elle refuse d'écrire un instantané vide : une table vide au moment de l'export écraserait silencieusement le fichier curé du dépôt.

`--path` vise un autre fichier, pour comparer deux états sans toucher à celui du dépôt.

---

## `app:collection-taxonomy-report`

Liste les entrées de catalogue que la taxonomie ne range pas encore.

**Signature** : `app:collection-taxonomy-report {--entity=} {--limit=20}` — **Classe** : `CollectionTaxonomyReportCommand`

Le rapport n'est pas stocké : l'absence de ligne de taxonomie pour une ligne de catalogue *est* le rapport, et une jointure gauche le reconstitue à tout moment. Une entrée rangée nulle part en connaissance de cause porte une ligne de taxonomie aux deux libellés nuls : elle est curée, donc hors de ce rapport.

La sortie rend, par collection, le nombre d'entrées à arbitrer sur le total du catalogue, puis les premières par identifiant. `--limit` règle ce détail, `--entity` restreint à une collection.

---

## `coverage:crap`

Liste les méthodes dont le CRAP dépasse un seuil, lues dans un rapport clover de PHPUnit.

**Signature** : `coverage:crap {--clover=} {--threshold=30}` — **Classe** : `CoverageCrapCommand`

Elle lit `coverage/clover.xml` par défaut, que `make coverage`, `make coverage-php` et l'étape de couverture de la CI produisent. Le clover se produit dans le conteneur, où vit pcov, et se lit en local : `coverage/` est monté des deux côtés. Les méthodes au-dessus du seuil sortent dans un tableau, de la pire à la moins grave, avec leur CRAP, leur complexité et leur couverture.

La commande échoue quand une méthode dépasse le seuil, quand le clover est absent, quand il est illisible ou quand le seuil n'est pas un entier positif. Dans les trois derniers cas, elle affiche un message d'une ligne et aucune pile d'exceptions. `make crap`, `make coverage`, `make coverage-php` et `make coverage-php-ci` l'appellent et échouent avec elle : c'est l'un des trois contrôles bloquants décrits dans [Qualité et CI](10-qualite-ci.md).

Le calcul est porté par `CloverReader` et `CrapReport`, décrits dans [Infrastructure](05-infrastructure.md).

---

## `mutation:scope`

Planifie la mutation : une ligne par classe à muter, suivie de ses tests dédiés, séparés par des espaces. Elle couvre le périmètre entier, ou seulement la part qu'une branche concerne.

**Signature** : `mutation:scope {--base=}` — **Classe** : `MutationScopeCommand`

Sans `--base`, elle rend chaque fichier PHP du périmètre déclaré dans `mutation-perimeter.txt`, les répertoires étant développés. Avec `--base`, elle compare la branche au point où elle a quitté cette référence : fichiers modifiés depuis le `merge-base`, fichiers non suivis, et entrées absentes du périmètre à ce point. Une base égale à l'identifiant nul de Git (`0000…`), que GitHub envoie comme commit précédent au premier push d'une branche, compte comme une absence de base : rien ne précède la branche, tout le périmètre est rendu. Les tests dédiés d'une classe sont les fichiers `tests/**/<Classe>*Test.php`. Une classe qui n'en a aucun sort seule sur sa ligne. La sélection et l'appariement sont ceux de `MutationScope`, décrits dans [Infrastructure](05-infrastructure.md).

`scripts/mutate.sh` déroule ce plan, une invocation Pest par ligne. Une branche qui ne touche rien du périmètre ne rend rien, et le script s'arrête alors sans lancer Pest.

Elle échoue quand le fichier de périmètre manque, quand une entrée déclarée n'existe plus (le plugin l'ignorerait sans rien dire), ou quand Git échoue, sur une base inconnue par exemple. Git est appelé avec une liste d'arguments, jamais par une chaîne passée au shell.

---

## `docs:coverage`

Mesure la part du code nommée dans les pages de `documentation/`, et échoue quand elle recule.

**Signature** : `docs:coverage` — **Classe** : `DocsCoverageCommand`

Elle rend le pourcentage de couverture, puis la liste des classes documentées nulle part, groupées par couche. Son code de sortie ne dépend pas du pourcentage mais d'un **compte** : elle échoue dès que le nombre de classes non documentées dépasse le plafond de `config/documentation.php`.

Le plafond ne remonte jamais. Une classe neuve non documentée le fait monter d'un, et le pipeline passe au rouge — c'est tout l'objet de la commande. Un ratio, lui, aurait aussi bougé en supprimant une classe documentée, et aurait donc échoué sans faute.

| Clé de `config/documentation.php` | Rôle |
|---|---|
| `source_paths` | Répertoires parcourus, chacun enraciné sur le namespace `App\`. |
| `pages_path` | Arborescence Markdown fouillée, sous-répertoires compris. |
| `exclude` | Classes hors périmètre, chacune avec la raison qui l'en sort. |
| `max_undocumented` | Plafond de classes tolérées sans page. |

Une classe compte comme documentée quand son nom court ouvre une portion entre backticks. La correspondance porte sur le jeton entier : `ImportStageRunner` ne satisfait pas `ImportStage`.

La commande ne modifie aucun fichier, et une étape bloquante du pipeline l'exécute. Voir [Qualité et CI](10-qualite-ci.md).
