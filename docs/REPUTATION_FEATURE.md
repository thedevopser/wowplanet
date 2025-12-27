# Fonctionnalité Recherche de Réputations WoW

## Vue d'ensemble

Cette fonctionnalité permet aux utilisateurs de rechercher et comparer les réputations de leurs personnages World of Warcraft en utilisant l'API Blizzard.

## Architecture

### Services

#### BlizzardOAuthService
- Gère l'authentification OAuth avec Battle.net
- Génère les URLs d'autorisation
- Échange le code d'autorisation contre un token d'accès
- Le token est stocké en session PHP

#### BlizzardApiService
- Effectue les requêtes vers l'API Blizzard
- Implémente un système de cache (5 min pour les profils, 10 min pour les réputations)
- Récupère les profils utilisateurs et les réputations des personnages
- Calcule les valeurs de réputation pour le classement

### Controllers

#### BlizzardOAuthController
- `/oauth` - Page de connexion OAuth
- `/oauth/callback` - Callback après authentification Blizzard
- `/oauth/logout` - Déconnexion

#### ReputationController
- `/reputation` - Page de recherche de réputations (GET)
- `/reputation/process` - Traitement de la recherche (POST)
- `/reputation/results` - Affichage des résultats (GET)

Le controller utilise le pattern Post-Redirect-Get (PRG) pour éviter les soumissions de formulaire en double et permettre au loader de fonctionner correctement.

### Templates

#### oauth/login.html.twig
- Interface de connexion Battle.net
- Instructions pour l'utilisateur
- Affichage des erreurs d'expiration de session

#### reputation/search.html.twig
- Formulaire de recherche de faction
- Loader animé avec Stimulus pendant le traitement
- Animation multi-niveaux avec cercles tournants et icône pulsante

#### reputation/results.html.twig
- **Podium amélioré** : Design moderne avec badges de rang, hauteurs différentes et animations
- **Grille responsive** : Layout en cartes (1/2/3/4 colonnes selon la taille d'écran)
- **Pagination côté client** : 12 résultats par page avec navigation Stimulus
- **Cartes compactes** : Badge de rang, infos personnage, standing coloré, progression
- **Animations** : Hover effects, fadeIn au changement de page

### Frontend (Stimulus)

#### reputation_search_controller.js
- Contrôle l'affichage du loader pendant la recherche
- Cache le formulaire et affiche l'animation
- Récupère le nom de faction pour affichage dynamique

#### results_pagination_controller.js
- Gère la pagination côté client (12 items par page)
- Navigation par boutons Précédent/Suivant et numéros de page
- Animation fadeIn au changement de page
- Scroll automatique vers le haut des résultats
- Mise à jour dynamique de l'indicateur "X-Y sur Z"

## Configuration

### Variables d'environnement (.env)

```env
BLIZZARD_CLIENT_ID=votre_client_id
BLIZZARD_CLIENT_SECRET=votre_client_secret
BLIZZARD_REGION=eu
BLIZZARD_LOCALE=fr_FR
BLIZZARD_REDIRECT_URI=http://localhost:8000/oauth/callback
```

### Configuration Battle.net

1. Créer une application sur https://develop.battle.net/access/clients
2. Ajouter l'URL de callback dans les "Redirect URIs"
3. Copier le Client ID et Client Secret dans `.env`

## Utilisation

1. **Authentification**
   - Accéder à `/oauth`
   - Cliquer sur "Se connecter avec Battle.net"
   - Autoriser l'accès aux données WoW
   - Redirection automatique vers `/reputation`

2. **Recherche de réputation**
   - Entrer le nom exact de la faction (ex: "Gilnéas", "Guilde Valdrakken")
   - Cliquer sur "Rechercher"
   - Un loader animé s'affiche pendant l'analyse (géré par Stimulus)
   - Redirection automatique vers `/reputation/results` une fois terminé
   - Les résultats affichent tous les personnages ayant cette réputation, classés par niveau

### Interface de chargement

Pendant la recherche, un loader animé avec Stimulus affiche :
- Animation de cercles tournants (bleu/violet)
- Icône ⚔️ pulsante au centre
- Texte dynamique avec le nom de la faction recherchée
- Points animés en bas pour indiquer l'activité

## Système de cache

Le cache Symfony est utilisé pour minimiser les appels API :

- **Profils utilisateurs** : 5 minutes (300 secondes)
- **Réputations des personnages** : 10 minutes (600 secondes)

Les clés de cache sont basées sur :
- Hash SHA256 du token pour les profils
- Slug du royaume + nom du personnage pour les réputations

## Gestion des erreurs

Le service API gère gracieusement les erreurs :

- **Erreur 404** : Certains personnages peuvent ne pas être accessibles (personnages transférés, supprimés, ou serveurs indisponibles). Ces erreurs sont loggées mais n'interrompent pas la recherche.
- **Token expiré** : Redirection automatique vers la page de connexion OAuth
- **Faction introuvable** : Message clair indiquant qu'aucun personnage n'a cette réputation
- **Profil invalide** : Message d'erreur demandant de vérifier les autorisations

## Normalisation des noms de factions

Pour garantir que les recherches fonctionnent correctement, les noms de factions sont normalisés avant comparaison :

- **Conversion en minuscules** : "Accord d'Uldum" → "accord d'uldum"
- **Normalisation des apostrophes** : `'` `'` `´` `` ` `` → `'`
- **Normalisation des tirets** : `–` `—` → `-`
- **Suppression des espaces en début/fin** : trim()

Cela permet de trouver les factions même si l'utilisateur tape :
- "Accord d'Uldum" (apostrophe droite)
- "Accord d'Uldum" (apostrophe typographique de l'API)
- "Champions d'Azeroth"
- "champions d'azeroth" (casse différente)

## Calcul de la valeur de réputation

Les réputations sont classées dans cet ordre :
1. Haï (0)
2. Hostile (1)
3. Inamical (2)
4. Neutre (3)
5. Amical (4)
6. Honoré (5)
7. Révéré (6)
8. Exalté (7)

**Formule** : `(niveau_standing * 100000) + progression_actuelle`

**Cas spécial Renown** : `10000000 + (niveau_renown * 10000)`

Cela garantit que les Renown sont toujours au-dessus des réputations classiques.

## Sécurité

- Aucune authentification Symfony requise (comme demandé)
- Le token OAuth est stocké en session PHP
- Validation stricte de tous les paramètres (PHPStan level max)
- Protection CSRF native de Symfony sur les formulaires
- Validation du state OAuth pour éviter les attaques CSRF

## Logging

Tous les événements importants sont loggés :
- Génération d'URL d'autorisation
- Échange de code contre token
- Récupération de profils
- Recherches de réputation
- Erreurs API

## Tests de qualité

Le code respecte les standards du projet :
- ✅ PHPCBF (PSR12) - Aucune violation
- ✅ PHPStan (level max) - Aucune erreur
- ✅ Rector (PHP 8.4 + Symfony 8.0) - Appliqué

## Améliorations futures possibles

1. **Select pré-rempli** : Liste déroulante des factions disponibles
2. **Pagination** : Pour les comptes avec beaucoup de personnages
3. **Filtres** : Par royaume, niveau, classe
4. **Export** : CSV, JSON des résultats
5. **Historique** : Suivi de l'évolution des réputations
6. **Graphiques** : Visualisation des progressions
