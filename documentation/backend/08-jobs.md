# Jobs de queue

Les jobs s'exécutent sur la queue `imports` via le worker dédié (`php artisan queue:work --queue=imports`). Ils communiquent leur état via le cache Laravel (`Cache::put`).

## Étiquette publique : `DescribedJob`

Un job qui implémente `App\Jobs\Contracts\DescribedJob` se présente dans la page Santé par un libellé (`label()`) et le compte qu'il sert (`account()`, `null` pour un job sans compte). Le hook `DescribeQueuedJob`, enregistré par `Queue::createPayloadUsing` dans `AppServiceProvider`, recopie ces deux valeurs à la racine de la charge utile, sous la clé `described`, au moment où elle est créée — `data.command` y est encore l'objet job. Un job qui n'implémente pas l'interface ne reçoit rien et s'affiche sous le nom court de sa classe.

La page lit la file sans jamais ouvrir `data` : l'étiquette est la seule chose qu'un job montre de lui-même.

| Job | Libellé | Compte |
|---|---|---|
| `RunImportJob` | Import du catalogue | aucun |
| `ComputeCrossCharacterJob` | Données des autres personnages | BattleTag reçu au lancement |

---

## `RunImportJob`

Moteur d'un import complet : **une passe d'étape par invocation**, puis re-dispatch. Rendre la main entre deux étapes évite qu'un import de plusieurs minutes ne confisque le worker, et traduit une pause de plafond horaire en délai de file plutôt qu'en `sleep`.

`app:wow-data-import` est la seule commande que le panneau lance, et elle passe par le pipeline orchestré.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$jobId` | `readonly string` | UUID identifiant ce job, et clé de suivi |
| `$command` | `readonly string` | Nom de la commande Artisan (ex. : `app:wow-data-import`) |
| `$parameters` | `readonly array<string, mixed>` | Options de la commande (`--type`, `--force`, `--full`, `--limit`) |
| `$timeout` | `int` | `1800` secondes (30 min) |

**Cycle de vie d'un import complet**

1. Première invocation : `ImportPipeline::begin()` publie une étape par entité demandée, les étapes déjà à jour pour ce build étant marquées ignorées.
2. Chaque invocation : `ImportPipeline::advance()` exécute une passe de la première étape non aboutie, puis republie l'import.
3. Tant qu'il reste une étape : re-dispatch, retardé du temps d'attente si le plafond horaire est atteint.

`retryUntil()` est fixé à 24 h : le chaînage peut s'étaler sur plusieurs heures si le quota Blizzard impose des pauses.

**Reprise** — un worker redémarré reprend à l'étape en cours. Le suivi vit dans le cache et n'est qu'un affichage ; l'autorité durable est `ImportBuildGate`, qui retient en base chaque étape aboutie pour ce build.

**Consulter l'état** : `AdminService::getImportJobStatus(string $jobId)`, servi par `GET /api/admin/import/{jobId}`.

---

## `ComputeCrossCharacterJob`

Récupère les données de tous les personnages d'un compte et calcule la progression agrégée.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$jobId` | `readonly string` | UUID identifiant ce job |
| `$bnetUserId` | `readonly string` | Identifiant Battle.net de l'utilisateur |
| `$characters` | `readonly list<array<string, mixed>>` | Liste des personnages à traiter |
| `$accessToken` | `readonly string` | Token OAuth2 utilisateur pour l'API Blizzard |
| `$battleTag` | `readonly string` | BattleTag du compte, pris en session au lancement : son étiquette dans la page Santé |
| `$timeout` | `int` | `600` secondes (10 min) |

**Cycle de vie**

1. Au démarrage : clé `cross_character:{jobId}` → `{status: 'running'}`
2. Appelle `CrossCharacterService::fetchAndMergeCharacters()`
3. Persiste le résultat dans `CrossCharacterData` (upsert sur `bnet_user_id`)
4. En cas de succès : clé → `{status: 'completed'}`
5. En cas d'échec, l'exception n'est pas attrapée : le worker appelle `failed()`, qui journalise « Cross-character job failed » et pose `{status: 'failed'}` pour le hub. `failed()` est aussi appelé quand le job dépasse son `timeout`, si bien que le hub ne reste jamais sur `running`.

**Échec et relance** — `tries = 1` : pas de relance automatique, le job part dans `failed_jobs` et apparaît dans « Jobs échoués » de la page Santé sous son libellé, d'où un administrateur peut le relancer ou le supprimer. Le job implémente `ShouldBeEncrypted` : sa charge utile, qui porte un jeton Blizzard, est chiffrée dans la file comme dans `failed_jobs`, et seule l'étiquette publique `described` reste en clair. Une relance après expiration du jeton échoue sur `ExpiredBlizzardTokenException`, qui dit de relancer le calcul depuis le hub.

> La limite mémoire est portée à 256 Mo via `ini_set('memory_limit', '256M')` car le calcul cross-personnage peut traiter des dizaines de personnages en parallèle.

**Consulter l'état** : `CrossCharacterService::getJobStatus(string $jobId)`

---
