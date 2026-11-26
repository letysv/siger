<?php

namespace App\Http\Controllers;

use App\Models\ServicioAcuse;
use App\Models\ServicioArchivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoExpedienteController extends Controller
{
    public function verArchivo(ServicioArchivo $archivo): StreamedResponse
    {
        Gate::authorize('view', $archivo);

        return $this->responderArchivo($archivo, $archivo->ruta, $archivo->nombre, false, 'Archivo consultado');
    }

    public function descargarArchivo(ServicioArchivo $archivo): StreamedResponse
    {
        Gate::authorize('download', $archivo);

        return $this->responderArchivo($archivo, $archivo->ruta, $archivo->nombre, true, 'Archivo descargado');
    }

    public function verAcuse(ServicioAcuse $acuse): StreamedResponse
    {
        Gate::authorize('view', $acuse);

        return $this->responderArchivo($acuse, $acuse->ruta, $acuse->nombre, false, 'Acuse consultado');
    }

    public function descargarAcuse(ServicioAcuse $acuse): StreamedResponse
    {
        Gate::authorize('download', $acuse);

        return $this->responderArchivo($acuse, $acuse->ruta, $acuse->nombre, true, 'Acuse descargado');
    }

    private function responderArchivo(Model $documento, string $ruta, string $nombre, bool $descarga, string $evento): StreamedResponse
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($ruta), 404);

        activity('documentos')
            ->causedBy(Auth::user())
            ->performedOn($documento)
            ->withProperties(['nombre' => $nombre, 'descarga' => $descarga])
            ->log($evento);

        return $descarga
            ? $disco->download($ruta, $nombre)
            : $disco->response($ruta, $nombre);
    }
}
