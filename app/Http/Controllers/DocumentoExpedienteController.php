<?php

namespace App\Http\Controllers;

use App\Models\RegistroOperativo;
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

    public function verConformidad(RegistroOperativo $registro): StreamedResponse
    {
        $this->autorizarDocumentoPartida($registro, 'documentos.ver');
        abort_if(empty($registro->archivo_conformidad), 404);

        return $this->responderArchivo(
            $registro,
            $registro->archivo_conformidad,
            basename($registro->archivo_conformidad),
            false,
            'Conformidad consultada'
        );
    }

    public function descargarConformidad(RegistroOperativo $registro): StreamedResponse
    {
        $this->autorizarDocumentoPartida($registro, 'documentos.descargar');
        abort_if(empty($registro->archivo_conformidad), 404);

        return $this->responderArchivo(
            $registro,
            $registro->archivo_conformidad,
            basename($registro->archivo_conformidad),
            true,
            'Conformidad descargada'
        );
    }

    public function verEvidencia(RegistroOperativo $registro, int $indice): StreamedResponse
    {
        $this->autorizarDocumentoPartida($registro, 'documentos.ver');
        $evidencia = $this->obtenerEvidencia($registro, $indice);

        return $this->responderArchivo(
            $registro,
            $evidencia['ruta'],
            $evidencia['nombre_original'] ?? basename($evidencia['ruta']),
            false,
            'Evidencia consultada'
        );
    }

    public function descargarEvidencia(RegistroOperativo $registro, int $indice): StreamedResponse
    {
        $this->autorizarDocumentoPartida($registro, 'documentos.descargar');
        $evidencia = $this->obtenerEvidencia($registro, $indice);

        return $this->responderArchivo(
            $registro,
            $evidencia['ruta'],
            $evidencia['nombre_original'] ?? basename($evidencia['ruta']),
            true,
            'Evidencia descargada'
        );
    }

    private function autorizarDocumentoPartida(RegistroOperativo $registro, string $permiso): void
    {
        $usuario = Auth::user();
        abort_unless(
            $usuario !== null && $usuario->activo && $usuario->checkPermissionTo($permiso, 'web'),
            403
        );
        abort_if($registro->partida === null, 404);
        Gate::authorize('view', $registro->partida);
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerEvidencia(RegistroOperativo $registro, int $indice): array
    {
        $lista = is_array($registro->evidencias) ? array_values($registro->evidencias) : [];
        abort_unless(isset($lista[$indice]) && is_array($lista[$indice]) && ! empty($lista[$indice]['ruta']), 404);

        return $lista[$indice];
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
