<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Cash', 'Card'] as $name) {
            DB::table('payment_methods')->updateOrInsert(['name' => $name], ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
