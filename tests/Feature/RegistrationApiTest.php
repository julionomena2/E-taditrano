<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_creates_account_without_email_verification(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/register', [
            'email' => 'AWA@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'telephone' => '0341234567',
            'mot_de_passe' => 'StrongPass9',
            'mot_de_passe_confirmation' => 'StrongPass9',
            'photo_profil' => UploadedFile::fake()->createWithContent(
                'profil.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pZ8AAAAASUVORK5CYII=')
            ),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('utilisateur.id_utilisateur', 'awa@example.mg')
            ->assertJsonPath('utilisateur.nom', 'Rabe')
            ->assertJsonPath('utilisateur.prenom', 'Awa')
            ->assertJsonMissingPath('utilisateur.mot_de_passe');

        $utilisateur = Utilisateur::findOrFail('awa@example.mg');
        $this->assertTrue(Hash::check('StrongPass9', $utilisateur->mot_de_passe));
        $this->assertNotNull($utilisateur->photo);
        Storage::disk('public')->assertExists($utilisateur->photo);
        $this->assertAuthenticatedAs($utilisateur, 'utilisateur');
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'email' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'telephone' => '0341234567',
            'mot_de_passe' => 'StrongPass9',
            'mot_de_passe_confirmation' => 'DifferentPass9',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mot_de_passe' => 'La confirmation du mot de passe ne correspond pas.',
            ]);

        $this->assertDatabaseMissing('utilisateurs', ['id_utilisateur' => 'awa@example.mg']);
    }

    public function test_registration_rejects_an_existing_email_address(): void
    {
        Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'ExistingPass9',
            'telephone' => '0341234567',
        ]);

        $this->postJson('/api/register', [
            'email' => 'AWA@example.mg',
            'nom' => 'Another',
            'prenom' => 'User',
            'telephone' => '0349876543',
            'mot_de_passe' => 'StrongPass9',
            'mot_de_passe_confirmation' => 'StrongPass9',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Cette adresse e-mail est déjà utilisée.');

        $this->assertDatabaseCount('utilisateurs', 1);
    }

    public function test_email_verification_routes_are_not_available(): void
    {
        $this->postJson('/api/email/verification-code', ['email' => 'awa@example.mg'])->assertNotFound();
        $this->postJson('/api/email/verify', ['email' => 'awa@example.mg', 'code' => '123456'])->assertNotFound();
    }
}
