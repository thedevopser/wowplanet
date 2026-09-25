# Qualité et intégration continue

Ce que le pipeline vérifie, ce qui fait échouer une merge request, et ce que le hook local attrape avant elle.

Le pipeline vit dans `.github/workflows/ci.yml`. Il tourne sur chaque pull request vers `main` et sur chaque push sur `main` — et sur rien d'autre : une branche avec une PR ouverte tournerait deux fois par push s'il écoutait toutes les branches, et rien n'entre dans `main` sans passer par une PR de toute façon.

---

## Le principe

**Aucune étape ne modifie de fichier.** C'est la règle qui commande le choix de chaque outil. Un formateur qui corrige en CI donne un pipeline vert sur du code non conforme, et le problème réapparaît au commit suivant. Chaque outil est donc invoqué dans son mode vérification, et l'étape échoue au moindre écart.

Une étape finale le prouve plutôt que de le supposer : elle vérifie que l'arbre de travail est resté propre. Elle attrape le jour où quelqu'un remplace `pint --test` par `pint`, ou retire un `--dry-run`.

**Toutes les étapes sont bloquantes.** Il n'y a pas d'étape informative dont on regarde le résultat de loin. Si une vérification signale quelque chose, on corrige le code.

---

## Les étapes

Les vérifications tournent dans trois jobs parallèles, suivis d'un quatrième qui les rassemble.

| Job | Étape | Échoue quand |
| --- | --- | --- |
| Static analysis | Rector (`make refactor-check`) | Une refactorisation reste à appliquer. |
| Static analysis | Style (`make lint-check`, Pint en `--parallel`) | Un fichier s'écarte du preset Pint. |
| Static analysis | Analyse statique (`make static`) | Larastan trouve une erreur au niveau maximum. |
| Static analysis | Couverture de documentation (`make docs-coverage`) | Une classe n'est documentée nulle part. |
| Static analysis | Frontières du `mixed` (`make mixed-check`) | Un `mixed` apparaît hors des fichiers déclarés dans `mixed-boundaries.txt`, ou un fichier déclaré n'en contient plus. |
| PHP tests | Build des assets | Vite échoue : les tests Inertia lisent le manifeste. |
| PHP tests | Collation de la base de test | La base n'est pas en ICU sur `fr-FR`. |
| PHP tests | Migrations | Une migration échoue sur une base neuve. |
| PHP tests | Pest en parallèle, avec couverture et CRAP (`make coverage-php-ci`) | Un test échoue, la couverture PHP passe sous 70 %, ou une méthode dépasse un CRAP de 30. |
| Front end | Garde des tokens (`make tokens-check`) | Un composant utilise une classe de la palette brute de Tailwind (`text-slate-400`, `text-white`), une taille arbitraire en px, rem ou em (`w-[240px]`) ou une couleur arbitraire (`bg-[#1e293b]`). |
| Front end | Build des assets (`npm run build`) | Vite ou le build SSR échoue. |
| Front end | Contrôle des bundles produits | `public/build/manifest.json` ou `bootstrap/ssr/ssr.js` manque. |
| Front end | Vitest avec couverture (`make coverage-js`) | Un test échoue, ou un seuil JS n'est pas tenu. |
| Chacun des trois | Arbre de travail inchangé | Une étape a réécrit un fichier suivi. |
| Tests and quality | Résultat des trois jobs | L'un d'eux n'a pas réussi. |

**Tests and quality** est la vérification qu'exige la règle de protection de `main`. Elle ne lance rien : elle attend les trois autres jobs et échoue si l'un d'eux n'a pas réussi. Découper le pipeline n'a donc pas demandé de toucher à la règle, et un job ajouté ne compte que s'il figure dans ses `needs`.

Seul le job PHP démarre les conteneurs PostgreSQL et Redis. Les résultats de Rector (`/tmp/rector_cached_files`) et de PHPStan (`/tmp/phpstan`) sont conservés d'une exécution à l'autre par `actions/cache`. Les deux outils invalident eux-mêmes ce qu'un fichier ou un réglage modifié rend périmé, et la clé change avec `composer.lock`, `rector.php` et `phpstan.neon`.

La mutation tourne dans un job à part, `mutation`, en parallèle des autres. Elle échoue quand une classe du périmètre touchée par le changement passe sous 80 % de mutants tués. Voir [La mutation](#la-mutation).

Cinq points méritent une explication.

**Le build est fait deux fois, et les bundles sont contrôlés séparément.** Les tests fonctionnels Inertia rendent `app.blade.php`, qui lit le manifeste : sans build préalable, ils échouent pour une mauvaise raison, et une entrée absente du manifeste les fait échouer pour une bonne. Le job PHP reconstruit donc les assets, quelques secondes, plutôt que d'attendre ceux du job front. Et `npm run build` enchaîne le build client et le build SSR — le sidecar SSR tournant en production, un bundle qui ne casse que de ce côté ne doit pas passer, d'où la vérification explicite des deux fichiers.

**La couverture de documentation plafonne un compte, pas un ratio.** Le plafond est à zéro : l'étape échoue dès qu'une classe n'est décrite nulle part. Un pourcentage aurait aussi bougé en supprimant une classe documentée, donc échoué sans faute. Le détail de la commande est dans [Commandes Artisan](09-commands.md).

**Le `mixed` est cantonné à ses frontières.** PHPStan au niveau maximum interdit d'utiliser une valeur `mixed` sans la vérifier, pas de l'annoncer : un code qui déclare `array<string, mixed>` puis caste chaque accès le passe sans broncher. Le contrôle est donc textuel. `make mixed-check` cherche le mot `mixed` dans `app/` et échoue sur toute occurrence hors des fichiers listés dans `mixed-boundaries.txt`, en nommant le fichier et la ligne. Chaque entrée de la liste y est justifiée : décodage JSON, valeur fautive d'une exception de contrat, raison d'un rejet Guzzle, lignes `stdClass` du query builder, colonne `jsonb`, contrat d'Inertia. Le contrôle échoue aussi sur une entrée périmée, un fichier déclaré qui n'en contient plus : la liste ne peut que rester juste. Une valeur de forme inconnue qui entre ailleurs se rétrécit dès la ligne qui la reçoit, par `is_array()`, `is_string()` ou un `ResponsePayload`.

**Le front ne peint qu'avec le design system.** La palette par défaut de Tailwind est désactivée dans `app.css` : une classe `text-slate-400` ne produit plus aucun CSS, et l'élément qui la porte perdrait sa couleur sans que rien ne casse. `make tokens-check` (`scripts/check-design-tokens.mjs`, logique dans `resources/js/utils/designTokenGuard.js`, testée) le dit avant, en nommant fichier, ligne et classe. Il refuse aussi les tailles et les couleurs arbitraires ; les variantes d'état (`data-[state=open]:`), les gabarits de grille, les transitions nommées et les unités de viewport restent permis. Une exception se déclare dans le script avec son fichier, sa classe et sa raison ; la liste est vide. `make quality` l'enchaîne.

**La collation est vérifiée à chaque exécution.** L'image PostgreSQL est sur Alpine, qui n'embarque aucune locale système : la collation `fr-FR` vient du fournisseur ICU, réglé par `POSTGRES_INITDB_ARGS`. C'est le tri des noms accentués qui en dépend, et une base créée sans ce réglage passerait inaperçue jusqu'à ce qu'un test d'ordre échoue de façon incompréhensible. L'étape interroge `pg_database` par de simples `SELECT`, parce que le `psql` du runner est plus ancien que le serveur et que `\l` y lit une colonne renommée en PostgreSQL 17.

---

## L'ordre Rector → Pint → Larastan

Il est fixe, et il n'est pas arbitraire.

Rector transforme la sémantique, Pint met en forme le résultat, Larastan analyse ce qu'il reste. Formater avant de transformer laisse passer du code non formaté, puisque Rector réécrira ensuite. Analyser avant de transformer revient à analyser du code qui n'existe plus.

Faire cohabiter un refactoriseur et un formateur ne pose pas de problème une fois l'ordre fixé : sur un fichier volontairement non conforme aux deux, Rector et Pint convergent en un aller-retour puis ne bougent plus. Ils ne se défont pas, ils se complètent — l'un travaille la sémantique, l'autre la mise en forme.

Le hook pre-commit applique exactement le même ordre, à une différence près : en local les outils écrivent, en CI ils se contentent de constater.

---

## Les seuils de couverture

Trois contrôles, qui ne répondent pas à la même question.

| Contrôle | Seuil | Ce qu'il détecte |
| --- | --- | --- |
| CRAP par méthode (`coverage:crap`) | 30 au plus, pour chaque méthode de `app/` | Une méthode complexe que les tests n'exécutent pas. Le message la nomme, avec son CRAP. |
| Score de mutation (`scripts/mutate.sh`) | 80 % au moins, pour chaque classe du périmètre | Une règle métier exécutée mais que rien ne vérifie. Le message nomme la classe et ses survivants. |
| Plancher de lignes | 70 % en PHP ; 70 % en lignes, instructions, fonctions et branches en JS | Une suite entière qui a disparu. Ce n'est pas un objectif. |
| Classes documentées nulle part | 0 | Une classe qui n'est décrite dans aucune page. |

Le pourcentage de lignes récompense les tests qui exécutent sans rien vérifier, et ne voit pas une méthode métier exécutée mais jamais assertée. D'où ce plancher bas, qui ne sert qu'à détecter une suite disparue, et deux contrôles qui nomment ce qui manque au lieu d'annoncer une moyenne en baisse.

Le plancher PHP est global. Côté JS, seuls `inertia.js`, `ssr.js`, `app.js` et `bootstrap.js` sont exclus de la mesure : les layouts et `components/inertia/` sont mesurés et testés, et ne doivent pas être remis dans les exclusions pour faire remonter un chiffre.

La couverture PHP a besoin de **pcov**, présent dans le stage `dev` de l'image et absent du PHP de la machine. D'où deux cibles distinctes : `make coverage-php` passe par le conteneur et exige donc la stack, `make coverage-php-ci` s'exécute sur le PHP appelant, le runner de CI portant déjà pcov.

Les deux cibles, comme `make coverage`, écrivent aussi `coverage/clover.xml`, puis enchaînent `make crap` : la commande `coverage:crap` y lit le CRAP de chaque méthode et échoue en listant celles qui dépassent 30, de la pire à la moins grave. Le CRAP vaut `complexité² × (1 − couverture)³ + complexité` : une méthode triviale ne peut pas dépasser le seuil, une méthode complexe non testée le dépasse vite. Voir [Commandes Artisan](09-commands.md).

---

## La mutation

La couverture dit qu'une ligne a été exécutée, pas qu'un test vérifie ce qu'elle fait. La mutation comble cet écart. Le plugin de Pest altère le code — une condition inversée, un `return` retiré, une borne décalée — puis relance les tests qui couvrent la ligne modifiée. Un mutant **tué** fait échouer au moins un test. Un mutant qui **survit** est un comportement que rien ne protège.

`make mutate` lance la mutation sur tout le périmètre, dans le conteneur qui porte pcov. `make mutate-changed` la limite aux classes que la branche touche. Les deux échouent dès qu'une classe passe sous 80 % de mutants tués (`MUTATION_MIN`, surchargeable pour une mesure sans seuil : `make mutate MUTATION_MIN=`).

### Le périmètre

Elle ne s'applique qu'au code qui porte des règles métier, parce qu'ailleurs les survivants ne seraient que du bruit. Le périmètre est une liste explicite, `mutation-perimeter.txt`, un chemin par ligne, chacun avec la règle qui lui vaut sa place. On n'a pas voulu d'une règle déduite, du CRAP par exemple : une liste bouge par une modification qui se relit en revue, jamais toute seule.

| Entrée | Pourquoi elle y est |
| --- | --- |
| `ScoreCalculator`, `PvpBracketClassifier`, `ExpansionId` | Le domaine pur : la formule du score, le classement des brackets PvP, les identifiants d'extension. |
| `AccountScoreProgress`, `CrossCharacterProgress`, `AccountScoreService`, `CrossCharacterService`, `ComputeCrossCharacterJob` | Les règles à l'échelle du compte : quel personnage l'emporte quand plusieurs connaissent la même chose. |
| Les agrégateurs de `Progress/` (hauts-faits, collections, métiers, PvP, quêtes, raids, réputations) | Les chiffres qui alimentent le score : ce qui compte comme fait, obtenu ou connu, dimension par dimension. |
| `TalentAggregator` | Quels talents apparaissent sélectionnés, et quel arbre de héros revient à la spécialisation active. |
| `ImportPipeline`, `ImportRun`, `ImportStage` | La chaîne d'import : ordre et dépendances des étapes, transitions d'état, attentes, clôture. |
| `StageFreshness`, `ImportBuildGate` | La reprise : une étape déjà faite pour le build courant est sautée, chaque famille étant jugée contre son propre amont. |
| `VolumeShrink` | Le seuil unique qui signale un catalogue ayant perdu du volume après un chargement. |

Quelques absences sont délibérées. Les autres classes de `app/Domain` (`ScoreInput`, `ScoreDimension`, `CompletionScore`, `ScoreWeights`, `FavoriteLimitReachedException`) transportent des données ou des constantes sans aucune ligne exécutable : il n'y a rien à y muter. `EquipmentAggregator` recopie des champs et pose des valeurs par défaut, sans aucune règle. Les services d'orchestration pure et les DTO de transport n'ont rien à vérifier au-delà de ce que PHPStan garantit déjà.

La liste est exprimée en chemins, pas en noms de classes. L'option `--class` du plugin fait une correspondance par préfixe : `ImportStage` y attraperait aussi `ImportStageRunner` et `ImportStageResult`. Un chemin inexistant, lui, serait ignoré sans bruit par le plugin. C'est pourquoi le périmètre est lu par `php artisan mutation:scope`, qui échoue en nommant toute entrée périmée.

### Chaque classe contre ses tests dédiés

Par défaut, le plugin confronte chaque mutant à **tous** les tests qui couvrent la ligne mutée, et relance pour chacun un processus Pest. Sur une classe utilisée partout, c'est ruineux. `ImportRun` est lu par le pipeline, les jobs, les contrôleurs du panneau et l'historique : chacun de ses mutants relançait des centaines de tests fonctionnels, à raison d'un mutant par minute. La mesure du périmètre complet dépassait deux heures et demie.

`scripts/mutate.sh`, qu'appellent les deux cibles, mute donc **une classe à la fois, contre ses seuls tests dédiés** : les fichiers `tests/**/<Classe>*Test.php`, par exemple `ImportRunTest.php` et `ImportRunStateTest.php` pour `ImportRun`. `ImportRun` passe ainsi de plus de deux heures à 36 secondes, et le périmètre complet de plus de deux heures et demie à un quart d'heure, avant la parallélisation décrite plus bas.

C'est aussi la bonne exigence. Une règle métier doit être protégée par les tests de sa classe, pas par hasard, au détour du test fonctionnel d'autre chose. Un mutant que seul un test indirect tuerait compte comme survivant, et c'est voulu. Une classe du périmètre sans aucun test à son nom est signalée `no test` dans le récapitulatif.

Une invocation par classe donne aussi un score par classe, que le script récapitule à la fin. Le `--min` de Pest, qui porte sur une exécution entière, devient ainsi un seuil par classe.

### Les mutants d'une classe en parallèle

Les classes passent l'une après l'autre, mais les mutants d'une même classe tournent en parallèle (`--mutate --parallel`), un processus par cœur : 4 sur un runner GitHub. Le plugin donne à chaque processus un `TEST_TOKEN` et active le mode parallèle de Laravel. Chaque processus a donc sa base, `wowplanet_test_test_<n>`, et son préfixe Redis, exactement comme `make test`. En local, sur 24 cœurs, le périmètre complet passe de 13 min 36 à 2 min 16, avec des survivants identiques un à un.

**Ne jamais passer `--processes`.** Le plugin retire `--parallel` des arguments qu'il transmet à chaque processus de mutant, mais pas `--processes`. Ce processus, qui n'est pas parallèle, refuse l'option et échoue, et le plugin compte alors le mutant comme tué. Tout le périmètre passe à 100 % sans que rien ne soit vérifié. Le nombre de processus reste donc celui du plugin.

Paratest n'accepte qu'un seul chemin de test, alors qu'une classe peut avoir ses tests dédiés dans plusieurs fichiers. Le script écrit donc, pour chaque classe, une copie de `phpunit.xml` dont la seule suite liste ces fichiers (`.phpunit-mutation-<pid>.xml`, à la racine pour garder ses chemins relatifs et rester visible du conteneur), la passe par `--configuration`, et la supprime en sortie.

### Ne muter que ce que la branche touche

`make mutate-changed` ne mute que les classes du périmètre que la branche concerne, comparée à `MUTATION_BASE` (`origin/main` par défaut). Une classe est retenue dans trois cas :

- la branche la modifie ;
- elle modifie ou supprime un de ses tests dédiés, parce que retirer un test compte autant que modifier la classe ;
- elle vient de l'ajouter à `mutation-perimeter.txt`.

C'est aussi ce que fait le job `mutation` de la CI, avec `MUTATION_MIN=80` : une pull request est comparée à sa branche cible, une poussée sur `main` au commit qu'elle suit. Le job récupère tout l'historique (`fetch-depth: 0`), sans quoi la base de comparaison manquerait.

Une branche qui ne touche rien du périmètre n'exécute aucune mutation. Le choix de n'avoir **aucun passage complet** en CI est délibéré, et il a un prix : une régression venue d'un fichier hors du diff, une dépendance modifiée par exemple, n'est pas vue. `make mutate` reste disponible en local pour le périmètre entier.

### Toutes les lignes sont mutées, sauf les constantes et les valeurs par défaut

Une ligne que les tests dédiés n'exécutent jamais produit des mutants **non couverts**, qui comptent contre le score. C'est ce qui rend visible une méthode entière sans test. L'option `--covered-only` du plugin, qui ne mute que les lignes exécutées, les ferait disparaître du compte : elle a été essayée puis écartée, parce qu'elle cachait 144 mutants de vrai code non testé, dont 115 dans `CrossCharacterProgress`.

Les déclarations de constantes posent le problème inverse, et les valeurs par défaut des propriétés (`public int $timeout = 600`) avec elles. Ni l'une ni l'autre n'est une ligne exécutable, et pcov ne les attribue donc à aucun test. Ses mutants seraient comptés non couverts à tout jamais, quels que soient les tests : 163 sur le périmètre, dans les tables de correspondance surtout. Chaque déclaration concernée porte donc la directive du plugin :

```php
// @pest-mutate-ignore
public const LEGION = 6;

/**
 * Valeur d'un boss selon son meilleur palier ; il ne compte qu'une fois.
 *
 * @pest-mutate-ignore
 */
private const RAID_DIFFICULTY_VALUES = [
```

La directive doit être la dernière chose sur sa ligne. Le plugin lit ce qui la suit comme une liste de mutateurs à ignorer : dans `/** @pest-mutate-ignore */`, il lit ` */` et n'ignore rien. D'où la forme `//`, ou une ligne à elle seule dans un docblock sur plusieurs lignes. Posée ainsi, elle écarte la déclaration entière, tableaux sur plusieurs lignes compris.

Ce sont les tests qui protègent ces valeurs, pas la mutation : `ScoreCalculatorTest` vérifie par exemple que les poids somment à 1, et les calculs du score assertent leurs effets. Une constante ajoutée au périmètre sans la directive se voit tout de suite, par des mutants non couverts sur sa ligne.

---

## La suite en parallèle

`make test`, et donc le hook pre-commit, lance Pest en parallèle (`vendor/bin/pest --parallel`, paratest), un processus par cœur. La suite passe de 36 secondes à moins de 10 sur une machine de développement. Chaque processus doit disposer de son propre état.

**La base.** Laravel crée une base par processus, `wowplanet_test_test_<n>`, à partir de `wowplanet_test`, et la migre. Elle hérite de la collation `fr-FR` du serveur. Un test de `DatabaseConfigTest` le vérifie sur l'ordre des noms accentués.

**Redis.** Les quatre index de test (budget, journal d'import, queue, cache) sont partagés par tous les processus. Chacun travaille donc sous son propre préfixe de clé, `wowplanet-test-<n>:`, posé par `Tests\TestCase` avant toute connexion, `n` étant le `TEST_TOKEN` de paratest. Avant chaque test, `clearTestRedis()` ne supprime que les clés de ce préfixe. Un `FLUSHDB`, ou un `Cache::flush()` sur le store Redis, effacerait celles des processus voisins : c'est pourquoi les tests n'en font plus. `RedisIsolationTest` vérifie le préfixe de chaque connexion et le vidage ciblé.

**Le réseau.** Aucun test ne sort de la machine. `Http::preventStrayRequests()` fait échouer toute requête HTTP sans doublure, en nommant l'URL.

`vendor/bin/pest` sans `--parallel` reste valable, par exemple pour cibler un fichier ou déboguer un test.

---

## Le hook pre-commit

Installé par `make install-hooks`, il rejoue le pipeline en local, avec les outils en mode écriture.

```
[1/5] Rector      → puis re-stage
[2/5] Pint        → puis re-stage
[3/5] Larastan
[4/5] Pest
[5/5] Vitest
```

Trois comportements à connaître.

**Il ne déclenche que ce qui est concerné.** Les quatre étapes PHP ne partent que si un fichier PHP est indexé — ou `composer.json`, `phpstan.neon`, `pint.json`, `rector.php`. L'étape front ne part que si un fichier `.js`, `.ts`, `.vue`, `.css` ou un `package.json` l'est. Rien d'indexé de pertinent, rien à faire.

**Les étapes PHP exigent la stack.** La suite Pest tourne sur PostgreSQL : le hook vérifie que les conteneurs `app` et `postgres` répondent, et refuse le commit en le disant plutôt que de laisser dérouler une pile d'exceptions.

**Il re-stage ce que Rector et Pint ont corrigé, et seulement dans le périmètre du commit.** Un fichier corrigé hors du commit reste dans l'arbre de travail, et le hook le liste en sortie. Il avertit aussi en amont quand un fichier n'est indexé que partiellement : si un outil le réécrit, tout son contenu entre dans le commit.

---

## Correspondance avec les cibles `make`

Le pipeline n'invoque presque jamais un binaire directement, il passe par le `Makefile`. Une cible et sa variante de vérification ne diffèrent que par le mode.

| Cible locale | Écrit | Variante de vérification | Écrit |
| --- | --- | --- | --- |
| `make refactor` | oui | `make refactor-check` | non, sort en 2 s'il reste du travail |
| `make lint` | oui | `make lint-check` | non, sort en 1 au moindre écart |
| `make static` | non | — | — |
| `make tokens-check` | non | — | — |

`phpcbf` n'a pas de mode à blanc : il réécrit systématiquement et sort en code 0. Il n'a donc aucune place dans un pipeline, et le projet n'en utilise pas.
