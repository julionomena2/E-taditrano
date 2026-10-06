<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (session('admin_logged_in')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identifiant' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        if ($credentials['identifiant'] === 'admin' && $credentials['mot_de_passe'] === 'admin123') {
            session()->put('admin_logged_in', true);
            session()->put('admin_name', 'Administrateur');

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'identifiant' => 'Identifiant ou mot de passe incorrect.',
        ])->withInput();
    }

    public function logout()
    {
        session()->forget('admin_logged_in');
        session()->forget('admin_name');

        return redirect()->route('login');
    }
}
