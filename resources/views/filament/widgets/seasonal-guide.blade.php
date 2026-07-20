<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">الموسمية — متى يكثر الطلب</x-slot>
        <x-slot name="description">توجيه موسمي عام من السوق المصري</x-slot>

        {{-- وسم صدق: هذه الطبقة ليست من بيانات متجرك بعد --}}
        <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
            🗓️ توجيه عام مبنيّ على مواسم السوق المصري لا على طلبات متجرك — يُستبدَل تدريجيًّا
            بـ«أفضل كتب كل موسم من بياناتك» بعد موسمين متماثلين من التشغيل.
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($seasons as $s)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 p-3.5">
                    <div class="flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                        <span class="text-lg" aria-hidden="true">{{ $s['icon'] }}</span>
                        <span>{{ $s['name'] }}</span>
                        <span class="ms-auto text-xs font-medium text-gray-400">{{ $s['when'] }}</span>
                    </div>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $s['note'] }}</p>
                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        @foreach ($s['cats'] as $cat)
                            <span class="inline-flex items-center rounded-md bg-primary-50 dark:bg-primary-500/10 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-400">
                                {{ $cat }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
