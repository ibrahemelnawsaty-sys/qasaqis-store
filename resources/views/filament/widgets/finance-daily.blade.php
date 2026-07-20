<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">الأداء اليومي</x-slot>
        <x-slot name="description">صافي المبيعات المحقّقة لكل يوم — بتوقيت القاهرة، الأحدث أولًا.</x-slot>

        @php($rows = $this->getRows())

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-start">
                <thead>
                    <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                        <th class="py-2 px-3 text-start font-medium">التاريخ</th>
                        <th class="py-2 px-3 text-start font-medium">اليوم</th>
                        <th class="py-2 px-3 text-start font-medium">الطلبات</th>
                        <th class="py-2 px-3 text-start font-medium">صافي المبيعات (ج.م)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5 {{ $row['orders'] === 0 ? 'opacity-50' : '' }}">
                            <td class="py-2 px-3 font-mono" dir="ltr">{{ $row['date'] }}</td>
                            <td class="py-2 px-3">{{ $row['day'] }}</td>
                            <td class="py-2 px-3 tabular-nums">{{ number_format($row['orders']) }}</td>
                            <td class="py-2 px-3 tabular-nums font-semibold">{{ $row['net'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                لا بيانات في هذا النطاق.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
