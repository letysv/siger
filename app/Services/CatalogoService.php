<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio y persistencia para los catálogos institucionales (Fase 1A).
 */
class CatalogoService
{
    public function guardarArea(array $datos, ?Area $area = null): Area
    {
        $normalizados = [
            'clave' => Str::upper(trim((string) ($datos['clave'] ?? ''))),
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'titular' => $this->textoOpcional($datos['titular'] ?? null),
            'tipo_area' => trim((string) ($datos['tipo_area'] ?? '')),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        $validados = Validator::make($normalizados, [
            'clave' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cat_areas', 'clave')->ignore($area?->getKey()),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'titular' => ['nullable', 'string', 'max:255'],
            'tipo_area' => ['required', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ], [
            'clave.unique' => 'Ya existe un área registrada con esa clave.',
        ])->validate();

        return DB::transaction(function () use ($area, $validados): Area {
            $registro = $area ?? new Area;
            $registro->fill($validados);
            $registro->save();

            return $registro;
        });
    }

    public function guardarDepartamento(array $datos, ?Departamento $departamento = null): Departamento
    {
        $normalizados = [
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'siglas' => Str::upper(trim((string) ($datos['siglas'] ?? ''))),
            'titular_responsable' => $this->textoOpcional($datos['titular_responsable'] ?? null),
            'ubicacion_oficina' => $this->textoOpcional($datos['ubicacion_oficina'] ?? null),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        $validados = Validator::make($normalizados, [
            'nombre' => ['required', 'string', 'max:255'],
            'siglas' => [
                'required',
                'string',
                'max:12',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('cat_departamentos', 'siglas')->ignore($departamento?->getKey()),
            ],
            'titular_responsable' => ['nullable', 'string', 'max:255'],
            'ubicacion_oficina' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ], [
            'siglas.regex' => 'Las siglas solo pueden contener letras mayúsculas, números, guion o guion bajo.',
            'siglas.unique' => 'Ya existe un departamento registrado con esas siglas.',
        ])->validate();

        if ($departamento !== null && $departamento->esProtegido()) {
            if ($validados['siglas'] !== strtoupper((string) $departamento->siglas)) {
                throw ValidationException::withMessages([
                    'siglas' => 'Las siglas de los departamentos institucionales base (RM, SG, ADQ) no pueden modificarse.',
                ]);
            }

            if (! $validados['activo']) {
                throw ValidationException::withMessages([
                    'activo' => 'Los departamentos institucionales base (RM, SG, ADQ) no pueden desactivarse.',
                ]);
            }
        }

        return DB::transaction(function () use ($departamento, $validados): Departamento {
            $registro = $departamento ?? new Departamento;
            $registro->fill($validados);
            $registro->save();

            return $registro;
        });
    }

    public function guardarTipoServicio(array $datos, ?TipoServicio $tipoServicio = null): TipoServicio
    {
        $normalizados = [
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
            'requiere_cantidad' => (bool) ($datos['requiere_cantidad'] ?? false),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        $validados = Validator::make($normalizados, [
            'descripcion' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cat_tipo_servicios', 'descripcion')->ignore($tipoServicio?->getKey()),
            ],
            'requiere_cantidad' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ], [
            'descripcion.unique' => 'Ya existe un tipo de servicio con esa descripción.',
        ])->validate();

        return DB::transaction(function () use ($tipoServicio, $validados): TipoServicio {
            $registro = $tipoServicio ?? new TipoServicio;
            $registro->fill($validados);
            $registro->save();

            return $registro;
        });
    }

    public function guardarOperativo(array $datos, ?Operativo $operativo = null): Operativo
    {
        $userId = $datos['user_id'] ?? null;
        $normalizados = [
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'departamento_id' => $datos['departamento_id'] ? (int) $datos['departamento_id'] : null,
            'user_id' => ($userId !== null && $userId !== '') ? (int) $userId : null,
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        $validados = Validator::make($normalizados, [
            'nombre' => ['required', 'string', 'max:255'],
            'departamento_id' => ['required', 'integer', 'exists:cat_departamentos,id'],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('cat_operativos', 'user_id')->ignore($operativo?->getKey()),
            ],
            'activo' => ['required', 'boolean'],
        ], [
            'user_id.unique' => 'La cuenta de usuario seleccionada ya está vinculada a otro operativo.',
        ])->validate();

        $departamento = Departamento::findOrFail($validados['departamento_id']);
        $cambioDepartamento = $operativo === null || (int) $operativo->departamento_id !== (int) $departamento->id;

        if ($cambioDepartamento && ! $departamento->activo) {
            throw ValidationException::withMessages([
                'departamento_id' => 'El departamento seleccionado está inactivo.',
            ]);
        }

        if ($validados['user_id'] !== null) {
            $usuario = User::findOrFail($validados['user_id']);
            $cambioUsuario = $operativo === null || (int) $operativo->user_id !== (int) $usuario->id;

            if ($cambioUsuario && ! $usuario->activo) {
                throw ValidationException::withMessages([
                    'user_id' => 'No se puede vincular un operativo con una cuenta de usuario inactiva.',
                ]);
            }

            if ($usuario->departamento_id !== null && (int) $usuario->departamento_id !== (int) $departamento->id) {
                throw ValidationException::withMessages([
                    'user_id' => 'El usuario seleccionado está adscrito a un departamento distinto.',
                ]);
            }
        }

        return DB::transaction(function () use ($operativo, $validados): Operativo {
            $registro = $operativo ?? new Operativo;
            $registro->fill($validados);
            $registro->save();

            return $registro;
        });
    }

    public function alternarEstado(Area|Departamento|TipoServicio|Operativo $registro): Area|Departamento|TipoServicio|Operativo
    {
        if ($registro instanceof Departamento && $registro->esProtegido() && $registro->activo) {
            throw ValidationException::withMessages([
                'estado' => 'Los departamentos institucionales base (RM, SG, ADQ) no pueden desactivarse.',
            ]);
        }

        return DB::transaction(function () use ($registro): Area|Departamento|TipoServicio|Operativo {
            $registro->activo = ! $registro->activo;
            $registro->save();

            return $registro;
        });
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
