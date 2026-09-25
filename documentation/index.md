# Documentation WowPlanet

Application Laravel 12 + Vue 3 de suivi de progression World of Warcraft. Architecture en couches : Domain → Application → Infrastructure, exposée via Http.

---

## Table des matières

### Backend PHP

| Fichier | Contenu |
|---|---|
| [backend/01-domain.md](backend/01-domain.md) | Entités, Value Objects, Services métier purs |
| [backend/02-application-services.md](backend/02-application-services.md) | Services de la couche Application |
| [backend/03-aggregators.md](backend/03-aggregators.md) | Agrégateurs de progression (progress/) |
| [backend/04-dtos.md](backend/04-dtos.md) | Data Transfer Objects |
| [backend/05-infrastructure.md](backend/05-infrastructure.md) | Blizzard API, parsers, mappings |
| [backend/06-http.md](backend/06-http.md) | Controllers et Middleware |
| [backend/07-models.md](backend/07-models.md) | Modèles Eloquent |
| [backend/08-jobs.md](backend/08-jobs.md) | Jobs de queue |
| [backend/09-commands.md](backend/09-commands.md) | Commandes Artisan |
| [backend/10-qualite-ci.md](backend/10-qualite-ci.md) | Qualité, pipeline de CI, seuils de couverture, hook pre-commit |

### Frontend JavaScript

| Fichier | Contenu |
|---|---|
| [frontend/01-stores.md](frontend/01-stores.md) | Stores Pinia |
| [frontend/02-composables.md](frontend/02-composables.md) | Composables Vue |
| [frontend/03-utils.md](frontend/03-utils.md) | Utilitaires JS |
| [frontend/04-pages-composants.md](frontend/04-pages-composants.md) | Pages et composants Vue |

---

## Flux de données principaux

```
Requête HTTP
  └─> Controller (Http/)
        └─> Service (Application/Services/)
              ├─> BlizzardApiClient (Infrastructure/Blizzard/)
              ├─> Aggregators (Application/Services/Progress/)
              └─> Models (Eloquent)
                    └─> PostgreSQL
```

```
Import de données (admin)
  └─> AdminController → RunImportJob (queue: imports)
        └─> WowDataImportCommand
              ├─> ReferenceMaps + FactionReference (socle wow_ref_*)
              └─> BlizzardBatchImporter
                    └─> Importers spécialisés (Achievement/Quest/Mount/Pet/Decor/Profession)
```

```
Cross-character (compte)
  └─> UserCharacterController → ComputeCrossCharacterJob (queue: imports)
        └─> CrossCharacterService
              └─> CharacterProfileService (pour chaque personnage)
                    └─> BlizzardApiClient (appels async)
```
