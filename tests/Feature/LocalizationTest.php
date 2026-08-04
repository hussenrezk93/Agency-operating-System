<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'array']);
    }

    public function test_english_login_uses_ltr_direction(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            // The English copy, via the key — the direction above is the real assertion.
            ->assertSee(__('agencyos.auth.title', [], 'en'));
    }

    public function test_arabic_locale_switch_uses_rtl_and_is_persisted(): void
    {
        $response = $this->from('/login')->post('/locale', ['locale' => 'ar']);

        $response
            ->assertRedirect('/login')
            ->assertCookie('agencyos_locale', 'ar');

        $this->withSession(['locale' => 'ar'])
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            // Proves the Arabic file is actually being used, not just the RTL attribute.
            ->assertSee(__('agencyos.auth.title', [], 'ar'));
    }

    public function test_dashboard_mock_data_follows_the_selected_language(): void
    {
        $user = new User([
            'id' => 1,
            'username' => 'admin',
            'full_name' => 'Khaled Samir',
            'status' => UserStatus::Active->value,
            'must_change_password' => false,
        ]);
        $user->setRelation('role', new Role(['code' => 'admin', 'name' => 'Admin']));

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('خالد سمير')
            ->assertSee('تصميم منشورات شهر أغسطس')
            ->assertDontSee('Design August social posts');

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Khaled Samir')
            ->assertSee('Design August social posts')
            ->assertDontSee('تصميم منشورات شهر أغسطس');
    }
}
