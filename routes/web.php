<?php

use App\Http\Controllers\DocumentoExpedienteController;
use App\Livewire\Catalogos\GestionCatalogos;
use App\Livewire\Expedientes\DetalleExpediente;
use App\Livewire\Expedientes\ListadoExpedientes;
use App\Livewire\Expedientes\RegistrarExpediente;
use App\Livewire\Usuarios\GestionUsuarios;
use App\Models\Area;
use App\Models\Servicio;
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

    Route::get('/expedientes', ListadoExpedientes::class)
        ->name('expedientes.index')
        ->middleware('can:servicios.ver');

    Route::get('/expedientes/registrar', RegistrarExpediente::class)
        ->name('expedientes.create')
        ->can('create', Servicio::class);

    Route::get('/expedientes/{servicio}', DetalleExpediente::class)
        ->name('expedientes.show')
        ->can('view', 'servicio');

    Route::get('/expedientes/archivos/{archivo}/ver', [DocumentoExpedienteController::class, 'verArchivo'])
        ->name('expedientes.archivos.ver');

    Route::get('/expedientes/archivos/{archivo}/descargar', [DocumentoExpedienteController::class, 'descargarArchivo'])
        ->name('expedientes.archivos.descargar');

    Route::get('/expedientes/acuses/{acuse}/ver', [DocumentoExpedienteController::class, 'verAcuse'])
        ->name('expedientes.acuses.ver');

    Route::get('/expedientes/acuses/{acuse}/descargar', [DocumentoExpedienteController::class, 'descargarAcuse'])
        ->name('expedientes.acuses.descargar');
});
