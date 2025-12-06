# Configuration PHPStan

Ce projet utilise PHPStan pour l'analyse statique du code avec les extensions Doctrine et Symfony.

## Image Docker

L'analyse est effectuée via l'image Docker `jakzal/phpqa:php8.4` qui contient PHPStan et toutes ses extensions.

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

- **phpstan.neon.dist** : Configuration principale de PHPStan
- **tests/object-manager.php** : Loader pour l'EntityManager Doctrine (requis pour phpstan-doctrine)
- **tests/console-application.php** : Loader pour l'application console Symfony (requis pour phpstan-symfony)

## Utilisation

```bash
# Lancer l'analyse PHPStan
make quality

# Ou directement via Docker
docker run --rm -t -v ${PWD}:/project -w /project jakzal/phpqa:php8.4 phpstan analyse -c ./quality/phpstan.neon.dist
```

## Niveau d'analyse

Le projet est configuré avec le niveau `max` de PHPStan pour une analyse stricte maximale.

## Cache

Le cache de PHPStan est stocké dans `var/cache/phpstan/` pour améliorer les performances des analyses successives.
