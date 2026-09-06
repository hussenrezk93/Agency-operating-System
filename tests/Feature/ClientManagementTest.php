<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BRD §7.1/§15 — clients are Manager/TL-created, Manager-only to edit or deactivate. */
class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $tl;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $this->employee = User::factory()->role(RoleCode::Employee)->create();
    }

    public function test_manager_creates_a_client(): void
    {
        $this->actingAs($this->manager)->postJson('/clients', [
            'name' => 'Acme Co',
            'phone' => '+201000000000',
        ])->assertCreated();

        $this->assertDatabaseHas('clients', ['name' => 'Acme Co', 'status' => 'active']);
    }

    public function test_team_leader_creates_a_client(): void
    {
        $this->actingAs($this->tl)->postJson('/clients', [
            'name' => 'Beta Ltd',
            'phone' => '+201000000001',
        ])->assertCreated();

        $this->assertDatabaseHas('clients', ['name' => 'Beta Ltd']);
    }

    public function test_employee_cannot_create_a_client(): void
    {
        $this->actingAs($this->employee)->postJson('/clients', [
            'name' => 'Blocked Co',
            'phone' => '+201000000002',
        ])->assertForbidden();

        $this->assertDatabaseMissing('clients', ['name' => 'Blocked Co']);
    }

    public function test_a_client_without_a_phone_is_rejected(): void
    {
        $this->actingAs($this->manager)->postJson('/clients', ['name' => 'No Phone Co'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_a_phone_number_containing_letters_is_rejected(): void
    {
        $this->actingAs($this->manager)->postJson('/clients', [
            'name' => 'Letters Co',
            'phone' => '0100abc1234',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('clients', ['name' => 'Letters Co']);
    }

    /** Common real-world formats — spaces, hyphens, parentheses — must keep working. */
    public function test_a_formatted_phone_number_is_accepted(): void
    {
        $this->actingAs($this->manager)->postJson('/clients', [
            'name' => 'Formatted Co',
            'phone' => '(010) 123-4567',
        ])->assertCreated();

        $this->assertDatabaseHas('clients', ['name' => 'Formatted Co']);
    }

    public function test_manager_updates_and_deactivates_a_client(): void
    {
        $client = Client::factory()->create(['created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->patchJson("/clients/{$client->id}", ['name' => 'Renamed Co'])
            ->assertOk();
        $this->assertSame('Renamed Co', $client->fresh()->name);

        $this->actingAs($this->manager)->postJson("/clients/{$client->id}/deactivate")->assertOk();
        $this->assertSame('inactive', $client->fresh()->status->value);

        $this->actingAs($this->manager)->postJson("/clients/{$client->id}/reactivate")->assertOk();
        $this->assertSame('active', $client->fresh()->status->value);
    }

    public function test_updating_a_clients_phone_to_contain_letters_is_rejected(): void
    {
        $client = Client::factory()->create(['created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->patchJson("/clients/{$client->id}", ['phone' => 'call-me-maybe'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_team_leader_cannot_edit_or_deactivate_a_client(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->tl)
            ->patchJson("/clients/{$client->id}", ['name' => 'Should Fail'])
            ->assertForbidden();

        $this->actingAs($this->tl)
            ->postJson("/clients/{$client->id}/deactivate")
            ->assertForbidden();
    }

    public function test_a_guest_cannot_reach_client_administration(): void
    {
        $this->get('/clients')->assertRedirect(route('login'));
    }
}
