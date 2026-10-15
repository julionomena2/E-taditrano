<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UtilisateurProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:50'],
            'telephone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'photo_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mot_de_passe_actuel' => ['nullable', 'required_with:mot_de_passe', 'string', 'max:128'],
            'mot_de_passe' => [
                'nullable',
                'string',
                'min:8',
                'max:128',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
        ], [
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'email.max' => 'L’adresse e-mail ne peut pas dépasser 50 caractères.',
            'telephone.regex' => 'Le numéro de téléphone doit contenir exactement 10 chiffres, sans espace.',
            'photo_profil.image' => 'La photo de profil doit être une image valide.',
            'photo_profil.mimes' => 'La photo doit être au format JPEG, PNG ou WebP.',
            'photo_profil.max' => 'La photo ne peut pas dépasser 5 Mo.',
            'mot_de_passe_actuel.required_with' => 'Saisissez votre mot de passe actuel pour en choisir un nouveau.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.max' => 'Le mot de passe ne peut pas dépasser 128 caractères.',
            'mot_de_passe.regex' => 'Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.',
            'mot_de_passe.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $utilisateur = Auth::guard('utilisateur')->user();
        $emailDejaUtilise = Utilisateur::query()
            ->whereRaw('LOWER(id_utilisateur) = ?', [$validated['email']])
            ->where('id_utilisateur', '!=', $utilisateur->getKey())
            ->exists();

        if ($emailDejaUtilise) {
            throw ValidationException::withMessages([
                'email' => 'Cette adresse e-mail est déjà utilisée.',
            ]);
        }

        if (! empty($validated['mot_de_passe']) && ! Hash::check(
            $validated['mot_de_passe_actuel'],
            $utilisateur->mot_de_passe,
        )) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $anciennePhoto = $utilisateur->photo;
        $nouvellePhoto = null;

        if ($request->hasFile('photo_profil')) {
            $nouvellePhoto = $request->file('photo_profil')->storePublicly('utilisateurs', 'public');
            if ($nouvellePhoto === false) {
                throw new RuntimeException('La nouvelle photo de profil n’a pas pu être enregistrée.');
            }
        }

        try {
            DB::transaction(function () use ($validated, $utilisateur, $nouvellePhoto): void {
                $utilisateur->fill([
                    'id_utilisateur' => $validated['email'],
                    'nom' => $validated['nom'],
                    'prenom' => $validated['prenom'],
                    'telephone' => $validated['telephone'],
                ]);

                if ($validated['mot_de_passe'] ?? null) {
                    $utilisateur->mot_de_passe = $validated['mot_de_passe'];
                }

                if ($nouvellePhoto !== null) {
                    $utilisateur->photo = $nouvellePhoto;
                }

                $utilisateur->save();
            });
        } catch (Throwable $exception) {
            if ($nouvellePhoto !== null) {
                Storage::disk('public')->delete($nouvellePhoto);
            }

            throw $exception;
        }

        Auth::guard('utilisateur')->login($utilisateur);
        $request->session()->regenerate();

        if ($nouvellePhoto !== null && $anciennePhoto !== null
            && ! Storage::disk('public')->delete($anciennePhoto)
            && Storage::disk('public')->exists($anciennePhoto)) {
            Log::warning('An old profile photo could not be deleted after replacement.', [
                'utilisateur_id' => $utilisateur->getKey(),
                'photo' => $anciennePhoto,
            ]);
        }

        return response()->json([
            'message' => 'Votre profil a été mis à jour.',
            'utilisateur' => $utilisateur,
            'csrf_token' => csrf_token(),
        ]);
    }
}
