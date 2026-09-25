# Data Transfer Objects (DTOs)

Les DTOs sont des conteneurs immuables qui transportent les données entre les couches. Ils ne contiennent pas de logique métier.

---

## `CharacterProfileDTO`

DTO principal représentant le profil complet d'un personnage après traitement. Créé par `CharacterProfileService::getProfile()` et consommé par les controllers et les agrégateurs de compte.

**Propriétés `readonly`**

| Propriété | Type | Description |
|---|---|---|
| `$name` | `string` | Nom du personnage |
| `$realm` | `string` | Nom du royaume |
| `$race` | `string` | Race |
| `$class` | `string` | Classe |
| `$classId` | `int` | ID de classe Blizzard (1–13) |
| `$level` | `int` | Niveau |
| `$ilvl` | `int` | Niveau d'objet moyen |
| `$faction` | `string` | `Alliance` ou `Horde` |
| `$avatarUrl` | `string` | URL de l'avatar |
| `$classIconUrl` | `string` | URL de l'icône de classe |
| `$collections` | `array<int, ExpansionCollection>` | Progression par extension (`expId → {quests, achievements, reputations}`) |
| `$mountsCount` | `int` | Nombre de montures possédées |
| `$petsCount` | `int` | Nombre de mascottes possédées |
| `$achievementPoints` | `int` | Points de haut-fait totaux |
| `$guild` | `string` | Nom de la guilde |
| `$mounts` | `list<CollectibleProgress>` | Liste complète des montures avec `is_completed` |
| `$pets` | `list<CollectibleProgress>` | Liste complète des mascottes avec `is_completed` |
| `$professions` | `list<ProfessionProgress>` | Métiers avec progression par extension |
| `$decorCount` | `int` | Nombre de décorations possédées |
| `$decor` | `list<DecorProgress>` | Liste complète des décorations avec `is_completed` |
| `$exaltedCount` | `int` | Nombre de réputations exaltées |
| `$mythicKeystone` | `?MythicKeystoneArray` | Cote, couleur et meilleures clés de la saison Mythique+ courante |
| `$completedQuestIds` | `list<int>` | IDs des quêtes complétées |
| `$completedAchievementIds` | `list<int>` | IDs des hauts-faits complétés |
| `$equipment` | `list<EquippedItem>` | Équipement par emplacement avec niveau d'objet et icône |
| `$appearances` | `list<AppearanceProgress>` | Apparences débloquées par emplacement et catégorie |
| `$appearancesCount` | `int` | Nombre d'apparences débloquées |
| `$raids` | `?list<RaidProgress>` | Progression des raids du tier courant, `null` sans progression |
| `$raidsCount` | `int` | Boss vaincus à la difficulté la plus haute atteinte |
| `$score` | `?CompletionScore` | Score calculé côté serveur |

Les formes des tableaux sont des alias PHPStan. Ceux qui décrivent la sortie d'un agrégateur (`QuestProgress`, `CollectibleProgress`, `ProfessionProgress`, `RaidProgress`, `EquippedItem`…) sont déclarés par `@phpstan-type` dans l'agrégateur qui la produit, et importés ici. Ceux qui décrivent ce que le DTO assemble lui-même (`ExpansionCollection`, `MythicKeystoneArray` et ses runs) sont déclarés sur le DTO.

---

## `AccountScoreProgress`

Agrège la progression de plusieurs personnages pour calculer le score de compte. Muable durant le calcul, puis figé via `buildResult()`.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$processed` | `int` | Nombre de personnages traités |
| `$errors` | `list<string>` | Erreurs rencontrées |
| `$completedQuestIds` | `array<int, true>` | Union des IDs de quêtes complétées |
| `$completedAchievementIds` | `array<int, true>` | Union des IDs de hauts-faits complétés |
| `$bestReputations` | `array<int, CompletionStats>` | Meilleure progression de réputation par extension |
| `$accountMounts` | `?list<CollectibleProgress>` | Montures du compte (depuis le 1er personnage) |
| `$accountPets` | `?list<CollectibleProgress>` | Mascottes du compte (depuis le 1er personnage) |
| `$accountDecor` | `?list<DecorProgress>` | Décorations du compte (depuis le 1er personnage) |
| `$bestProfessionStats` | `?CompletionStats` | Meilleur ratio de recettes parmi tous les personnages |
| `$characters` | `array<array{realmSlug, name}>` | Liste des personnages du compte |

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `mergeProfile` | `CharacterProfileDTO` | Fusionne les données d'un personnage dans la progression agrégée |
| `buildResult` | — | Construit et retourne le tableau final, `AccountScoreResult` |

Les formes sont des alias déclarés sur la classe ou importés des agrégateurs : `AccountScoreResult`, `AccountExpansionCollection`, `ExpansionTotals`, `AccountRaid`, `AccountRaidMode`, `CompletionStats`. Le résultat réécrit l'état accompli de chaque quête, haut fait et recette d'après le compte entier, et garde l'ordre des clés de ce que les agrégateurs produisent. Le score n'y figure pas : `AccountScoreService` le calcule et l'ajoute sous `score`. L'objet est mis en cache entre deux lots, ses propriétés restent donc des tableaux simples.

---

## `CrossCharacterProgress`

Agrège les progressions cross-personnages dans le contexte d'un job asynchrone. Suit quel personnage a complété quoi (pour les infobulles "complété sur X").

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$completedQuestIds` | `array<int, string>` | `quest_id → character_name` |
| `$completedAchievementIds` | `array<int, string>` | `achievement_id → character_name` |
| `$completedRecipeIds` | `array<int, true>` | IDs des recettes connues sur au moins un personnage |
| `$bestFactionStandings` | `array<int, FactionStanding>` | Meilleur standing de réputation par faction |
| `$recipeOwners` | `array<int, string>` | `recipe_id → character_name` |
| `$skillPointOwners` | `array<int, array<int, SkillPointOwner>>` | `[profId][expId] → {character_name, skill_points, max_skill_points}` |

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `fromStored` | `ResponsePayload` | Reprend des données déjà stockées, ancien format sans propriétaires compris |
| `mergeCharacter` | `string $name, FetchedCharacterProgress` | Fusionne ce qu'on a récupéré d'un personnage |
| `mergeFromProfile` | `string $name, CharacterProfileDTO` | Fusionne depuis un DTO complet |
| `buildResult` | — | Construit le tableau stocké en base, `CrossCharacterResult` |

Les règles de fusion : le renom l'emporte sur la réputation traditionnelle quel que soit l'ordre, un renom égal ne remplace pas l'entrée en place, une faction non commencée n'écrase rien, et pour les métiers le plus haut niveau de compétence par palier l'emporte.

---

## `FetchedCharacterProgress`

Ce que le calcul des données croisées tire d'un personnage, en lecture seule : `questIds` et `achievementIds` (listes d'identifiants accomplis), `reputations` (`CharacterReputationsResponse`) et `professions` (`CharacterProfessionsResponse`). Un endpoint en échec ou en 404 donne une partie vide, jamais une absence.
