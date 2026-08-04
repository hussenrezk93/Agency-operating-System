<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_full_structured_payload(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->withHeader('User-Agent', 'Agency OSTest/1.0')
            ->post('/logout');

        $log = AuditLog::where('action', 'auth.logout')->firstOrFail();

        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame('user', $log->entity_type);
        $this->assertSame($actor->id, $log->entity_id);
        $this->assertArrayHasKey('before', $log->metadata);
        $this->assertArrayHasKey('after', $log->metadata);
        $this->assertArrayHasKey('user_agent', $log->metadata);
        $this->assertNotNull($log->created_at);
    }

    public function test_it_captures_before_and_after_values(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditService::class);

        $log = $service->log(
            action: 'department.updated',
            entityType: 'department',
            entityId: 7,
            before: ['name' => 'Old'],
            after: ['name' => 'New'],
            actorId: $actor->id,
        );

        $this->assertSame(['name' => 'Old'], $log->metadata['before']);
        $this->assertSame(['name' => 'New'], $log->metadata['after']);
    }

    public function test_system_events_may_have_no_actor(): void
    {
        $log = app(AuditService::class)->log('system.boot', 'system');

        $this->assertNull($log->actor_user_id);
    }

    public function test_audit_rows_cannot_be_updated(): void
    {
        $log = app(AuditService::class)->log('system.test', 'system');

        $this->expectException(QueryException::class); // append-only trigger
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }

    public function test_audit_rows_cannot_be_deleted(): void
    {
        $log = app(AuditService::class)->log('system.test', 'system');

        $this->expectException(QueryException::class); // append-only trigger
        DB::table('audit_logs')->where('id', $log->id)->delete();
    }
}
