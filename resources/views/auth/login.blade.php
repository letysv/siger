<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label for="email" value="Usuario o correo electrónico" />
                <x-input id="email" class="block mt-1 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="Contraseña" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600">Mantener sesión iniciada</span>
                </label>
            </div>

            <div class="flex items-center justify-between gap-3 mt-5">
                <span class="text-xs text-slate-500">
                    ¿Olvidaste tu contraseña? Solicita el restablecimiento al Administrador.
                </span>

                <x-button class="shrink-0">
                    Iniciar sesión
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
