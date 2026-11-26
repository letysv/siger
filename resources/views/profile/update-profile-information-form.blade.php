<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        Información de la cuenta
    </x-slot>

    <x-slot name="description">
        Consulta tu usuario institucional, rol y departamento asignado, o actualiza tu nombre y correo opcional.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4 rounded-lg bg-slate-50 border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div>
                <div class="font-semibold uppercase text-slate-400">Usuario</div>
                <div class="mt-0.5 font-mono font-bold text-slate-800">{{ $this->user->username ?? '—' }}</div>
            </div>
            <div>
                <div class="font-semibold uppercase text-slate-400">Rol(es)</div>
                <div class="mt-0.5 font-semibold text-siger-800">{{ $this->user->getRoleNames()->implode(', ') ?: 'Sin rol' }}</div>
            </div>
            <div>
                <div class="font-semibold uppercase text-slate-400">Adscripción</div>
                <div class="mt-0.5 text-slate-700">
                    {{ $this->user->departamento ? $this->user->departamento->siglas.' — '.$this->user->departamento->nombre : 'Dirección / Alcance general' }}
                </div>
            </div>
        </div>
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="rounded-full size-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Select A New Photo') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Remove Photo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="Nombre completo *" />
            <x-input id="name" type="text" class="mt-1 block w-full" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="Correo electrónico (opcional)" />
            <x-input id="email" type="email" class="mt-1 block w-full" wire:model="state.email" autocomplete="email" />
            <x-input-error for="email" class="mt-2" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <p class="text-sm mt-2">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 font-medium text-sm text-green-600">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            Guardado.
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            Guardar cambios
        </x-button>
    </x-slot>
</x-form-section>
