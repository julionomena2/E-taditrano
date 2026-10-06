<?php

namespace App\Http\Controllers;

use App\Models\Quartier;
use Illuminate\Http\Request;

class QuartierController extends Controller
{
    public function index()
    {
        $quartiers = Quartier::with('ville')->get();

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
