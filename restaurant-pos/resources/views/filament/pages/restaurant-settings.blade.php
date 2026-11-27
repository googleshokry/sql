<x-filament-panels::page>
    <form wire:submit="save" style="display:grid;gap:16px">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">{{ __('حفظ') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
