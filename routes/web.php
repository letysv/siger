<?php

use App\Livewire\Catalogos\GestionCatalogos;
use App\Models\Area;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
});
