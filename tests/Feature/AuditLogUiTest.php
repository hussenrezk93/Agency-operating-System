<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real Blade replacement for the prototype-only audit-log.html screen — same shared
 * layout as every other admin page, real audit_logs data, BRD §19 (Admin only, read-only).
 */
class AuditLogUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->role(RoleCode::Admin)->create();
    }

    public function test_the_list_renders_with_real_entries_and_the_real_layout(): void
    {
        app(AuditService::class)->log(
            action: 'department.created',
            entityType: 'department',
            entityId: 1,
            after: ['name' => 'Marketing'],
            actorId: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)->get('/audit-log');

        $response->assertOk()->assertViewIs('audit-log.index');
        $response->assertSee('department.created');
        $response->assertSee($this->admin->full_name);
        // The same shared sidebar every other admin page uses, not a disconnected copy.
        $response->assertSee(__('agencyos.roles.admin'));
    }

    public function test_a_system_entry_shows_no_actor(): void
    {
        app(AuditService::class)->log(action: 'agencyos.scheduled_job_ran', entityType: 'system');

        $this->actingAs($this->admin)->get('/audit-log')
            ->assertOk()
            ->assertSee(__('agencyos.audit_log.system_actor'));
    }

    /**
     * The actor/action <select> options intentionally list every distinct value
     * regardless of the current filter (so switching filters is possible), so "does the
     * unfiltered actor's action appear anywhere on the page" isn't the right check —
     * it legitimately does, once, as a dropdown option. This checks the TABLE narrows.
     */
    public function test_the_actor_filter_narrows_the_list(): void
    {
        $other = User::factory()->role(RoleCode::Admin)->create();
        app(AuditService::class)->log('a.one', 'x', actorId: $this->admin->id);
        app(AuditService::class)->log('b.two', 'x', actorId: $other->id);

        $response = $this->actingAs($this->admin)->get('/audit-log?actor_id='.$this->admin->id);

        // The action is only ever printed inside a <span class="mono small"> in a table
        // row — the <select> option renders it as both a value and a label instead, so
        // this substring is unambiguously "is this action shown as a row, or only as a
        // filter option" regardless of how the dropdown happens to render its options.
        $response->assertSee('>a.one</span>', false);
        $response->assertDontSee('>b.two</span>', false);
    }

    public function test_export_returns_a_csv_of_the_filtered_rows(): void
    {
        app(AuditService::class)->log('department.created', 'department', actorId: $this->admin->id);

        $response = $this->actingAs($this->admin)->get('/audit-log/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('department.created', $response->getContent());
    }

    public function test_a_manager_cannot_reach_the_audit_log(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($manager)->get('/audit-log')->assertForbidden();
        $this->actingAs($manager)->get('/audit-log/export')->assertForbidden();
    }

    public function test_the_sidebar_no_longer_links_to_the_prototype_audit_log(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertDontSee('audit-log.html');
        $response->assertSee(route('audit-log.index'), false);
    }
}
