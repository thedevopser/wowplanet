*Français · [English](CONTRIBUTING.en.md)*

# Contribuer à WowPlanet

WowPlanet est un projet personnel. Le dépôt est public, les contributions sont les bienvenues, et la direction du projet reste celle du mainteneur : une proposition techniquement correcte peut être refusée parce qu'elle n'entre pas dans ce qui est prévu. Autant le dire avant que tu écrives du code.

Si tu hésites, ouvre une issue d'abord. Une discussion de dix lignes coûte moins cher qu'une pull request qu'on refuse.

## Avant de commencer

Le [README](README.md) décrit l'installation complète : Docker pour l'application, PHP 8.4 et Node 26 sur la machine pour l'outillage. La stack doit tourner pour tout ce qui touche la base, tests compris.

Vérifie que tout passe avant de toucher quoi que ce soit :

```bash
make quality
```

Si cette commande échoue sur un dépôt fraîchement cloné, c'est l'installation qu'il faut régler, pas ton code.

## La règle qui décide de tout : le test d'abord

Le développement se fait en TDD, sans exception et quelle que soit la partie du code. Tu écris le test qui échoue, tu le fais passer avec le minimum de code, tu refactorises en gardant les tests au vert.

**Une correction de bogue commence par un test qui reproduit le bogue.** Sans lui, rien ne prouve que le correctif corrige quelque chose. Une pull request de correction sans test de non-régression sera renvoyée pour ça et pour rien d'autre.

Les tests unitaires (`tests/Unit`) couvrent la logique en isolation, sans base, sans réseau, sans système de fichiers. Les tests fonctionnels (`tests/Feature`) vérifient les briques assemblées par leurs points d'entrée réels : contrôleur, commande, job. Les tests end-to-end ne sont pas systématiques, on en ajoute quand un parcours critique le justifie.

La couverture minimale est de **80 %, mesurée fichier par fichier**. Une moyenne globale qui cache un fichier à 20 % ne vaut rien. Ce qui n'est réellement pas testable s'exclut explicitement dans la configuration de couverture, avec une justification ; on n'abaisse jamais un seuil pour faire passer un fichier qu'on n'a pas eu envie de tester.

## Ce que la CI exige

Le pipeline tourne sur chaque branche et chaque pull request. **Une pull request rouge ne se merge pas**, il n'y a pas de dérogation.

| Étape | Commande | Ce qu'elle refuse |
| --- | --- | --- |
| Refactor | `make refactor` | Du code que Rector réécrirait |
| Style | `make lint` | Un écart au preset Laravel de Pint |
| Analyse statique | `make static` | La moindre erreur PHPStan au niveau max |
| Tests | `make test`, `make test-js` | Un test rouge |
| Couverture | `make coverage` | Un passage sous les seuils |
| Documentation | `make docs-coverage` | Une classe créée et non documentée |
| Typage | `make mixed-check` | Un `mixed` hors des frontières déclarées |

`make quality` enchaîne tout en local. Lance-la avant de pousser, c'est exactement ce que la CI rejouera.

Aucun outil ne modifie de fichier en CI : Rector tourne en `--dry-run`, Pint en `--test`. Si une étape signale quelque chose, on corrige le code.

**Le plafond de `make docs-coverage` ne remonte jamais.** Toute classe que tu crées sous `app/` doit être documentée dans `documentation/`, sinon la CI passe au rouge. C'est délibéré : c'est ce qui empêche la documentation de dériver pendant qu'on écrit.

**Pas de `mixed` hors de ses frontières.** Une donnée de forme inconnue — JSON d'une API, ligne brute de base — se rétrécit dès la ligne qui la reçoit : par un `ResponsePayload` pour une réponse JSON, par `is_array()` ou `is_string()` ailleurs. Les rares fichiers où le mot `mixed` reste légitime sont listés et justifiés dans `mixed-boundaries.txt` ; en ajouter un demande une vraie raison.

Le hook `make install-hooks` rejoue ces étapes au commit et re-stage ce que Rector et Pint ont corrigé. Il demande les conteneurs `app` et `postgres` dès qu'un fichier PHP est indexé.

## Conventions de code

**Typage strict en PHP.** `declare(strict_types=1)` en tête de fichier, tout paramètre, tout retour et toute propriété typés — `void` compris. `mixed` est interdit : si une valeur peut prendre plusieurs formes, on écrit l'union exacte. Les tableaux ne restent jamais en `array` nu, un docblock précise la clé et la valeur (`array<string, Product>`, `list<Order>`, ou la forme complète `array{id: int, name: string}`). Le `mixed` n'entre qu'aux frontières — `json_decode`, données de requête, bibliothèques non typées — et se rétrécit dès la ligne qui le reçoit.

**Programmation défensive.** Le code valide ce qu'il reçoit et échoue tôt, par des clauses de garde en tête de fonction plutôt que des `if` imbriqués. Une entrée invalide lève une exception typée : jamais de retour silencieux, jamais de `null` pour signaler une erreur. Un état impossible doit être impossible à construire.

**Fonctions pures autant que possible.** Les effets — base, réseau, fichiers, horloge, aléatoire — sont repoussés aux frontières et injectés, pour que le cœur métier reste déterministe et testable sans montage de mocks. L'immutabilité est le défaut.

**Les commentaires sont l'exception.** Un nom de fonction correct remplace un commentaire. On n'écrit un commentaire que quand l'information n'est pas dans le code : une contrainte métier non évidente, un contournement de bogue externe avec sa référence, un choix dicté par la performance. Les docblocks de types font exception, ils portent ce que le code ne dit pas.

**Langues.** Le code est en anglais : variables, fonctions, classes, fichiers, branches, clés de configuration, messages de log techniques, noms de tests. Seules les chaînes destinées à l'utilisateur final suivent la langue du produit. La documentation publiée est bilingue français et anglais.

## Branches, commits et pull requests

Une branche par fonctionnalité ou par correction, nommée en anglais. **Jamais de commit direct sur `main`**, même pour une ligne.

Un commit correspond à une intention et laisse le dépôt dans un état cohérent. Si un changement est trop gros pour tenir dans un commit lisible, découpe-le en sous-fonctionnalités cohérentes, une par commit. On ne regroupe pas plusieurs intentions dans un commit fourre-tout, et on ne saucissonne pas un changement atomique en commits sans signification.

**Les messages de commit et les descriptions de pull request sont en français**, quelle que soit la langue du dépôt. Un message de commit décrit ce que fait le changement, à l'impératif ou à l'infinitif. Une description de pull request décrit le contexte, le changement, et ce qu'il faut vérifier.

Ce qui n'a pas sa place dans ces textes : le récit de la démarche, la liste de ce qui a été exploré, les tests lancés pendant la mise au point, les hésitations. On décrit le changement et son effet, pas le chemin parcouru.

## Ce qu'on regarde en revue

Dans cet ordre : le test échoue-t-il sans le correctif ; la CI est-elle verte ; le changement fait-il ce que la description annonce et rien d'autre ; le code se lit-il sans commentaire pour l'expliquer ; les cas limites sont-ils traités ou explicitement écartés.

Une pull request qui touche à l'import Blizzard reçoit une attention particulière : le quota est une ressource partagée et limitée, et une erreur de garde-fou se paie en heures d'attente. Tout nouvel appel massif à l'API doit passer par le middleware de débit et le garde-fou de budget horaire.

## Signaler un bogue ou proposer une idée

Passe par les [issues](https://github.com/thedevopser/wowplanet/issues), les modèles sont là pour ça. Une faille de sécurité ne s'ouvre pas en issue : la marche à suivre est dans [SECURITY.md](SECURITY.md).

Les échanges sur le dépôt suivent le [code de conduite](CODE_OF_CONDUCT.md).

## Licence

Le projet est sous [GNU AGPL-3.0](LICENSE). Tu peux l'utiliser, le modifier et le redistribuer, y compris commercialement, à une condition : quiconque en héberge une version modifiée et la rend accessible sur un réseau doit en publier le code source complet sous la même licence. En proposant une contribution, tu acceptes qu'elle soit distribuée sous cette licence.
