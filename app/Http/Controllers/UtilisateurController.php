<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\Request;

class UtilisateurController extends Controller
{
    public function index()
    {
        $utilisateurs = Utilisateur::all();

        return response()->json($utilisateurs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_utilisateur' => 'required|string|max:50|unique:utilisateurs,id_utilisateur',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'mot_de_passe' => 'required|string|max:10',
            'telephone' => 'required|string|max:10',
        ]);

        $utilisateur = Utilisateur::create($data);

        return response()->json($utilisateur, 201);
    }

    public function show(string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);

        return response()->json($utilisateur);
    }

    public function update(Request $request, string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);

        $data = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'mot_de_passe' => 'sometimes|string|max:10',
            'telephone' => 'sometimes|string|max:10',
        ]);

        $utilisateur->update($data);

        return response()->json($utilisateur);
    }

    public function destroy(string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);
        $utilisateur->delete();

        return response()->json(null, 204);
    }
}
