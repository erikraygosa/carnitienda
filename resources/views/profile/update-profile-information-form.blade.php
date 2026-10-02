<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        Información del perfil
    </x-slot>

    <x-slot name="description">
        Actualiza la información de tu perfil y tu correo electrónico.
    </x-slot>

    <x-slot name="form">

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="Nombre" />
            <x-input id="name" type="text" class="mt-1 block w-full" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            Guardado.
        </x-action-message>

        <x-button wire:loading.attr="disabled">
            Guardar
        </x-button>
    </x-slot>
</x-form-section>
