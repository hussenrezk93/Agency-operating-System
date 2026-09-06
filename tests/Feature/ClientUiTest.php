<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 5-style real screens for clients, wired to the existing ClientService/ClientPolicy. */
class ClientUiTest extends TestCase
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

    public function test_the_client_list_renders_for_manager_and_tl_but_not_employee(): void
    {
        Client::factory()->create();

        $this->actingAs($this->manager)->get('/clients')->assertOk()->assertViewIs('clients.index');
        $this->actingAs($this->tl)->get('/clients')->assertOk()->assertViewIs('clients.index');
        $this->actingAs($this->employee)->get('/clients')->assertForbidden();
    }

    /**
     * A TL passes the coarse `role:manager,tl` route gate onto the list (same as the
     * test above) but ClientPolicy::update/deactivate/reactivate are Manager-only — the
     * list must not offer buttons for actions that only lead to a 403 when clicked.
     */
    public function test_the_list_only_offers_edit_and_deactivate_to_a_manager(): void
    {
        $client = Client::factory()->create();

        $managerView = $this->actingAs($this->manager)->get('/clients');
        $managerView->assertSee(__('agencyos.clients.index.edit'));
        $managerView->assertSee(__('agencyos.clients.index.deactivate'));

        $tlView = $this->actingAs($this->tl)->get('/clients');
        $tlView->assertDontSee(__('agencyos.clients.index.edit'));
        $tlView->assertDontSee(__('agencyos.clients.index.deactivate'));
    }

    public function test_the_create_form_renders_and_a_classic_submit_redirects_to_the_list(): void
    {
        $this->actingAs($this->manager)->get('/clients/create')->assertOk()->assertViewIs('clients.create');

        $response = $this->actingAs($this->tl)->post('/clients', [
            'name' => 'Classic Form Client',
            'phone' => '+201000000000',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Classic Form Client']);
    }

    /** The phone field strips non-digit characters live, not just on submit. */
    public function test_the_create_forms_phone_field_filters_out_letters_as_you_type(): void
    {
        $response = $this->actingAs($this->manager)->get('/clients/create');

        $response->assertOk();
        $response->assertSee('oninput="this.value=this.value.replace(/[^0-9+\s()\-]/g,\'\')"', false);
    }

    public function test_an_employee_cannot_open_the_create_form(): void
    {
        $this->actingAs($this->employee)->get('/clients/create')->assertForbidden();
    }

    public function test_the_edit_form_renders_for_manager_only_and_a_classic_update_redirects(): void
    {
        $client = Client::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->manager)->get(route('clients.edit-form', $client))
            ->assertOk()->assertViewIs('clients.edit');
        $this->actingAs($this->tl)->get(route('clients.edit-form', $client))->assertForbidden();

        $response = $this->actingAs($this->manager)->patch(route('clients.update', $client), [
            'name' => 'New Name',
            'phone' => $client->phone,
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertSame('New Name', $client->fresh()->name);
    }

    public function test_classic_deactivate_and_reactivate_redirect_to_the_list(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager)->post(route('clients.deactivate', $client))
            ->assertRedirect(route('clients.index'));
        $this->assertSame('inactive', $client->fresh()->status->value);

        $this->actingAs($this->manager)->post(route('clients.reactivate', $client))
            ->assertRedirect(route('clients.index'));
        $this->assertSame('active', $client->fresh()->status->value);
    }
}
