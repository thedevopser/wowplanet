<?php

/**
 * Expansion zones/categories extracted from BtWQuests addon.
 *
 * Each expansion has a list of zones with their BtWQuests internal category IDs.
 * These are NOT Blizzard API category IDs.
 *
 * @return array<int, list<array{name: string, btw_category_id: int}>>
 */

declare(strict_types=1);

return [
    // TBC
    2 => [
        ['name' => 'Péninsule des Flammes infernales', 'btw_category_id' => 201],
        ['name' => 'Marécage de Zangar', 'btw_category_id' => 202],
        ['name' => 'Forêt de Terokkar', 'btw_category_id' => 203],
        ['name' => 'Nagrand', 'btw_category_id' => 204],
        ['name' => 'Les Tranchantes', 'btw_category_id' => 205],
        ['name' => 'Raz-de-Néant', 'btw_category_id' => 206],
        ['name' => 'Vallée d’Ombrelune', 'btw_category_id' => 207],
    ],
    // WotLK
    3 => [
        ['name' => 'Toundra Boréenne', 'btw_category_id' => 301],
        ['name' => 'Fjord Hurlant', 'btw_category_id' => 302],
        ['name' => 'Désolation des dragons', 'btw_category_id' => 303],
        ['name' => 'Les Grisonnes', 'btw_category_id' => 304],
        ['name' => 'Zul’Drak', 'btw_category_id' => 305],
        ['name' => 'Bassin de Sholazar', 'btw_category_id' => 306],
        ['name' => 'Les pics Foudroyés', 'btw_category_id' => 307],
        ['name' => 'Couronne de glace', 'btw_category_id' => 308],
    ],
    // Cataclysm
    4 => [
        ['name' => 'Orneval', 'btw_category_id' => 113],
        ['name' => 'Serres-Rocheuses', 'btw_category_id' => 117],
        ['name' => 'Hautes-terres Arathies', 'btw_category_id' => 119],
        ['name' => 'Strangleronce septentrionale', 'btw_category_id' => 120],
        ['name' => 'Tarides du Sud', 'btw_category_id' => 121],
        ['name' => 'Cap Strangleronce', 'btw_category_id' => 122],
        ['name' => 'Désolace', 'btw_category_id' => 123],
        ['name' => 'Hinterlands', 'btw_category_id' => 124],
        ['name' => 'Marécage d’Âprefange', 'btw_category_id' => 125],
        ['name' => 'Féralas', 'btw_category_id' => 126],
        ['name' => 'Maleterres de l’Ouest', 'btw_category_id' => 127],
        ['name' => 'Terres Ingrates', 'btw_category_id' => 128],
        ['name' => 'Terres Foudroyées', 'btw_category_id' => 129],
        ['name' => 'Steppes Ardentes', 'btw_category_id' => 130],
        ['name' => 'Maleterres de l’Est', 'btw_category_id' => 131],
        ['name' => 'Gangrebois', 'btw_category_id' => 132],
        ['name' => 'Gorge des Vents brûlants', 'btw_category_id' => 133],
        ['name' => 'Silithus', 'btw_category_id' => 134],
        ['name' => 'Marais des Chagrins', 'btw_category_id' => 135],
        ['name' => 'Tanaris', 'btw_category_id' => 136],
        ['name' => 'Mille pointes', 'btw_category_id' => 137],
        ['name' => 'Cratère d’Un’Goro', 'btw_category_id' => 138],
        ['name' => 'Berceau-de-l’Hiver', 'btw_category_id' => 139],
        ['name' => 'Mont Hyjal', 'btw_category_id' => 401],
        ['name' => 'Vashj’ir', 'btw_category_id' => 402],
        ['name' => 'Le Tréfonds', 'btw_category_id' => 403],
        ['name' => 'Uldum', 'btw_category_id' => 404],
        ['name' => 'Hautes-terres du Crépuscule', 'btw_category_id' => 405],
    ],
    // MoP
    5 => [
        ['name' => 'La forêt de Jade', 'btw_category_id' => 501],
        ['name' => 'Vallée des Quatre vents', 'btw_category_id' => 502],
        ['name' => 'Étendues sauvages de Krasarang', 'btw_category_id' => 503],
        ['name' => 'Sommet de Kun-Lai', 'btw_category_id' => 504],
        ['name' => 'Steppes de Tanglong', 'btw_category_id' => 505],
        ['name' => 'Terres de l’Angoisse', 'btw_category_id' => 506],
    ],
    // WoD
    6 => [
        ['name' => 'Gorgrond', 'btw_category_id' => 603],
        ['name' => 'Talador', 'btw_category_id' => 604],
        ['name' => 'Flèches d’Arak', 'btw_category_id' => 605],
        ['name' => 'Nagrand', 'btw_category_id' => 606],
        ['name' => 'Jungle de Tanaan', 'btw_category_id' => 607],
    ],
    // Legion
    7 => [
        ['name' => 'Prodigieuse', 'btw_category_id' => 701],
        ['name' => 'Order Hall', 'btw_category_id' => 702],
        ['name' => 'Azsuna', 'btw_category_id' => 703],
        ['name' => 'Val’sharah', 'btw_category_id' => 704],
        ['name' => 'Haut-Roc', 'btw_category_id' => 705],
        ['name' => 'Tornheim', 'btw_category_id' => 706],
        ['name' => 'Suramar', 'btw_category_id' => 707],
        ['name' => 'Rivage Brisé', 'btw_category_id' => 711],
        ['name' => 'Argus', 'btw_category_id' => 712],
        ['name' => 'Chevalier de la mort', 'btw_category_id' => 713],
        ['name' => 'Chasseur de démons', 'btw_category_id' => 714],
        ['name' => 'Druide', 'btw_category_id' => 715],
        ['name' => 'Chasseur', 'btw_category_id' => 716],
        ['name' => 'Mage', 'btw_category_id' => 717],
        ['name' => 'Moine', 'btw_category_id' => 718],
        ['name' => 'Paladin', 'btw_category_id' => 719],
        ['name' => 'Prêtre', 'btw_category_id' => 720],
        ['name' => 'Voleur', 'btw_category_id' => 721],
        ['name' => 'Chaman', 'btw_category_id' => 722],
        ['name' => 'Démoniste', 'btw_category_id' => 723],
        ['name' => 'Guerrier', 'btw_category_id' => 724],
        ['name' => 'Secret', 'btw_category_id' => 725],
        ['name' => 'Métiers', 'btw_category_id' => 726],
        ['name' => 'Races alliées', 'btw_category_id' => 727],
    ],
    // BfA
    8 => [
        ['name' => 'Zuldazar', 'btw_category_id' => 801],
        ['name' => 'Nazmir', 'btw_category_id' => 802],
        ['name' => 'Vol’dun', 'btw_category_id' => 803],
        ['name' => 'Rade de Tiragarde', 'btw_category_id' => 804],
        ['name' => 'Drustvar', 'btw_category_id' => 805],
        ['name' => 'Vallée Chantorage', 'btw_category_id' => 806],
        ['name' => 'Île de Mécagone', 'btw_category_id' => 807],
        ['name' => 'Nazjatar', 'btw_category_id' => 808],
        ['name' => 'La forge du Cœur', 'btw_category_id' => 809],
        ['name' => 'Visions de N\'Zoth', 'btw_category_id' => 810],
        ['name' => 'Métiers', 'btw_category_id' => 897],
        ['name' => 'Races alliées', 'btw_category_id' => 898],
        ['name' => 'Secret', 'btw_category_id' => 899],
    ],
    // Shadowlands
    9 => [
        ['name' => 'Le Bastion', 'btw_category_id' => 901],
        ['name' => 'Maldraxxus', 'btw_category_id' => 902],
        ['name' => 'Sylvarden', 'btw_category_id' => 903],
        ['name' => 'Revendreth', 'btw_category_id' => 904],
        ['name' => 'Campagne kyriane', 'btw_category_id' => 906],
        ['name' => 'Campagne des nécro-seigneurs', 'btw_category_id' => 907],
        ['name' => 'Campagne des Faë nocturnes', 'btw_category_id' => 908],
        ['name' => 'Campagne des Venthyrs', 'btw_category_id' => 909],
        ['name' => 'Chaînes de domination', 'btw_category_id' => 910],
        ['name' => 'Les secrets des Fondateurs', 'btw_category_id' => 911],
    ],
    // Dragonflight
    10 => [
        ['name' => 'Rivages de l’Éveil', 'btw_category_id' => 1001],
        ['name' => 'Plaines d’Ohn’ahra', 'btw_category_id' => 1002],
        ['name' => 'Travée d’Azur', 'btw_category_id' => 1003],
        ['name' => 'Thaldraszus', 'btw_category_id' => 1004],
        ['name' => 'Amitié des îles aux Dragons', 'btw_category_id' => 1005],
        ['name' => 'Braises de Neltharion', 'btw_category_id' => 1006],
        ['name' => 'Les défenseurs du Rêve', 'btw_category_id' => 1007],
    ],
    // TWW
    11 => [
        ['name' => 'Île de Dorn', 'btw_category_id' => 1101],
        ['name' => 'Les abîmes Retentissants', 'btw_category_id' => 1102],
        ['name' => 'Sainte-Chute', 'btw_category_id' => 1103],
        ['name' => 'Azj-Kahet', 'btw_category_id' => 1104],
        ['name' => 'Terremine', 'btw_category_id' => 1106],
        ['name' => 'K’aresh', 'btw_category_id' => 1107],
        ['name' => 'Visions d’un soleil occulté', 'btw_category_id' => 1108],
        ['name' => 'Chroniques', 'btw_category_id' => 1109],
    ],
];
