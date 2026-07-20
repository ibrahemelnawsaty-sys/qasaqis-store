<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">تغطية المخزون — متى ينفد</x-slot>
        <x-slot name="description">سرعة البيع مقابل المتاح · الأشدّ إلحاحًا أوّلًا</x-slot>

        @if (! $hasData)
            <p class="text-sm text-gray-500 dark:text-gray-400">لا كتب تُدار مخزونيًّا بعد.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 text-start">
                            <th class="py-2 pe-3 font-medium text-start">الكتاب</th>
                            <th class="py-2 px-3 font-medium tabular-nums">المخزون</th>
                            <th class="py-2 px-3 font-medium tabular-nums">بيع 30ي</th>
                            <th class="py-2 px-3 font-medium tabular-nums">التغطية</th>
                            <th class="py-2 ps-3 font-medium">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                if ($r['out']) {
                                    $label = 'نفد'; $tone = 'danger';
                                } elseif ($r['cover'] === null) {
                                    $label = 'راكد'; $tone = 'gray';
                                } elseif ($r['cover'] <= 7) {
                                    $label = 'حرج'; $tone = 'danger';
                                } elseif ($r['cover'] <= 14) {
                                    $label = 'منخفض'; $tone = 'warning';
                                } else {
                                    $label = 'كافٍ'; $tone = 'success';
                                }
                            @endphp
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pe-3 text-gray-800 dark:text-gray-200">{{ $r['title'] }}</td>
                                <td class="py-2.5 px-3 tabular-nums text-gray-600 dark:text-gray-400">{{ $r['stock'] }}</td>
                                <td class="py-2.5 px-3 tabular-nums text-gray-600 dark:text-gray-400">{{ $r['sold30'] }}</td>
                                <td class="py-2.5 px-3 tabular-nums text-gray-600 dark:text-gray-400">
                                    {{ $r['cover'] === null ? '—' : $r['cover'] . ' يوم' }}
                                </td>
                                <td class="py-2.5 ps-3">
                                    <x-filament::badge :color="$tone">{{ $label }}</x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
