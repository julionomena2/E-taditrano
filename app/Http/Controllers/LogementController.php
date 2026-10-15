<?php

namespace App\Http\Controllers;

use App\Models\Logement;
use Illuminate\Http\Request;

class LogementController extends Controller
{
    /**
     * Renvoie au frontend les logements disponibles, éventuellement limités à un quartier.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'id_quartier' => ['sometimes', 'string', 'max:20', 'exists:quartiers,id_quartier'],
        ]);

        $logements = Logement::with(['quartier:id_quartier,nom_quartier,id_ville', 'photos'])
            ->when(
                isset($filters['id_quartier']),
                fn ($query) => $query->where('id_quartier', $filters['id_quartier']),
            )
            ->whereRaw('LOWER(status) = ?', ['disponible'])
            ->latest('date_publication')
            ->get();

        return response()->json($logements);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_utilisateur' => 'required|string|max:50|exists:utilisateurs,id_utilisateur',
            'id_quartier' => 'required|string|max:20|exists:quartiers,id_quartier',
            'description' => 'required|string',
            'type_logement' => 'required|string|max:100',
            'prix' => 'required|integer|min:0',
            'date_publication' => 'required|date',
            'status' => 'required|string|max:100',
            'photo' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $logement = Logement::create($data);

        return response()->json($logement, 201);
    }

    public function show(int $id)
    {
        $logement = Logement::with(['quartier:id_quartier,nom_quartier,id_ville', 'photos'])
            ->findOrFail($id);

        return response()->json($logement);
    }

    public function update(Request $request, int $id)
    {
        $logement = Logement::findOrFail($id);

        $data = $request->validate([
            'id_utilisateur' => 'sometimes|string|max:50|exists:utilisateurs,id_utilisateur',
            'id_quartier' => 'sometimes|string|max:20|exists:quartiers,id_quartier',
            'description' => 'sometimes|string',
            'type_logement' => 'sometimes|string|max:100',
            'prix' => 'sometimes|integer|min:0',
            'date_publication' => 'sometimes|date',
            'status' => 'sometimes|string|max:100',
            'photo' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $logement->update($data);

        return response()->json($logement);
    }

    public function destroy(int $id)
    {
        $logement = Logement::findOrFail($id);
        $logement->delete();

        return response()->json(null, 204);
    }

    /**
     * Valide les coordonnées envoyées par React, calcule les distances et renvoie les annonces dans le rayon demandé.
     */
    public function nearestFromEtablissement(Request $request)
    {
        $filters = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'rayon_km' => ['nullable', 'numeric', 'min:0.1'],
            'id_quartier' => ['sometimes', 'string', 'max:20', 'exists:quartiers,id_quartier'],
        ]);

        $lat = $filters['latitude'];
        $lng = $filters['longitude'];
        $rayon = $filters['rayon_km'] ?? 5;

        $logements = Logement::with(['quartier:id_quartier,nom_quartier,id_ville', 'photos'])
            ->selectRaw(
                '
                logements.*,
                (
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )
                ) AS distance_km
            ',
                [$lat, $lng, $lat]
            )
            ->having('distance_km', '<=', $rayon)
            ->when(
                isset($filters['id_quartier']),
                fn ($query) => $query->where('id_quartier', $filters['id_quartier']),
            )
            ->whereRaw('LOWER(status) = ?', ['disponible'])
            ->orderBy('distance_km')
            ->get();

        return response()->json($logements);
    }
}
