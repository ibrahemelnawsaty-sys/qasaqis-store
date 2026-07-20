<x-filament-widgets::widget>
    <div class="fin-scope">
        <section class="fin-band" aria-label="الأداء اليومي">
            <header class="fin-band-head">
                <h2 class="fin-band-title">الأداء اليومي</h2>
                <span class="fin-badge fin-badge-neutral">صافي المبيعات لكل يوم · الأحدث أولًا</span>
            </header>

            @php($rows = $this->getRows())

            <table class="fin-day">
                <thead>
                    <tr>
                        <th scope="col">التاريخ</th>
                        <th scope="col" class="fin-day-col-day">اليوم</th>
                        <th scope="col">الطلبات</th>
                        <th scope="col">صافي المبيعات (ج.م)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row['orders'] === 0 ? 'is-empty' : '' }}">
                            <td class="fin-day-date fin-num" dir="ltr">{{ $row['date'] }}</td>
                            <td class="fin-day-col-day">{{ $row['day'] }}</td>
                            <td class="fin-num" dir="ltr">{{ number_format($row['orders']) }}</td>
                            <td>
                                <span class="fin-day-net-cell">
                                    <span class="fin-day-bar"><i aria-hidden="true" style="inline-size:{{ $row['bar'] }}%"></i></span>
                                    <span class="fin-num" dir="ltr">{{ $row['net'] }}</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="fin-day-empty">لا بيانات في هذا النطاق.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-filament-widgets::widget>
