<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $now = now();

        $defaults = [
            ['name' => 'GIS / Geospatial', 'slug' => 'gis', 'color' => '#38bdf8'],
            ['name' => 'Land Management', 'slug' => 'land', 'color' => '#34d399'],
            ['name' => 'Software / IT', 'slug' => 'software', 'color' => '#a78bfa'],
            ['name' => 'Data Analytics', 'slug' => 'analytics', 'color' => '#fbbf24'],
            ['name' => 'System Integration', 'slug' => 'integration', 'color' => '#fb7185'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#94a3b8'],
        ];

        foreach ($defaults as $service) {
            $exists = DB::table('services')->where('slug', $service['slug'])->exists();

            if ($exists) {
                DB::table('services')->where('slug', $service['slug'])->update([
                    'name' => $service['name'],
                    'color' => $service['color'],
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('services')->insert([
                'name' => $service['name'],
                'slug' => $service['slug'],
                'color' => $service['color'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep seeded interests — removing them would break existing check-ins.
    }
};
