<x-form-section submit="updatePassword">
    <x-slot name="title">
        Actualizar contraseña
    </x-slot>

    <x-slot name="description">
        Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="current_password" value="Contraseña actual" />
            <div class="relative mt-1" x-data="{ ver: false }">
                <x-input id="current_password" x-bind:type="ver ? 'text' : 'password'" class="block w-full pr-10" wire:model="state.current_password" autocomplete="current-password" />
                <button type="button" x-on:click="ver = ! ver" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-bind:title="ver ? 'Ocultar contraseña' : 'Ver contraseña'">
                    <i class="fa-solid" x-bind:class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            <x-input-error for="current_password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password" value="Nueva contraseña" />
            <div class="relative mt-1" x-data="{ ver: false }">
                <x-input id="password" x-bind:type="ver ? 'text' : 'password'" class="block w-full pr-10" wire:model="state.password" autocomplete="new-password" />
                <button type="button" x-on:click="ver = ! ver" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-bind:title="ver ? 'Ocultar contraseña' : 'Ver contraseña'">
                    <i class="fa-solid" x-bind:class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            <x-input-error for="password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password_confirmation" value="Confirmar contraseña" />
            <div class="relative mt-1" x-data="{ ver: false }">
                <x-input id="password_confirmation" x-bind:type="ver ? 'text' : 'password'" class="block w-full pr-10" wire:model="state.password_confirmation" autocomplete="new-password" />
                <button type="button" x-on:click="ver = ! ver" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600" x-bind:title="ver ? 'Ocultar contraseña' : 'Ver contraseña'">
                    <i class="fa-solid" x-bind:class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            <x-input-error for="password_confirmation" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            Guardado.
        </x-action-message>

        <x-button>
            Guardar
        </x-button>
    </x-slot>
</x-form-section>
