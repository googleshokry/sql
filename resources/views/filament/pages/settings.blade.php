<x-filament-panels::page>
    <form wire:submit="save" style="display:flex;flex-direction:column;gap:1rem">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-o-check">{{ __('حفظ الإعدادات') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
