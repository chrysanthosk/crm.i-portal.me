<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;
use App\Models\VatType;

class ServiceLookupsSeeder extends Seeder
{
    public function run(): void
    {
        // Categories (edit as you like)
        $categories = [
            'Physiotherapy',
            'Massage',
            'Rehabilitation',
            'Wellness',
        ];

        foreach ($categories as $name) {
            ServiceCategory::firstOrCreate(['name' => $name], ['description' => null]);
        }

        // VAT Types (common examples)
        $vatTypes = [
            ['name' => 'VAT 0%',  'vat_percent' => 0.00],
            ['name' => 'VAT 5%',  'vat_percent' => 5.00],
            ['name' => 'VAT 9%',  'vat_percent' => 9.00],
            ['name' => 'VAT 19%', 'vat_percent' => 19.00],
        ];

        foreach ($vatTypes as $v) {
            VatType::firstOrCreate(['name' => $v['name']], ['vat_percent' => $v['vat_percent']]);
        }
    }
}
