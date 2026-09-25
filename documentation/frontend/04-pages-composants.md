# Pages et Composants Vue

---

## Configuration (`app.js`, `router.js`, `bootstrap.js`)

**`app.js`** — Point d'entrée Vue. Configure `window.whTooltips` pour les infobulles Wowhead (locale `fr`), crée l'application avec Pinia et Vue Router, monte sur `#app`.

**`bootstrap.js`** — Configure Axios avec l'en-tête `X-Requested-With: XMLHttpRequest` (requis par Laravel pour détecter les requêtes AJAX).

**`router.js`** — Vue Router en mode history. Après chaque navigation : met à jour le `document.title` et rafraîchit les liens Wowhead.

---

## Pages (`resources/js/pages/`)

Chaque page correspond à une route Vue Router. Elles consomment les stores et appellent les API via Axios.

| Page | Route | Description |
|---|---|---|
| `HomePage.vue` | `/` | Page d'accueil avec barre de recherche de personnage. |
| `AccountPage.vue` | `/mon-compte/:vue?` | Hub « Mon compte » : `h1`, puis trois vues en `Tabs` pilotées par l'URL (`router.push`, sans requête) : Personnages (`account/CharactersView`), Score du compte (`account/ScoreView`) et Classes (`account/ClassesView`). Non indexé. |
| `CharacterPage.vue` | `/character/:realm/:name/:section?/:sub?` | Fiche d'un personnage en quatre sections (Aperçu, Progression, Endgame, Collections), chacune avec ses sous-onglets, choisies par les props `section` et `sub`. Changer d'onglet écrit l'URL et l'historique par `router.push`, sans requête : le retour navigateur revient à l'onglet précédent. |
| `DatabaseIndexPage.vue` | `/base-de-donnees` | Page d'accueil de la base de données WoW. |
| `DatabaseMountsPage.vue` | `/base-de-donnees/montures/:category?` | Liste des montures avec filtre par catégorie. |
| `DatabaseAchievementsPage.vue` | `/base-de-donnees/hauts-faits/:expansion?` | Hauts-faits filtrés par extension. |
| `DatabaseQuestsPage.vue` | `/base-de-donnees/quetes/:expansion?` | Quêtes filtrées par extension. |
| `DatabasePetsPage.vue` | `/base-de-donnees/mascottes/:category?` | Mascottes filtrées par catégorie. |
| `DatabaseDecorsPage.vue` | `/base-de-donnees/decorations/:category?` | Décorations filtrées par catégorie. |
| `DatabaseProfessionsPage.vue` | `/base-de-donnees/professions/:profession?` | Professions et recettes. |
| `FaqPage.vue` | `/faq` | Questions fréquentes. |
| `PrivacyPage.vue` | `/privacy` | Politique de confidentialité. |
| `CguPage.vue` | `/cgu` | Conditions générales d'utilisation. |
| `AdminDashboardPage.vue` | `/admin` | Tableau de bord de l'administration. Ouvre sur le bandeau de détection de patch, chargé par `<Deferred>` : la page se rend d'abord, la comparaison des builds arrive ensuite. |
| `AdminImportsPage.vue` | `/admin/imports` | Lancement et suivi des imports de données Blizzard. |
| `AdminHistoryPage.vue` | `/admin/history` | Historique chronologique des imports, paginé. Cocher deux imports ouvre leur comparaison. |
| `AdminHistoryEntryPage.vue` | `/admin/history/{jobId}` | Rapport d'un import : déclencheur, mode, résultat, durée, quota, rapport par entité et journal — ou la mention de son expiration. |
| `AdminHistoryComparePage.vue` | `/admin/history/compare` | Deux imports côte à côte, et leurs entités communes. |
| `AdminReferencePage.vue` | `/admin/reference` | Socle de référence et fichiers téléchargés. |
| `AdminTaxonomyPage.vue` | `/admin/taxonomy` | Entrées de collection à arbitrer, et leur rangement par lot ou une par une. |
| `AdminHealthPage.vue` | `/admin/health` | Diagnostic : services, quota Blizzard, queue et jobs échoués, volumétries, erreurs récentes. Rassemble en tête les anomalies nommées par le serveur, et dit explicitement qu'il n'y en a aucune plutôt que de ne rien afficher. Relancer ou supprimer un job échoué demande une confirmation. |
| `AdminToolsPage.vue` | `/admin/tools` | Caches applicatifs, mode maintenance et annonces Discord. |
| `NotFoundPage.vue` | `/:pathMatch(.*)` | Page 404. |
| `UiShowcasePage.vue` | `/ui` | Démonstration des primitives d'interface, dans les deux thèmes. Route déclarée seulement en local, comme `/docs` : en production l'adresse tombe dans la 404. Non indexable. |

---

## Composants (`resources/js/components/`)

### Composants structurels

| Composant | Description |
|---|---|
| `layouts/AppLayout.vue` | Cadre de toutes les pages publiques : lien d'évitement, header, contenu, footer, panneau des tâches et pile de toasts. Reprend l'état de connexion des props partagées à chaque visite, lance le suivi du thème et l'affichage des messages flash au montage. Le document défile, pas une région interne ; le contenu tient dans le même conteneur que le header (`max-w-7xl`, gouttière de 16 px puis 24 px dès 768 px). |
| `AppHeaderInertia.vue` | En-tête collant. Visiteur : Base de données, Classements PvP, « Se connecter avec Battle.net » et bouton de thème. Connecté : les deux mêmes entrées plus « Mon compte », et un menu utilisateur ouvert par le BattleTag (thème, Discord, Administration, déconnexion) ; « Mon compte » mène au hub et reste actif sur toutes ses vues. Liens Inertia dans un `nav` nommé, entrée active en `aria-current`. Sous 1 024 px, la navigation passe dans un `Drawer`. Le logo n'est plus un `h1`. |
| `AppFooterInertia.vue` | Pied de page : mention de site fan, colonne « Explorer » (Base de données, Classements PvP, Mon compte, vers le hub) et colonne « Informations » (FAQ, Addons, Confidentialité, CGU, Discord), chacune dans un `nav` nommé par son intitulé. N'ajoute aucun titre au plan de la page. |
| `DatabaseLayout.vue` | Layout avec sidebar pour les pages de base de données (`/base-de-donnees/*`). La sidebar reste collée sous le header et défile seule. |
| `AdminLayout.vue` | Layout du panneau d'administration : barre d'onglets vers les sept sous-pages, nommée « Administration », onglet actif déduit de l'URL, souligné en accent et marqué `aria-current`. Onglets de 44 px de haut, qui défilent horizontalement sur petit écran. |
| `admin/AdminPageHeader.vue` | En-tête commun des pages du panneau : le seul `h1` de la page, une description facultative et un emplacement `actions` pour les boutons de la page. |
| `admin/DiscordComposer.vue` | Compositeur d'annonce Discord : canal (`Select`), couleur (pastilles nommées, état pressé annoncé), titre, description en Markdown, champs, footer et aperçu. L'aperçu garde les couleurs d'un embed Discord (`brand-discord-*`) quel que soit le thème du panneau. |
| `admin/ImportEntityTable.vue` | Tableau des entités importables : sélection, volumétrie en base, date et build du dernier import, ordre de grandeur du coût d'un import forcé. |
| `admin/BuildStatusBanner.vue` | Bandeau de détection de patch : le build servi par chaque amont, la date de sa dernière lecture, et les boutons de revérification et de mise à jour groupée. Un amont muet prime sur tout le reste — le bandeau n'annonce jamais que tout est à jour sur la foi d'un appel raté. La date de lecture est affichée même quand tout va bien : sans elle, une revérification qui retrouve les mêmes builds ne se verrait pas. L'attente ne se clôt qu'au retour du rechargement de la prop, pas à celui du POST. |
| `admin/BuildStatusEntryList.vue` | Une ligne par étape de la chaîne, socle compris : les deux builds côte à côte, l'alerte qui dit pourquoi elle est en retard, et la mise à jour de cette entité seule. Une entité qu'on n'a pas pu situer n'est pas relançable. |
| `admin/ImportRunPanel.vue` | Suivi d'un import en cours : entité, avancement (`ProgressBar` nommée), temps écoulé et restant, quota consommé, motif d'attente, pause, reprise et annulation confirmée, état par entité et journal qui défile. Une région `role="status"` annonce l'entité et l'état de l'import, jamais le pourcentage qui change chaque seconde. |
| `admin/ImportTrackingCard.vue` | Carte « Suivi » qui branche `ImportRunPanel` sur l'état rendu par `useImportProgress` et affiche en alerte un ordre refusé. Seule présentation du suivi, sur le tableau de bord, les imports et le socle de référence. |
| `admin/ReferenceTableList.vue` | Tableau des huit tables du socle : volumétrie, comparaison avec le chargement précédent, alertes table vide, dégarnie ou restée sur un ancien build, et synchronisation table par table. |
| `admin/PendingTaxonomyTable.vue` | Tableau des entrées de collection à arbitrer : identifiant, nom, valeur d'attente laissée par l'importer, sélection par entrée ou sur toute la page servie. |
| `admin/TaxonomySnapshotPanel.vue` | L'instantané de la taxonomie face à la base : téléchargement de l'instantané à verser au dépôt, dérive orientée — entrées à recharger en base, ou à télécharger et commiter —, et rechargement confirmé depuis le panneau. Ne conseille jamais de commande de terminal. |
| `admin/ImportHistoryTable.vue` | Table chronologique des imports : début, durée, déclencheur, mode, entités, résultat, et la chute de volume nommée par entité. Sélection de deux imports au plus. |
| `admin/ImportHistoryStepTable.vue` | Rapport par entité d'un import : lignes créées, mises à jour et supprimées, appels, durée, et volume en base face au précédent relevé. |
| `admin/ImportHistoryComparisonTable.vue` | Volumes de chaque entité commune à deux imports, côte à côte, avec l'écart signé. |
| `admin/AdminStatusBadge.vue` | Statut d'une mesure de santé (`kind="health"` : OK, Attention, Anomalie, Injoignable) ou d'un import et de ses étapes (`kind="import"`), en mots, sur un `Badge` dont le ton suit la gravité. Un import annulé ou abandonné se distingue d'un import complet au premier coup d'œil. Libellés et tons dans `utils/adminStatus.js`. |
| `admin/HealthServiceList.vue` | État de PostgreSQL et de chaque index Redis, avec le motif d'une panne. |
| `admin/HealthVolumetryTable.vue` | Volumétrie des tables, catalogue et données utilisateur séparés : lignes, part active, part sans icône, et l'anomalie d'une table du catalogue vide. |
| `admin/FailedJobList.vue` | Jobs échoués avec leur classe, leur motif et leur date, et les boutons de relance et de suppression. La confirmation est portée par la page. |
| `admin/ApplicationErrorList.vue` | Dernières erreurs applicatives, horodatées, avec l'endroit où l'exception a été levée. |
| `admin/ReferenceFileList.vue` | Inventaire des fichiers du magasin de référence : taille, état (`live`, `obsolete`, `taxonomy`, `orphan`), chargement d'origine, sélection et suppression. Seuls un fichier obsolète et un orphelin s'attrapent par une case ; un fichier en service et un instantané de taxonomie ne se suppriment qu'un par un, par leur bouton. Signale à part les chargements dont le fichier a disparu du disque. |
| `DatabasePageHeader.vue` | En-tête commun aux pages de base de données (titre + fil d'Ariane). |
| `DatabasePagination.vue` | Composant de pagination réutilisable pour les listes de la base de données. |
| `BreadcrumbNav.vue` | Fil d'Ariane générique. |

### Onglets de la page personnage

Chaque onglet correspond à une section du profil. Ils reçoivent les données du store `character` et affichent la progression.

| Composant | Section |
|---|---|
| `QuestsTab.vue` | Sous-onglet Quêtes, sur `sheet/ExpansionChecklistTab` : extension en paramètre `?extension=`, résumé, recherche, zones dépliables et paginées, chaque quête liée à Wowhead avec sa marque de complétion. |
| `AchievementsTab.vue` | Sous-onglet Hauts-faits, même présentation que les quêtes, par catégorie et avec l'icône de chaque haut-fait. |
| `ReputationsTab.vue` | Sous-onglet Réputations : factions triées (commencées, puis en cours, puis par nom), palier en `Badge` à la couleur du palier, barre de progression libellée, meilleur palier du compte pour les factions partagées et meilleur personnage pour les autres (`utils/reputations.js`). |
| `sheet/CollectionTab.vue` | Sous-onglets Montures, Mascottes et Décorations, par la prop `kind` (`mounts`, `pets`, `decor`) : catégorie choisie dans un `Select` (`?categorie=`), résumé, recherche, sources traduites et dépliables, liste simple des éléments non classés. Libellés, liens Wowhead et ordre des catégories viennent de `utils/collections.js`. |
| `TransmogTab.vue` | Sous-onglet Garde-robe : apparences débloquées sur le compte, catégorie dans un `Select` (`?categorie=`, « Tout » par défaut), une barre libellée par emplacement. |
| `ProfessionsTab.vue` | Sous-onglet Métiers : métier (`?metier=`) et extension choisis dans des `Select`, archéologie en compétence globale, métiers de récolte en points de compétence, palier non appris expliqué, recettes par catégorie. |
| `EquipmentTab.vue` | Sous-onglet Équipement et talents : titre `h2` repliable en vrai bouton avec le niveau d'objet équipé, emplacements en `sheet/EquipmentSlot` autour de la silhouette dès 768 px, en liste en dessous, puis la section des talents. |
| `RaidsTab.vue` | Sous-onglet Raids, le raid le plus récent (celui du palier) en tête : par raid, un résumé des quatre difficultés (couleur de qualité du jeu, jauge à pastilles par boss, « Terminé », « En cours » ou « Non entamé »), puis une table « boss × difficulté » repliable depuis le titre, une coche datée par kill et « Non vaincu » sinon, et le nombre de boss jamais vaincus dont Blizzard ne donne pas le nom (`utils/raids.js`). Le repli est mémorisé dans le navigateur quand il le permet. |
| `PvpTab.vue` | Sous-onglet PvP, chargé à l'ouverture : squelette annoncé pendant le chargement, `ErrorState` avec « Réessayer », en-tête de saison, puis un `h3` par mode et une carte par bracket, bilan en `sheet/RecordLine`. |
| `MythicPlusTab.vue` | Sous-onglet Mythique+ : en-tête de saison (cote dans sa couleur Blizzard rendue lisible, donjons joués, plus haute clé dans les temps, donjons dans les temps), puis une carte par donjon, triée par niveau : la clé en grand dans la couleur de son score, l'état dans les temps ou non en toutes lettres, score, durée, date et composition du groupe, et la meilleure course de l'autre type quand elle existe (`utils/mythicRuns.js`). |
| `CharacterOverview.vue` | Section Aperçu de la fiche : compteurs clés en `StatTile`, chacun à la couleur de sa dimension du score (montures, mascottes, décorations, réputations terminées, points de hauts-faits), et la cote Mythique+ à la couleur que lui donne Blizzard, puis le `ScorePanel` du personnage, dont chaque carte de dimension mène à son sous-onglet sans requête. Sans score, un `EmptyState`. |
| `ScorePanel.vue` | Panneau de score partagé par la fiche et le score du compte : titre en `h2`, radar, score global et rang, « Partager ce score », cartes de dimension (liens facultatifs par `dimensionLinks`, événement `navigate` sur un clic simple) et recommandations dépliables en vrais boutons. |
| `TalentTreeSection.vue` | Talents du personnage, chargés à la première ouverture : arbres de classe, de spécialisation et héroïques en `Tabs`, arbre héroïque choisi en boutons `aria-pressed`, squelette au chargement, `ErrorState` avec « Réessayer ». |
| `TalentTreeGrid.vue` | Grille de l'arbre de talents complet. |
| `TalentNode.vue` | Nœud de l'arbre de talents, lié à son sort sur Wowhead ; un talent choisi est bordé d'or (accent), les autres estompés. |
| `sheet/EquipmentSlot.vue` | Un emplacement d'équipement : icône bordée de la couleur de qualité, nom dans sa variante lisible, niveau d'objet, lien Wowhead ; « Vide » sans objet. |
| `sheet/BetterElsewhere.vue` | Mention « Meilleur : X — … » quand un autre personnage du compte est allé plus loin, dans la même forme pour les réputations et les métiers. |
| `sheet/CrossDataBanner.vue` | Sur la fiche d'un de ses personnages, bandeau qui signale des données croisées non calculées (« Lancer le calcul »), en cours de calcul (annonce polie) ou en échec (« Réessayer »). Silencieux quand elles sont prêtes, et sur la fiche d'un autre joueur. |
| `sheet/PipGauge.vue` | Jauge à une pastille par étape (un boss), les premières remplies de la couleur donnée ; annoncée comme une image décrite. |
| `sheet/RunTiming.vue` | « Dans les temps » ou « Hors temps » d'une course Mythique+, en icône et en texte. |
| `sheet/RunGroup.vue` | Composition du groupe d'une course, dans un `details` : nom, royaume, spécialisation et niveau d'objet. |
| `sheet/RecordLine.vue` | Bilan d'un mode PvP en toutes lettres : joués, victoires, défaites, taux de victoire. |

### Composants réutilisables

| Composant | Description |
|---|---|
| `CollectionIcon.vue` | Icône d'un élément de collection, ou son initiale à défaut d'image, sur les tokens. |
| `CategoryIcon.vue` | Icône d'une catégorie de collection. |
| `CharacterCard.vue` | En-tête de la fiche : avatar, nom en `h1` à la couleur de sa classe, guilde, niveau, race, classe dans sa couleur lisible, royaume et badge de faction, sur un liseré à la couleur de classe ; score global dans un anneau `ScoreBadge` à la couleur de son rang. Pour le propriétaire (`isOwner`), « Favori » (`aria-pressed`, limite de trois annoncée en toast) et « Ajouter une tâche », qui ouvre le panneau des tâches sur ce personnage. Les compteurs sont passés dans l'Aperçu. |
| `sheet/ExpansionFilter.vue` | Choix de l'extension en `Select`, de la plus récente à la plus ancienne, chacune avec sa progression en chiffres et en barre à la couleur de la dimension. |
| `sheet/ProgressSummary.vue` | Résumé d'un sous-onglet : titre en `h2`, part faite dans la couleur lisible de la dimension, décompte et barre libellée. |
| `sheet/GroupedChecklist.vue` | Groupes (zones, catégories) dépliables en vrais boutons, huit par page avec `Pagination`, chaque élément rendu par le slot `item`. |
| `sheet/CompletionMark.vue` | Marque d'un élément : « Fait », « Fait par X » (visible, depuis les données croisées) ou « À faire », l'icône doublée d'un texte. |
| `sheet/ExpansionChecklistTab.vue` | Sous-onglet générique des quêtes et hauts-faits, paramétré par ses libellés, son lien Wowhead et ses accesseurs de données croisées. |
| `SearchFilter.vue` | Champ de recherche libellé (`type="search"`), bouton d'effacement nommé, bascule « Masquer… » en `aria-pressed`, slot pour d'autres bascules. |
| `ScoreBadge.vue` | Anneau de score à la couleur du rang, chiffre et rang en variante lisible du thème, annoncé comme une image (« Score 25,8 sur 100, rang Commun »). |
| `ScoreRadar.vue` | Radar SVG des dimensions applicables du score, en `role="img"` avec un résumé textuel des valeurs. Traits et libellés sur les variables des tokens, pour rester lisibles dans les deux thèmes. |
| `ShareScoreModal.vue` | Fenêtre « Partager ce score » (ou « Partager le score du compte ») sur `Dialog` : piège à focus, Échap, retour du focus. Carte de score dessinée dans un canevas décrit aux lecteurs d'écran, à télécharger ou à copier (téléchargement à défaut de presse-papiers d'images). |
| `CardGridSkeleton.vue` | Grille de cartes en `Skeleton`, qui réserve la place d'une liste de personnages ou de classes pendant son chargement. `role="status"`, `aria-busy` et un libellé masqué (`label`) disent ce qui se charge. Remplace l'ancien `LoadingSpinner` plein écran. |
| `inertia/TaskSidebarInertia.vue` | Panneau des tâches récurrentes, ouvert par un bouton flottant nommé « Mes tâches » qui annonce le nombre de tâches en attente. Une région nommée, fermée par un bouton, par Échap ou, sur mobile, par le fond. Un personnage par section dépliable (`aria-expanded`) ; chaque tâche a une vraie case à cocher libellée par son nom, sa fréquence en toutes lettres et un bouton de suppression nommé, toujours visible. Le formulaire de création libelle ses champs. Sur la fiche d'un de ses personnages, le propriétaire y trouve ce personnage en tête ; la fiche d'un autre joueur n'y est jamais proposée. Nom, royaume et avatar viennent de la liste du compte, puis des props de la fiche, à défaut des slugs. |
