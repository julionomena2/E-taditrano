<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use Illuminate\Http\Request;

class EtablissementController extends Controller
{
    /**
     * Filtre les établissements selon les identifiants reçus du formulaire de recherche React.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'id_ville' => ['sometimes', 'string', 'max:5', 'exists:villes,id_ville'],
            'id_quartier' => ['sometimes', 'string', 'max:20', 'exists:quartiers,id_quartier'],
        ]);

        $etablissements = Etablissement::with('quartier:id_quartier,nom_quartier,id_ville')
            ->when(
                isset($filters['id_quartier']),
                fn ($query) => $query->where('id_quartier', $filters['id_quartier']),
            )
            ->when(
                isset($filters['id_ville']),
                fn ($query) => $query->whereHas(
                    'quartier',
                    fn ($quartiers) => $quartiers->where('id_ville', $filters['id_ville']),
                ),
            )
            ->orderBy('nom_etablissement')
            ->get();

        return response()->json($etablissements);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom_etablissement' => 'required|string|max:100',
            'id_quartier' => 'required|string|max:20|exists:quartiers,id_quartier',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $etablissement = Etablissement::create($data);

        return response()->json($etablissement, 201);
    }

    public function show(int $id)
    {
        $etablissement = Etablissement::with('quartier')->findOrFail($id);

        return response()->json($etablissement);
    }

    public function update(Request $request, int $id)
    {
        $etablissement = Etablissement::findOrFail($id);

        $data = $request->validate([
            'nom_etablissement' => 'sometimes|string|max:100',
            'id_quartier' => 'sometimes|string|max:20|exists:quartiers,id_quartier',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $etablissement->update($data);

        return response()->json($etablissement);
    }

    public function destroy(int $id)
    {
        $etablissement = Etablissement::findOrFail($id);
        $etablissement->delete();

        return response()->json(null, 204);
    }
}
