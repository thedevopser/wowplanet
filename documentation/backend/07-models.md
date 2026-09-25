# Modèles Eloquent

Tous les modèles utilisent PostgreSQL. Les modèles WoW ont `$incrementing = false` car leur ID est celui de Blizzard, et n'ont donc pas de séquence associée.

---

## `User`

Modèle d'authentification standard Laravel. Non utilisé directement pour l'authentification Battle.net (qui passe par la session), mais présent pour les guards Laravel.

**Attributs** : `name`, `email`, `password`  
**Cachés** : `password`, `remember_token`

---

## `CharacterVisit`

Enregistre les personnages consultés (pour le sitemap et les statistiques).

| Colonne | Type | Description |
|---|---|---|
| `realm_slug` | `string` | Slug du royaume (ex. : `hyjal`) |
| `character_name` | `string` | Nom en minuscules |
| `display_name` | `string` | Nom avec casse d'origine |
| `display_realm` | `string` | Nom du royaume affiché |
| `class_name` | `string` | Nom de la classe |
| `level` | `int` | Niveau au moment de la visite |
| `last_visited_at` | `datetime` | Horodatage de la dernière consultation |

---

## `CharacterTask`

Tâche récurrente créée par un utilisateur pour suivre une activité sur un personnage donné.

| Colonne | Type | Description |
|---|---|---|
| `bnet_user_id` | `string` | Identifiant Battle.net de l'utilisateur |
| `realm_slug` | `string` | Royaume du personnage |
| `character_name` | `string` | Nom du personnage |
| `name` | `string` | Libellé de la tâche |
| `reset_type` | `string` | `daily`, `weekly` ou `monthly` |
| `is_completed` | `bool` | État courant |
| `completed_at` | `datetime?` | Date de complétion |
| `sort_order` | `int` | Ordre d'affichage |

---

## `CharacterFavorite`

Personnage mis en favori par un utilisateur, trois au plus (voir `CharacterFavoriteService`). C'est une table applicative : clé auto-incrémentée, et rattachement à l'utilisateur par `bnet_user_id`, indexé, puisqu'il n'y a pas de modèle d'utilisateur authentifié (voir [HTTP](06-http.md#authentification)).

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | Auto-incrémenté |
| `bnet_user_id` | `string` | Identifiant Battle.net de l'utilisateur, indexé |
| `realm_slug` | `string` | Royaume, en minuscules |
| `character_name` | `string` | Nom, en minuscules |
| `sort_order` | `int` | Rang d'affichage |

Le triplet `bnet_user_id`, `realm_slug`, `character_name` est unique : un personnage n'est favori qu'une fois par utilisateur.

---

## `CrossCharacterData`

Stocke le résultat du calcul cross-personnage pour un compte Battle.net. Pas de timestamps.

| Colonne | Type | Description |
|---|---|---|
| `bnet_user_id` | `string` (PK) | Identifiant Battle.net (clé primaire non auto-incrémentée) |
| `data` | `array` (JSON) | Résultat complet du calcul (quêtes, hauts-faits, réputations, professions) |
| `character_count` | `int` | Nombre de personnages inclus dans le calcul |
| `fetched_at` | `datetime` | Date du dernier calcul |

---

## `WowAchievement`

Haut-fait WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `expansion_id` | `int` | Extension (0–11) |
| `category_name` | `string` | Catégorie (ex. : `Quêtes`, `Donjons & raids`) |
| `icon_url` | `string?` | URL de l'icône Wowhead |
| `points` | `int` | Points de haut-fait |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` (neutre) |
| `is_active` | `bool` | `false` si le haut-fait a été supprimé du jeu |

---

## `WowMount`

Monture WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `source` | `string?` | Source d'obtention (ex. : `PvP`, `Raid`) |
| `category` | `string?` | Catégorie (ex. : `Aérien`, `Terrestre`) |
| `source_spell_id` | `int?` | ID du sort d'invocation |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible dans le jeu |

---

## `WowPet`

Mascotte de combat WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `category` | `string?` | Catégorie (ex. : `Magique`, `Mécanique`) |
| `source` | `string?` | Source d'obtention |
| `creature_id` | `int?` | ID de créature associée |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible |

---

## `WowQuest`

Quête WoW notable (hauts-faits ou longue chaîne). ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `expansion_id` | `int` | Extension (0–11) |
| `zone_name` | `string` | Zone où se trouve la quête |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` |
| `is_active` | `bool` | Toujours disponible |

---

## `WowDecor`

Décoration de logement WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `category` | `string?` | Catégorie de décoration |
| `source` | `string?` | Source d'obtention |
| `item_id` | `int?` | ID de l'objet associé |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible |

---

## `WowAppearance`

Apparence de la garde-robe, importée par `AppearanceImporter`. Comme les autres tables `wow_*`, sa clé est l'identifiant d'apparence imposé par Blizzard, sans séquence (`$incrementing = false`). C'est la plus volumineuse du catalogue, autour de 49 000 lignes, d'où les agrégations faites en SQL plutôt qu'en mémoire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID d'apparence Blizzard |
| `name_fr` | `string` | Nom en français, celui d'un objet qui la porte |
| `slot` | `string?` | Emplacement, dans le vocabulaire historique de la base (`HEAD`, `CHEST`, `WEAPON`…) |
| `category` | `string?` | Catégorie d'objet |
| `quality` | `int?` | Qualité de l'objet |
| `item_id` | `int?` | Un objet qui porte l'apparence |
| `icon_file_data_id` | `int?` | Identifiant du fichier d'icône |
| `icon_url` | `string?` | URL de l'icône |
| `expansion_id` | `int?` | Extension d'origine |
| `source` | `string?` | Source d'obtention |
| `is_active` | `bool` | Présente dans les index de l'API au dernier import |

Seules les apparences listées par les index d'emplacement de l'API entrent : ce sont eux qui disent ce qui est collectionnable, le balayage des objets ne fait que les renseigner.

---

## `WowProfession`

Métier WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `type` | `string` | `primary` ou `secondary` |
| `max_skill_levels` | `array?` | Niveaux max par extension `{expansion_id: max}` |
| `is_active` | `bool` | Toujours disponible |

**Relation** : `recipes()` → `HasMany<WowRecipe>` (via `profession_id`)

---

## `WowRecipe`

Recette de métier. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `profession_id` | `int` | FK vers `WowProfession` |
| `expansion_id` | `int` | Extension (0–11) |
| `category_name` | `string?` | Sous-catégorie (ex. : `Armure`, `Arme`) |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` |
| `wowhead_spell_id` | `int?` | ID du sort pour les liens Wowhead |
| `is_active` | `bool` | Toujours disponible |

**Relation** : `profession()` → `BelongsTo<WowProfession>`

---

## `WowImportState`

Build WoW du dernier import réussi, une ligne par entité importée. Table `wow_import_states`, clé primaire `entity`, sans séquence ni horodatage Eloquent.

| Colonne | Rôle |
|---|---|
| `entity` | Entité importée : `quests`, `mounts`, `appearances`… |
| `build` | Build WoW servi par l'API au moment de l'import. |
| `last_modified` | En-tête `Last-Modified` du dernier index revalidé, renvoyé tel quel en `If-Modified-Since`. |
| `imported_at` | Date de l'import. |

C'est la seule table applicative sans `bnet_user_id` : elle ne porte pas de donnée utilisateur mais un état de pipeline. Lue et écrite par `ImportBuildGate`.

---

## `WowUpstreamBuild`

Dernier build connu de chaque amont, une ligne par amont. Table `wow_upstream_builds`, clé primaire `source`, sans séquence ni horodatage Eloquent.

| Colonne | Rôle |
|---|---|
| `source` | `blizzard` ou `wago`. |
| `build` | Dernier build lu chez cet amont. |
| `checked_at` | Date de la valeur affichée, et non de la dernière tentative : un échec ne la touche pas. |
| `outcome` | Issue du dernier appel : `ok` ou `unreachable`. |

**À ne pas confondre avec `WowImportState`, malgré la ressemblance.** Celle-ci porte le build qu'un amont **sert** ; l'autre porte le build sous lequel un import a **abouti**. C'est contre la première qu'on situe la seconde, et les deux ne se lisent jamais dans la même famille : le catalogue se compare à Blizzard, le socle à wago.

En base et non en cache parce que c'est la mémoire de secours d'une panne amont : le panneau sait vider les caches, et perdre cette valeur au moment précis où elle sert serait le contraire de ce qu'on en attend. La fraîcheur, elle, reste en Redis pour une heure. Lue et écrite par `UpstreamBuildProbe`.

---

## `ApplicationError`

Journal des erreurs applicatives que la page de santé consulte. Table `application_errors`, écrite par `DatabaseErrorHandler` et bornée à ses dernières entrées.

| Colonne | Rôle |
|---|---|
| `level` | Niveau Monolog (`ERROR`, `CRITICAL`…). |
| `message` | Message journalisé, tronqué. |
| `exception_class` | Classe de l'exception journalisée, s'il y en a une. |
| `location` | Fichier et ligne où elle a été levée. |
| `occurred_at` | Date de l'erreur. |

En base et non dans un fichier : la page lit une source que le serveur détermine, sans jamais recevoir de chemin, et cette mémoire doit survivre à un Redis tombé, puisque c'est précisément dans ce cas qu'on la cherche.

---

## `ImportHistoryEntry`

Un import de la chaîne, tel que l'historique le garde. Table `import_history`, clé primaire `job_id`.

| Colonne | Rôle |
|---|---|
| `job_id` | Identifiant de l'import, celui du suivi. |
| `trigger` | Identifiant Battle.net de l'administrateur qui l'a lancé, ou `console`. |
| `mode` | `incremental` ou `forced`. |
| `status` | `running` tant qu'il tourne, puis `completed`, `failed` ou `cancelled`. |
| `started_at`, `finished_at` | Lancement et clôture. `finished_at` reste nul pour un import dont le worker est mort. |
| `budget_used` | Quota Blizzard consommé sur l'heure glissante à la clôture. |

En base et non en cache : c'est de la donnée d'exploitation, qui doit survivre à un `cache:clear` comme à un redémarrage de Redis. Gardée douze mois.

---

## `ImportHistoryStep`

Une étape d'un import archivé. Table `import_history_steps`, une ligne par étape, supprimée avec son import.

| Colonne | Rôle |
|---|---|
| `stage` | Valeur de `ImportStage`. |
| `status` | État final de l'étape. |
| `created`, `updated`, `deleted` | Lignes touchées, reprises du rapport de l'orchestration. |
| `api_calls`, `duration_ms` | Ce que l'étape a coûté. |
| `rows_after` | Ce que les tables de l'étape pesaient à la clôture de l'import. Nul pour le socle, et tant que l'import tourne. |
| `error` | Motif d'un échec. |

---

## `WowReferenceDownload`

Inventaire du magasin de fichiers de référence : un fichier DB2 téléchargé, une ligne. Table `wow_reference_downloads`, clé primaire `filename`, sans séquence ni horodatage Eloquent.

| Colonne | Rôle |
|---|---|
| `filename` | Nom du fichier dans le magasin, slug de la table et build. |
| `source_table` | Nom de la table DB2 chez wago (`Faction`, `AreaTable`…). |
| `build` | Build WoW LIVE au moment du téléchargement. |
| `bytes` | Taille du fichier téléchargé. |
| `row_count` | Nombre de lignes effectivement chargées en base. |
| `downloaded_at` | Date du téléchargement. |

Les lignes s'accumulent d'un build à l'autre : c'est cet inventaire que la purge du magasin consommera. `row_count` sert de garde-fou à la synchronisation suivante, qui refuse une source dont la volumétrie s'effondre sous la moitié du dernier chargement plutôt que d'écraser le socle.

Le nom échappe volontairement au préfixe `wow_ref_`, pour qu'aucun traitement balayant la famille des tables de référence ne vide l'inventaire avec elles.

---

## `WowCollectionTaxonomy`

Table `wow_collection_taxonomy`. Rangement curé d'une entrée de collection : sa catégorie de niveau 1, sa source de niveau 2, et le marqueur `obtainable` qui dit si un joueur peut encore l'obtenir.

| Colonne | Rôle |
|---|---|
| `entity` | Collection concernée : `mount`, `pet` ou `decor`. |
| `entry_id` | Identifiant Blizzard — id de monture, species id de mascotte, id de décoration. |
| `category` | Catégorie de niveau 1, libellé anglais brut, nullable. |
| `source` | Source de niveau 2, libellé anglais brut, nullable. |

Clé primaire composite `(entity, entry_id)` : deux collections peuvent curer le même identifiant sans se gêner, et la table n'a pas de séquence — les identifiants viennent de Blizzard. **Toutes les écritures passent par `insertOrIgnore` au niveau du constructeur de requête** ; un `save()` sur une instance chargée ne saurait pas la retrouver.

C'est notre donnée, pas celle de l'API, qui n'expose qu'un vocabulaire de onze valeurs là où la curation en compte 170 pour les seules montures. Chargée depuis l'instantané versionné `database/data/collection_taxonomy.csv`, elle n'est ensuite qu'enrichie : un rafraîchissement ajoute les entrées inconnues et ne touche jamais à une ligne existante, pour qu'un ajustement manuel y survive.

Une ligne dont la catégorie est nulle est une entrée rangée nulle part **en connaissance de cause** ; l'absence de ligne est une entrée à arbitrer, que `app:collection-taxonomy-report` liste. Ne pas confondre les deux.

Le nom échappe au préfixe `wow_ref_` pour la même raison que `wow_reference_downloads` : aucun balayage des tables de référence DB2 ne doit pouvoir vider la curation.
