<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            // ── LUZON (METRO MANILA) ──
            ['name' => 'LBC SM North EDSA', 'courier' => 'LBC', 'lat' => 14.6567, 'lng' => 121.0294, 'province' => 'Metro Manila', 'city' => 'QC'],
            ['name' => 'J&T Cubao Center', 'courier' => 'J&T', 'lat' => 14.6178, 'lng' => 121.0503, 'province' => 'Metro Manila', 'city' => 'QC'],
            ['name' => 'LBC Makati Cinema Square', 'courier' => 'LBC', 'lat' => 14.5516, 'lng' => 121.0135, 'province' => 'Metro Manila', 'city' => 'Makati'],
            ['name' => 'J&T Makati Hub', 'courier' => 'J&T', 'lat' => 14.5547, 'lng' => 121.0244, 'province' => 'Metro Manila', 'city' => 'Makati'],
            ['name' => 'LBC Manila Port Area', 'courier' => 'LBC', 'lat' => 14.5995, 'lng' => 120.9842, 'province' => 'Metro Manila', 'city' => 'Manila'],
            
            // ── VISAYAS (CEBU) ──
            ['name' => 'LBC Cebu IT Park', 'courier' => 'LBC', 'lat' => 10.3283, 'lng' => 123.9056, 'province' => 'Cebu', 'city' => 'Cebu City'],
            ['name' => 'J&T Cebu Mandaue', 'courier' => 'J&T', 'lat' => 10.3340, 'lng' => 123.9350, 'province' => 'Cebu', 'city' => 'Mandaue'],
            ['name' => 'LBC SM City Cebu', 'courier' => 'LBC', 'lat' => 10.3117, 'lng' => 123.9183, 'province' => 'Cebu', 'city' => 'Cebu City'],
            ['name' => 'J&T Cebu Talisay', 'courier' => 'J&T', 'lat' => 10.2588, 'lng' => 123.8392, 'province' => 'Cebu', 'city' => 'Talisay'],

            // ── MINDANAO (DAVAO) ──
            ['name' => 'LBC Davao Abreeza', 'courier' => 'LBC', 'lat' => 7.0913, 'lng' => 125.6127, 'province' => 'Davao del Sur', 'city' => 'Davao City'],
            ['name' => 'J&T Davao Ecoland', 'courier' => 'J&T', 'lat' => 7.0478, 'lng' => 125.5960, 'province' => 'Davao del Sur', 'city' => 'Davao City'],
            ['name' => 'LBC SM City Davao', 'courier' => 'LBC', 'lat' => 7.0456, 'lng' => 125.5892, 'province' => 'Davao del Sur', 'city' => 'Davao City'],
            
            // ── MINDANAO (CAGAYAN DE ORO) ──
            ['name' => 'LBC Limketkai CDO', 'courier' => 'LBC', 'lat' => 8.4842, 'lng' => 124.6548, 'province' => 'Misamis Oriental', 'city' => 'CDO'],
            ['name' => 'J&T CDO Bulua', 'courier' => 'J&T', 'lat' => 8.4983, 'lng' => 124.6125, 'province' => 'Misamis Oriental', 'city' => 'CDO'],
            ['name' => 'LBC Centrio Mall CDO', 'courier' => 'LBC', 'lat' => 8.4875, 'lng' => 124.6492, 'province' => 'Misamis Oriental', 'city' => 'CDO'],
            ['name' => 'J&T CDO Carmen', 'courier' => 'J&T', 'lat' => 8.4812, 'lng' => 124.6321, 'province' => 'Misamis Oriental', 'city' => 'CDO'],
            
            // ── MINDANAO (BUTUAN) ──
            ['name' => 'LBC Butuan Main', 'courier' => 'LBC', 'lat' => 8.9475, 'lng' => 125.5406, 'province' => 'Agusan del Norte', 'city' => 'Butuan'],
            ['name' => 'J&T Butuan Hub', 'courier' => 'J&T', 'lat' => 8.9510, 'lng' => 125.5280, 'province' => 'Agusan del Norte', 'city' => 'Butuan'],
            ['name' => 'LBC Robinsons Butuan', 'courier' => 'LBC', 'lat' => 8.9412, 'lng' => 125.5234, 'province' => 'Agusan del Norte', 'city' => 'Butuan'],

            // ── OTHER REGIONS ──
            ['name' => 'LBC SM Baguio', 'courier' => 'LBC', 'lat' => 16.4116, 'lng' => 120.6000, 'province' => 'Benguet', 'city' => 'Baguio'],
            ['name' => 'J&T Baguio Center', 'courier' => 'J&T', 'lat' => 16.4158, 'lng' => 120.5960, 'province' => 'Benguet', 'city' => 'Baguio'],
            ['name' => 'LBC Naga City', 'courier' => 'LBC', 'lat' => 13.6218, 'lng' => 123.1842, 'province' => 'Camarines Sur', 'city' => 'Naga'],
            ['name' => 'J&T General Santos', 'courier' => 'J&T', 'lat' => 6.1134, 'lng' => 125.1719, 'province' => 'South Cotabato', 'city' => 'GenSan'],
            ['name' => 'LBC Zamboanga Hub', 'courier' => 'LBC', 'lat' => 6.9103, 'lng' => 122.0792, 'province' => 'Zamboanga del Sur', 'city' => 'Zamboanga'],
        ];

        foreach ($branches as $branch) {
            \App\Models\Branch::create($branch);
        }
    }
}
