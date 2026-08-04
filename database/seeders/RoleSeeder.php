<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Idempotent — safe for every environment including production. */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleCode::cases() as $role) {
            Role::firstOrCreate(['code' => $role->value], ['name' => $role->label()]);
        }
    }
}
