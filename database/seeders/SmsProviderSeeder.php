<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SmsProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'sms.to',
                'doc_url' => 'https://github.com/intergo/sms.to-php',
                'is_active' => 1,
                'priority' => 0,
                'created_at' => now(),
            ],
            [
                'name' => 'twilio',
                'doc_url' => 'https://www.twilio.com/docs/sms',
                'is_active' => 1,
                'priority' => 1,
                'created_at' => now(),
            ],
            [
                'name' => 'infobip',
                'doc_url' => 'https://www.infobip.com/docs/sms',
                'is_active' => 1,
                'priority' => 2,
                'created_at' => now(),
            ],
        ];

        foreach ($providers as $p) {
            DB::table('sms_providers')->updateOrInsert(
                ['name' => $p['name']],
                $p
            );
        }
    }
}
