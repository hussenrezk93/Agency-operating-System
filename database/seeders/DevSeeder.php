<?php

namespace Database\Seeders;

use App\Enums\ActivationState;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * DEVELOPMENT ONLY — refuses to run in production.
 *
 * Seeds exactly the two real accounts this deployment uses. No demo/mock users, clients,
 * projects, tasks, or chat messages — this seeder is idempotent (safe to rerun) and never
 * creates anything beyond these two people and the one department Leila leads.
 *
 * Shared password for both, documented and non-production: Demo123!
 *   manager  — Admin
 *   leila.mansour  — Team Leader, Marketing
 */
class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo123!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(RoleSeeder::class);
        $role = fn (RoleCode $c) => Role::where('code', $c->value)->firstOrFail()->id;

        $marketing = Department::firstOrCreate(['name' => 'Marketing'], ['is_active' => true]);

        $mk = fn (array $attrs) => User::updateOrCreate(
            ['username' => $attrs['username']],
            $attrs + ['password_hash' => Hash::make(self::DEMO_PASSWORD), 'status' => 'active',
                'must_change_password' => false, 'email_verified_at' => now()],
        );

        $admin = $mk(['username' => 'manager', 'full_name' => 'Rana Toulan',
            'role_id' => $role(RoleCode::Admin), 'personal_email' => 'manager@example.com']);

        $leader = $mk(['username' => 'leila.mansour', 'full_name' => 'Leila Mansour',
            'role_id' => $role(RoleCode::TeamLeader), 'department_id' => $marketing->id,
            'personal_email' => 'leila.mansour@example.com']);

        DepartmentLeadershipAssignment::firstOrCreate(
            ['department_id' => $marketing->id, 'assignment_type' => 'primary', 'is_active' => true],
            [
                'user_id' => $leader->id,
                'start_date' => now()->toDateString(),
                'activation_state' => ActivationState::Active->value,
                'assigned_by' => $admin->id,
            ],
        );
    }
}
