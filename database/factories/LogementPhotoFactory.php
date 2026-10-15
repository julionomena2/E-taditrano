<?php

namespace Database\Factories;

use App\Models\Logement;
use App\Models\LogementPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogementPhoto>
 */
class LogementPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'logement_id' => null,
            'chemin' => fake()->filePath(),
            'ordre' => 0,
        ];
    }

    public function pourLogement(Logement $logement): static
    {
        return $this->state(fn (): array => [
            'logement_id' => $logement->getKey(),
        ]);
    }
}
