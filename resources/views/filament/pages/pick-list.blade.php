{{--
    قالب قائمة التجهيز. كل نص ظاهر يأتي من App\Filament\Pages\PickList::labels()
    ومن بيانات الطلب — لا نص مثبّت هنا (بند 6.4، على نمط قالب why-icon القائم).

    لماذا CSS خالص لا Tailwind؟ لوحة الأدمن تستعمل CSS الجاهز المرفق مع Filament
    (لا theme مبني بـ Vite في AdminPanelProvider)، فأي فئة Tailwind لا يستعملها
    Filament نفسه لن توجد في الملف المُصرَّف. الأنماط أدناه مقصورة على .qsq-pick
    فلا تسرّب إلى بقية اللوحة.

    الطباعة: الألوان رمادية/سوداء عمدًا — بوليصة تعبئة تُطبع على طابعة منزلية،
    فالخلفيات الملوّنة تستهلك حبرًا ولا تُطبع أصلًا في الوضع الافتراضي.
--}}
<x-filament-panels::page>
    <style>
        .qsq-pick { color: #111827; }
        .qsq-pick h2 { font-size: 1.05rem; font-weight: 700; margin: 0 0 .25rem; }
        .qsq-pick .qsq-hint { font-size: .8rem; color: #6b7280; margin: 0 0 .75rem; }
        .qsq-pick .qsq-empty { font-size: .95rem; line-height: 1.9; }
        .qsq-pick .qsq-toolbar { margin-block-end: 1rem; }
        .qsq-pick .qsq-warn {
            font-size: .85rem;
            border: 1px solid #9ca3af;
            border-radius: .5rem;
            padding: .5rem .75rem;
            margin-block-end: 1rem;
        }
        .qsq-pick .qsq-card {
            border: 1px solid #d1d5db;
            border-radius: .75rem;
            padding: 1rem;
            margin-block-end: 1rem;
            background: #fff;
        }
        .qsq-pick table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .qsq-pick th, .qsq-pick td {
            border: 1px solid #d1d5db;
            padding: .4rem .5rem;
            text-align: start;
            vertical-align: top;
        }
        .qsq-pick th { background: #f3f4f6; font-weight: 700; }
        .qsq-pick .qsq-num { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .qsq-pick .qsq-box { display: inline-block; width: 1rem; height: 1rem; border: 1px solid #6b7280; }
        .qsq-pick .qsq-totals { font-size: .85rem; margin-block-start: .5rem; }
        .qsq-pick .qsq-totals span { margin-inline-end: 1.25rem; }
        .qsq-pick .qsq-slip-head {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1.5rem;
            font-weight: 700;
            margin-block-end: .5rem;
        }
        .qsq-pick .qsq-field { font-size: .875rem; line-height: 1.7; }
        .qsq-pick .qsq-field b { font-weight: 700; }
        .qsq-pick .qsq-collect {
            font-size: 1rem;
            font-weight: 700;
            border: 2px solid #111827;
            border-radius: .5rem;
            padding: .35rem .6rem;
            display: inline-block;
            margin-block: .5rem;
        }

        @media print {
            /* إخفاء هيكل اللوحة (فئات تخطيط Filament v3) وكل ما ليس بوليصة. */
            .fi-topbar, .fi-sidebar, .fi-sidebar-close-overlay, .fi-header,
            .fi-footer, .fi-body > .fi-sidebar, .qsq-no-print { display: none !important; }
            .fi-main-ctn, .fi-main, .fi-page, .fi-body {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
                background: #fff !important;
            }
            .qsq-pick .qsq-card {
                border: none;
                padding: 0;
                margin-block-end: 0;
                break-inside: avoid;
            }
            /* بوليصة لكل صفحة — آخر واحدة بلا صفحة فارغة بعدها. */
            .qsq-pick .qsq-slip { break-after: page; padding-block: .5rem; }
            .qsq-pick .qsq-slip:last-child { break-after: auto; }
            .qsq-pick .qsq-summary { break-after: page; }
            .qsq-pick th { background: transparent !important; }
            .qsq-pick, .qsq-pick * { color: #000 !important; }
        }

        @page { margin: 12mm; }
    </style>

    <div class="qsq-pick">
        @if ($orders->isEmpty())
            <p class="qsq-empty">{{ $labels['empty'] }}</p>
        @else
            @if ($truncated)
                <p class="qsq-warn">{{ $labels['truncated'] }}</p>
            @endif

            <div class="qsq-no-print qsq-toolbar">
                <x-filament::button icon="heroicon-m-printer" x-on:click="window.print()">
                    {{ $labels['print'] }}
                </x-filament::button>
            </div>

            {{-- ١) ملخّص السحب من المخزن --}}
            <section class="qsq-card qsq-summary">
                <h2>{{ $labels['summary_heading'] }}</h2>
                <p class="qsq-hint">{{ $labels['summary_hint'] }}</p>

                <table>
                    <thead>
                        <tr>
                            <th class="qsq-num">{{ $labels['slip_packed'] }}</th>
                            <th>{{ $labels['col_book'] }}</th>
                            <th>{{ $labels['col_sku'] }}</th>
                            <th class="qsq-num">{{ $labels['col_quantity'] }}</th>
                            <th class="qsq-num">{{ $labels['col_orders'] }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr>
                                <td class="qsq-num"><span class="qsq-box"></span></td>
                                <td>{{ $row['title'] }}</td>
                                <td>{{ $row['sku'] !== '' ? $row['sku'] : $labels['dash'] }}</td>
                                <td class="qsq-num">{{ $row['quantity'] }}</td>
                                <td class="qsq-num">{{ $row['orders_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="qsq-totals">
                    <span>{{ $labels['total_titles'] }}: {{ count($summary) }}</span>
                    <span>{{ $labels['total_copies'] }}: {{ collect($summary)->sum('quantity') }}</span>
                    <span>{{ $labels['total_orders'] }}: {{ $orders->count() }}</span>
                </p>
            </section>

            {{-- ٢) بوليصة تعبئة لكل طلب --}}
            <h2 class="qsq-no-print">{{ $labels['slips_heading'] }}</h2>

            @foreach ($orders as $order)
                @php
                    $collect = \App\Filament\Pages\PickList::amountToCollect($order);
                @endphp

                <section class="qsq-card qsq-slip">
                    <div class="qsq-slip-head">
                        <span>{{ $labels['slip_order'] }}: {{ $order->order_number }}</span>
                        <span>{{ $labels['slip_date'] }}: {{ $order->created_at?->format('Y-m-d H:i') ?? $labels['dash'] }}</span>
                    </div>

                    <div class="qsq-field">
                        <div><b>{{ $labels['slip_customer'] }}:</b> {{ $order->customer_name }}</div>
                        <div>
                            <b>{{ $labels['slip_phone'] }}:</b> {{ $order->customer_phone }}
                            @if (filled($order->customer_phone_alt))
                                — <b>{{ $labels['slip_phone_alt'] }}:</b> {{ $order->customer_phone_alt }}
                            @endif
                        </div>
                        <div><b>{{ $labels['slip_address'] }}:</b> {{ \App\Filament\Pages\PickList::addressLine($order) }}</div>
                        @if (filled($order->address_notes))
                            <div><b>{{ $labels['slip_address_notes'] }}:</b> {{ $order->address_notes }}</div>
                        @endif
                        @if (filled($order->shipping_company) || filled($order->tracking_number))
                            <div>
                                <b>{{ $labels['slip_shipping'] }}:</b>
                                {{ $order->shipping_company ?: $labels['dash'] }} / {{ $order->tracking_number ?: $labels['dash'] }}
                            </div>
                        @endif
                        <div><b>{{ $labels['slip_payment'] }}:</b> {{ \App\Filament\Pages\PickList::paymentLine($order) }}</div>
                        @if (filled($order->customer_note))
                            <div><b>{{ $labels['slip_note'] }}:</b> {{ $order->customer_note }}</div>
                        @endif
                    </div>

                    @if ($collect !== null)
                        <div class="qsq-collect">{{ $labels['slip_collect'] }}: {{ $collect }}</div>
                    @else
                        <div class="qsq-field"><b>{{ $labels['slip_total'] }}:</b> {{ $order->grand_total }}</div>
                    @endif

                    @if ($order->items->isEmpty())
                        <p class="qsq-hint">{{ $labels['slip_no_items'] }}</p>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th class="qsq-num">{{ $labels['slip_packed'] }}</th>
                                    <th>{{ $labels['col_book'] }}</th>
                                    <th class="qsq-num">{{ $labels['col_quantity'] }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td class="qsq-num"><span class="qsq-box"></span></td>
                                        <td>{{ $item->book_title }}</td>
                                        <td class="qsq-num">{{ $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
