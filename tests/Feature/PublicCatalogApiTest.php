<?php

namespace Tests\Feature;

use App\Models\Etablissement;
use App\Models\Logement;
use App\Models\LogementPhoto;
use App\Models\Quartier;
use App\Models\Utilisateur;
use App\Models\Ville;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicCatalogApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_endpoints_return_database_records_and_filter_by_parent(): void
    {
        $ville = Ville::create(['id_ville' => 'ANT', 'nom_Ville' => 'Antananarivo']);
        $quartier = Quartier::create([
            'id_quartier' => 'Q-ANK',
            'nom_quartier' => 'Ankatso',
            'id_ville' => $ville->getKey(),
        ]);
        Etablissement::create([
            'nom_etablissement' => 'Université',
            'id_quartier' => $quartier->getKey(),
            'latitude' => -18.91,
            'longitude' => 47.55,
        ]);

        $this->getJson('/api/villes')
            ->assertOk()
            ->assertJsonPath('0.id_ville', 'ANT')
            ->assertJsonPath('0.nom_Ville', 'Antananarivo');

        $this->getJson('/api/quartiers?id_ville=ANT')
            ->assertOk()
            ->assertJsonPath('0.id_quartier', 'Q-ANK');

        $this->getJson('/api/etablissements?id_ville=ANT')
            ->assertOk()
            ->assertJsonPath('0.nom_etablissement', 'Université');
    }

    public function test_available_housing_is_filtered_by_neighborhood_without_exposing_passwords(): void
    {
        $ville = Ville::create(['id_ville' => 'ANT', 'nom_Ville' => 'Antananarivo']);
        $quartier = Quartier::create([
            'id_quartier' => 'Q-ANK',
            'nom_quartier' => 'Ankatso',
            'id_ville' => $ville->getKey(),
        ]);
        Utilisateur::create([
            'id_utilisateur' => 'owner@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Miora',
            'mot_de_passe' => 'secret123',
            'telephone' => '0341234567',
        ]);
        $logement = Logement::create([
            'id_utilisateur' => 'owner@example.mg',
            'id_quartier' => $quartier->getKey(),
            'description' => 'Studio lumineux',
            'type_logement' => 'Studio',
            'prix' => 150000,
            'date_publication' => now()->toDateString(),
            'status' => 'Disponible',
        ]);
        LogementPhoto::query()->create([
            'logement_id' => $logement->id,
            'chemin' => 'logements/1/salon.jpg',
            'ordre' => 0,
        ]);

        $response = $this->getJson('/api/logements?id_quartier=Q-ANK')
            ->assertOk()
            ->assertJsonPath('0.type_logement', 'Studio')
            ->assertJsonPath('0.quartier.nom_quartier', 'Ankatso')
            ->assertJsonPath('0.photos.0.chemin', 'logements/1/salon.jpg')
            ->assertDontSee('mot_de_passe', false);

        $response->assertJsonMissingPath('0.utilisateur');
    }

    public function test_catalog_filter_returns_validation_errors_for_unknown_city(): void
    {
        $this->getJson('/api/quartiers?id_ville=UNKNOWN')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_ville');
    }

    public function test_nearby_housing_is_limited_to_the_radius_and_sorted_by_distance(): void
    {
        $ville = Ville::create(['id_ville' => 'ANT', 'nom_Ville' => 'Antananarivo']);
        $quartier = Quartier::create([
            'id_quartier' => 'Q-ANK',
            'nom_quartier' => 'Ankatso',
            'id_ville' => $ville->getKey(),
        ]);
        Utilisateur::create([
            'id_utilisateur' => 'owner@example.mg',
            'nom' => 'Rabe',
            'prenom' => 'Miora',
            'mot_de_passe' => 'secret123',
            'telephone' => '0341234567',
        ]);
        $proche = $this->createHousingAt($quartier, 'proche', -18.9, 47.501);
        $eloigne = $this->createHousingAt($quartier, 'eloigne', -18.9, 47.51);
        $this->createHousingAt($quartier, 'hors-rayon', -18.9, 48);
        $this->createHousingAt($quartier, 'indisponible', -18.9, 47.5005, 'Loué');
        $this->createHousingAt($quartier, 'sans-coordonnees', null, null);

        $response = $this->getJson('/api/logements/proches?latitude=-18.9&longitude=47.5&rayon_km=25')
            ->assertOk()
            ->assertJsonCount(2);

        $this->assertSame($proche->id, $response->json('0.id'));
        $this->assertSame($eloigne->id, $response->json('1.id'));
        $this->assertLessThan(
            (float) $response->json('1.distance_km'),
            (float) $response->json('0.distance_km'),
        );
    }

    public function test_nearby_housing_rejects_a_radius_above_twenty_five_kilometers(): void
    {
        $this->getJson('/api/logements/proches?latitude=-18.9&longitude=47.5&rayon_km=25.1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rayon_km');
    }

    private function createHousingAt(
        Quartier $quartier,
        string $description,
        ?float $latitude,
        ?float $longitude,
        string $status = 'Disponible',
    ): Logement {
        return Logement::create([
            'id_utilisateur' => 'owner@example.mg',
            'id_quartier' => $quartier->getKey(),
            'description' => $description,
            'type_logement' => 'Studio',
            'prix' => 150000,
            'date_publication' => now()->toDateString(),
            'status' => $status,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
