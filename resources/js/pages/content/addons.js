// The addons presented on the Addons page, described in each language of the site.
export const ADDONS = Object.freeze({
    fr: [
        {
            slug: 'maptidy',
            name: 'MapTidy',
            tagline: 'Filtrage des marqueurs de quêtes',
            image: '/images/addons/maptidy.png',
            curseforge: 'https://www.curseforge.com/wow/addons/maptidy',
            description:
                'MapTidy filtre les marqueurs de quêtes sur la carte du monde et la minicarte par '
                + '<strong>type de quête</strong>. Tous les types sont visibles par défaut : '
                + 'l\'addon ne cache jamais un marqueur par erreur. Chaque filtre est mémorisé par personnage.',
            features: [
                'Affichage ou masquage des marqueurs par type de quête',
                'Option « masquer le déjà-fait par le bataillon », réglable type par type',
                'Préréglages nommés sauvegardés au niveau du compte, rappelables sur tous vos personnages',
                'Panneau redimensionnable et déplaçable (AceGUI-3.0), position mémorisée',
            ],
        },
        {
            slug: 'whattodo',
            name: 'WhatTodo',
            tagline: 'Liste de tâches à faire',
            image: '/images/addons/whattodo.png',
            curseforge: 'https://www.curseforge.com/wow/addons/whattodo',
            description:
                'WhatTodo affiche une <strong>liste de tâches</strong> qui se '
                + 'réinitialise automatiquement selon la fréquence de chaque tâche. L\'état '
                + '« fait / à faire » est persisté par personnage.',
            features: [
                'Tâches quotidiennes, hebdomadaires et mensuelles',
                'Reset automatique à 5h (heure serveur) : chaque jour, chaque mercredi, le 1er du mois',
                'État recalculé tout seul au passage de l\'heure de reset',
                'Interface en français sur les clients FR, en anglais ailleurs',
            ],
        },
        {
            slug: 'tanktruckreverse',
            name: 'TankTruckReverse',
            tagline: 'Bip de recul pour tanks',
            image: '/images/addons/tanktruckreverse.png',
            curseforge: 'https://www.curseforge.com/wow/addons/tanks-truck-reverse',
            description:
                'TankTruckReverse joue un <strong>bip de camion en marche '
                + 'arrière</strong> quand un tank recule pendant un combat. Minuscule et sans '
                + 'configuration : le son se déclenche uniquement en spécialisation tank, en '
                + 'combat, touche de recul enfoncée.',
            features: [
                'Détection fiable via les fonctions de déplacement du jeu (aucun calcul de position)',
                'Se déclenche seulement en combat, en spé tank, en reculant (le pas-chassé et la course avant ne comptent pas)',
                'Répétition du bip toutes les ~0,7 s tant que la touche de recul est maintenue',
                'Commandes : /ttr (activer/désactiver), /ttr test, /ttr debug',
            ],
        },
    ],
    en: [
        {
            slug: 'maptidy',
            name: 'MapTidy',
            tagline: 'Quest marker filtering',
            image: '/images/addons/maptidy.png',
            curseforge: 'https://www.curseforge.com/wow/addons/maptidy',
            description:
                'MapTidy filters the quest markers on the world map and the minimap by '
                + '<strong>quest type</strong>. Every type is visible by default: '
                + 'the addon never hides a marker by mistake. Each filter is saved per character.',
            features: [
                'Show or hide markers by quest type',
                '“Hide what your Warband already did” option, set type by type',
                'Named presets saved account-wide, available on all your characters',
                'Resizable, movable panel (AceGUI-3.0) that remembers its position',
            ],
        },
        {
            slug: 'whattodo',
            name: 'WhatTodo',
            tagline: 'To-do list',
            image: '/images/addons/whattodo.png',
            curseforge: 'https://www.curseforge.com/wow/addons/whattodo',
            description:
                'WhatTodo shows a <strong>task list</strong> that resets '
                + 'automatically according to the frequency of each task. The '
                + '“done / to do” state is saved per character.',
            features: [
                'Daily, weekly and monthly tasks',
                'Automatic reset at 5:00 (server time): every day, every Wednesday, on the 1st of the month',
                'State recomputed on its own when the reset time passes',
                'Interface in French on FR clients, in English everywhere else',
            ],
        },
        {
            slug: 'tanktruckreverse',
            name: 'TankTruckReverse',
            tagline: 'Reverse beep for tanks',
            image: '/images/addons/tanktruckreverse.png',
            curseforge: 'https://www.curseforge.com/wow/addons/tanks-truck-reverse',
            description:
                'TankTruckReverse plays a <strong>truck reversing '
                + 'beep</strong> when a tank backs up during combat. Tiny and with no '
                + 'setup: the sound only plays in a tank specialization, in '
                + 'combat, with the backward key held down.',
            features: [
                'Reliable detection through the game movement functions (no position calculation)',
                'Only plays in combat, in a tank spec, while moving backward (strafing and running forward do not count)',
                'The beep repeats every ~0.7 s while the backward key is held',
                'Commands: /ttr (toggle), /ttr test, /ttr debug',
            ],
        },
    ],
});
