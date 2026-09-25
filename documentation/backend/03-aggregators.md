# Agrégateurs de progression

Situés dans `app/Application/Services/Progress/`, ces classes transforment les réponses de l'API Blizzard, lues par leurs objets de `Infrastructure/Blizzard/Responses/`, en structures de progression prêtes à consommer par le frontend. Chacun est spécialisé sur un type de contenu.

Les formes rendues sont décrites par des alias PHPStan (`@phpstan-type`) déclarés sur l'agrégateur qui les produit, et importés par ceux qui les consomment — `CharacterProfileDTO`, `CharacterProfileService`, les agrégats de compte. Aucun agrégateur ne rend de `mixed`.

---

## Le trajet d'une donnée de progression

Prenons les quêtes, du clic sur une fiche jusqu'à l'écran.

1. **L'endpoint.** `CharacterProfileService::getProfile()` lit le résumé du personnage, puis lance en parallèle les douze endpoints du profil, dont `profile/wow/character/{realm}/{name}/quests/completed`.
2. **L'objet de réponse.** La réponse brute passe par `ResponsePayload` et devient un `CompletedQuestsResponse`, qui n'expose que ce qui sert : `questIds`, une `list<int>`. C'est là que s'arrête le `mixed` de l'API (voir [Infrastructure](05-infrastructure.md)).
3. **L'agrégateur.** `QuestProgressAggregator::aggregate()` croise ces identifiants avec le catalogue importé (`wow_quests`), filtré sur la faction du personnage, et rend par extension un `QuestProgress` : total, complétées, détail par zone.
4. **Le DTO.** Le service range les quêtes, les hauts-faits et les réputations dans `collections`, par extension, construit `CharacterProfileDTO` et y calcule le score (`ScoreCalculator`, voir [Domain](01-domain.md)).
5. **La réponse.** `CharacterController::page()` passe le DTO en prop `character` de la page Inertia `CharacterPage`, et `CharacterController::show()` le rend en JSON. Les deux sérialisent ses propriétés publiques telles quelles : ce que le front lit est exactement la forme déclarée par l'alias.

Le talent et le JcJ font exception : chargés à la demande par leur onglet, ils ont leur propre contrôleur (`TalentController`, `PvpController`) et ne passent ni par le DTO ni par le score.

## Le motif commun

Chaque agrégateur reçoit la réponse de son endpoint, déjà typée, ou la liste d'identifiants qu'elle porte. Il en existe deux familles.

- **Ceux qui mesurent une progression** — quêtes, hauts-faits, collections, métiers, réputations — confrontent la réponse à un référentiel : le catalogue importé dans les tables `wow_*`, ou le socle DB2 par `FactionReference` pour les réputations. La réponse dit ce que le personnage *possède*, le référentiel dit ce qui *existe* : la progression est le rapport entre les deux, et elle n'a de sens que parce que le référentiel est complet. Aucun de ces agrégateurs n'appelle l'API pour découvrir du contenu.
- **Ceux qui mettent en forme** — équipement, raids, JcJ, talents — n'ont pas de référentiel en base. Ils ne travaillent que sur la réponse de l'API, complétée de ce que leur appelant a déjà résolu : icônes d'objets, noms de raids, paliers.

Aucun agrégateur n'appelle lui-même l'API ni ne lit la session ou la requête. Tous rendent des tableaux dont la forme est un alias déclaré sur eux. Chacun a son test sous `tests/Feature/Application/Services/Progress/`.

**Ajouter une dimension de progression**, c'est écrire un agrégateur et son test, déclarer l'alias de sa forme, l'appeler depuis `CharacterProfileService` et porter le résultat dans `CharacterProfileDTO`. Si la dimension doit compter dans le score, il faut aussi l'ajouter à `ScoreInput`, lui donner un poids dans `ScoreWeights` et incrémenter `VERSION`.

---

## `AchievementProgressAggregator`

Construit l'arbre de progression des hauts-faits par extension et catégorie.

**Méthode principale**

```
aggregate(list<int> $completedAchievementIds): array<int, AchievementProgress>
```

`AchievementProgress` se décline en `AchievementCategory` puis `AchievementItem` (`id`, `name`, `icon_url`, `is_completed`).

Prend la liste des IDs de hauts-faits complétés par le personnage. Retourne un tableau indexé par `expansion_id` contenant le total, le complété, et la liste détaillée par catégorie.

Les clés viennent de `ExpansionId::allSlugs()` : les douze extensions **et** le seau `UNCLASSIFIED`, qui porte les hauts faits que leur catégorie ne date pas. Une borne `0..11` en dur les ferait sortir de l'agrégat, donc du score et de l'onglet.

---

## `CollectionProgressAggregator`

Croise les listes complètes de montures, mascottes, décorations et apparences de la base avec ce que le personnage possède.

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `aggregateMounts` | `array $characterMountIds` | Retourne toutes les montures actives avec `is_completed` selon la collection du personnage. Inclut `source`, `category`, `wowhead_id`, `icon_url`. |
| `aggregatePets` | `array $characterPetIds` | Idem pour les mascottes. |
| `aggregateDecor` | `array $characterDecorIds` | Idem pour les décorations. |
| `aggregateAppearances` | `array $characterAppearanceIds` | Apparences de la garde-robe regroupées par emplacement, avec le nombre débloqué sur le total. |

---

## `EquipmentAggregator`

Transforme l'équipement porté (`CharacterEquipmentResponse`) en liste d'emplacements avec noms traduits, niveau d'objet et icônes.

**Méthode principale**

```
aggregate(CharacterEquipmentResponse $equipment, array<int, string> $iconMap = []): list<EquippedItem>
```

Chaque objet porté donne une ligne, même sans identifiant ; la qualité vaut `COMMON` à défaut.

`$iconMap` est un tableau `item_id → icon_url` pré-chargé pour éviter des appels API supplémentaires.

---

## `ProfessionProgressAggregator`

Calcule la progression de chaque métier par extension : points de compétence et recettes apprises.

**Constantes**

| Constante | Description |
|---|---|
| `PROFESSION_NAMES_FR` | Tableau de 14 professions avec leur nom français |
| `SECONDARY_PROFESSION_IDS` | `[185, 356, 794]` — IDs des métiers secondaires (Cuisine, Pêche, Premiers Secours) |

**Méthode principale**

```
aggregate(CharacterProfessionsResponse $professions, string $characterFaction): list<ProfessionProgress>
```

Retourne la liste des métiers du personnage, principaux puis secondaires, avec pour chaque extension (`ProfessionExpansionProgress`) le nombre de recettes connues/total et les points de compétence. L'extension d'un palier se déduit de son nom par `ExpansionTierMatcher`. Quand le personnage n'a pas pratiqué une extension, le maximum de points vient de la colonne `max_skill_levels` de `wow_professions`. Les recettes de même nom sont dédoublonnées au profit de celle qui est connue.

---

## `QuestProgressAggregator`

Construit la progression des quêtes par extension et zone.

**Méthode principale**

```
aggregate(list<int> $completedQuestIds, string $faction): array<int, QuestProgress>
```

`QuestProgress` se décline en `QuestZone` puis `QuestItem` (`id`, `name`, `is_completed`).

Le paramètre `$faction` (`Alliance` ou `Horde`) filtre les quêtes de faction opposée. Retourne un tableau indexé par `expansion_id`.

---

## `ReputationProgressAggregator`

Agrège la progression de réputation par extension.

**Méthode principale**

```
aggregate(CharacterReputationsResponse $reputations, string $characterFaction = ''): array<int, ReputationProgress>
```

Chaque faction est une `FactionProgress`. Les factions du référentiel jamais rencontrées par le personnage sont ajoutées, non commencées (palier `-1`), sauf doublon de nom avec une faction déjà listée.

Une réputation est considérée "complétée" quand elle atteint Exalté (ou le niveau de renom maximum pour les systèmes de renom modernes). Les factions de la faction opposée au personnage sont exclues.

---

## `RaidProgressAggregator`

Construit la progression en raid du tier courant, affichée par l'onglet raids de la fiche.

**Méthode principale**

```
aggregate(CharacterRaidsResponse $raids, RaidNameMap $nameMap = []): list<RaidProgress>|null
```

Seuls les raids de la pseudo-extension `CURRENT_SEASON_EXPANSION_ID` (505), que Blizzard remplit avec le tier courant, sont retenus. `null` quand le personnage n'y a rien fait. Chaque raid garde un mode par difficulté, jamais aplatis, triés LFR, Normal, Héroïque puis Mythique, une difficulté inconnue en dernier. Les noms français du raid et des boss viennent de `$nameMap`, résolu par `CharacterProfileService` depuis le journal d'instance ; le nom brut de l'API sert de repli.

Sortie décrite par les alias `RaidProgress`, `RaidModeView` et `RaidEncounterView`.

---

## `PvpProgressAggregator`

Normalise le PvP d'un personnage pour l'onglet PvP. Service pur : aucun appel, aucune dépendance hors `PvpBracketClassifier`.

**Méthode principale**

```
aggregate(PvpSummaryResponse $summary, array<string, PvpBracketResponse> $brackets, PvpTierMap $tiers, int $currentSeasonId, array<string, string> $specNames = []): PvpProfile|null
```

Un bracket d'une saison antérieure est écarté quand la saison courante est connue, comme un bracket sans rating ni partie jouée. Les brackets sont regroupés par mode dans l'ordre de `PvpBracketClassifier::GROUPS` : mêlée solo et blitz par rating décroissant, les autres par slug. La spécialisation vient de l'API, sinon du repli français `$specNames`. `null` pour un résumé vide, ou quand il n'y a ni bracket, ni honneur, ni champ de bataille joué.

Sortie décrite par les alias `PvpProfile`, `PvpGroup`, `PvpBracket` et `PvpStatistics`.

---

## `TalentAggregator`

Transforme les spécialisations du personnage et l'arbre de talents en structure utilisable par le composant `TalentTreeGrid.vue`.

**Méthode principale**

```
aggregate(CharacterSpecializationsResponse $specializations, TalentTreeResponse $talentTree, array<int, string> $spellIcons = []): TalentBuild
```

Les deux réponses sont les objets de `Blizzard/Responses/Talent/`. Les icônes arrivent à part, indexées par sort : l'agrégateur ne les lit plus dans l'arbre. La sortie est décrite par des alias PHPStan déclarés sur la classe : `TalentBuild`, `HeroTreeView`, `TalentNodeView`, `TalentEntry` et `TalentChoiceEntry`, cette dernière portant en plus le drapeau `selected`.

Gère les talents de classe, de spécialisation et les arbres héroïques (Hero Tree, introduits dans The War Within). Retourne la spécialisation active, les nœuds sélectionnés et la structure complète de l'arbre.
