# Utilitaires JavaScript

Fonctions pures dans `resources/js/utils/`. Aucune dépendance vers Vue ou les stores.

---

## `themeTokens.js`

Valeurs du thème sombre pour ce qui ne peut pas lire les variables CSS, comme un `<canvas>`. `DARK_THEME` reprend `background`, `surface`, `surface-raised`, `border`, `text`, `text-muted`, `text-subtle` et `accent`, et un test vérifie qu'elles sont celles du bloc `.dark` d'`app.css` : changer un token sans changer ce fichier fait échouer la suite.

---

## `scoreCardRenderer.js`

Génère la carte de score à partager, sur un `<canvas>` HTML5. Le score arrive calculé par le serveur, la carte ne fait que le dessiner.

### `renderScoreCard(options)`

Dessine une carte de 700 px de large et retourne l'élément `<canvas>`. Sa hauteur vaut 220 px plus 30 px par dimension applicable. Le canvas est deux fois plus grand que la carte, pour que l'image reste nette sur un écran dense et une fois partagée.

**Paramètres**

| Paramètre | Type | Description |
|---|---|---|
| `variant` | `'personal'\|'account'` | Personnage unique ou score du compte |
| `characterName` | `string` | Nom du personnage |
| `characterRealm` | `string` | Royaume |
| `characterClass` | `string` | Classe |
| `characterRace` | `string` | Race |
| `characterLevel` | `number` | Niveau |
| `classId` | `number` | Identifiant de classe, pour la couleur du nom |
| `characterCount` | `number` | Nombre de personnages analysés (mode `account`) |
| `globalScore` | `number` | Score global (0–100) |
| `rank` | `string` | Rang renvoyé par le serveur |
| `dimensions` | `array` | Dimensions du score, seules celles marquées `applicable` sont dessinées |

**Couleurs**

La carte est toujours sombre, quel que soit le thème du visiteur. Le fond et les textes viennent de `DARK_THEME`, les couleurs du jeu de la variante `onDark` de `wowColors.js` : le nom dans la couleur de sa classe, le score et son badge dans la couleur du rang, chaque barre dans la couleur de sa dimension. Une classe ou un rang que `wowColors.js` ne connaît pas encore est dessiné dans la couleur de texte.

**Structure de la carte**
- En-tête : WowPlanet, « Score de complétion », puis le personnage ou « Score du compte »
- Score global et « / 100 », centrés ensemble, puis le badge du rang
- Une barre par dimension applicable, avec pourcentage et ratio
- Pied de page : `wowplanet.fr`

---

## `adminStatus.js`

Libellé et ton de `Badge` de chaque statut du panneau d'administration. `healthStatus(status)` couvre les quatre statuts du rapport de santé et lève une `UnknownAdminStatusError` sur tout autre. `importStatus(status)` couvre ceux d'un import et de ses étapes ; un statut qu'il ne connaît pas encore s'affiche sous son nom brut, en ton neutre, pour que l'historique reste lisible.

---

## `designTokenGuard.js`

`findTokenViolations(source, file, allowed)` rend, ligne par ligne, les classes d'un fichier qui sortent du design system : palette brute de Tailwind (`raw-palette`), taille arbitraire en px, rem ou em (`arbitrary-size`), couleur arbitraire (`arbitrary-colour`). Les variantes (`hover:`, `data-[state=open]:`) sont retirées avant l'examen. Une exception de `allowed` ne vaut que pour son fichier et lève une `UndeclaredExceptionError` si elle ne dit pas pourquoi elle existe. Utilisée par `scripts/check-design-tokens.mjs` (`make tokens-check`).

---

## `formatBytes.js`

### `formatBytes(bytes)`

Une taille en octets, lisible : `'154 ko'`, `'43,5 Mo'`, `'100 Mo'`.

La décimale n'apparaît qu'à partir du mégaoctet, et seulement sous la centaine : en dessous elle n'apporte rien, au-dessus elle sépare les 43,5 Mo d'un fichier de référence des 100 Mo du magasin entier.

Une valeur absente, non numérique ou négative rend `'—'` plutôt qu'un `NaN` à l'écran.

Utilisé par `AdminReferencePage.vue` et `admin/ReferenceFileList.vue`, où l'espace occupé et l'espace qu'une purge libérerait sont les deux chiffres de l'écran.

---

## `formatDuration.js`

`formatDuration(seconds)` rend une durée lisible, en deux unités au plus : `42 s`, `4 min 12 s`, `2 h 12 min`. Une valeur absente ou négative rend un tiret. Partagé par le suivi d'import et l'historique.

---

## `importHistory.js`

Libellés et formats de l'historique des imports : `MODES` nomme les deux modes, `triggerLabel()` nomme la console et laisse tout autre déclencheur tel que le serveur l'a enregistré, `formatHistoryDate()` date à la minute, et `formatSigned()` signe un écart de volume avec le vrai signe moins, qu'on ne confond pas avec un tiret.

---

## `contrast.js`

Rapport de contraste selon la formule WCAG 2, en fonctions pures.

- `relativeLuminance(hex)` rend la luminance relative d'une couleur, de 0 pour le noir à 1 pour le blanc.
- `contrastRatio(hexA, hexB)` rend le rapport entre deux couleurs, de 1 à 21, quel que soit l'ordre des arguments.

Les deux acceptent un hexadécimal à trois ou six chiffres, sans tenir compte de la casse. Toute autre valeur lève une `InvalidHexColorError` qui cite la valeur refusée. Le test des tokens du [design system](05-design-system.md) s'en sert pour garantir les contrastes des deux thèmes.

---

## `wowColors.js`

Couleurs du jeu, définies une seule fois : classes, qualités d'objet, factions, rangs et dimensions du score. C'est la seule source de ces couleurs dans le front.

Chaque couleur est un objet figé à trois valeurs :

- `base`, la valeur officielle, pour ce qui n'est pas du texte : liseré, barre, bordure, point de légende ;
- `onDark` et `onLight`, la même teinte dont seule la luminosité est ajustée, juste assez pour atteindre 4,5:1 en texte sur les trois surfaces de chaque thème (`THEME_SURFACES`, dont un test vérifie qu'elles sont celles d'`app.css`). Ces variantes sont calculées, jamais saisies.

| Fonction | Entrée | Exemple |
| --- | --- | --- |
| `classColor(classId)` | identifiant de classe Blizzard, 1 à 13, nombre ou chaîne | `classColor(6).base` → `#C41E3A` |
| `qualityColor(quality)` | `POOR` … `HEIRLOOM`, casse indifférente | `qualityColor('epic')` |
| `factionColor(faction)` | `ALLIANCE`, `HORDE`, casse indifférente | `factionColor('Horde')` |
| `rankColor(rank)` | rang renvoyé par le serveur : `Légendaire`, `Épique`, `Rare`, `Commun`, `Débutant` | rendu par la qualité d'objet correspondante (`Commun` en vert inhabituel, `Débutant` en gris médiocre) |
| `dimensionColor(key)` | clé de dimension du score (`quests`, `achievements`…, les neuf de `ScoreWeights`) | teintes espacées de 40°, jamais à moins de 30° l'une de l'autre |
| `readableVariants(hex)` | n'importe quelle couleur, par exemple la cote Mythique+ de l'API | `readableVariants(rgbToHex(rating_color))` |
| `colorForTheme(color, theme)` | un objet couleur et le thème effectif (`dark` ou `light`) | `colorForTheme(classColor(5), effective.value)` |
| `rgbToHex({ r, g, b })` | objet couleur de l'API Blizzard | `#FF8000` |

Une valeur inconnue lève une `UnknownWowColorError` qui nomme le type et la valeur : il n'y a jamais de couleur par défaut silencieuse. Les listes `CLASS_IDS`, `QUALITIES`, `FACTIONS`, `RANKS` et `DIMENSION_KEYS` sont exportées.



`standingColor(standing)` colore les huit paliers de réputation, de Détesté (0) à Exalté (7), et le renom (`renown`), avec les mêmes variantes lisibles que les autres couleurs du jeu.
---

## `characterSheet.js`

Sections et sous-onglets de la fiche personnage, miroir de `App\Http\Character\CharacterSheetSection` côté serveur.

- `SECTIONS` : l'Aperçu (`OVERVIEW`, sans sous-onglet), puis Progression, Endgame et Collections, chacune avec ses sous-onglets `{ value, label }` dans l'ordre de la fiche. Les `value` sont les slugs d'URL.
- `viewOf(section, sub)` rend `{ section, sub }` à partir des props de la page : l'Aperçu sans segment, le premier sous-onglet pour une section seule. Un couple inconnu lève une `UnknownSheetViewError`.
- `sheetUrl(realm, name, section, sub)` construit l'adresse d'une vue : l'URL de base pour l'Aperçu, sinon les deux segments en plus.

---

## `scoreRecommendations.js`

Le bloc « Il vous reste… » du score, commun au personnage et au compte. `buildRecommendations(profile)` regroupe les montures, mascottes et décorations par source, les hauts-faits par catégorie et les quêtes par zone, ne garde que les groupes commencés et inachevés, et rend les douze plus proches de la complétion (`MAX_RECOMMENDATIONS`), chacun avec ses vingt premiers manquants (`MAX_ITEMS_SHOWN`) liés à Wowhead par `wowheadUrl(type, item)`. Chaque recommandation porte la clé de sa dimension (`dimensionKey`), qui donne sa couleur.

---

## `formatScore.js`

`formatScore(score)` écrit un score avec une décimale au plus et la virgule française (« 25,8 »). L'anneau de l'en-tête et le panneau de score l'affichent ainsi tous les deux, pour qu'un même score se lise pareil partout.

---

## `reputations.js`

Paliers de réputation tels que la fiche les montre. `effectiveStanding(faction, best)` rend, pour une faction partagée par le compte, le meilleur palier de ses personnages (le renom l'emporte sur la valeur brute) ; `betterElsewhere(faction, best, name)` désigne, pour une faction propre au personnage, un autre personnage mieux placé. `sortFactions()` met en tête les factions commencées, puis celles en cours, puis trie par nom. `standingKey()` donne la clé de couleur de `standingColor()` : l'exaltation pour toute réputation terminée, quelle que soit son échelle ; `renown` pour un renom, un niveau de compagnon ou une amitié au-delà des paliers classiques encore en cours ; le palier sinon ; rien pour une faction non commencée. `standingLabel()` rend le nom du palier, « Non commencée », ou « Niveau 80 · max » pour un niveau de compagnon terminé, que Blizzard nomme comme les autres.

---

## `mythicRuns.js`

Meilleures courses Mythique+ d'un personnage. Le profil Blizzard peut rendre plusieurs courses du même donjon : `bestRunsByTiming(runs)` ne garde que le niveau le plus haut de chaque donjon, séparément pour les courses dans les temps et hors temps, triées du plus haut niveau au plus bas. `uniqueDungeonCount()` compte les donjons joués, `formatRunDuration(ms)` écrit une durée en `minutes:secondes`. `dungeonCards(runs)` fait une carte par donjon, menée par sa meilleure course dans les temps (à défaut, hors temps) et accompagnée de la meilleure course de l'autre type, pour qu'une clé plus haute ratée ne se perde pas ; `seasonStats(runs)` donne le nombre de donjons, la plus haute clé dans les temps et le nombre de donjons faits dans les temps.

---

## `collections.js`

Les trois collections de la fiche : montures, mascottes et décorations. `COLLECTIONS` décrit chacune (champ du profil, titre, dimension du score, libellés de recherche, ordre des catégories propres à la collection, lien Wowhead). Les dictionnaires de traduction des catégories et des sources, autrefois recopiés dans chaque onglet, vivent ici ; le catalogue garde les noms anglais de Blizzard, et les noms d'extension restent en anglais comme en jeu. `translateSource()` traduit aussi une source préfixée (« Renown: », « Trading Post: »…). `groupCollection(items, order)` regroupe une collection par catégorie puis par source, dans l'ordre demandé, puis les catégories inconnues, puis « Non classé » pour les éléments sans catégorie ou sans source.

---

## `raids.js`

Progression de raid telle que la fiche la montre. Blizzard ne liste, par difficulté, que les boss déjà vaincus. `DIFFICULTIES` donne les quatre difficultés dans l'ordre du jeu, chacune avec la qualité d'objet dont elle prend la couleur (Outil Raids en Inhabituel, Normal en Rare, Héroïque en Épique, Mythique en Légendaire). `difficultySummaries(raid)` résume chaque difficulté, entamée ou non ; `bossMatrix(raid)` croise les boss connus et les difficultés entamées, avec la date du dernier kill, et compte les boss jamais vaincus, dont le nom n'est pas connu. `sortNewestFirst(raids)` met en tête le raid le plus récent : Blizzard numérote les instances dans l'ordre de leur sortie, le plus grand identifiant est donc le raid du palier en cours.
