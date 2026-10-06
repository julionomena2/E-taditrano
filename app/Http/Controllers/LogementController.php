<?php

namespace App\Http\Controllers;

use App\Models\Logement;
use Illuminate\Http\Request;

class LogementController extends Controller
{
    public function index()
    {
        $logements = Logement::with(['utilisateur', 'quartier'])->get();

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
        $logement = Logement::with(['utilisateur', 'quartier'])->findOrFail($id);

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

    public function nearestFromEtablissement(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'rayon_km' => 'nullable|numeric|min:0.1',
        ]);

        $lat = $request->latitude;
        $lng = $request->longitude;
        $rayon = $request->rayon_km ?? 5;

        $logements = Logement::selectRaw(
            "
                logements.*,
                (
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )
                ) AS distance_km
            ",
            [$lat, $lng, $lat]
        )
            ->having('distance_km', '<=', $rayon)
            ->orderBy('distance_km')
            ->get();

        return response()->json($logements);
    }
}
