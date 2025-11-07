<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReferralSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the referral_sources table with default values
     */
    public function run(): void
    {
        $sources = [
            ['id' => 1, 'name' => 'Google Ads'],
            ['id' => 2, 'name' => 'Facebook'],
            ['id' => 3, 'name' => 'LinkedIn'],
            ['id' => 4, 'name' => 'Friend/Colleague'],
            ['id' => 5, 'name' => 'Email Campaign'],
            ['id' => 6, 'name' => 'Website Contact Form'],
            ['id' => 7, 'name' => 'Trade Show'],
            ['id' => 8, 'name' => 'Direct Visit'],
            ['id' => 9, 'name' => 'Customer Referral'],
            ['id' => 10, 'name' => 'Other'],
        ];

        $now = Carbon::now();

        foreach ($sources as &$source) {
            $source['created_at'] = $now;
            $source['updated_at'] = $now;
        }

        // Use upsert to avoid duplicates
        DB::table('referral_sources')->upsert(
            $sources,
            ['id'], // unique key
            ['name', 'updated_at'] // columns to update if exists
        );
    }
}
