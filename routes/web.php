<?php

use App\Livewire\Catalogos\GestionCatalogos;
use App\Livewire\Usuarios\GestionUsuarios;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/catalogos', GestionCatalogos::class)
        ->name('catalogos.index')
        ->can('viewAny', Area::class);

    Route::get('/usuarios', GestionUsuarios::class)
        ->name('usuarios.index')
        ->can('viewAny', User::class);
});
