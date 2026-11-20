<?php

namespace App\Http\Controllers;

use App\Models\ServicioAcuse;
use App\Models\ServicioArchivo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoExpedienteController extends Controller
{
    public function verArchivo(ServicioArchivo $archivo): StreamedResponse
    {
        Gate::authorize('view', $archivo);

        return $this->responderArchivo($archivo->ruta, $archivo->nombre, false);
    }

    public function descargarArchivo(ServicioArchivo $archivo): StreamedResponse
    {
        Gate::authorize('download', $archivo);

        return $this->responderArchivo($archivo->ruta, $archivo->nombre, true);
    }

    public function verAcuse(ServicioAcuse $acuse): StreamedResponse
    {
        Gate::authorize('view', $acuse);

        return $this->responderArchivo($acuse->ruta, $acuse->nombre, false);
    }

    public function descargarAcuse(ServicioAcuse $acuse): StreamedResponse
    {
        Gate::authorize('download', $acuse);

        return $this->responderArchivo($acuse->ruta, $acuse->nombre, true);
    }

    private function responderArchivo(string $ruta, string $nombre, bool $descarga): StreamedResponse
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($ruta), 404);

        return $descarga
            ? $disco->download($ruta, $nombre)
            : $disco->response($ruta, $nombre);
    }
}
