<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BRD §19 — the audit log belongs to the Admin alone and is append-only. */
class AuditLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_the_admin_may_view_the_audit_log(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->assertTrue($admin->can('viewAny', AuditLog::class));
        $this->assertFalse($manager->can('viewAny', AuditLog::class));
        $this->assertFalse($tl->can('viewAny', AuditLog::class));
        $this->assertFalse($employee->can('viewAny', AuditLog::class));
    }

    public function test_nobody_may_create_update_or_delete_audit_entries(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $log = app(AuditService::class)->log('system.test', 'system');

        $this->assertFalse($admin->can('create', AuditLog::class));
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', $log));
    }

    public function test_audit_metadata_never_contains_secrets(): void
    {
        User::factory()->create(['username' => 'employee', 'personal_email' => 'e@dev.local']);

        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!']);
        $this->post('/logout');

        foreach (AuditLog::all() as $log) {
            $blob = json_encode($log->metadata);
            foreach (['Demo123!', '$2y$', 'token', 'smtp', 'session'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $blob);
            }
        }
    }

    public function test_only_the_admin_may_export(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->assertTrue($admin->can('export', AuditLog::class));
        $this->assertFalse($manager->can('export', AuditLog::class));
    }
}
