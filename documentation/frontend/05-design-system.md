# Design system

Les tokens de l'interface sont déclarés dans `resources/css/app.css`, et tous les écrans ne consomment qu'eux. La palette par défaut de Tailwind est désactivée (`--color-*: initial` en tête du `@theme`) : une classe comme `text-slate-400` ou `text-white` ne produit plus aucun CSS, et seules existent les couleurs décrites ici. `designScales.test.js` compile des classes de l'ancienne palette et vérifie qu'elles ne génèrent rien. Le document prend `bg-background` et `text-default`.

---

## Thèmes

Le thème sombre est porté par la classe `.dark` sur `<html>`. La variante `dark:` de Tailwind suit cette classe, et non la préférence du système :

```css
@custom-variant dark (&:where(.dark, .dark *));
```

Le visiteur a trois choix : `system` (le défaut, qui suit `prefers-color-scheme` et réagit à son changement en direct), `dark` et `light`. La classe est posée avant le premier rendu, sans flash : par le serveur quand le cookie `wowplanet-theme` vaut `dark`, sinon par un script inline en tête de `<head>` (`resources/js/themeBoot.js`). Côté Vue, tout passe par le composable [`useTheme`](02-composables.md). Le détail serveur est dans la [couche HTTP](../backend/06-http.md#thème-apphttptheme).

---

## Typographie

Les deux polices sont auto-hébergées, installées par `@fontsource-variable/inter` et `@fontsource/cinzel`, importées en tête de `app.css` et servies par Vite depuis le domaine du site : aucune requête ne part vers Google Fonts, et la politique de sécurité du contenu n'admet plus que `font-src 'self'`.

| Token | Utilitaire | Pile | Usage |
| --- | --- | --- | --- |
| `--font-sans` | `font-sans` (défaut du document) | `'Inter Variable'`, `'Inter Fallback'`, puis polices système | tout le texte |
| `--font-display` | `font-display` | `'Cinzel'`, `'Cinzel Fallback'`, puis serif | `h1` et `h2` uniquement, jamais sous 20 px |

Inter est variable (un seul fichier par sous-ensemble de caractères) et son sous-ensemble latin est préchargé par `app.blade.php`. Cinzel n'est chargée qu'en 400, 600 et 700 ; ses graisses 600 et 700, celles des `h2` et des `h1`, sont préchargées aussi, puisque chaque page s'ouvre sur un titre. Les deux sont en `font-display: swap`.

`'Inter Fallback'` est Arial remise aux métriques d'Inter (`size-adjust`, `ascent-override`, `descent-override`, `line-gap-override`) : le texte affiché avant l'arrivée d'Inter occupe déjà la même place, et le changement de police ne décale pas la mise en page. `'Cinzel Fallback'` fait de même pour les titres, à partir de Times New Roman (ou de Liberation Serif, qui en reprend les métriques) : calibrée au navigateur sur les titres de l'accueil, de la FAQ et du PvP, elle garde leur nombre de lignes et la hauteur des pages. Sans elle, l'arrivée de Cinzel décalait l'accueil (CLS 0,08) et le PvP (0,19).

**Chiffres alignés.** Tout nombre qu'on compare d'une ligne à l'autre — score, compteur, classement, pourcentage, durée — porte l'utilitaire `tabular-nums`, sans exception : Inter a des chiffres proportionnels par défaut, qui font danser les colonnes.

---

## Couche primitive

Couleurs brutes, déclarées dans `@theme`. Elles servent à définir les tokens sémantiques et ne s'utilisent pas directement dans un composant.

| Token | Valeur |
| --- | --- |
| `night-950` à `night-600` | `#0B0F1A`, `#121826`, `#1A2233`, `#263047`, `#3A4663` |
| `parchment-50`, `parchment-100` | `#F7F4EC`, `#F1ECE2` |
| `gold-400`, `gold-700` | `#D4A844` (or sur fond sombre), `#7A5D0E` (or sur fond clair) |

---

## Couche sémantique

Chaque token a une valeur par thème, portée par une variable `--wp-*` redéfinie sous `.dark`. Les utilitaires Tailwind pointent vers ces variables par `@theme inline`, ils changent donc de valeur avec le thème sans variante `dark:`.

| Rôle | Utilitaires | Sombre | Clair |
| --- | --- | --- | --- |
| Fond de page | `bg-background` | `#0B0F1A` | `#F7F4EC` |
| Surface | `bg-surface` | `#121826` | `#FFFFFF` |
| Surface haute | `bg-surface-raised` | `#1A2233` | `#F1ECE2` |
| Bordure décorative | `border-default` | `#263047` | `#E2DBCB` |
| Bordure de contrôle | `border-strong` | `#596B97` | `#98865E` |
| Texte | `text-default` | `#E8E4D8` | `#1B1E27` |
| Texte secondaire | `text-muted` | `#A7ADBB` | `#4F5566` |
| Texte discret | `text-subtle` | `#8A92A5` | `#5F6475` |
| Accent | `*-accent` | `#D4A844` | `#7A5D0E` |
| Texte sur l'accent | `*-on-accent` | `#0B0F1A` | `#FFFFFF` |
| Information | `*-info` | `#6FA8F5` | `#1F5FBF` |
| Succès | `*-success` | `#4CC38A` | `#1B7A4B` |
| Avertissement | `*-warning` | `#E8A33D` | `#9A5B00` |
| Danger | `*-danger` | `#F2555A` | `#C42B30` |

Les fonds, textes et bordures ont chacun leur espace de noms (`--background-color-*`, `--text-color-*`, `--border-color-*`) : on écrit `text-muted` et `border-default`, pas `text-text-muted` ni `border-border`. L'accent et les quatre couleurs d'état sont des couleurs générales (`--color-*`), disponibles pour tous les utilitaires : `bg-accent`, `text-danger`, `ring-accent`.

`text-subtle` est le texte le plus pâle autorisé. `border-default` est décorative : un champ, une case ou tout autre contrôle prend `border-strong`.

### Contrastes garantis

`resources/js/tests/themeTokens.test.js` relit les valeurs des deux thèmes dans `app.css` et vérifie, par la formule WCAG 2 de `contrast.js` :

- chaque token de texte, l'accent et les quatre couleurs d'état à 4,5:1 sur les trois fonds ;
- `on-accent` à 4,5:1 sur l'accent ;
- `border-strong` à 3:1 sur les trois fonds.

Modifier une valeur sans respecter ces seuils fait échouer `make test-js`.

### Accessibilité vérifiée par axe

Les pages principales (accueil, fiche, hub, catalogue et montures, PvP, FAQ, 404, santé et taxonomie de l'administration) ont chacune un test qui les passe à [axe](https://github.com/dequelabs/axe-core) par `vitest-axe`, avec `expectNoAxeViolations()` de `resources/js/tests/axe.js`. Le test échoue en listant chaque règle enfreinte et l'élément fautif. Deux exclusions : `color-contrast`, que happy-dom ne sait pas calculer et que couvre le test des tokens ci-dessus ; et, pour une page montée seule, les règles de points de repère, vérifiées une fois sur `AppLayout` avec l'option `{ landmarks: true }`. Une nouvelle page principale reçoit le même test.

### Couleurs de marque

Une seule marque tierce a ses couleurs dans `@theme` : Discord. Elles ne changent pas avec le thème, puisqu'elles reproduisent celles de Discord.

| Token | Valeur | Usage |
| --- | --- | --- |
| `brand-discord`, `on-brand-discord` | `#5865F2`, `#FFFFFF` | Fond de l'invitation Discord et texte posé dessus (4,6:1) ; le bleu n'est jamais une couleur de texte, qui ne tiendrait que 3,8:1 sur le fond sombre. |
| `brand-discord-embed` | `#2B2D31` | Fond de l'aperçu d'annonce du panneau d'administration, tel que Discord dessine un embed. |
| `brand-discord-text`, `brand-discord-muted`, `brand-discord-link` | `#DBDEE1`, `#B5BAC1`, `#00A8FC` | Texte, texte secondaire et liens de cet aperçu. |

`themeTokens.test.js` vérifie ces valeurs et le contraste de chaque couleur de texte sur son fond.

---

## Profondeur et mouvement

Les primitives n'utilisent que ces valeurs, déclarées dans `@theme`.

| Échelle | Utilitaires | Valeurs |
| --- | --- | --- |
| Rayons | `rounded-ui-sm`, `rounded-ui-md`, `rounded-full` | 6 px (badges, champs), 10 px (cartes, boutons), pastilles et avatars |
| Élévation | aucun, `shadow-elevation-1`, `shadow-elevation-2` | 0 : à plat, bordure seule ; 1 : carte survolée, menu ; 2 : dialogue, toast |
| Superposition | `z-base`, `z-sticky`, `z-header`, `z-overlay`, `z-dialog`, `z-toast` | 0, 10, 20, 40, 50, 60 |
| Durées | `duration-fast`, `duration-base`, `duration-slow` | 150, 200, 300 ms |
| Courbes | `ease-enter`, `ease-exit` | entrée décélérée, sortie accélérée ; une sortie dure 70 % de l'entrée |

Les rayons sont préfixés `ui-` pour ne pas se confondre avec l'échelle de rayons par défaut de Tailwind, qui reste compilée : seule la palette de couleurs par défaut a été retirée.

**Mouvement réduit.** Sous `prefers-reduced-motion: reduce`, une règle globale en fin d'`app.css` ramène toutes les transitions et animations à une durée nulle, et une seule itération. Les indicateurs de chargement (`animate-spin`, `animate-pulse`) s'arrêtent et restent affichés à opacité fixe. La règle ne concerne que les visiteurs qui l'ont demandé. Une primitive animée en CSS n'a donc rien à faire de plus ; `resources/js/tests/designScales.test.js` compile les utilitaires avec Tailwind et vérifie cette règle.

---

## Icônes

Une seule famille, Lucide (`lucide-vue-next`), à travers le composant `resources/js/components/ui/Icon.vue`. Il reçoit le composant Lucide lui-même, et non un nom en chaîne : chaque écran importe les icônes qu'il utilise, et seules celles-là entrent dans le bundle (le paquet est déclaré sans effet de bord).

```vue
<script setup>
import { Search } from 'lucide-vue-next';
import Icon from '../components/ui/Icon.vue';
</script>

<template>
    <Icon :icon="Search" />
    <Icon :icon="Search" size="sm" label="Rechercher" />
</template>
```

| Prop | Valeurs | Défaut |
| --- | --- | --- |
| `icon` | un composant Lucide | obligatoire |
| `size` | `sm` (16 px), `md` (20 px), `lg` (24 px) ; `ICON_SIZES` est exporté | `md` |
| `label` | texte | aucun |

Le trait fait toujours 2 px. Sans `label`, l'icône est décorative : `aria-hidden="true"`, ignorée des lecteurs d'écran. Avec `label`, elle porte du sens : `role="img"` et `aria-label`. Une icône seule dans un bouton ne prend pas de `label` elle-même : c'est le bouton qui porte le nom, par `IconButton`.

---

## Primitives

Composants de base, dans `resources/js/components/ui/`, chacun avec son test Vitest. Ils n'utilisent que les tokens sémantiques et les échelles ci-dessus : `components/ui/primitives.test.js` fait échouer la suite dès qu'une classe de palette brute (`text-white`, `bg-slate-800`…) ou une taille ou une couleur arbitraire (`w-[37px]`) y apparaît. Les variantes sont des énumérations validées ; une valeur hors liste déclenche l'avertissement de Vue et retombe sur la valeur par défaut. Tous ont un focus visible au clavier. On les voit tous, dans les deux thèmes, sur la page locale `/ui`.

| Primitive | Props | À savoir |
| --- | --- | --- |
| `Button` | `variant` (`primary`, `secondary`, `ghost`, `danger`), `size` (`sm` 36 px, `md` 44 px), `type`, `href`, `external`, `loading`, `disabled` | Avec `href`, rend un `<Link>` Inertia ; avec `external` en plus, un simple `<a>`, pour une route qui redirige hors du site et demande un chargement complet (la connexion Battle.net). `primary` est l'action principale, une seule par écran. Pendant `loading`, le bouton est désactivé, porte `aria-busy`, et le libellé reste en place, masqué, sous le spinner : la largeur ne bouge pas. Un lien ne peut être ni désactivé ni en chargement, et le dit par un avertissement. |
| `IconButton` | `icon`, `label` (obligatoire), `iconSize`, `variant` (`ghost`, `secondary`, `danger`), `disabled` | Cible de 44 px quelle que soit la taille de l'icône. Le `label` devient l'`aria-label` du bouton, l'icône reste décorative. |
| `Pagination` | `page`, `pageCount`, `label`, événement `update:page` | `nav` nommé ; boutons de 44 px, tous nommés (« Page 3 », « Page précédente »), page courante en `aria-current="page"`. Au-delà de sept pages, garde la première, la dernière et les voisines de la courante, le reste en ellipse. Rien n'est rendu pour une seule page. |
| `Card` | `variant` (`flat`, `interactive`), `as` (`div`, `article`, `section`, `li`) | Jamais cliquable par elle-même. En `interactive`, elle se soulève au survol et affiche le focus clavier de ce qu'elle contient. Pour rendre toute la carte cliquable, on étend son lien : `after:absolute after:inset-0`. |
| `StatTile` | `label`, `value`, `suffix`, `delta`, `valueColor`, `ruleColor` | Valeur en chiffres tabulaires, suffixe après une espace fine insécable. `valueColor` et `ruleColor` teintent la valeur et un liseré supérieur d'une couleur du jeu : la variante lisible du thème pour la valeur, la couleur de base pour le liseré. La variation porte son signe (vrai signe moins), une flèche et un texte pour les lecteurs d'écran, pas seulement une couleur. |
| `Badge` | `tone` (`neutral`, `info`, `success`, `warning`, `danger`, `class`, `quality`, `faction`, `rank`), `value` | Les tons du jeu passent par `wowColors.js` et prennent la variante lisible du thème effectif. `value` est obligatoire pour eux : une valeur inconnue lève une erreur. Un badge sans texte déclenche un avertissement. |
| `SectionHeader` | `title`, `level` (2 à 4), `description`, slot `actions` | Le niveau suit la hiérarchie de la page. Le `h2` est en Cinzel, les niveaux inférieurs en Inter. |
| `ProgressBar` | `value`, `max`, `label` ou `ariaLabel`, `color` | `role="progressbar"` et ses valeurs ARIA. Nommée par son libellé visible, ou à défaut par `ariaLabel` ; sans l'un ni l'autre, elle avertit. Une valeur hors bornes avertit et le remplissage reste dans la piste. Couleur de l'accent par défaut, n'importe quelle couleur sinon, par exemple `dimensionColor(key).base`. Aucune animation en boucle. |


### Primitives interactives, sur Reka UI

`Tabs`, `Dialog`, `Drawer` et `DropdownMenu` reposent sur [Reka UI](https://reka-ui.com) (sans style), qui fournit la gestion du clavier, du focus et des attributs ARIA. Tout le style vient des tokens. Tous se rendent côté serveur sans toucher au DOM, ce que vérifie `components/ui/ssr.test.js` en environnement `node`.

| Primitive | Props | À savoir |
| --- | --- | --- |
| `Tabs` | `tabs` (`[{ value, label }]`), `label` (nom de la liste), `variant` (`primary`, `secondary`), `v-model` | Un slot nommé par valeur d'onglet porte son panneau. `tablist`, `tab` et `tabpanel` sont reliés dans les deux sens. Flèches, Début et Fin au clavier, activation au focus. Pilotable de l'extérieur par `v-model`, pour que l'URL choisisse l'onglet ; sans `v-model`, s'ouvre sur le premier. `primary` souligne les sections, `secondary` affiche des pastilles qui défilent horizontalement en mobile, avec le bord droit estompé tant que des onglets y sont cachés. |
| `Dialog` | `title` (obligatoire), `description`, `size` (`reading` par défaut, `max-w-lg` ; `wide`, `max-w-3xl`, pour un contenu visuel comme la carte de score), `v-model:open`, slots `trigger`, défaut et `footer` | Titre relié par `aria-labelledby`, description par `aria-describedby`. Piège à focus, fermeture par Échap ou par le bouton « Fermer », retour du focus au déclencheur. Fond à 60 % d'opacité. Entrée et sortie par `animate-dialog-in` et `animate-dialog-out`, sur la durée `base`, la sortie à 70 %. Peut s'ouvrir sans déclencheur, par `v-model:open` seul. |
| `Combobox` | `label` (obligatoire), `options` (liste de chaînes), `placeholder`, `v-model` | Champ à saisie libre avec des suggestions, sur Reka `Combobox` : la valeur est ce qui est tapé, les options ne sont que des propositions filtrées par ce texte. Libellé relié par `for`, liste ouverte par la saisie ou par le bouton « Afficher les valeurs de … », navigation au clavier. Une saisie qui ne correspond à aucune suggestion est annoncée comme une nouvelle valeur. Remplace les `datalist` natives, que le navigateur dessine sans tenir compte du thème. |
| `Drawer` | `title` (obligatoire), `side` (`right` par défaut, `left`), `closeLabel`, `v-model:open`, slots `trigger` et défaut | Tiroir plein hauteur sur Reka `Dialog` : piège à focus, Échap, retour du focus, bouton de fermeture nommé (« Fermer le menu » par défaut). Porte la navigation principale sous 1 024 px. |
| `Select` | `label` (visible, obligatoire), `options` (`[{ value, label, hint?, progress? }]`), `v-model` | Liste déroulante sur Reka `Select`, stylée sur les tokens : le déclencheur est un `combobox` nommé par son libellé, chaque option peut porter une indication (un décompte) et une petite barre de progression, à la couleur donnée ou à l'accent. Remplace le `<select>` natif, que le système dessine. |
| `DropdownMenu` | `items` (`[{ key, label, href?, external?, icon?, tone? }]`), `label`, `choices` (`{ label, options: [{ value, label }] }`), `v-model:choice`, slot `trigger`, événement `select` | S'ouvre au clavier (Entrée, Espace, flèche bas), se parcourt aux flèches, se ferme par Échap. Les actions `tone: 'danger'` sont toujours placées en dernier, derrière un séparateur. Un élément avec `href` est un lien Inertia, ou un lien ouvert dans un nouvel onglet avec `external` ; les autres émettent `select` avec leur `key`. `choices` ajoute en tête un groupe de choix exclusif, nommé, en `menuitemradio` avec `aria-checked` : c'est le choix du thème du menu utilisateur. |

Deux animations d'usage général sont déclarées avec elles : `animate-fade-in` et `animate-fade-out`.

### Primitives d'état

Chargement, absence de contenu, erreur et notification ont chacun une seule forme.

| Primitive | Props | À savoir |
| --- | --- | --- |
| `Skeleton` | `shape` (`block`, `text`, `circle`), taille par les classes de l'appelant | Réserve la place du contenu à venir, contre le décalage de mise en page. Masqué aux lecteurs d'écran : c'est le conteneur qui porte `aria-busy`. Pulsation seulement sous `motion-safe`. À préférer pour tout chargement de contenu de plus de 300 ms. |
| `Spinner` | `size` (`sm`, `md`, `lg`), `label` | `role="status"` et texte masqué (« Chargement… » par défaut). Réservé aux attentes sans forme connue. Rotation seulement sous `motion-safe`. |
| `EmptyState` | `title`, `message`, `icon`, slot `action` | Icône Lucide (`Inbox` par défaut), titre, message, action facultative. |
| `ErrorState` | `message`, `title`, événement `retry` | `role="alert"`. Le bouton « Réessayer » n'apparaît que si `@retry` est écouté : on ne propose de rejouer que ce qui peut l'être. |
| `ToastStack` | `offset` (`default`, `above-fab`) | La pile unique des notifications, en bas à droite (`above-fab` la remonte au-dessus du bouton flottant des tâches). Montée une seule fois, par `AppLayout`. Fermeture automatique en 5 s, sauf les erreurs et les notifications qui proposent une action. Bouton « Fermer la notification ». Ne prend jamais le focus ; F8 y mène au clavier. Annonce polie, assertive pour les erreurs. |

On notifie depuis n'importe quel composant par le store `toasts` :

```js
import { useToastStore } from '../stores/toasts';

useToastStore().show({ title: 'Favori ajouté', tone: 'success' });
useToastStore().show({ title: 'Échec de la synchronisation', description: 'Réessayez dans un instant.', tone: 'error' });
```

`tone` vaut `info` (défaut), `success`, `warning` ou `error`. `action` (`{ label, href }`) ajoute un lien sous le texte, en chargement complet de page : c'est ainsi que « Se connecter » et « Se reconnecter » accompagnent la connexion requise et la session expirée, qui remplacent les anciennes bannières. Un titre vide, un ton inconnu ou une action incomplète lève une `InvalidToastError`.
