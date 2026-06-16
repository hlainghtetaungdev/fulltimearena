<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\StaffAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        StaffAccount::query()->firstOrCreate(
            ['role' => 'super', 'username' => env('SUPER_USERNAME', 'admin')],
            [
                'display_name' => 'Super Admin',
                'password_hash' => Hash::make(env('SUPER_PASSWORD', 'ChangeMeNow!')),
                'active' => true,
            ],
        );

        foreach ([
            'form_open' => '1',
            'site_version' => (string) time(),
            'team_a_name' => 'Team A',
            'team_b_name' => 'Team B',
            'prize_total' => '1,000,000 Kyat',
            'prize_each' => '50,000 Kyat',
            'live_refresh_seconds' => '60',
            'score_detail_enabled' => '1',
        ] as $key => $value) {
            Setting::query()->firstOrCreate(['setting_key' => $key], ['setting_value' => $value]);
        }
    }
}
