<?php

namespace App\Http\Controllers;

use App\Models\Quartier;
use Illuminate\Http\Request;

class QuartierController extends Controller
{
    /**
     * Valide le filtre transmis par React et renvoie les quartiers correspondants en JSON.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'id_ville' => ['sometimes', 'string', 'max:5', 'exists:villes,id_ville'],
        ]);

        $quartiers = Quartier::with('ville:id_ville,nom_Ville')
            ->when(
                isset($filters['id_ville']),
                fn ($query) => $query->where('id_ville', $filters['id_ville']),
            )
            ->orderBy('nom_quartier')
            ->get();

        return response()->json($quartiers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_quartier' => 'required|string|max:20|unique:quartiers,id_quartier',
            'nom_quartier' => 'required|string|max:100',
            'id_ville' => 'required|string|max:5|exists:villes,id_ville',
        ]);

        $quartier = Quartier::create($data);

        return response()->json($quartier, 201);
    }

    public function show(string $id)
    {
        $quartier = Quartier::with('ville')->findOrFail($id);

        return response()->json($quartier);
    }

    public function update(Request $request, string $id)
    {
        $quartier = Quartier::findOrFail($id);

        $data = $request->validate([
            'nom_quartier' => 'sometimes|string|max:100',
            'id_ville' => 'sometimes|string|max:5|exists:villes,id_ville',
        ]);

        $quartier->update($data);

        return response()->json($quartier);
    }

    public function destroy(string $id)
    {
        $quartier = Quartier::findOrFail($id);
        $quartier->delete();

        return response()->json(null, 204);
    }
}
