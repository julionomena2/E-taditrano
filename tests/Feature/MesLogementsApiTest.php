<?php

namespace Tests\Feature;

use App\Models\Logement;
use App\Models\LogementPhoto;
use App\Models\Quartier;
use App\Models\Utilisateur;
use App\Models\Ville;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MesLogementsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listing_requires_an_authenticated_user(): void
    {
        $this->getJson('/api/mes-logements')->assertUnauthorized();
        $this->postJson('/api/mes-logements', [])->assertUnauthorized();
    }

    public function test_authenticated_user_can_publish_a_listing_with_photos_and_coordinates(): void
    {
        Storage::fake('public');
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $quartier = $this->creerQuartier();
        $this->travelTo(Carbon::parse('2025-03-04 12:00:00'));

        $response = $this->actingAs($proprietaire, 'utilisateur')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/mes-logements', [
                ...$this->donneesAnnonce($quartier),
                'photos' => [$this->fausseImage('salon.jpg')],
            ]);

        $response->assertCreated()
            ->assertJsonPath('id_utilisateur', 'proprietaire@example.mg')
            ->assertJsonPath('id_quartier', 'Q-TEST')
            ->assertJsonPath('date_publication', '2025-03-04T00:00:00.000000Z')
            ->assertJsonPath('status', 'Disponible')
            ->assertJsonPath('latitude', '-18.9000000')
            ->assertJsonPath('longitude', '47.5000000')
            ->assertJsonCount(1, 'photos');

        $logement = Logement::query()->sole();
        $photo = $logement->photos()->sole();
        $this->assertSame($proprietaire->getAuthIdentifier(), $logement->id_utilisateur);
        $this->assertSame($photo->chemin, $logement->photo);
        Storage::disk('public')->assertExists($photo->chemin);
    }

    public function test_user_can_only_list_their_own_announcements(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $autreProprietaire = $this->creerUtilisateur('autre@example.mg');
        $quartier = $this->creerQuartier();
        $annoncePersonnelle = $this->creerLogement($proprietaire, $quartier);
        $annonceAutre = $this->creerLogement($autreProprietaire, $quartier);

        $this->actingAs($proprietaire, 'utilisateur')
            ->getJson('/api/mes-logements')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $annoncePersonnelle->id)
            ->assertJsonPath('0.quartier.ville.nom_Ville', 'Antananarivo')
            ->assertJsonMissing(['id' => $annonceAutre->id]);
    }

    public function test_owner_can_replace_photos_and_update_all_listing_fields(): void
    {
        Storage::fake('public');
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $quartier = $this->creerQuartier();
        $annonce = $this->creerLogement($proprietaire, $quartier);
        $anciennePhoto = LogementPhoto::query()->create([
            'logement_id' => $annonce->id,
            'chemin' => 'logements/1/ancienne.jpg',
            'ordre' => 0,
        ]);
        Storage::disk('public')->put($anciennePhoto->chemin, 'ancienne image');

        $this->actingAs($proprietaire, 'utilisateur')
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/mes-logements/{$annonce->id}", [
                ...$this->donneesAnnonce($quartier, [
                    'description' => 'Appartement rénové',
                    'type_logement' => 'Appartement',
                    'prix' => 300000,
                    'latitude' => -18.91,
                    'longitude' => 47.51,
                ]),
                '_method' => 'PATCH',
                'photos_a_supprimer' => [$anciennePhoto->id],
                'photos' => [$this->fausseImage('chambre.png')],
            ])
            ->assertOk()
            ->assertJsonPath('description', 'Appartement rénové')
            ->assertJsonPath('type_logement', 'Appartement')
            ->assertJsonPath('prix', 300000)
            ->assertJsonPath('latitude', '-18.9100000')
            ->assertJsonPath('longitude', '47.5100000')
            ->assertJsonCount(1, 'photos');

        $annonce->refresh();
        $nouvellePhoto = $annonce->photos()->sole();
        $this->assertSame('Appartement rénové', $annonce->description);
        $this->assertSame(300000, $annonce->prix);
        $this->assertDatabaseMissing('logement_photos', ['id' => $anciennePhoto->id]);
        Storage::disk('public')->assertMissing($anciennePhoto->chemin);
        Storage::disk('public')->assertExists($nouvellePhoto->chemin);
    }

    public function test_user_cannot_modify_or_delete_another_owners_listing(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $autreProprietaire = $this->creerUtilisateur('autre@example.mg');
        $quartier = $this->creerQuartier();
        $annonce = $this->creerLogement($autreProprietaire, $quartier);

        $this->actingAs($proprietaire, 'utilisateur')
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/mes-logements/{$annonce->id}", [
                ...$this->donneesAnnonce($quartier),
                '_method' => 'PATCH',
            ])
            ->assertNotFound();

        $this->deleteJson("/api/mes-logements/{$annonce->id}")
            ->assertNotFound();
        $this->assertModelExists($annonce);
    }

    public function test_listing_requires_valid_coordinates_and_rejects_more_than_ten_photos(): void
    {
        Storage::fake('public');
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $quartier = $this->creerQuartier();
        $donneesInvalides = $this->donneesAnnonce($quartier, ['latitude' => 91]);

        $this->actingAs($proprietaire, 'utilisateur')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/mes-logements', $donneesInvalides)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);

        $this->post('/api/mes-logements', [
            ...$this->donneesAnnonce($quartier),
            'photos' => array_map(
                fn (int $numero): UploadedFile => $this->fausseImage("logement-{$numero}.jpg"),
                range(1, 11),
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos']);

        $this->assertDatabaseCount('logements', 0);
        $this->assertDatabaseCount('logement_photos', 0);
    }

    public function test_owner_can_delete_their_listing_and_its_photos(): void
    {
        Storage::fake('public');
        $proprietaire = $this->creerUtilisateur('proprietaire@example.mg');
        $quartier = $this->creerQuartier();
        $annonce = $this->creerLogement($proprietaire, $quartier);
        $photo = LogementPhoto::query()->create([
            'logement_id' => $annonce->id,
            'chemin' => 'logements/1/photo.jpg',
            'ordre' => 0,
        ]);
        Storage::disk('public')->put($photo->chemin, 'photo');

        $this->actingAs($proprietaire, 'utilisateur')
            ->deleteJson("/api/mes-logements/{$annonce->id}")
            ->assertNoContent();

        $this->assertModelMissing($annonce);
        $this->assertDatabaseMissing('logement_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->chemin);
    }

    private function creerUtilisateur(string $email): Utilisateur
    {
        return Utilisateur::query()->create([
            'id_utilisateur' => $email,
            'nom' => 'Rabe',
            'prenom' => 'Miora',
            'mot_de_passe' => 'mot-de-passe-secret',
            'telephone' => '0341234567',
        ]);
    }

    private function fausseImage(string $nom): UploadedFile
    {
        return UploadedFile::fake()->create($nom, 128, 'image/jpeg');
    }

    private function creerQuartier(): Quartier
    {
        $ville = Ville::query()->create([
            'id_ville' => 'ANT',
            'nom_Ville' => 'Antananarivo',
        ]);

        return Quartier::query()->create([
            'id_quartier' => 'Q-TEST',
            'nom_quartier' => 'Ankatso',
            'id_ville' => $ville->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donneesAnnonce(Quartier $quartier, array $remplacements = []): array
    {
        return array_replace([
            'id_quartier' => $quartier->getKey(),
            'description' => 'Logement lumineux et bien situé.',
            'type_logement' => 'Studio',
            'prix' => 200000,
            'latitude' => -18.9,
            'longitude' => 47.5,
        ], $remplacements);
    }

    private function creerLogement(Utilisateur $proprietaire, Quartier $quartier): Logement
    {
        return Logement::query()->create([
            'id_utilisateur' => $proprietaire->getAuthIdentifier(),
            'id_quartier' => $quartier->getKey(),
            'description' => 'Studio lumineux',
            'type_logement' => 'Studio',
            'prix' => 200000,
            'date_publication' => '2025-01-01',
            'status' => 'Disponible',
            'latitude' => -18.9,
            'longitude' => 47.5,
        ]);
    }
}
