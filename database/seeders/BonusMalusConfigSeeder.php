<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BonusMalusConfigSeeder extends Seeder
{
    /**
     * Seed default Hungarian bonus-malus multipliers for all organizations
     *
     * @return void
     */
    public function run()
    {
        // Default Hungarian multipliers (15 levels)
        $defaultMultipliers = [
            15 => 9.25,  // B10
            14 => 8.25,  // B09
            13 => 7.25,  // B08
            12 => 6.25,  // B07
            11 => 5.25,  // B06
            10 => 4.25,  // B05
            9  => 3.50,  // B04
            8  => 2.75,  // B03
            7  => 2.00,  // B02
            6  => 1.50,  // B01
            5  => 1.00,  // A00
            4  => 0.90,  // M01
            3  => 0.70,  // M02
            2  => 0.40,  // M03
            1  => 0.00,  // M04
        ];

        // Get all existing organizations (exclude soft-deleted)
        $organizations = DB::table('organization')
            ->whereNull('removed_at')
            ->get();

        $insertData = [];
        
        foreach ($organizations as $org) {
            foreach ($defaultMultipliers as $level => $multiplier) {
                $insertData[] = [
                    'organization_id' => $org->id,
                    'level' => $level,
                    'multiplier' => $multiplier,
                ];
            }
        }

        // Bulk insert with duplicate check
        if (!empty($insertData)) {
            foreach ($insertData as $data) {
                DB::table('bonus_malus_config')->insertOrIgnore($data);
            }
        }

        $this->command->info('✅ Seeded bonus-malus multipliers for ' . count($organizations) . ' organization(s)');
        $this->command->info('   Total multiplier records: ' . count($insertData));
    }
}