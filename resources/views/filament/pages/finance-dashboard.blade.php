<x-filament-panels::page>
    {{ $this->filtersForm }}

    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="1"
        :data="['filters' => $this->filters ?? []]"
    />
</x-filament-panels::page>
