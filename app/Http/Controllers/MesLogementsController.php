<?php

namespace App\Http\Controllers;

use App\Models\Logement;
use App\Models\LogementPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MesLogementsController extends Controller
{
    private const TYPES_LOGEMENT = ['Studio', 'Chambre', 'Appartement', 'Maison', 'Autre'];

    private const NOMBRE_MAXIMUM_PHOTOS = 10;

    public function index(): JsonResponse
    {
        $logements = Logement::query()
            ->where('id_utilisateur', Auth::guard('utilisateur')->id())
            ->with(['quartier.ville', 'photos'])
            ->latest('date_publication')
            ->latest('id')
            ->get()
            ->map(fn (Logement $logement): array => $this->donneesLogement($logement));

        return response()->json($logements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglesAnnonce());
        $utilisateur = Auth::guard('utilisateur')->user();
        $cheminsStockes = [];

        try {
            $logement = DB::transaction(function () use ($request, $validated, $utilisateur, &$cheminsStockes): Logement {
                $logement = Logement::create([
                    'id_utilisateur' => $utilisateur->getAuthIdentifier(),
                    'id_quartier' => $validated['id_quartier'],
                    'description' => trim($validated['description']),
                    'type_logement' => $validated['type_logement'],
                    'prix' => $validated['prix'],
                    'date_publication' => now()->toDateString(),
                    'status' => 'Disponible',
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                ]);

                $this->stockerPhotos($request, $logement, $cheminsStockes);

                return $logement;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($cheminsStockes);

            throw $exception;
        }

        return response()->json($this->donneesLogement($logement->load(['quartier.ville', 'photos'])), 201);
    }

    public function update(Request $request, Logement $logement): JsonResponse
    {
        $this->verifierProprietaire($logement);
        $validated = $request->validate([
            ...$this->reglesAnnonce(),
            'photos_a_supprimer' => ['sometimes', 'array'],
            'photos_a_supprimer.*' => ['integer', 'distinct'],
            'supprimer_photo_legacy' => ['sometimes', 'boolean'],
        ]);

        $identifiantsPhotosSupprimees = array_map('intval', $validated['photos_a_supprimer'] ?? []);
        $photosExistantes = $logement->photos;
        $photosASupprimer = $photosExistantes
            ->whereIn('id', $identifiantsPhotosSupprimees);

        if ($photosASupprimer->count() !== count($identifiantsPhotosSupprimees)) {
            abort(404);
        }

        $photosAjoutees = count($request->file('photos', []));
        $nombrePhotosRestantes = $photosExistantes->count()
            - $photosASupprimer->count()
            + $photosAjoutees;
        $supprimerPhotoLegacy = (bool) ($validated['supprimer_photo_legacy'] ?? false);
        $photoLegacy = $photosExistantes->isEmpty() ? $logement->photo : null;

        if ($photoLegacy && ! $supprimerPhotoLegacy) {
            $nombrePhotosRestantes++;
        }

        if ($nombrePhotosRestantes > self::NOMBRE_MAXIMUM_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'Une annonce ne peut pas contenir plus de 10 photos.',
            ]);
        }

        $cheminsASupprimer = $photosASupprimer->pluck('chemin')->all();
        if ($supprimerPhotoLegacy && $photoLegacy) {
            $cheminsASupprimer[] = $photoLegacy;
        }

        $cheminsStockes = [];
        try {
            DB::transaction(function () use (
                $request,
                $validated,
                $logement,
                $photosASupprimer,
                $photoLegacy,
                $supprimerPhotoLegacy,
                &$cheminsStockes,
            ): void {
                $logement->update([
                    'id_quartier' => $validated['id_quartier'],
                    'description' => trim($validated['description']),
                    'type_logement' => $validated['type_logement'],
                    'prix' => $validated['prix'],
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                ]);

                if ($photoLegacy && ! $supprimerPhotoLegacy) {
                    $logement->photos()->create([
                        'chemin' => $photoLegacy,
                        'ordre' => 0,
                    ]);
                }

                $photosASupprimer->each->delete();
                $this->stockerPhotos($request, $logement, $cheminsStockes);

                $logement->photo = $logement->photos()->first()?->chemin;
                $logement->save();
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($cheminsStockes);

            throw $exception;
        }

        Storage::disk('public')->delete(array_unique($cheminsASupprimer));

        return response()->json($this->donneesLogement($logement->fresh()->load(['quartier.ville', 'photos'])));
    }

    public function destroy(Logement $logement): JsonResponse
    {
        $this->verifierProprietaire($logement);
        $cheminsASupprimer = $logement->photos->pluck('chemin')->all();
        if ($logement->photo) {
            $cheminsASupprimer[] = $logement->photo;
        }

        DB::transaction(fn (): bool => $logement->delete());
        Storage::disk('public')->delete(array_unique($cheminsASupprimer));

        return response()->json(null, 204);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function reglesAnnonce(): array
    {
        return [
            'id_quartier' => ['required', 'string', 'max:20', 'exists:quartiers,id_quartier'],
            'description' => ['required', 'string', 'max:10000'],
            'type_logement' => ['required', Rule::in(self::TYPES_LOGEMENT)],
            'prix' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['sometimes', 'array', 'max:'.self::NOMBRE_MAXIMUM_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @param  array<int, string>  $cheminsStockes
     */
    private function stockerPhotos(Request $request, Logement $logement, array &$cheminsStockes): void
    {
        $ordre = $logement->photos()->count();

        foreach ($request->file('photos', []) as $photo) {
            $chemin = $photo->store("logements/{$logement->getKey()}", 'public');
            $cheminsStockes[] = $chemin;
            $logement->photos()->create([
                'chemin' => $chemin,
                'ordre' => $ordre++,
            ]);
        }

        if ($logement->photo === null && $logement->photos()->exists()) {
            $logement->photo = $logement->photos()->first()->chemin;
            $logement->save();
        }
    }

    private function verifierProprietaire(Logement $logement): void
    {
        abort_unless($logement->id_utilisateur === Auth::guard('utilisateur')->id(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesLogement(Logement $logement): array
    {
        $donnees = $logement->toArray();
        $donnees['photos'] = $logement->photos->isNotEmpty()
            ? $logement->photos->map(fn (LogementPhoto $photo): array => [
                'id' => $photo->getKey(),
                'chemin' => $photo->chemin,
                'ordre' => $photo->ordre,
            ])->all()
            : ($logement->photo ? [[
                'id' => null,
                'chemin' => $logement->photo,
                'ordre' => 0,
                'legacy' => true,
            ]] : []);

        return $donnees;
    }
}
