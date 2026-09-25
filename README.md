*Français · [English](README.en.md)*

# WowPlanet

Application web de suivi de progression **World of Warcraft** : profils de personnages,
collections (montures, mascottes, transmog…), Mythic+, raids, score de compte.

**Stack** : Laravel 12 + Vue 3 via Inertia · FrankenPHP · PostgreSQL 18 · Redis 8 · données via l'**API Blizzard**.

> L'application tourne dans Docker, l'outillage tourne en local : tests, lint, analyse
> statique et build d'assets s'exécutent avec le PHP et le Node de la machine.

---

## 1. Prérequis machine

Pour lancer le projet en dev, il te faut sur ton PC :

| Outil | Rôle |
| --- | --- |
| **Docker** + **Docker Compose** | Exécute app, worker, Vite, PostgreSQL et Redis. |
| **PHP 8.4** + **Composer** | Outillage PHP : Pest, Pint, Larastan, Rector, Artisan. Les extensions `pdo_pgsql` et `redis` sont requises : l'outillage local attaque la stack sans intermédiaire. |
| **Node 26** + **npm** | Outillage JS : Vitest, build des assets. |
| **Traefik** (reverse-proxy local) | Sert le projet en HTTPS sur `wowplanet.dev.local`. Proxy partagé, **non inclus dans ce repo** — il doit déjà tourner sur le réseau Docker `dev-network`. |

> La couverture PHP est la seule commande d'outillage qui reste dans le conteneur : elle a
> besoin de **pcov**, installé dans l'image de dev et absent du PHP de la machine.

Le `compose.yml` s'attache à un réseau Docker **externe** nommé `dev-network` et publie ses
routes via des labels Traefik. Deux prérequis en découlent :

```bash
# Créer le réseau partagé (une seule fois, s'il n'existe pas déjà)
docker network create dev-network

# Résoudre le domaine local → ajouter cette ligne à /etc/hosts
127.0.0.1  wowplanet.dev.local
```

**PostgreSQL et Redis sont fournis par `compose.yml`**, il n'y a rien à installer sur la machine.
PostgreSQL 18 porte les données, Redis 8 porte le cache, la queue et les sessions, sur trois index
de base distincts — 1, 2 et 3. Ce cloisonnement n'est pas cosmétique : `cache:clear` émet un
`FLUSHDB`, et un index partagé effacerait donc les imports en attente et déconnecterait tout le
monde à chaque vidage de cache.

> **Un `.env`, deux points de vue.** Les variables `DB_*` et `REDIS_*` sont écrites du point de vue
> de la machine — `127.0.0.1:55432` pour PostgreSQL, `127.0.0.1:56379` pour Redis — de sorte que
> l'outillage local atteigne la stack. À l'intérieur des conteneurs, `compose.yml` injecte
> `DB_HOST=wowplanet-postgres` et `REDIS_HOST=wowplanet-redis`, qui l'emportent. C'est le premier
> piège du projet : les deux jeux de valeurs sont justes, chacun à sa place.

---

## 2. Installation (première fois, après clone)

```bash
# 1. Copier le fichier d'environnement
cp .env.example .env

# 2. Démarrer la stack : app, worker, PostgreSQL et Redis (sur dev-network)
make up

# 3. Installer les dépendances PHP + JS (Composer et npm de la machine)
make install

# 4. Générer la clé applicative
php artisan key:generate

# 5. Créer le schéma
php artisan migrate

# 6. (optionnel) Installer le hook pre-commit qualité
make install-hooks
```

> La base applicative `wowplanet` et la base de tests `wowplanet_test` sont créées au premier
> démarrage du conteneur PostgreSQL. Les scripts d'initialisation ne s'exécutant que sur un volume
> vide, la base de tests se crée à la main sur un volume déjà peuplé :
>
> ```bash
> docker compose exec postgres \
>     psql -U wowplanet -d postgres -c 'CREATE DATABASE wowplanet_test OWNER wowplanet'
> ```

Vérifier que tout répond :

```bash
make psql       # console SQL sur la base applicative
make redis-cli  # console Redis
make test       # la suite Pest, sur wowplanet_test
```

---

## 3. Ce que tu dois renseigner dans `.env`

| Variable | Requis | Détail |
| --- | --- | --- |
| `APP_KEY` | oui | Généré automatiquement par `php artisan key:generate` (étape 4). |
| `BLIZZARD_CLIENT_ID` | oui | Client OAuth de l'[API Blizzard](https://develop.battle.net/access/clients). |
| `BLIZZARD_CLIENT_SECRET` | oui | Secret du client OAuth Blizzard. |
| `BLIZZARD_REGION` | oui | Région de l'API (`eu` par défaut). |
| `BLIZZARD_REDIRECT_URI` | oui | URL de callback OAuth. En dev : `https://wowplanet.dev.local/auth/blizzard/callback` (déjà dans `.env.example`). |
| `ADMIN_BNET_ID` | oui (pour l'admin) | Ton BattleTag/ID Bnet, requis pour accéder au panel d'administration. |
| `DISCORD_WEBHOOK_URL_CHANGELOG` | non | Webhook Discord pour le changelog. |
| `DISCORD_WEBHOOK_URL_DISCUSSION` | non | Webhook Discord pour les discussions. |

> Les credentials Blizzard s'obtiennent en créant un client sur le
> [Blizzard Developer Portal](https://develop.battle.net/). Ils sont nécessaires pour l'import
> des données et l'authentification Battle.net.

---

## 4. Lancer en mode dev

```bash
make dev       # Serveur Vite (HMR) via Traefik
make worker    # Worker de queue "imports" (imports Blizzard, calculs cross-character)
```

Accès : **https://wowplanet.dev.local**

Arrêt :

```bash
make dev-stop      # Stoppe Vite
make worker-stop   # Stoppe le worker
make down          # Stoppe l'application
```

> **Worker** : FrankenPHP garde le code en mémoire. Après une modification d'un job ou d'une
> commande Artisan, redémarre le worker pour prendre en compte les changements :
> `docker compose restart worker`.

---

## 5. Commandes utiles (dev quotidien)

| Commande | Description | Exécution |
| --- | --- | --- |
| `make test` | Tests PHP (Pest), sur `wowplanet_test`. | local, **stack requise** |
| `make test-js` | Tests JS (Vitest). | local |
| `make lint` | Corrige le style (Laravel Pint). | local |
| `make static` | Analyse statique (Larastan). | local |
| `make refactor` | Refactorisations automatiques (Rector). | local |
| `make quality` | lint + static + refactor + tests PHP et JS. | local, **stack requise** |
| `make coverage` | Couverture PHP + JS, rapports HTML. | PHP dans le conteneur, JS en local |
| `make psql`, `make redis-cli` | Consoles SQL et Redis. | conteneur |
| `make clean` | Vide les caches Laravel. | conteneur |

Le style, l'analyse statique et les tests JS tournent sans rien démarrer. Tout ce qui exécute la
suite Pest a besoin de PostgreSQL : `make test` le vérifie et le dit en une ligne si la base ne
répond pas, plutôt que de laisser dérouler une pile d'exceptions. La couverture PHP, elle, exige la
stack pour une autre raison — elle a besoin de pcov, qui n'existe que dans le conteneur.

`make help` liste toutes les cibles disponibles.

---

## 6. Architecture (résumé)

- **Backend** — Clean Architecture (`Domain` → `Application` → `Infrastructure` / `Http`),
  Laravel 12, servi par FrankenPHP.
- **Frontend** — Vue 3 avec Inertia et Pinia, rendu serveur optionnel via le sidecar
  `inertia-ssr`. Les controllers rendent les pages de `resources/js/pages/`, et les routes
  `api.php` servent les appels XHR.
- **Persistance** — PostgreSQL 18 pour les données, Redis 8 pour le cache, la queue et les
  sessions, chacun sur son propre index de base. La collation des bases est `fr-FR` par le
  fournisseur ICU, ce qui donne aux noms accentués l'ordre de tri français.

Détails d'architecture et conventions : voir le dossier [`documentation/`](documentation/),
également servi sur `/docs` en environnement local.

---

## 7. Contribuer, signaler, réutiliser

- [Guide de contribution](CONTRIBUTING.md) — TDD, seuils de couverture, ce que la CI exige et les conventions de code.
- [Code de conduite](CODE_OF_CONDUCT.md) — ce qu'on attend des échanges sur le dépôt.
- [Politique de sécurité](SECURITY.md) — comment signaler une faille, en privé et jamais en issue publique.
- [Licence](LICENSE) — GNU AGPL-3.0 : **quiconque héberge une version modifiée de WowPlanet doit en publier le code source.**
