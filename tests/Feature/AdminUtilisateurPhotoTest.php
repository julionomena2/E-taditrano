<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUtilisateurPhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_user_form_renders_a_profile_photo_upload_field(): void
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.utilisateurs'));

        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('type="file" name="photo"', false);
    }

    public function test_user_list_shows_a_photo_button_and_centered_photo_dialog(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('utilisateurs/profil.png', 'photo');

        Utilisateur::create([
            'id_utilisateur' => 'U-PHOTO-VIEW',
            'nom' => 'Diallo',
            'prenom' => 'Awa',
            'mot_de_passe' => 'secret123',
            'telephone' => '0123456789',
            'photo' => 'utilisateurs/profil.png',
        ]);

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.utilisateurs'));

        $response->assertSee('data-profile-photo=', false);
        $response->assertSee('>Voir la photo</button>', false);
        $response->assertSee('<dialog id="profile-photo-dialog"', false);
        $response->assertSee('id="profile-photo-close"', false);
        $response->assertDontSee('class="profile-photo">', false);
    }

    public function test_it_stores_a_valid_profile_photo_when_creating_an_admin_user(): void
    {
        Storage::fake('public');
        $photo = $this->profilePhoto();

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.resource.store', ['model' => 'utilisateurs']), [
                'id_utilisateur' => 'U-PHOTO-1',
                'nom' => 'Diallo',
                'prenom' => 'Awa',
                'mot_de_passe' => 'secret123',
                'telephone' => '0123456789',
                'photo' => $photo,
            ]);

        $response->assertRedirect(route('admin.utilisateurs'));
        $utilisateur = Utilisateur::findOrFail('U-PHOTO-1');
        $this->assertNotNull($utilisateur->photo);
        Storage::disk('public')->assertExists($utilisateur->photo);
    }

    public function test_it_rejects_a_non_image_profile_photo(): void
    {
        Storage::fake('public');

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.resource.store', ['model' => 'utilisateurs']), [
                'id_utilisateur' => 'U-PHOTO-2',
                'nom' => 'Diallo',
                'prenom' => 'Awa',
                'mot_de_passe' => 'secret123',
                'telephone' => '0123456789',
                'photo' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseMissing('utilisateurs', ['id_utilisateur' => 'U-PHOTO-2']);
        $this->assertSame([], Storage::disk('public')->allFiles('utilisateurs'));
    }

    public function test_it_replaces_the_existing_profile_photo_when_updating_an_admin_user(): void
    {
        Storage::fake('public');
        $oldPhoto = 'utilisateurs/ancienne.jpg';
        Storage::disk('public')->put($oldPhoto, 'ancienne image');

        $utilisateur = Utilisateur::create([
            'id_utilisateur' => 'U-PHOTO-3',
            'nom' => 'Diallo',
            'prenom' => 'Awa',
            'mot_de_passe' => 'secret123',
            'telephone' => '0123456789',
            'photo' => $oldPhoto,
        ]);

        $response = $this->withSession(['admin_logged_in' => true])
            ->put(route('admin.resource.update', ['model' => 'utilisateurs', 'id' => $utilisateur->getKey()]), [
                'photo' => $this->profilePhoto('nouvelle.png'),
            ]);

        $response->assertRedirect(route('admin.utilisateurs'));
        $utilisateur->refresh();
        $this->assertNotSame($oldPhoto, $utilisateur->photo);
        Storage::disk('public')->assertMissing($oldPhoto);
        Storage::disk('public')->assertExists($utilisateur->photo);
    }

    private function profilePhoto(string $name = 'profil.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pZ8AAAAASUVORK5CYII=')
        );
    }
}
