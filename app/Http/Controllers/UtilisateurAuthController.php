<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UtilisateurAuthController extends Controller
{
    public function session(): JsonResponse
    {
        return response()->json([
            'utilisateur' => Auth::guard('utilisateur')->user(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:50'],
            'mot_de_passe' => ['required', 'string', 'max:128'],
        ], [
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'email.max' => 'L’adresse e-mail ne peut pas dépasser 50 caractères.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.max' => 'Le mot de passe ne peut pas dépasser 128 caractères.',
        ]);

        if (! Auth::guard('utilisateur')->attempt([
            'id_utilisateur' => $credentials['email'],
            'password' => $credentials['mot_de_passe'],
        ])) {
            return response()->json([
                'message' => 'Adresse e-mail ou mot de passe incorrect.',
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'utilisateur' => Auth::guard('utilisateur')->user(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('utilisateur')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json([
            'utilisateur' => null,
            'csrf_token' => csrf_token(),
            'message' => 'Vous êtes déconnecté.',
        ]);
    }
}
