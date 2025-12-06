# Configuration Qualité de Code

Ce projet utilise plusieurs outils de qualité de code pour garantir la maintenabilité et la cohérence :

- **PHPCBF** : Vérification et correction automatique du style de code (PSR12)
- **PHPStan** : Analyse statique du code avec extensions Doctrine et Symfony
- **Rector** : Refactoring et modernisation du code

## Image Docker

Tous les outils sont disponibles via l'image Docker `jakzal/phpqa:php8.4` qui contient PHPCBF, PHPStan et toutes leurs extensions.

## Extensions configurées

### Extension Doctrine
- **phpstan-doctrine/extension.neon** : Analyse avancée des entités Doctrine et de l'ORM
- Support de Doctrine ORM 3.5
- Configuration de l'ObjectManager pour Symfony 8.0 via `tests/object-manager.php`

### Extension Symfony
- **phpstan-symfony/extension.neon** : Analyse des services, conteneurs et composants Symfony
- Support de Symfony 8.0
- Analyse du conteneur de services via le cache du kernel
- Support de l'application console via `tests/console-application.php`

## Fichiers de configuration

- **phpcbf.xml** : Configuration PHPCBF pour le standard PSR12
- **phpstan.neon.dist** : Configuration principale de PHPStan
- **rector.php** : Configuration Rector
- **tests/object-manager.php** : Loader pour l'EntityManager Doctrine (requis pour phpstan-doctrine)
- **tests/console-application.php** : Loader pour l'application console Symfony (requis pour phpstan-symfony)

## Utilisation

### PHPCBF - Vérification et correction du style (PSR12)

```bash
# Vérifier le style de code
make phpcbf-check

# Corriger automatiquement les violations de style
make phpcbf-fix

# Directement via Docker
docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpcs --standard=./quality/phpcbf.xml
docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpcbf --standard=./quality/phpcbf.xml
```

### PHPStan - Analyse statique

```bash
# Lancer l'analyse PHPStan + Rector
make quality

# Ou directement via Docker
docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpstan analyse -c ./quality/phpstan.neon.dist
```

## Standards de code

### PHPCBF (PHP Code Beautifier and Fixer)
- **Standard** : PSR-12 (PHP Standards Recommendation 12)
- **Vérification** : Analyse automatique du respect du standard
- **Correction** : Correction automatique des violations détectées
- **Parallélisation** : 4 processus en parallèle

### PHPStan
- **Niveau d'analyse** : `max` pour une analyse stricte maximale
- **Cache** : Stocké dans `var/cache/phpstan/` pour améliorer les performances

## Hooks Git

Le projet utilise des hooks Git pour garantir la qualité du code avant chaque commit :

### Installation des hooks

```bash
make hooks
```

### Hook pre-commit

Le hook pre-commit exécute automatiquement :
1. **PHPCBF** : Vérifie le respect du standard PSR12
2. **PHPStan** : Analyse statique du code
3. **Rector** : Vérification du refactoring (dry-run)
4. **PHPUnit** : Tests unitaires

Si l'une de ces vérifications échoue, le commit est bloqué.

### Correction rapide

Si PHPCBF détecte des violations de style :
```bash
make phpcbf-fix
```

## GitHub Actions

Le workflow `.github/workflows/code-quality.yml` est optimisé pour éviter les duplications :

### Sur Pull Request (toutes branches)
Vérification complète de la qualité :
1. Style de code (PHPCBF/PSR12)
2. Analyse statique (PHPStan)
3. Refactoring (Rector)
4. Tests (PHPUnit)

### Sur Push vers `main`
Validation finale :
- Tests (PHPUnit) uniquement

Cette stratégie évite les duplications puisque les PR sont déjà validées avant le merge.
