<?php

namespace Tests\Feature;

use App\Models\Logement;
use App\Models\Quartier;
use App\Models\Utilisateur;
use App\Models\Ville;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UtilisateurProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_profile_and_preserves_owned_housing_when_email_changes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('utilisateurs/ancienne.png', 'ancienne-photo');

        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
            'photo' => 'utilisateurs/ancienne.png',
        ]);
        $ville = Ville::create(['id_ville' => 'ANT', 'nom_Ville' => 'Antananarivo']);
        $quartier = Quartier::create([
            'id_quartier' => 'Q-ANK',
            'nom_quartier' => 'Ankatso',
            'id_ville' => $ville->getKey(),
        ]);
        $logement = Logement::create([
            'id_utilisateur' => $utilisateur->getKey(),
            'id_quartier' => $quartier->getKey(),
            'description' => 'Studio lumineux',
            'type_logement' => 'Studio',
            'prix' => 150000,
            'date_publication' => '2026-10-14',
            'status' => 'Disponible',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $response = $this->post('/api/auth/profile', [
            '_method' => 'PATCH',
            'id_utilisateur' => 'intrus@example.mg',
            'nom' => 'Raman',
            'prenom' => 'Miora',
            'email' => ' Miora@Example.MG ',
            'telephone' => '0349876543',
            'mot_de_passe_actuel' => 'StrongPass9',
            'mot_de_passe' => 'NewStrongPass8',
            'mot_de_passe_confirmation' => 'NewStrongPass8',
            'photo_profil' => UploadedFile::fake()->createWithContent(
                'profil.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pZ8AAAAASUVORK5CYII=')
            ),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('utilisateur.id_utilisateur', 'miora@example.mg')
            ->assertJsonPath('utilisateur.nom', 'Raman')
            ->assertJsonPath('utilisateur.prenom', 'Miora')
            ->assertJsonPath('utilisateur.telephone', '0349876543')
            ->assertJsonMissingPath('utilisateur.mot_de_passe')
            ->assertJsonStructure(['csrf_token']);

        $this->assertDatabaseMissing('utilisateurs', ['id_utilisateur' => 'awa@example.mg']);
        $this->assertDatabaseHas('utilisateurs', [
            'id_utilisateur' => 'miora@example.mg',
            'nom' => 'Raman',
            'prenom' => 'Miora',
        ]);
        $this->assertDatabaseHas('logements', [
            'id' => $logement->getKey(),
            'id_utilisateur' => 'miora@example.mg',
        ]);

        $utilisateurMisAJour = Utilisateur::findOrFail('miora@example.mg');
        $this->assertTrue(Hash::check('NewStrongPass8', $utilisateurMisAJour->mot_de_passe));
        $this->assertAuthenticatedAs($utilisateurMisAJour, 'utilisateur');
        Storage::disk('public')->assertMissing('utilisateurs/ancienne.png');
        Storage::disk('public')->assertExists($utilisateurMisAJour->photo);

        $this->getJson('/api/auth/session')
            ->assertOk()
            ->assertJsonPath('utilisateur.id_utilisateur', 'miora@example.mg');
    }

    public function test_rejects_an_incorrect_current_password_without_changing_the_profile(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->patchJson('/api/auth/profile', [
            'nom' => 'Changed',
            'prenom' => 'Awa',
            'email' => 'awa@example.mg',
            'telephone' => '0341234567',
            'mot_de_passe_actuel' => 'WrongPass9',
            'mot_de_passe' => 'NewStrongPass8',
            'mot_de_passe_confirmation' => 'NewStrongPass8',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mot_de_passe_actuel' => 'Le mot de passe actuel est incorrect.',
            ]);

        $this->assertDatabaseHas('utilisateurs', [
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
        ]);
        $this->assertTrue(Hash::check('StrongPass9', $utilisateur->fresh()->mot_de_passe));
    }

    public function test_rejects_an_email_address_already_used_by_another_account(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        Utilisateur::create([
            'id_utilisateur' => 'miora@example.mg',
            'nom' => 'Raman',
            'prenom' => 'Miora',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0347654321',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->patchJson('/api/auth/profile', [
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'email' => 'MIORA@example.mg',
            'telephone' => '0341234567',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Cette adresse e-mail est déjà utilisée.');

        $this->assertDatabaseHas('utilisateurs', ['id_utilisateur' => 'awa@example.mg']);
        $this->assertDatabaseHas('utilisateurs', ['id_utilisateur' => 'miora@example.mg']);
    }

    public function test_rejects_invalid_profile_data_without_persisting_changes(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->patchJson('/api/auth/profile', [
            'nom' => 'Changed',
            'prenom' => 'Awa',
            'email' => 'awa@example.mg',
            'telephone' => '123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'telephone' => 'Le numéro de téléphone doit contenir exactement 10 chiffres, sans espace.',
            ]);

        $this->assertDatabaseHas('utilisateurs', [
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'telephone' => '0341234567',
        ]);
    }

    public function test_requires_the_current_password_before_setting_a_new_password(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->patchJson('/api/auth/profile', [
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'email' => 'awa@example.mg',
            'telephone' => '0341234567',
            'mot_de_passe' => 'NewStrongPass8',
            'mot_de_passe_confirmation' => 'NewStrongPass8',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mot_de_passe_actuel' => 'Saisissez votre mot de passe actuel pour en choisir un nouveau.',
            ]);

        $this->assertTrue(Hash::check('StrongPass9', $utilisateur->fresh()->mot_de_passe));
    }

    public function test_rejects_a_new_password_with_a_mismatched_confirmation(): void
    {
        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->patchJson('/api/auth/profile', [
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'email' => 'awa@example.mg',
            'telephone' => '0341234567',
            'mot_de_passe_actuel' => 'StrongPass9',
            'mot_de_passe' => 'NewStrongPass8',
            'mot_de_passe_confirmation' => 'DifferentPass8',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mot_de_passe' => 'La confirmation du mot de passe ne correspond pas.',
            ]);

        $this->assertTrue(Hash::check('StrongPass9', $utilisateur->fresh()->mot_de_passe));
    }

    public function test_rejects_a_non_image_profile_upload(): void
    {
        Storage::fake('public');

        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'awa@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'mot_de_passe' => 'StrongPass9',
            'telephone' => '0341234567',
        ]);
        $this->actingAs($utilisateur, 'utilisateur');

        $this->post('/api/auth/profile', [
            '_method' => 'PATCH',
            'nom' => 'Rabe',
            'prenom' => 'Awa',
            'email' => 'awa@example.mg',
            'telephone' => '0341234567',
            'photo_profil' => UploadedFile::fake()->create('profil.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'photo_profil' => 'La photo de profil doit être une image valide.',
            ]);

        $this->assertNull($utilisateur->fresh()->photo);
        Storage::disk('public')->assertDirectoryEmpty('utilisateurs');
    }

    public function test_returns_401_when_a_guest_attempts_to_update_a_profile(): void
    {
        $this->patchJson('/api/auth/profile', [
            'nom' => 'Raman',
            'prenom' => 'Miora',
            'email' => 'miora@example.mg',
            'telephone' => '0349876543',
        ])->assertUnauthorized();
    }
}
