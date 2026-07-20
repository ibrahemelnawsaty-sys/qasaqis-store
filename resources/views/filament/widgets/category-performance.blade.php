<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">أداء الأقسام — آخر 30 يومًا</x-slot>
        <x-slot name="description">أيّ أقسام الكتالوج تُحرّك الطلب</x-slot>

        @if (! $hasData)
            <p class="text-sm text-gray-500 dark:text-gray-400">لا مبيعات في آخر 30 يومًا بعد — يظهر الأداء مع أوّل الطلبات.</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($rows as $r)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->name }}</span>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">
                                {{ (int) $r->qty }} نسخة
                                @if ($canFinancials)
                                    · <span class="font-bold text-primary-600 dark:text-primary-400">{{ number_format((float) $r->revenue) }} ج.م</span>
                                @endif
                            </span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden">
                            @php
                                $pct = $canFinancials
                                    ? ((float) $r->revenue / $maxRevenue) * 100
                                    : ((float) $r->qty / $maxQty) * 100;
                            @endphp
                            <div class="h-full rounded-full bg-primary-500" style="width: {{ max(3, round($pct)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
