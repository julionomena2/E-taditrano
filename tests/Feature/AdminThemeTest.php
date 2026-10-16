<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminThemeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_login_uses_the_frontend_theme_and_password_visibility_control(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('font-family: "Aptos", "Segoe UI", sans-serif', false)
            ->assertSee('--primary-dark: #123b2e', false)
            ->assertSee('data-toggle-password="mot_de_passe"', false)
            ->assertSee('aria-label="Afficher le mot de passe"', false)
            ->assertSee("input.type = visible ? 'text' : 'password'", false);
    }

    public function test_admin_pages_use_the_frontend_theme(): void
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.utilisateurs'));

        $response->assertOk()
            ->assertSee('font-family: "Aptos", "Segoe UI", sans-serif', false)
            ->assertSee('--primary-dark: #123b2e', false)
            ->assertSee('background: var(--bg)', false);
    }
}
