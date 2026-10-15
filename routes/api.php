<?php

use App\Http\Controllers\EtablissementController;
use App\Http\Controllers\LogementController;
use App\Http\Controllers\MesLogementsController;
use App\Http\Controllers\MessagerieController;
use App\Http\Controllers\QuartierController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\UtilisateurAuthController;
use App\Http\Controllers\UtilisateurProfileController;
use App\Http\Controllers\VilleController;
use Illuminate\Support\Facades\Route;

// Ces routes utilisent une session Laravel pour partager l'authentification avec le frontend React.
Route::middleware('web')->group(function () {
    Route::get('/auth/session', [UtilisateurAuthController::class, 'session']);
    Route::post('/auth/login', [UtilisateurAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/logout', [UtilisateurAuthController::class, 'logout'])->middleware('auth:utilisateur');
    Route::patch('/auth/profile', [UtilisateurProfileController::class, 'update'])->middleware('auth:utilisateur');
    Route::post('/register', [RegistrationController::class, 'register'])->middleware('throttle:registration');

    Route::middleware('auth:utilisateur')->prefix('mes-logements')->group(function () {
        Route::get('/', [MesLogementsController::class, 'index']);
        Route::post('/', [MesLogementsController::class, 'store']);
        Route::patch('/{logement}', [MesLogementsController::class, 'update']);
        Route::delete('/{logement}', [MesLogementsController::class, 'destroy']);
    });

    Route::middleware('auth:utilisateur')->prefix('messagerie')->group(function () {
        Route::get('/conversations', [MessagerieController::class, 'conversations']);
        Route::get('/{contact}/messages', [MessagerieController::class, 'messages']);
        Route::post('/{contact}/messages', [MessagerieController::class, 'envoyer']);
    });
});

// Endpoints publics de catalogue utilisés pour construire les filtres ville, quartier et établissement.
Route::get('/villes', [VilleController::class, 'index']);
Route::get('/quartiers', [QuartierController::class, 'index']);
Route::get('/etablissements', [EtablissementController::class, 'index']);
// La recherche de logements accepte un filtre de quartier ou les coordonnées d'un établissement proche.
Route::get('/logements/proches', [LogementController::class, 'nearestFromEtablissement']);
Route::get('/logements', [LogementController::class, 'index']);
Route::get('/logements/{id}', [LogementController::class, 'show'])->whereNumber('id');
