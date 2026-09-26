# Couche Http — Controllers et Middleware

Les controllers reçoivent la requête, délèguent aux services et rendent soit une page Inertia, soit une `JsonResponse`. Ils ne contiennent pas de logique métier. Tous étendent `Controller`, classe abstraite vide laissée par le squelette de Laravel.

---

## Deux fichiers de routes, deux natures de réponse

**`routes/web.php` porte les pages.** Chaque route rend un composant Inertia résolu par son nom dans `resources/js/pages/`. Le nom passé à `Inertia::render()` doit donc correspondre au nom du fichier `.vue`. Le premier chargement arrive en HTML complet, les navigations suivantes en JSON Inertia, sans rechargement.

**`routes/api.php` porte les endpoints XHR**, préfixés par `/api`. Ce sont les appels que le front lance après coup : onglets chargés à la demande, actions de l'utilisateur, pilotage du panneau d'administration. Le groupe `api` démarre la session, ce que Laravel ne fait pas par défaut, parce que ces endpoints en dépendent pour l'authentification et le limiteur par session.

**Un même contrôleur sert souvent les deux.** `CharacterController::page()` rend la fiche en page Inertia pour une visite, `CharacterController::show()` rend le même `CharacterProfileDTO` en JSON pour un rafraîchissement côté client. Les deux passent par le même service, et la forme servie est identique.

**La route catch-all ferme `web.php`.** `/{any}` rend la page 404 Inertia (`SeoController::notFound()`), avec un vrai statut 404, pour toute URL inconnue. Elle exclut `api/` et `docs/` par une expression régulière : une URL d'API inconnue garde la 404 JSON de Laravel, et la documentation locale reste servie. **Toute nouvelle route de premier niveau doit être déclarée avant elle**, sans quoi le catch-all l'avale et rend la 404 sans erreur.

---

## Authentification

Il n'y a **pas de garde Laravel, pas de modèle d'utilisateur authentifié et pas de middleware `auth`**. C'est délibéré : l'application n'a pas de comptes à elle, seulement l'identité Battle.net de la personne connectée, et tout ce qu'elle lit sur un compte, elle le lit chez Blizzard avec le token de l'utilisateur.

Le flux est entièrement porté par la session :

1. `AuthController::redirect()` envoie vers Battle.net avec un `state` retenu en session.
2. `AuthController::callback()` vérifie le `state`, échange le code contre un token et le range sous `blizzard_user_token`. Il lit ensuite l'identité sur `oauth/userinfo` : `bnet_user_id` et `bnet_battletag`.
3. Si cet identifiant est celui d'`ADMIN_BNET_ID`, le callback pose aussi `is_admin`. C'est la seule façon de devenir administrateur.

**La présence du token en session vaut authentification**, et rien d'autre ne la vaut. Aucune route n'est protégée par un middleware d'authentification : chaque action qui en a besoin vérifie elle-même, et rend un 401 JSON, ou redirige une page vers l'accueil avec le flash `auth_required`.

Côté contrôleur, le point de passage est le trait `ResolvesBnetUser` : `getAuthenticatedUserId()` rend le `bnet_user_id` de la session, ou `null` si aucun token n'est présent. C'est cet identifiant qui indexe les données applicatives (favoris, tâches, données croisées). `UserCharacterService::isAuthenticated()` fait le même test pour les contrôleurs qui ne manipulent pas l'identifiant.

L'administration est la seule exception à l'absence de middleware : l'alias `admin` (`EnsureIsAdmin`) protège les pages et l'API du panneau.

`HandleInertiaRequests` partage l'état de connexion avec toutes les pages (`auth.isAuthenticated`, `auth.isAdmin`, `auth.battletag`), plus les messages flash (`flash.success`, `flash.error`, `flash.authRequired`). C'est la seule source de cet état côté front : aucun endpoint ne le redonne. Il lit la session directement, sans passer par `UserCharacterService`, qui est couplé au client Blizzard : l'état d'authentification ne doit coûter aucun appel sortant à chaque requête.

---

## Limiteurs de débit

Trois limiteurs, définis dans `AppServiceProvider` :

| Limiteur | Quota | Clé | Porté par |
|---|---|---|---|
| `api` | 60 par minute | Adresse IP | La fiche personnage et ses onglets en JSON, accessibles sans connexion. |
| `authenticated` | 30 par minute | Session | Les endpoints liés au compte : auth, personnages, score, favoris, tâches. |
| `admin` | 180 par minute | Session | Pages et API du panneau d'administration, voir plus bas. |

Un limiteur par session ne tient que si la session persiste d'un appel à l'autre. En test, un appel JSON n'envoie de cookie qu'avec `withCredentials()` : sans lui, chaque requête ouvre une session neuve et échappe au limiteur.

---

## Controllers (`app/Http/Controllers/`)

### `AdminController`

Porte les endpoints JSON du panneau d'administration, appelés en XHR depuis ses pages. Toutes les routes sont protégées par le middleware `admin`.

**Dépendance** : `AdminService`

| Méthode | Route | Description |
|---|---|---|
| `status()` | `GET /api/admin/status` | Retourne si le mode maintenance est actif. |
| `import(Request)` | `POST /api/admin/import` | Met un import en file et rend son `jobId`. Prend `scope` (`all` ou `selection`), `stages` (toute étape de la chaîne, socle compris) et `mode` (`incremental` ou `forced`). Rend 409 si un import tourne déjà. |
| `importStatus(Request, string $jobId)` | `GET /api/admin/import/{jobId}` | Avancement d'un import, plus les lignes de journal apparues depuis `?cursor=`. |
| `currentImport()` | `GET /api/admin/import/current` | Le `jobId` de l'import en cours, ou `null`. Déclarée avant la route à paramètre, qui prendrait sinon `current` pour un identifiant. |
| `pauseImport(string $jobId)`, `resumeImport(string $jobId)`, `cancelImport(string $jobId)` | `POST /api/admin/import/{jobId}/pause`, `POST /api/admin/import/{jobId}/resume`, `POST /api/admin/import/{jobId}/cancel` | Pilote l'import en cours. L'ordre est posé par `AdminService` et lu par le job à la frontière de tranche suivante. Rend 409 si l'import n'est plus en cours. |
| `syncReference(Request, ReferenceCatalog)` | `POST /api/admin/reference/sync` | Met une synchronisation du socle en file. Prend `scope` (`all` ou `table`) et `table`, confronté au catalogue serveur. Rend 409 si un import tourne déjà. |
| `purgeReference(Request, ReferenceFileInventory)` | `POST /api/admin/reference/purge` | Retire des fichiers du magasin de référence et rend ce qui a été libéré. Prend `scope` (`obsolete` ou `selection`) et `files`. Rend 409 si un import tourne déjà. |
| `arbitrateTaxonomy(Request)` | `POST /api/admin/taxonomy/arbitrate` | Range ou réaffecte des entrées de collection, applique le rangement au catalogue et rend l'état de l'instantané versionné. Prend `entity`, `entries`, `category` et `source` — les deux derniers nuls rangent délibérément nulle part. Une entrée absente du catalogue rend un 422 avec `message` et `entries`. |
| `retryFailedJob(FailedJobs, string $uuid)` | `POST /api/admin/failed-jobs/{uuid}/retry` | Remet un job échoué dans sa queue, pour que le worker le rejoue. Rend 409 si un import tourne, 404 si le job est inconnu, 422 si sa charge utile ne se relit plus. |
| `forgetFailedJob(FailedJobs, string $uuid)` | `DELETE /api/admin/failed-jobs/{uuid}` | Supprime un job échoué sans le rejouer. Rend 404 si le job est inconnu. |
| `loadTaxonomySnapshot(TaxonomySnapshotMerge)` | `POST /api/admin/taxonomy/load` | Recharge l'instantané versionné en base, additivement, et rend le nombre d'entrées ajoutées. |
| `downloadTaxonomySnapshot(TaxonomySnapshotExporter)` | `GET /api/admin/taxonomy/snapshot` | Télécharge l'instantané régénéré depuis la base, à verser au dépôt. Rend 422 si la taxonomie est vide. |
| `checkBuilds(BuildStatus)` | `POST /api/admin/build-check` | Rouvre les deux amonts sans attendre l'expiration du cache. Ne rend pas la comparaison : c'est le rechargement de la prop différée du tableau de bord qui la sert. |
| `clearCache()` | `POST /api/admin/clear-cache` | Vide tous les caches. |
| `maintenance(Request)` | `POST /api/admin/maintenance` | Active/désactive le mode maintenance. |
| `discord(Request)` | `POST /api/admin/discord` | Envoie un embed Discord. |

---

### `AuthController`

Le flux OAuth2 Battle.net, décrit plus haut.

| Méthode | Route | Description |
|---|---|---|
| `redirect()` | `GET /auth/blizzard/redirect` | Redirige vers la page d'autorisation Battle.net. |
| `callback(Request)` | `GET /auth/blizzard/callback` | Reçoit le code, remplit la session et redirige vers la page que le visiteur avait demandée avant de se connecter (`url.intended` en session), ou vers `/mon-compte` à défaut. Seul un chemin du site est suivi, jamais une URL absolue, pour ne pas servir de redirection ouverte. Un échec redirige vers l'accueil avec un message flash d'erreur en français, et garde la page retenue pour la tentative suivante. |

La déconnexion et le statut ne sont pas ici mais dans `UserCharacterController`.

---

### `UserCharacterController`

Les endpoints JSON liés au compte connecté, sous le limiteur `authenticated`.

**Dépendances** : `UserCharacterService`, `AccountScoreService`, `CrossCharacterService`

| Méthode | Route | Description |
|---|---|---|
| `logout()` | `POST /api/auth/logout` | Vide les clés de session de l'authentification. |
| `index()` | `GET /api/user/characters` | Personnages du compte avec leur avatar. 401 sans connexion. |
| `classIcons()` | `GET /api/class-icons` | Icône de chaque classe, par identifiant. |
| `accountScore()` | `GET /api/account/score` | Score de compte, depuis le cache ou en cours de calcul. |
| `refreshAccountScore()` | `POST /api/account/score/refresh` | Invalide le score de compte pour le recalculer. |
| `crossCharacter(Request)` | `GET /api/account/cross-character` | Lance ou rend le calcul de progression sur tous les personnages. Passe au service le BattleTag de la session (`bnet_battletag`), qui étiquette le calcul dans la page Santé. |
| `crossCharacterStatus(string $jobId)` | `GET /api/account/cross-character/{jobId}` | État d'un calcul en cours. |
| `crossCharacterData()` | `GET /api/account/cross-character-data` | Données stockées du dernier calcul. |

---

### `AccountController`

Le hub « Mon compte », une seule page Inertia `AccountPage` à trois vues : `GET /mon-compte` (personnages), `/mon-compte/score` et `/mon-compte/classes`, passées en prop `view` (`personnages`, `score`, `classes`). Une vue inconnue rend la 404. Sans connexion, la page retient l'URL demandée en session sous `url.intended`, que le callback OAuth rouvrira, et redirige vers l'accueil avec le flash `auth_required`. Les données de chaque vue arrivent ensuite par les endpoints de `UserCharacterController`.

Les anciennes adresses `/my-characters`, `/my-score` et `/class-stats` redirigent en 301 vers leur vue ; elles restent exclues par `robots.txt`, comme `/mon-compte`.

### `CharacterController`

La fiche personnage, sous ses deux formes.

**Dépendances** : `CharacterProfileService`, `UserCharacterService`, `CrossCharacterService`, `CharacterSeoService`

| Méthode | Route | Description |
|---|---|---|
| `page(Request, string $realm, string $name, ?string $section, ?string $sub)` | `GET /character/{realm}/{name}/{section?}/{sub?}` | Page Inertia `CharacterPage`, avec le profil en prop `character`, ses métadonnées, `section` et `sub` (la vue demandée, voir `CharacterSheetView`), et `isOwner`, vrai quand le personnage appartient au compte connecté. Toutes les vues d'une fiche partagent la même URL canonique, celle de base, et seule celle-ci figure au sitemap. Une URL qui n'est pas en minuscules, segments de section compris, est redirigée en 301 vers sa forme en minuscules. Un segment inconnu rend la page introuvable, en 404, sans rien journaliser. Un personnage introuvable rend la même page avec `character` à `null` et un statut 404. |
| `show(string $realm, string $name)` | `GET /api/character/{realm}/{name}` | Le même `CharacterProfileDTO` en JSON. 500 si l'API échoue. |

Quand le personnage consulté appartient au compte connecté (`UserCharacterService::ownsCharacter()`), les deux le fusionnent au passage dans les données croisées du compte (`CrossCharacterService::mergeCurrentCharacter()`). La fiche d'un autre joueur n'y entre jamais : elle créditerait le compte d'une progression qu'il n'a pas faite. Un échec de cette fusion n'est que journalisé : il ne doit jamais empêcher d'afficher la fiche.

---

### `CharacterFavoriteController`

Les favoris de l'utilisateur connecté, par `ResolvesBnetUser` et `CharacterFavoriteService`. 401 sans connexion.

| Méthode | Route | Description |
|---|---|---|
| `index()` | `GET /api/character-favorites` | Les favoris dans leur ordre. |
| `store(Request)` | `POST /api/character-favorites` | Ajoute un favori (`realm_slug`, `character_name`). 422 avec le maximum quand la limite est atteinte (`FavoriteLimitReachedException`). |
| `destroy(string $realm, string $name)` | `DELETE /api/character-favorites/{realm}/{name}` | Retire un favori. |

---

### `TalentController`

Sert l'onglet talents de la fiche personnage.

**Dépendances** : `BlizzardApiClient`, `TalentAggregator`

| Méthode | Route | Description |
|---|---|---|
| `show(string $realm, string $name)` | `GET /api/character/{realm}/{name}/talents` | Arbre de talents de la spécialisation active, avec le loadout actif. 404 sans spécialisation active ou sans arbre, 500 si l'API échoue. |

Trois appels à l'API : les spécialisations du personnage, la spécialisation jouable qui donne l'identifiant de l'arbre, puis l'arbre lui-même et le media de chacun de ses sorts. Les deux derniers sont des données statiques, mis en cache une semaine :

| Clé | Contenu |
|---|---|
| `playable_spec:{specId}` | L'identifiant de l'arbre. Le store Redis rend les nombres en chaîne, d'où la conversion à la lecture. |
| `talent_tree:v2:{treeId}:{specId}` | La réponse décodée de l'arbre et la carte `spellId → icône`, pas des objets : le contenu se relit à l'identique sur le store `array` comme sur Redis, et survit à un changement de classe. `v2` écarte les entrées de l'ancien format, où les icônes étaient injectées dans l'arbre. |
| `spell_icon:{spellId}` | L'icône d'un sort, trente jours. |

---

### `PvpController`

**Dépendances** : `PvpProfileService`, `PvpLeaderboardService`, `CharacterSeoService`

| Méthode | Route | Description |
|---|---|---|
| `show(string $realm, string $name)` | `GET /api/character/{realm}/{name}/pvp` | PvP du personnage, `{pvp: null}` sans données. Une erreur de l'API rend aussi `null` : l'onglet ne doit jamais casser la fiche. |
| `leaderboard(Request $request, ?string $bracket)` | `GET /classements-pvp/{bracket?}` | Page Inertia `PvpLeaderboardPage` : classement paginé, brackets disponibles, recherche. |

---

### `CharacterTaskController`

CRUD des tâches personnalisées (quotidiennes, hebdomadaires, mensuelles) associées aux personnages.

**Dépendance** : `CharacterTaskService`

| Méthode | Route | Description |
|---|---|---|
| `index()` | `GET /api/character-tasks` | Liste toutes les tâches de l'utilisateur connecté. |
| `store(Request)` | `POST /api/character-tasks` | Crée une tâche. Champs : `realm_slug`, `character_name`, `name`, `reset_type`. |
| `update(int $id)` | `PUT /api/character-tasks/{id}` | Bascule l'état complété/non complété. |
| `destroy(int $id)` | `DELETE /api/character-tasks/{id}` | Supprime une tâche. |

---

### `DatabaseController`

Les pages publiques de la base de données, en Inertia, alimentées par `DatabaseQueryService`.

| Méthode | Route | Composant |
|---|---|---|
| `index()` | `GET /base-de-donnees` | `DatabaseIndexPage` |
| `mounts(?string $category)` | `GET /base-de-donnees/montures/{category?}` | `DatabaseMountsPage` |
| `achievements(Request, ?string $expansion)` | `GET /base-de-donnees/hauts-faits/{expansion?}` | `DatabaseAchievementsPage` |
| `quests(Request, ?string $expansion)` | `GET /base-de-donnees/quetes/{expansion?}` | `DatabaseQuestsPage` |
| `pets(?string $category)` | `GET /base-de-donnees/mascottes/{category?}` | `DatabasePetsPage` |
| `decors(?string $category)` | `GET /base-de-donnees/decorations/{category?}` | `DatabaseDecorsPage` |
| `appearances(Request, ?string $slot)` | `GET /base-de-donnees/garde-robe/{slot?}` | `DatabaseTransmogPage` |
| `professions(Request, ?string $profession)` | `GET /base-de-donnees/professions/{profession?}` | `DatabaseProfessionsPage` |
| `sitemap()` | `GET /sitemap-database.xml` | Sitemap XML des pages de la base. |

Les pages paginées lisent `?page=` et `?search=`. Toutes partagent les props de la barre latérale, `counts` et `subCategories`, passées en closures : un rechargement partiel d'Inertia (changement de page, recherche) ne les recalcule pas. Elles sont en cache une heure, voir `DatabaseQueryService`.

---

### `SeoController`

Les pages de contenu et les fichiers destinés aux moteurs de recherche.

**Dépendance** : `CharacterSeoService`

| Méthode | Route | Description |
|---|---|---|
| `home()` | `GET /` | Page `HomePage`. |
| `faqPage()`, `cguPage()`, `privacyPage()`, `addonsPage()` | `GET /faq`, `/cgu`, `/privacy`, `/addons` | Pages statiques, chacune avec ses métadonnées. |
| `notFound(Request)` | `GET /{any}` | La route catch-all : page `NotFoundPage` en 404. |
| `sitemap()`, `sitemapPages()` | `GET /sitemap.xml`, `/sitemap-pages.xml` | Index des sitemaps, et sitemap des pages statiques. |
| `robots()` | `GET /robots.txt` | Le `robots.txt`. |

---

### `DocsController`

Sert cette documentation même, par docsify : `index()` rend sur `/docs` la vue qui charge docsify, `file(string $path)` rend sur `/docs/{path}` une page Markdown de `documentation/`. **En local uniquement** : les routes ne sont déclarées que si l'application tourne en local, et chaque méthode refait la vérification par `abort_unless(app()->isLocal(), 404)`. Un chemin qui contient `..` ou qui ne finit pas par `.md` rend un 404.

C'est ce qui explique que `documentation/` reste en français seul : elle n'est servie à personne d'autre qu'au développeur qui fait tourner le projet.

---

## Controllers d'administration (`app/Http/Controllers/Admin/`)

Le panneau est découpé en sept sous-pages Inertia, une par domaine. Chaque contrôleur a une méthode `page()` qui rend son composant, et l'historique en a deux de plus, pour le détail et la comparaison, sans garde interne : le groupe de routes porte `throttle:admin` et le middleware `admin`, qui redirige vers l'accueil un visiteur non administrateur.

Le panneau a son propre limiteur, `admin`, à 180 requêtes par minute et par session, là où le reste du site connecté s'en tient à 30. Le suivi d'un import interroge le serveur chaque seconde : sous le limiteur commun, trente secondes de suivi épuisaient le budget de la session et fermaient tout le panneau en 429. Le plafond couvre une minute de suivi, un second onglet et la navigation. Pages et endpoints du panneau partagent ce même compteur.

| Contrôleur | Route | Composant | Contenu |
|---|---|---|---|
| `DashboardController` | `GET /admin` | `AdminDashboardPage` | Tableau de bord. Passe `pendingTaxonomy`, le nombre d'entrées de collection restant à arbitrer, et `buildStatus` **en prop différée** — la comparaison des builds coûte jusqu'à deux requêtes sortantes, et un amont lent doit retarder un bandeau, jamais le panneau. |
| `ImportsController` | `GET /admin/imports` | `AdminImportsPage` | Lancement et suivi des imports. Passe `entities`, l'inventaire des sept entités de catalogue. |
| `ReferenceController` | `GET /admin/reference` | `AdminReferencePage` | Socle de référence et fichiers téléchargés. |
| `HealthController` | `GET /admin/health` | `AdminHealthPage` | Diagnostic de l'application. Passe `health`, le rapport de `HealthReport` : services, quota Blizzard, queue et jobs échoués, volumétries, erreurs récentes. Chaque section porte son statut, et une section qu'on n'a pas pu mesurer rend `unavailable` au lieu de faire tomber la page. |
| `HealthController::queue()` | `GET /api/admin/health/queue` | — (JSON) | La section Queue seule, remesurée par le suivi en direct de l'onglet Santé sans refaire les volumétries ni les sondes. Rend le tableau de `HealthReport::queue()`, sous la même garde que la page : jobs pris (`running`) et en attente (`waiting`) avec leur libellé, leur compte et leur date, compteurs et jobs échoués. Une queue Redis injoignable rend `unavailable` et non une 500. Seule l'étiquette publique d'un job est lue, jamais sa charge utile. Un appel sans session admin reçoit un 403 JSON. |
| `HistoryController` | `GET /admin/history` | `AdminHistoryPage` | Historique des imports, 25 par page (`?page=`). Passe `history` : les entrées, la page courante et le nombre de pages. |
| `HistoryController::entry()` | `GET /admin/history/{jobId}` | `AdminHistoryEntryPage` | Rapport d'un import, chaque entité située face au précédent import qui l'a mesurée, et son journal tant qu'il vit. 404 si l'import est inconnu. |
| `HistoryController::compare()` | `GET /admin/history/compare?first=&second=` | `AdminHistoryComparePage` | Comparaison de deux imports différents, du plus ancien au plus récent. Déclarée avant la route à paramètre. |
| `TaxonomyController` | `GET /admin/taxonomy` | `AdminTaxonomyPage` | Entrées de collection à arbitrer ou déjà rangées, et leur rangement. Accepte `?entity=`, `?search=` et `?mode=` (`pending` par défaut, `curated` pour les entrées déjà rangées ; une valeur inconnue retombe sur `pending`), et sert une tranche de 50 entrées. Les deux modes servent `counts` (en attente et au catalogue) et `curatedCounts` (rangées), par collection. En mode `curated`, `?category=` filtre sur une catégorie exacte, `__none__` visant les entrées rangées nulle part, et la page sert `category` (le filtre actif) et `categories` (les catégories avec leur effectif). |
| `ToolsController` | `GET /admin/tools` | `AdminToolsPage` | Caches, maintenance, annonces Discord. |

Ces routes se déclarent avant la route catch-all de `routes/web.php`, qui avalerait sinon toute route de premier niveau.

`ReferenceController::page()` passe trois props : `tables`, l'état des huit tables du socle ; `store`, le contenu du magasin des fichiers téléchargés avec ses totaux et les entrées d'inventaire sans fichier ; et `liveBuild`, le build que wago sert, ou `null` s'il n'a pas pu être lu.

**Aucun chemin de fichier ne vient de la requête.** `purgeReference()` liste d'abord ce que le disque porte réellement, et confronte chaque nom reçu à cette liste. La règle passe par `Rule::in()` avec un tableau et non par la forme `in:a,b,c` : un nom de fichier orphelin est arbitraire et peut contenir une virgule, que la forme en chaîne couperait silencieusement.


---

## Middleware (`app/Http/Middleware/`)

### `EnsureIsAdmin`

Alias `admin`. Vérifie le drapeau `is_admin` que le callback OAuth pose en session. Au refus, la réponse dépend de ce que la requête attend : un 403 JSON pour un appel XHR, une redirection vers l'accueil pour une visite de page, avec le message flash d'erreur `REFUSAL_MESSAGE` que l'accueil affiche en toast. Utilisé sur les routes `/api/admin/*` et sur les sept pages `/admin`.

### `HandleInertiaRequests`

Middleware d'Inertia, ajouté au groupe `web`. Il fixe la vue Blade racine (`app`) et partage avec toutes les pages le choix de thème validé (`theme`, voir [Thème](#thème-apphttptheme)), l'état d'authentification et les messages flash, voir [Authentification](#authentification). Les props sont des closures, évaluées seulement si la page les lit. C'est l'une des frontières de `mixed-boundaries.txt` : il surcharge `share()`, dont le contrat d'Inertia ne type pas les props par défaut.

### `SecurityHeaders`

Ajoute les en-têtes de sécurité HTTP à chaque réponse :

| En-tête | Valeur |
|---|---|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Restreint les fonctionnalités du navigateur |
| `Strict-Transport-Security` | Uniquement en production (HSTS) |
| `Content-Security-Policy` | Uniquement en production |

La politique n'autorise aucun script inline, à une exception près : le script de démarrage du thème, admis par son empreinte `sha256` que fournit `ThemeBootScript::cspSource()`. Modifier `resources/js/themeBoot.js` met l'empreinte à jour d'elle-même, puisqu'elle est calculée sur le texte servi.

## Thème (`app/Http/Theme/`)

Le thème est appliqué avant le premier rendu, sans flash, en SSR comme sans. Le choix du visiteur, `system`, `dark` ou `light`, vit dans le cookie `wowplanet-theme`, écrit par le navigateur et exclu du chiffrement des cookies (`bootstrap/app.php`), puisque le script de démarrage doit le relire.

### `CharacterSheetSection`, `CharacterSheetView` et `UnknownCharacterSheetSegment`

La fiche a quatre sections : l'Aperçu, à l'URL de base, puis trois sections à segment. `CharacterSheetSection` les énumère (`progression`, `endgame`, `collections`) et donne pour chacune ses sous-onglets, dans l'ordre de la fiche : quêtes, hauts-faits, réputations et métiers ; Mythique+, raids, PvP et équipement ; montures, mascottes, décorations et garde-robe. Les slugs sont en français, comme `/base-de-donnees`.

`CharacterSheetView::fromSegments($section, $sub)` valide les deux segments facultatifs qui suivent le nom du personnage. Sans segment, c'est l'Aperçu ; une section seule ouvre son premier sous-onglet, sans redirection ; tout segment inconnu, ou un sous-onglet sans section, lève `UnknownCharacterSheetSegment`, que `CharacterController::page()` traduit en 404.

### `ThemeChoice`

Énumération des trois choix. `fromRequest()` relit le cookie et rend `System` pour toute valeur absente ou inconnue : la valeur, écrite côté client, n'est jamais utilisée sans être validée. `rendersDark()` n'est vrai que pour `Dark`, le seul cas où le serveur peut poser seul la classe `.dark` sur `<html>` ; pour `System`, il ne connaît pas la préférence du système.

### `ThemeBootScript`

Lit `resources/js/themeBoot.js`, que `app.blade.php` inline en tête de `<head>`, avant toute feuille de style. Ce script pose la classe d'après le cookie, à défaut d'après l'ancien `localStorage`, à défaut d'après `prefers-color-scheme`. `cspSource()` rend son empreinte pour la politique de sécurité du contenu. Une source illisible lève une `UnexpectedValueException`.
