<?php

namespace App\Http\Controllers;

use App\Models\Ville;
use Illuminate\Http\Request;

class VilleController extends Controller
{
    public function index()
    {
        $villes = Ville::all();

        return response()->json($villes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_ville' => 'required|string|max:5|unique:villes,id_ville',
            'nom_Ville' => 'required|string|max:100',
        ]);

        $ville = Ville::create($data);

        return response()->json($ville, 201);
    }

    public function show(string $id)
    {
        $ville = Ville::findOrFail($id);

        return response()->json($ville);
    }

    public function update(Request $request, string $id)
    {
        $ville = Ville::findOrFail($id);

        $data = $request->validate([
            'nom_Ville' => 'sometimes|string|max:100',
        ]);

        $ville->update($data);

        return response()->json($ville);
    }

    public function destroy(string $id)
    {
        $ville = Ville::findOrFail($id);
        $ville->delete();

        return response()->json(null, 204);
    }
}
