# Couche Domain

Contient la logique métier pure, sans dépendance vers Laravel ou la base de données.

---

## Value Objects (`app/Domain/ValueObjects/`)

### `ExpansionId`

Value Object immuable représentant une extension WoW. Lance `InvalidArgumentException` si la valeur est hors plage.

**Constantes**

| Constante | Valeur | Extension |
|---|---|---|
| `CLASSIC` | `0` | World of Warcraft Classic |
| `BURNING_CRUSADE` | `1` | The Burning Crusade |
| `WRATH_OF_THE_LICH_KING` | `2` | Wrath of the Lich King |
| `CATACLYSM` | `3` | Cataclysm |
| `MISTS_OF_PANDARIA` | `4` | Mists of Pandaria |
| `WARLORDS_OF_DRAENOR` | `5` | Warlords of Draenor |
| `LEGION` | `6` | Legion |
| `BATTLE_FOR_AZEROTH` | `7` | Battle for Azeroth |
| `SHADOWLANDS` | `8` | Shadowlands |
| `DRAGONFLIGHT` | `9` | Dragonflight |
| `THE_WAR_WITHIN` | `10` | The War Within |
| `MIDNIGHT` | `11` | Midnight |
| `UNCLASSIFIED` | `99` | Non classé — ce que rien ne date |

`UNCLASSIFIED` est hors de la suite des extensions à dessein. Une entrée que sa source ne rattache à aucune extension — un haut fait de la Voile d'hiver, de la Pêche, d'un champ de bataille — n'est pas du contenu d'origine, et la verser dans `CLASSIC` la déguiserait en contenu du jeu de base. La valeur est prise loin devant pour que la prochaine extension reste `12`.

Tout ce qui parcourt les extensions passe par `allSlugs()` et non par une borne `0..11` en dur, sans quoi ce seau disparaîtrait des agrégats.

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `toString()` | `string` | Nom complet de l'extension (ex. : `"The Burning Crusade"`) |
| `toOrdinal()` | `string` | Rang en français (ex. : `"la 1re extension"`) |
| `toSlug()` | `string` | Slug URL (ex. : `"burning-crusade"`) |
| `fromSlug(string $slug)` | `?self` | Construit depuis un slug, `null` si inconnu (statique) |
| `allSlugs()` | `array<int, string>` | Tableau complet `id → slug` (statique) |

---

### `ScoreInput`

Entrée du calcul de score, commune au profil d'un personnage et au profil virtuel d'un compte. `CharacterProfileService` la remplit avec ce que les agrégateurs ont produit pour un personnage, `AccountScoreService` avec le cumul de tous les personnages du compte. La formule ne sait pas lequel des deux elle note, et c'est voulu : un seul barème, deux usages.

Le domaine ne décrit que ce que la formule lit. Chaque forme, déclarée en alias PHPStan sur la classe (`ScoreCollection`, `ScoreItem`, `ScoreProfession`, `ScoreRaid`…), est un sous-ensemble de ce que rendent les agrégateurs, et une clé absente vaut zéro. Un agrégateur peut donc enrichir sa sortie sans toucher au domaine.

| Entrée | Contenu |
|---|---|
| `collections` | Par extension, les compteurs `completed` / `total` des quêtes, hauts-faits et réputations. |
| `mounts`, `pets`, `decor` | Le catalogue complet, chaque entrée portant `is_completed`. |
| `appearances` | Par emplacement, les apparences collectées sur le total. |
| `professions` | Par métier et par extension, les recettes connues et les points de compétence. |
| `raids` | Le tier de raid courant, tel que le rend `RaidProgressAggregator`. `null` quand il n'a pas pu être lu. |
| `bestProfessionStats` | Le meilleur ratio de métier du compte, quand il est connu. |

---

### `ScoreDimension`

Une ligne du score : sa clé, son libellé, son poids, ses compteurs et sa note sur 100. Le drapeau `applicable` distingue deux situations que la note seule confond : « aucune donnée », qui sort la dimension du calcul, et « progression nulle », qui compte pour zéro. Le front s'en sert pour afficher la dimension grisée plutôt qu'à 0 %.

---

### `CompletionScore`

Le résultat du calcul : la note globale sur 100 arrondie au dixième, le rang qui en découle, la version de la formule qui l'a produite, et la liste complète des dimensions, applicables ou non, dans l'ordre d'affichage. C'est lui qui voyage jusqu'au front, dans `CharacterProfileDTO` comme dans le résultat du score de compte.

---

### `ScoreWeights`

Source unique de la pondération. Elle porte les neuf poids, dont la somme vaut 1, leurs libellés, et l'ordre des clés, qui est l'ordre d'affichage.

| Dimension | Poids |
|---|---|
| Quêtes | 0,13 |
| Hauts-faits | 0,20 |
| Réputations | 0,12 |
| Raids | 0,07 |
| Montures | 0,13 |
| Garde-robe | 0,12 |
| Mascottes | 0,08 |
| Décorations | 0,07 |
| Métiers | 0,08 |

Le JcJ n'y figure pas. Son onglet est chargé à la demande, après la fiche : au moment où le score est calculé, ses données n'existent pas encore, et il noterait à zéro tout profil qui ne joue qu'en JcE.

`VERSION` s'incrémente à tout changement de pondération. Elle entre dans la clé de cache du score de compte (`account_score:v{VERSION}:{session}`) : changer le barème purge d'office les scores calculés avec l'ancien, sans commande à lancer.

---

## Services (`app/Domain/Services/`)

### `ScoreCalculator`

Service pur, et seule implémentation de la formule de score. Il reçoit un `ScoreInput` et rend un `CompletionScore`, sans rien lire d'autre.

**La formule.** Chaque dimension est notée de 0 à 100, `completed / total`. La note globale est la moyenne de ces notes pondérée par `ScoreWeights`, puis divisée par la somme des poids *des seules dimensions applicables* — c'est la renormalisation.

**Pourquoi renormaliser.** Une dimension dont le total est nul n'a pas de données : le catalogue de la dimension est vide, l'API n'a rien rendu, ou le personnage n'a accès à aucun contenu mesurable. La noter zéro sanctionnerait le joueur pour une absence qui ne dépend pas de lui, et plafonnerait son score sous 100 quoi qu'il fasse. Elle sort donc du calcul, et son poids se répartit sur les autres à proportion des leurs. Un personnage dont le tier de raid n'a pas pu être lu est noté sur les 93 % de poids restants, ramenés à 100 : son score reste comparable à celui d'un personnage complet. À l'inverse, une dimension applicable où le joueur n'a rien fait compte bien pour zéro. Si aucune dimension n'est applicable, la note vaut 0.

**Les mesures qui ne sont pas un simple compte.**

- **Raids** : chaque boss du tier courant compte une fois, à la valeur de sa meilleure difficulté — 0,25 en outil de raid, 0,5 en normal, 0,75 en héroïque, 1 en mythique. `completed` est donc un équivalent-mythique : huit boss tués en normal valent quatre. Tuer un boss dans plusieurs modes ne le compte pas plusieurs fois.
- **Métiers** : le meilleur ratio de métier du compte, quand il est connu, prime sur le cumul du personnage courant. À défaut, les recettes connues sur le total du référentiel ; sans référentiel de recettes, les points de compétence sont la seule mesure disponible.
- **Garde-robe** : la somme des apparences collectées, emplacement par emplacement.

**Le rang** se lit sur la note globale : Légendaire à partir de 90, Épique à 75, Rare à 50, Commun à 25, Débutant en dessous.

Le score est calculé côté serveur et arrive au front déjà fait. Aucun composant ne refait le calcul.

---

### `PvpBracketClassifier`

Traduit le slug d'un bracket JcJ renvoyé par l'API en mode de jeu (arène, champs de bataille cotés, mêlée solo, blitz, autres) et en libellé français. Aucune liste de brackets n'est figée : le mode se déduit de la forme du slug, de sorte qu'un bracket ajouté par Blizzard reste affichable sans livraison, rangé au pire dans « Autres modes ».

Pour les brackets par spécialisation (`shuffle-priest-shadow`, `blitz-…`), il extrait la classe et la spécialisation, et compose un libellé court (« Ombre ») et un libellé complet (« Mêlée solo — Ombre »). `GROUPS` fixe l'ordre d'affichage des modes.

Il est partagé par l'onglet JcJ du profil (`PvpProgressAggregator`, `PvpProfileService`) et par la page des classements (`PvpLeaderboardService`), qui doivent nommer les mêmes modes de la même façon.

---

## Exceptions (`app/Domain/Exceptions/`)

### `FavoriteLimitReachedException`

Levée par `CharacterFavoriteService` quand un utilisateur ajoute un favori alors qu'il a déjà atteint le maximum (`MAX_FAVORITES`, trois). `CharacterFavoriteController` la traduit en 422, avec le maximum dans la réponse.
