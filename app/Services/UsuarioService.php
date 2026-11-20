<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio y persistencia para la gestión de cuentas de usuario (Fase 1B).
 */
class UsuarioService
{
    private const ROLES_GENERALES = ['Super usuario', 'Administrador'];

    public function crear(array $datos, ?User $actor = null): User
    {
        $normalizados = $this->normalizarDatos($datos);

        $validados = Validator::make($normalizados, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'departamento_id' => ['nullable', 'integer', 'exists:cat_departamentos,id'],
            'activo' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ], [
            'username.regex' => 'El usuario solo puede contener letras minúsculas, números, punto, guion o guion bajo.',
            'username.unique' => 'Ya existe una cuenta registrada con ese nombre de usuario.',
            'email.unique' => 'Ya existe una cuenta registrada con ese correo electrónico.',
            'roles.required' => 'Debes seleccionar al menos un rol para el usuario.',
            'roles.min' => 'Debes seleccionar al menos un rol para el usuario.',
        ])->validate();

        $this->validarDepartamentoActivo($validados['departamento_id']);

        return DB::transaction(function () use ($validados, $actor): User {
            $usuario = new User;
            $usuario->name = $validados['name'];
            $usuario->username = $validados['username'];
            $usuario->email = $validados['email'];
            $usuario->password = $validados['password'];
            $usuario->email_verified_at = now();
            $usuario->departamento_id = $validados['departamento_id'];
            $usuario->activo = $validados['activo'];
            $usuario->save();

            $roles = $this->ordenarRoles($validados['roles']);
            $usuario->syncRoles($roles);

            activity('usuarios')
                ->causedBy($actor)
                ->performedOn($usuario)
                ->withProperties(['roles' => $roles])
                ->log('Roles asignados al crear usuario');

            return $usuario->load(['departamento', 'roles', 'operativo']);
        });
    }

    public function actualizar(User $usuario, array $datos, ?User $actor = null): User
    {
        $normalizados = $this->normalizarDatos($datos);

        $validados = Validator::make($normalizados, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($usuario->getKey()),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->getKey()),
            ],
            'departamento_id' => ['nullable', 'integer', 'exists:cat_departamentos,id'],
            'activo' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ], [
            'username.regex' => 'El usuario solo puede contener letras minúsculas, números, punto, guion o guion bajo.',
            'username.unique' => 'Ya existe una cuenta registrada con ese nombre de usuario.',
            'email.unique' => 'Ya existe una cuenta registrada con ese correo electrónico.',
            'roles.required' => 'Debes seleccionar al menos un rol para el usuario.',
            'roles.min' => 'Debes seleccionar al menos un rol para el usuario.',
        ])->validate();

        $cambioDepartamento = (int) ($usuario->departamento_id ?? 0) !== (int) ($validados['departamento_id'] ?? 0);
        if ($cambioDepartamento) {
            $this->validarDepartamentoActivo($validados['departamento_id']);
        }

        $this->validarCoherenciaOperativo($usuario, $validados['departamento_id']);
        $rolesNuevos = $this->ordenarRoles($validados['roles']);
        $this->validarAutoProteccion($usuario, $actor, $validados['activo'], $rolesNuevos);

        return DB::transaction(function () use ($usuario, $validados, $rolesNuevos, $actor): User {
            $rolesAnteriores = $this->ordenarRoles($usuario->getRoleNames()->all());

            $usuario->name = $validados['name'];
            $usuario->username = $validados['username'];
            $usuario->email = $validados['email'];
            $usuario->departamento_id = $validados['departamento_id'];
            $usuario->activo = $validados['activo'];
            $usuario->save();

            if ($rolesAnteriores !== $rolesNuevos) {
                $usuario->syncRoles($rolesNuevos);

                activity('usuarios')
                    ->causedBy($actor)
                    ->performedOn($usuario)
                    ->withProperties([
                        'roles_anteriores' => $rolesAnteriores,
                        'roles_nuevos' => $rolesNuevos,
                    ])
                    ->log('Roles de usuario actualizados');
            }

            if (! $usuario->activo) {
                $this->invalidarSesiones($usuario);
            }

            return $usuario->load(['departamento', 'roles', 'operativo']);
        });
    }

    public function alternarEstado(User $usuario, User $actor): User
    {
        if ((int) $usuario->getKey() === (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'activo' => 'No puedes desactivar tu propia cuenta de usuario.',
            ]);
        }

        return DB::transaction(function () use ($usuario): User {
            $usuario->activo = ! $usuario->activo;
            $usuario->save();

            if (! $usuario->activo) {
                $this->invalidarSesiones($usuario);
            }

            return $usuario->load(['departamento', 'roles', 'operativo']);
        });
    }

    public function restablecerContrasena(User $usuario, array $datos, ?User $actor = null): User
    {
        $validados = Validator::make([
            'password' => (string) ($datos['password'] ?? ''),
            'password_confirmation' => (string) ($datos['password_confirmation'] ?? ''),
        ], [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        return DB::transaction(function () use ($usuario, $validados, $actor): User {
            $usuario->password = $validados['password'];
            $usuario->save();

            $this->invalidarSesiones($usuario);

            activity('usuarios')
                ->causedBy($actor)
                ->performedOn($usuario)
                ->log('Contraseña restablecida por administración');

            return $usuario;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarDatos(array $datos): array
    {
        $departamentoId = $datos['departamento_id'] ?? null;
        $roles = collect($datos['roles'] ?? [])
            ->filter(fn ($rol): bool => is_string($rol) && trim($rol) !== '')
            ->map(fn (string $rol): string => trim($rol))
            ->unique()
            ->values()
            ->all();

        $email = Str::lower(trim((string) ($datos['email'] ?? '')));

        return [
            'name' => trim((string) ($datos['name'] ?? '')),
            'username' => Str::lower(trim((string) ($datos['username'] ?? ''))),
            'email' => $email === '' ? null : $email,
            'password' => (string) ($datos['password'] ?? ''),
            'password_confirmation' => (string) ($datos['password_confirmation'] ?? ''),
            'departamento_id' => ($departamentoId !== null && $departamentoId !== '') ? (int) $departamentoId : null,
            'activo' => (bool) ($datos['activo'] ?? true),
            'roles' => $roles,
        ];
    }

    private function validarDepartamentoActivo(?int $departamentoId): void
    {
        if ($departamentoId === null) {
            return;
        }

        $departamento = Departamento::findOrFail($departamentoId);
        if (! $departamento->activo) {
            throw ValidationException::withMessages([
                'departamento_id' => 'El departamento seleccionado está inactivo.',
            ]);
        }
    }

    private function validarCoherenciaOperativo(User $usuario, ?int $departamentoId): void
    {
        $operativo = $usuario->operativo;
        if ($operativo === null || $departamentoId === null) {
            return;
        }

        if ((int) $operativo->departamento_id !== $departamentoId) {
            throw ValidationException::withMessages([
                'departamento_id' => 'Este usuario está vinculado a un operativo en otro departamento. Actualiza primero su ficha en Catálogos.',
            ]);
        }
    }

    /**
     * @param  list<string>  $rolesNuevos
     */
    private function validarAutoProteccion(User $usuario, ?User $actor, bool $activo, array $rolesNuevos): void
    {
        if ($actor === null || (int) $usuario->getKey() !== (int) $actor->getKey()) {
            return;
        }

        if (! $activo) {
            throw ValidationException::withMessages([
                'activo' => 'No puedes desactivar tu propia cuenta de usuario.',
            ]);
        }

        $conservaAccesoGeneral = array_intersect($rolesNuevos, self::ROLES_GENERALES) !== [];
        if (! $conservaAccesoGeneral) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes retirar tu propio rol administrativo (Super usuario o Administrador).',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $roles
     * @return list<string>
     */
    private function ordenarRoles(array $roles): array
    {
        $lista = array_values(array_unique($roles));
        sort($lista);

        return $lista;
    }

    private function invalidarSesiones(User $usuario): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $usuario->getKey())->delete();
        }
    }
}
