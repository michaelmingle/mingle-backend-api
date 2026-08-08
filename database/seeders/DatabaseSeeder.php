<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe reference data only. Demo users, events and connections live
 * in DemoSeeder and are never pulled in here -- run `db:seed --class=DemoSeeder`
 * explicitly (it refuses to run in production).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SkillSeeder::class,
            InterestSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
