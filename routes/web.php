<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (session('admin_logged_in')) {
        return redirect()->route('dashboard');
    }

    return view('auth.login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('web')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    Route::get('/admin/{model}', [AdminController::class, 'resource'])->name('admin.resource');
    Route::post('/admin/{model}', [AdminController::class, 'store'])->name('admin.resource.store');
    Route::put('/admin/{model}/{id}', [AdminController::class, 'update'])->name('admin.resource.update');
    Route::delete('/admin/{model}/{id}', [AdminController::class, 'destroy'])->name('admin.resource.destroy');

    Route::get('/admin/utilisateurs', [AdminController::class, 'resource'])->defaults('model', 'utilisateurs')->name('admin.utilisateurs');
    Route::get('/admin/villes', [AdminController::class, 'resource'])->defaults('model', 'villes')->name('admin.villes');
    Route::get('/admin/quartiers', [AdminController::class, 'resource'])->defaults('model', 'quartiers')->name('admin.quartiers');
    Route::get('/admin/logements', [AdminController::class, 'resource'])->defaults('model', 'logements')->name('admin.logements');
    Route::get('/admin/etablissements', [AdminController::class, 'resource'])->defaults('model', 'etablissements')->name('admin.etablissements');
});
