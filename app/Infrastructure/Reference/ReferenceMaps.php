<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use Illuminate\Support\Facades\DB;

/**
 * Les correspondances que l'import tire du socle : extension et faction des quêtes,
 * faction des recettes, faction des zones.
 *
 * Ces cartes remplacent le parsing de CSV à chaque import. Elles sont construites une
 * fois en début de passe et gardées en mémoire : quelques dizaines de milliers d'entiers
 * ne pèsent rien, là où un aller-retour SQL par quête coûterait la passe entière.
 *
 * Une quête sans titre est ignorée partout, comme le faisait la lecture du CSV : elle
 * n'entre au catalogue sous aucune forme, donc son rangement n'intéresse personne.
 */
class ReferenceMaps
{
    /**
     * Masque de groupe de faction d'une zone, tel que porté par AreaTable.
     */
    private const ZONE_FACTION_GROUPS = [2 => 'Alliance', 4 => 'Horde'];

    /**
     * Gabarit d'icône du CDN Blizzard, celui-là même que l'API sert pour les mascottes
     * et les hauts faits. Les montures sont les seules à devoir le composer : ni leur
     * détail ni aucun espace de media n'expose leur icône.
     */
    private const ICON_URL = 'https://render.worldofwarcraft.com/%s/icons/56/%d.jpg';

    /**
     * @return array<int, int> [quest_id => expansion_id]
     */
    public function questExpansions(): array
    {
        $rows = DB::table('wow_ref_quest_v2_cli_task')
            ->join('wow_ref_content_tuning', 'wow_ref_quest_v2_cli_task.content_tuning_id', '=', 'wow_ref_content_tuning.id')
            ->where('wow_ref_quest_v2_cli_task.quest_title_lang', '!=', '')
            ->whereNotNull('wow_ref_content_tuning.expansion_id')
            ->get(['wow_ref_quest_v2_cli_task.id as quest_id', 'wow_ref_content_tuning.expansion_id as expansion_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[ReferenceValue::int($row->quest_id)] = ReferenceValue::int($row->expansion_id);
        }

        return $map;
    }

    /**
     * @return array<int, string> [quest_id => 'Alliance'|'Horde']
     */
    public function questFactions(): array
    {
        $rows = DB::table('wow_ref_quest_v2_cli_task')
            ->where('quest_title_lang', '!=', '')
            ->get(['id', 'filt_race_masks_0', 'filt_race_masks_1']);

        $map = [];
        foreach ($rows as $row) {
            $faction = RaceMask::faction(
                ReferenceValue::nullableInt($row->filt_race_masks_0),
                ReferenceValue::nullableInt($row->filt_race_masks_1),
            );

            if ($faction !== null) {
                $map[ReferenceValue::int($row->id)] = $faction;
            }
        }

        return $map;
    }

    /**
     * @return array<int, string> [recipe_id => 'Alliance'|'Horde']
     */
    public function recipeFactions(): array
    {
        $rows = DB::table('wow_ref_skill_line_ability')->get(['id', 'race_masks_0', 'race_masks_1']);

        $map = [];
        foreach ($rows as $row) {
            $faction = RaceMask::faction(
                ReferenceValue::nullableInt($row->race_masks_0),
                ReferenceValue::nullableInt($row->race_masks_1),
            );

            if ($faction !== null) {
                $map[ReferenceValue::int($row->id)] = $faction;
            }
        }

        return $map;
    }

    /**
     * @return array<int, int> [mount_id => source_spell_id]
     */
    public function mountSpells(): array
    {
        $rows = DB::table('wow_ref_mount')->whereNotNull('source_spell_id')->get(['id', 'source_spell_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[ReferenceValue::int($row->id)] = ReferenceValue::int($row->source_spell_id);
        }

        return $map;
    }

    /**
     * @return array<int, string> [mount_id => icon_url]
     */
    public function mountIcons(): array
    {
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');

        // Un sort porte parfois plusieurs lignes SpellMisc, une par difficulté. La plus
        // petite tranche l'égalité pour que deux imports rendent la même icône.
        $rows = DB::table('wow_ref_mount')
            ->join('wow_ref_spell_misc', 'wow_ref_mount.source_spell_id', '=', 'wow_ref_spell_misc.spell_id')
            ->whereNotNull('wow_ref_spell_misc.spell_icon_file_data_id')
            ->orderByDesc('wow_ref_spell_misc.id')
            ->get(['wow_ref_mount.id as mount_id', 'wow_ref_spell_misc.spell_icon_file_data_id as file_data_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[ReferenceValue::int($row->mount_id)] = sprintf(self::ICON_URL, $region, ReferenceValue::int($row->file_data_id));
        }

        return $map;
    }

    /**
     * @return array<int, string> [area_id => 'Alliance'|'Horde']
     */
    public function zoneFactions(): array
    {
        $rows = DB::table('wow_ref_area_table')
            ->whereIn('faction_group_mask', array_keys(self::ZONE_FACTION_GROUPS))
            ->get(['id', 'faction_group_mask']);

        $map = [];
        foreach ($rows as $row) {
            $map[ReferenceValue::int($row->id)] = self::ZONE_FACTION_GROUPS[ReferenceValue::int($row->faction_group_mask)];
        }

        return $map;
    }
}
