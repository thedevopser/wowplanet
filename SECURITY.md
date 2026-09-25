*Français · [English](#security-policy)*

# Politique de sécurité

## Versions couvertes

WowPlanet est déployé en continu depuis `main`. Il n'y a pas de versions parallèles à maintenir : **seul l'état courant de `main` est couvert**, et un correctif de sécurité part en production sans attendre une prochaine version.

## Signaler une faille

**N'ouvre pas d'issue publique.** Une issue est lisible par tout le monde, y compris pendant les heures où la faille n'est pas encore corrigée.

Passe par l'onglet **Security** du dépôt, bouton *Report a vulnerability* : le signalement est privé et n'est visible que du mainteneur. À défaut, écris directement à [@thedevopser](https://github.com/thedevopser) sur GitHub en demandant un canal privé, sans détailler la faille dans le premier message.

Ce qui aide, dans l'ordre d'utilité : ce que la faille permet de faire, les étapes exactes pour la reproduire, l'URL ou l'endpoint concerné, et si tu l'as, le bout de code fautif. Une preuve de concept minimale vaut mieux qu'une longue description.

## Ce à quoi tu peux t'attendre

Accusé de réception sous 72 heures. Un premier avis sur la validité et la gravité sous 7 jours. Ensuite, le rythme dépend de ce qu'il faut corriger : une erreur de configuration part le jour même, un défaut de conception prend le temps qu'il prend, et tu es tenu au courant.

Le projet est personnel et sans budget : **il n'y a pas de récompense financière**. Si tu le souhaites, tu es crédité dans l'avis de sécurité publié une fois le correctif déployé.

## Périmètre

Entrent dans le périmètre : l'application elle-même, sa gestion de session, le flux OAuth Battle.net, le panneau d'administration, les endpoints d'API, et tout ce qui permettrait de lire ou d'écrire les données d'un autre utilisateur.

N'entrent pas dans le périmètre : les failles de l'API Blizzard, à signaler à Blizzard ; les dépendances tierces non modifiées, à signaler en amont ; les rapports de scanner automatique sans exploitation démontrée ; l'absence d'un en-tête HTTP qui n'expose rien de concret ; le déni de service par volume de requêtes.

## Ce qu'on te demande

Ne touche qu'à tes propres comptes et à tes propres données. N'exfiltre rien, ne modifie rien qui ne t'appartienne pas, ne dégrade pas le service. Laisse un délai raisonnable avant toute publication.

---

*[Français](#politique-de-sécurité) · English*

# Security policy

## Supported versions

WowPlanet is deployed continuously from `main`. There are no parallel versions to maintain: **only the current state of `main` is supported**, and a security fix goes to production without waiting for a release.

## Reporting a vulnerability

**Do not open a public issue.** An issue is readable by everyone, including during the hours when the flaw is not yet fixed.

Use the repository's **Security** tab, *Report a vulnerability* button: the report is private and visible only to the maintainer. Failing that, write directly to [@thedevopser](https://github.com/thedevopser) on GitHub asking for a private channel, without detailing the flaw in that first message.

What helps, in order of usefulness: what the flaw allows someone to do, the exact steps to reproduce it, the URL or endpoint involved, and the offending code if you have it. A minimal proof of concept beats a long description.

## What to expect

Acknowledgement within 72 hours. A first assessment of validity and severity within 7 days. After that, the pace depends on what needs fixing: a misconfiguration ships the same day, a design flaw takes what it takes, and you are kept informed.

The project is personal and has no budget: **there is no bounty**. If you want it, you are credited in the security advisory published once the fix is deployed.

## Scope

In scope: the application itself, its session handling, the Battle.net OAuth flow, the admin panel, the API endpoints, and anything that would allow reading or writing another user's data.

Out of scope: flaws in the Blizzard API, which go to Blizzard; unmodified third-party dependencies, which go upstream; automated scanner output with no demonstrated exploitation; a missing HTTP header that exposes nothing concrete; denial of service through request volume.

## What is asked of you

Touch only your own accounts and your own data. Exfiltrate nothing, modify nothing that is not yours, do not degrade the service. Allow a reasonable delay before any public disclosure.
