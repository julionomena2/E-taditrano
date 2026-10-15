<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationController extends Controller
{
    /**
     * Valide les champs reçus du formulaire, enregistre le compte et renvoie son résumé en JSON.
     */
    public function register(Request $request): JsonResponse
    {
        // Le frontend normalise l'adresse avant l'envoi ; cette normalisation serveur protège aussi les autres clients API.
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:50'],
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'mot_de_passe' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
            'photo_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'telephone.regex' => 'Le numéro de téléphone doit contenir exactement 10 chiffres, sans espace.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.max' => 'Le mot de passe ne peut pas dépasser 128 caractères.',
            'mot_de_passe.regex' => 'Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.',
            'mot_de_passe.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'photo_profil.image' => 'La photo de profil doit être une image valide.',
            'photo_profil.mimes' => 'La photo doit être au format JPEG, PNG ou WebP.',
            'photo_profil.max' => 'La photo ne peut pas dépasser 5 Mo.',
        ]);

        $email = $validated['email'];
        if ($this->emailAlreadyRegistered($email)) {
            // Une adresse déjà utilisée est renvoyée comme erreur HTTP 422 que le client affiche à l'utilisateur.
            return response()->json(['message' => 'Cette adresse e-mail est déjà utilisée.'], 422);
        }

        $photo = null;
        if ($request->hasFile('photo_profil')) {
            // Le formulaire envoie la photo en multipart ; Laravel la stocke et seule sa référence est conservée au compte.
            $photo = $request->file('photo_profil')->storePublicly('utilisateurs', 'public');
            if ($photo === false) {
                throw new RuntimeException('La photo de profil n’a pas pu être enregistrée.');
            }
        }

        $utilisateur = Utilisateur::create([
            'id_utilisateur' => $email,
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'mot_de_passe' => $validated['mot_de_passe'],
            'telephone' => $validated['telephone'],
            'photo' => $photo,
        ]);

        Auth::guard('utilisateur')->login($utilisateur);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Votre compte a été créé.',
            'utilisateur' => $utilisateur,
            'csrf_token' => csrf_token(),
        ], 201);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }

    private function emailAlreadyRegistered(string $email): bool
    {
        return Utilisateur::whereRaw('LOWER(id_utilisateur) = ?', [$email])->exists();
    }
}
