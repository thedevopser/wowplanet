# Composables Vue

Fonctions de composition réutilisables dans `resources/js/composables/`.

---

## `useTheme` (`composables/useTheme.js`)

Choix et thème effectif du visiteur, partagés par tous les composants.

```js
import { useTheme } from '../composables/useTheme';

const { choice, effective, setChoice, toggle } = useTheme();
```

- `choice` : `system`, `dark` ou `light`. Avant le montage, il vient de la prop partagée `theme`, que le serveur a lue dans le cookie ; le rendu SSR et l'hydratation voient donc la même valeur.
- `effective` : `dark` ou `light`. Pour `system`, il vaut `dark` tant que la préférence du système n'est pas connue, comme au rendu serveur, puis la suit.
- `setChoice(value)` applique le choix à `<html>`, le retient dans `localStorage` et dans le cookie `wowplanet-theme` (un an, `SameSite=Lax`). Une valeur hors des trois choix lève une `InvalidThemeChoiceError`.
- `toggle()` bascule vers l'inverse du thème effectif, en choix explicite. C'est ce qu'utilisent les deux boutons du header.

`startThemeSync()`, appelée une fois par `AppLayout` au montage, relit la préférence du système et s'y abonne, reprend le choix enregistré, et recopie dans le cookie le choix d'un visiteur qui ne l'avait qu'en `localStorage`. L'état du module n'est écrit que dans le navigateur : un rendu serveur ne peut pas faire fuir le thème d'un visiteur vers un autre.

---

## `useFlashToasts` (`composables/useFlashToasts.js`)

Affiche les messages `flash` du serveur dans la pile de toasts.

```js
import { watchFlashMessages } from '../composables/useFlashToasts';

const stop = watchFlashMessages(usePage(), useToastStore());
```

`HandleInertiaRequests` partage `flash.success`, `flash.error` et `flash.authRequired` avec chaque page. `watchFlashMessages()` montre ceux de la page courante, puis ceux qu'apporte chaque visite suivante : un succès en toast `success`, une erreur en toast `error`, qui ne se ferme pas seul, et une connexion requise en toast d'information avec l'action « Se connecter ». Un message flash ne vit que le temps d'une réponse, et chaque visite apporte un nouvel objet `flash` : le même message n'est donc montré qu'une fois. `AppLayout` l'appelle au montage, jamais pendant le rendu serveur.

---

## `useQueryParam` (`composables/useQueryParam.js`)

Un filtre d'onglet (extension, catégorie, zone) lu et écrit dans la chaîne de requête, pour qu'il se partage par lien.

```js
import { useQueryParam } from '../composables/useQueryParam';

const extension = useQueryParam('extension', 'all');
extension.value = 'tww'; // …?extension=tww
```

La valeur suit l'adresse de la page. L'écrire passe par `router.replace` d'Inertia, sans requête serveur et sans empiler l'historique ; les autres paramètres sont gardés, et revenir à la valeur par défaut retire le paramètre.

---

## `useSheetNavigation` (`composables/useSheetNavigation.js`)

Navigation interne à une fiche personnage.

```js
const { urlOf, show } = useSheetNavigation(() => props.realm, () => props.name);
show('collections', 'montures');
```

`urlOf(section, sub)` rend l'adresse d'une vue ; `show(section, sub)` y va par `router.push`, qui écrit l'URL et l'historique et met à jour les props `section` et `sub` sans requête. `DIMENSION_VIEWS` associe chaque dimension du score au sous-onglet qui la détaille.

---

## `useWowColor` (`composables/useWowColor.js`)

Couleurs du jeu adaptées au thème courant.

```js
import { useWowColor } from '../composables/useWowColor';
import { classColor } from '../utils/wowColors';

const { readable, safe } = useWowColor();
const color = safe(classColor, character.classId); // null pour une classe inconnue
const textColor = readable(color); // onDark ou onLight selon le thème
```

`readable(color)` rend la variante lisible (4,5:1) d'une couleur de `wowColors.js` pour le thème effectif, et suit ses changements : tout texte coloré par la classe, la qualité, la faction, le rang ou la dimension passe par elle. `safe(lookup, value)` applique une fonction de `wowColors.js` et rend `null` au lieu de lever `UnknownWowColorError` : Blizzard envoie parfois une valeur que le module ne connaît pas, que l'écran montre alors sans couleur.

---

## `useWowheadTooltips` (`composables/useWowheadTooltips.js`)

Active les infobulles Wowhead sur les liens détectés dans le DOM. L'intégration utilise `window.$WowheadPower` injecté par le script externe Wowhead (`whTooltips` configuré dans `app.js`).

**Utilisation**

```js
import { useWowheadTooltips } from '../composables/useWowheadTooltips';

// Dans setup()
const { refreshWowheadLinks } = useWowheadTooltips();
```

**Comportement**

- Appelle automatiquement `$WowheadPower.refreshLinks()` après `onMounted` et `onUpdated` (via `nextTick`).
- Exposé `refreshWowheadLinks()` pour un rafraîchissement manuel si nécessaire.

> Ce composable est nécessaire dans tout composant qui affiche des liens Wowhead (hauts-faits, sorts, objets) car le DOM Vue est rendu après l'initialisation du script Wowhead.

---

## `useImportProgress` (`composables/useImportProgress.js`)

Suit un import de bout en bout pour le panneau d'administration : avancement, budget consommé, motif d'attente et journal qui défile.

**Utilisation**

```js
import { useImportProgress } from '../composables/useImportProgress';

const tracking = useImportProgress();

onMounted(() => tracking.attach());   // raccroche un import déjà en cours
onUnmounted(() => tracking.stop());

await tracking.start(jobId);          // suit un import qu'on vient de lancer
```

**Ce qu'il expose**

| Membre | Rôle |
|---|---|
| `jobId`, `status`, `isRunning` | Ce qui est suivi et où il en est |
| `stage`, `stageLabel`, `percent` | Étape en cours et avancement |
| `elapsedSeconds`, `etaSeconds` | Temps écoulé, temps restant estimé |
| `budget`, `waiting`, `steps` | Quota consommé, motif d'attente, détail par étape |
| `lines` | Journal, borné à `MAX_LOG_LINES` |
| `start(jobId)`, `attach()`, `stop()` | Suivre un import, en raccrocher un, cesser |

**Comportement**

- Interroge `GET /api/admin/import/{jobId}?cursor=` toutes les `POLL_INTERVAL_MS` (1 s) et n'accumule que le delta de journal rendu. L'intervalle est une constante exportée, pas un nombre au milieu d'un composant.
- `attach()` demande d'abord `GET /api/admin/import/current` : un import lancé depuis un autre onglet, un autre poste ou la ligne de commande est raccroché de la même façon.
- Cesse d'interroger dès que le serveur dit `completed`, `failed`, `cancelled` ou `not_found`, en gardant à l'écran le rapport de fin.
- **Un échec de requête n'interrompt pas le suivi** : une coupure d'une seconde est indiscernable d'un import qui continue, et c'est le serveur qui dit quand il finit.
- Le journal gardé en mémoire est borné à `MAX_LOG_LINES` (2 000 lignes) : un import volumineux ne fait pas enfler l'onglet.

> Le polling est un choix assumé — un seul administrateur, un objet observé qui dure des minutes — et le contrat « curseur plus delta » rend un passage ultérieur à SSE contenu.

---

## `useQueuePolling` (`composables/useQueuePolling.js`)

Tient à jour la section Queue de la page Santé sans recharger le reste du diagnostic : jobs pris et en attente, compteurs, jobs échoués.

**Utilisation**

```js
import { useQueuePolling } from '../composables/useQueuePolling';

const { queue, interrupted, now } = useQueuePolling(() => props.health.queue);
```

**Ce qu'il expose**

| Membre | Rôle |
|---|---|
| `queue` | La dernière section mesurée : celle de la page au départ, puis celle de `GET /api/admin/health/queue` |
| `interrupted` | Vrai quand la dernière mesure a échoué ; la mesure précédente reste affichée |
| `now` | L'heure du navigateur en secondes, avancée chaque seconde pour faire courir les durées entre deux mesures |

**Comportement**

- Mesure toutes les `BUSY_INTERVAL_MS` (5 s) tant qu'un job est pris ou en attente, toutes les `IDLE_INTERVAL_MS` (30 s) sinon : un job lancé après l'ouverture de l'onglet finit toujours par apparaître.
- **Jamais deux mesures en vol** : la suivante n'est programmée qu'à la réponse de la précédente, par un `setTimeout` et non un `setInterval`. FrankenPHP plante en dev sous requêtes concurrentes, et une réponse lente ne doit pas s'empiler.
- Un échec réseau garde la mesure affichée, lève `interrupted` et réessaie au rythme suivant.
- Onglet masqué (`visibilitychange`) : plus aucune mesure ni aucun tic d'horloge ; au retour, une mesure immédiate.
- Suit la source qu'on lui donne : quand la page recharge `health` (bouton « Rafraîchir », action sur un job échoué), la section rechargée remplace la mesure et règle le rythme.
- S'arrête avec la portée qui l'a créé (`onScopeDispose`), donc au démontage de la page.
