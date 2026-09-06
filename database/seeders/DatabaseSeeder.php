<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // A fresh install lands on a populated workspace rather than empty dashboards.
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
