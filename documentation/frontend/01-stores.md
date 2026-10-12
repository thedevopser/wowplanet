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

## `useFavoriteStore` (`stores/favorites.js`)

Personnages mis en favori par le compte connecté, trois au plus (`MAX_FAVORITES`). Royaume et nom sont comparés sans tenir compte de la casse.

### État

| Propriété | Type | Description |
|---|---|---|
| `favorites` | `array` | Favoris du compte : `{ id, realm_slug, character_name, sort_order }` |
| `loading` | `bool` | Chargement de la liste en cours |

### Getters

| Getter | Description |
|---|---|
| `favoriteCount` | Nombre de favoris. |
| `isFull` | La limite de trois est atteinte. |
| `favoriteKeys` | `Set<string>` des clés `royaume\|nom` en minuscules. |
| `isFavorite(realmSlug, characterName)` | Le personnage est en favori. |

### Actions

| Action | Description |
|---|---|
| `fetchFavorites()` | Charge les favoris depuis `GET /api/character-favorites`. |
| `addFavorite(realmSlug, characterName)` | Ajoute le favori à l'écran tout de suite, puis l'enregistre ; un refus du serveur le retire et relève l'erreur. Sans effet si la limite est atteinte ou si le personnage est déjà en favori. |
| `removeFavorite(realmSlug, characterName)` | Retire le favori à l'écran tout de suite, puis le supprime ; un refus du serveur le remet et relève l'erreur. |
| `toggleFavorite(realmSlug, characterName)` | Ajoute ou retire selon l'état courant. |

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

