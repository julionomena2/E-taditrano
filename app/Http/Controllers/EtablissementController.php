<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use Illuminate\Http\Request;

class EtablissementController extends Controller
{
    public function index()
    {
        $etablissements = Etablissement::with('quartier')->get();

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
