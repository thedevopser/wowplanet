# Stores Pinia

Les stores centralisent l'état global de l'application Vue. Ils sont consommés depuis les pages et composants via `useXxxStore()`.

---

## `useCharacterStore` (`stores/character.js`)

Store principal de l'application. Gère l'authentification, les données de personnage, et la progression cross-compte. Le thème n'y est plus : il passe par le composable `useTheme`.

### État

| Propriété | Type | Description |
|---|---|---|
| `character` | `object\|null` | Données du personnage actuellement affiché |
| `error` | `string\|null` | Dernière erreur de chargement |
| `isAuthenticated` | `bool` | Utilisateur Battle.net connecté |
| `isAdmin` | `bool` | Utilisateur admin |
| `battletag` | `string` | BattleTag du compte connecté, vide pour un visiteur |
| `userCharacters` | `array` | Personnages du compte connecté |
| `classIcons` | `object` | `class_id → icon_url` |
| `loadingCharacters` | `bool` | Chargement de la liste des personnages |
| `crossCharacter` | `object\|null` | Données cross-personnage du compte |
| `crossCharacterStatus` | `string` | `idle`, `loading`, `ready`, `error`, `not_available` |
| `expansions` | `array` | Liste des 12 extensions WoW (id + nom) |

### Getters

| Getter | Description |
|---|---|
| `latestExpansionId` | ID de la dernière extension (actuellement `11`) |
| `expansionNamesDesc` | Noms des extensions en ordre décroissant |
| `crossCharQuestIds` | `Set<int>` des IDs de quêtes complétées sur n'importe quel personnage |
| `crossCharAchievementIds` | `Set<int>` des IDs de hauts-faits complétés ailleurs |
| `crossCharRecipeIds` | `Set<int>` des IDs de recettes connues ailleurs |

> Les Sets sont construits une seule fois (lazy) et invalidés lors d'une mise à jour des données cross-personnage.

### Actions

| Action | Description |
|---|---|
| `applySharedAuth(auth)` | Reprend `isAuthenticated`, `isAdmin` et `battletag` des props partagées. `AppLayout` l'appelle à chaque visite, rendu serveur compris : le header est juste dès le premier affichage. |
| `fetchUserCharacters()` | Charge la liste des personnages du compte. |
| `fetchClassIcons()` | Charge les icônes de classes (ne re-télécharge pas si déjà chargées). |
| `logout()` | Déconnecte et remet l'état à zéro. |
| `computeCrossCharacter()` | Démarre ou lit le calcul cross-personnage. Poll automatiquement si un job est en cours. |
| `loadCrossCharacterData()` | Charge les données cross-personnage déjà calculées. |
| `isQuestCompletedElsewhere(questId)` | `boolean` — la quête est complétée sur un autre personnage. |
| `isAchievementCompletedElsewhere(achievementId)` | Idem pour les hauts-faits. |
| `isRecipeKnownElsewhere(recipeId)` | Idem pour les recettes. |
| `getQuestOwner(questId)` | Nom du personnage qui a complété la quête. |
| `getAchievementOwner(achievementId)` | Nom du personnage qui a le haut-fait. |
| `getRecipeOwner(recipeId)` | Nom du personnage qui connaît la recette. |
| `getBestFactionStanding(factionId)` | Meilleur standing de réputation sur le compte. |
| `getBestSkillPoints(profId, expId)` | Meilleurs points de compétence pour un métier/extension. |

---

## `useDatabaseSidebarStore` (`stores/databaseSidebar.js`)

Gère l'état de la sidebar de navigation de la base de données (sections ouvertes/fermées, sous-catégories chargées).

### État

| Propriété | Type | Description |
|---|---|---|
| `counts` | `object` | Nombre total par section (`mounts`, `pets`, etc.) |
| `expanded` | `object` | `{sectionKey: bool}` — sections dépliées |
| `subCategories` | `object` | `{sectionKey: array}` — sous-catégories chargées |
| `loading` | `object` | `{sectionKey: bool}` — chargement en cours |

### Actions

| Action | Description |
|---|---|
| `fetchCounts()` | Charge les compteurs depuis `GET /api/database/counts` (une seule fois). |
| `fetchSubCategories(sectionKey)` | Charge les sous-catégories d'une section si non chargées. |
| `toggleSection(sectionKey)` | Ouvre/ferme une section. Déclenche `fetchSubCategories` si ouverture. |
| `expandActiveSection(routePath)` | Replie tout et déplie la section correspondant au chemin de route courant. |

---

## `useTaskStore` (`stores/tasks.js`)

Gère les tâches récurrentes associées aux personnages (suivi des resets quotidien/hebdo/mensuel).

### État

| Propriété | Type | Description |
|---|---|---|
| `tasks` | `array` | Toutes les tâches de l'utilisateur |
| `loading` | `bool` | Chargement en cours |
| `sidebarOpen` | `bool` | Sidebar des tâches visible (persisté en localStorage) |
| `composeRequest` | `object\|null` | Dernière demande d'ouverture sur un personnage : `{ realm_slug, character_name, id }`, `id` croissant à chaque demande |

### Getters

| Getter | Description |
|---|---|
| `charactersWithTasks` | Liste dédupliquée des personnages ayant au moins une tâche. |
| `totalPendingCount` | Nombre total de tâches non complétées sur tous les personnages. |
| `characterTasks(realmSlug, characterName)` | Tâches d'un personnage triées par type de reset (daily → weekly → monthly). |
| `pendingCount(realmSlug, characterName)` | Tâches en attente pour un personnage. |

### Actions

| Action | Description |
|---|---|
| `fetchTasks()` | Charge les tâches puis applique les resets. |
| `createTask(realmSlug, characterName, taskName, resetType)` | Crée une tâche via l'API. |
| `toggleTask(taskId)` | Bascule l'état complété/non complété. |
| `deleteTask(taskId)` | Supprime une tâche. |
| `applyResets()` | Vérifie localement si des tâches doivent être réinitialisées selon leur type de reset. |
| `toggleSidebar()` | Ouvre/ferme la sidebar des tâches. |
| `closeSidebar()` | Ferme la sidebar des tâches. |
| `openFor(realmSlug, characterName)` | Ouvre la sidebar sur un personnage, section dépliée, formulaire ouvert et champ du nom focalisé. C'est le point d'entrée de l'action « Ajouter une tâche » de la fiche. |

**Logique de reset**

| Type | Réinitialisation |
|---|---|
| `daily` | Chaque jour à 5h00 (heure locale) |
| `weekly` | Chaque mercredi à 5h00 |
| `monthly` | Le 1er de chaque mois à 5h00 |

---

## `toasts` (`stores/toasts.js`)

Pile des notifications affichées par `components/ui/ToastStack.vue`. C'est un store Pinia plutôt qu'un état de module : des notifications pourront être poussées pendant un rendu serveur (messages flash), et chaque requête SSR a sa propre instance de Pinia.

| Élément | Description |
|---|---|
| `items` | `[{ id, title, description, tone }]`, dans l'ordre d'arrivée |
| `show({ title, description?, tone? })` | Ajoute une notification et rend son identifiant. `tone` : `info` (défaut), `success`, `warning`, `error`. Lève une `InvalidToastError` sur un titre vide ou un ton inconnu. |
| `dismiss(id)` | Retire la notification, qu'elle se soit fermée seule ou par son bouton. |

