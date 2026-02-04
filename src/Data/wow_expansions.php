<?php

/**
 * WoW expansion definitions.
 *
 * Each expansion defines:
 * - name: display name
 * - order: chronological order (1 = Classic, 11 = The War Within)
 *
 * Quest-to-expansion mapping is in quest_expansion_map.php (generated from BtWQuests addon data).
 * Zone names per expansion are in expansion_zones.php.
 *
 * @return list<array{name: string, order: int}>
 */

declare(strict_types=1);

return [
    ['name' => 'Classic', 'order' => 1],
    ['name' => 'The Burning Crusade', 'order' => 2],
    ['name' => 'Wrath of the Lich King', 'order' => 3],
    ['name' => 'Cataclysm', 'order' => 4],
    ['name' => 'Mists of Pandaria', 'order' => 5],
    ['name' => 'Warlords of Draenor', 'order' => 6],
    ['name' => 'Legion', 'order' => 7],
    ['name' => 'Battle for Azeroth', 'order' => 8],
    ['name' => 'Shadowlands', 'order' => 9],
    ['name' => 'Dragonflight', 'order' => 10],
    ['name' => 'The War Within', 'order' => 11],
];
