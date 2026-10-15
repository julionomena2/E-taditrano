<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UtilisateurAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_the_session_user_and_csrf_token_after_valid_login(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => ' AWA@EXAMPLE.MG ',
            'mot_de_passe' => 'StrongPass9',
        ]);

        $response->assertOk()
            ->assertJsonPath('utilisateur.id_utilisateur', $utilisateur->getKey())
            ->assertJsonMissingPath('utilisateur.mot_de_passe')
            ->assertJsonStructure(['csrf_token']);

        $this->assertAuthenticatedAs($utilisateur, 'utilisateur');

        $this->getJson('/api/auth/session')
            ->assertOk()
            ->assertJsonPath('utilisateur.id_utilisateur', $utilisateur->getKey())
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_database_session_stores_the_email_user_id_as_a_string(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'julionomena2@gmail.com',
            'nom' => 'Nomena',
            'prenom' => 'Julio',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        config(['session.driver' => 'database']);
        config(['auth.defaults.guard' => 'utilisateur']);

        $this->postJson('/api/auth/login', [
            'email' => 'julionomena2@gmail.com',
            'mot_de_passe' => 'StrongPass9',
        ])->assertOk();

        $this->getJson('/api/messagerie/conversations')
            ->assertOk();

        $this->assertDatabaseHas('sessions', [
            'user_id' => 'julionomena2@gmail.com',
        ]);
    }

    public function test_returns_422_with_a_french_message_when_email_is_invalid(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'adresse-invalide',
            'mot_de_passe' => 'StrongPass9',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'Saisissez une adresse e-mail valide.',
            ]);

        $this->assertGuest('utilisateur');
    }

    public function test_returns_401_without_authenticating_when_password_is_incorrect(): void
    {
        Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'awa@example.mg',
            'mot_de_passe' => 'WrongPass9',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Adresse e-mail ou mot de passe incorrect.');

        $this->assertGuest('utilisateur');
    }

    public function test_returns_422_when_login_credentials_are_missing(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'L’adresse e-mail est obligatoire.',
                'mot_de_passe' => 'Le mot de passe est obligatoire.',
            ]);

        $this->assertGuest('utilisateur');
    }

    public function test_restores_a_guest_session_with_a_csrf_token(): void
    {
        $this->getJson('/api/auth/session')
            ->assertOk()
            ->assertJsonPath('utilisateur', null)
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'awa@example.mg',
            'mot_de_passe' => 'StrongPass9',
        ])->assertOk();

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('utilisateur', null)
            ->assertJsonStructure(['csrf_token']);

        $this->assertGuest('utilisateur');
    }
}
