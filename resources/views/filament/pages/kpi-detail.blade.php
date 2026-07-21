{{--
    قالب صفحة تفاصيل مؤشّر (KPI). كل البيانات من App\Filament\Pages\KpiDetail
    وسِجل OpsKpi — الرقم الرئيسي محسوب من نفس استعلام البطاقة فلا ينحرف (بند 1.1).

    CSS خالص (لا Tailwind مخصّص) كبقية لوحة العمليات، يتبع الوضع الداكن عبر
    .fi-theme-dark / html.dark، وخصائص منطقية للاتجاه (بند 6.2).
--}}
<x-filament-panels::page>
    @php
        $money = static fn ($v): string => number_format((float) $v, 0);
        $isMoney = in_array($def['metric'], ['sum', 'avg'], true);
    @endphp

    <style>
        .kpid { --card:#fff; --bg:#F7F4FB; --ink:#2E2440; --soft:#6E6280; --faint:#A99FB6; --line:rgba(46,36,64,.12); --accent:#6E2FB0; }
        .dark .kpid, .fi-theme-dark .kpid { --card:#1F1A2E; --bg:#16121F; --ink:#F1E9FA; --soft:#B9A9CE; --faint:#7d6f90; --line:rgba(255,255,255,.12); --accent:#C79BF0; }
        .kpid { color:var(--ink); display:flex; flex-direction:column; gap:18px; }
        .kpid a { color:inherit; }
        .kpid-back { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:var(--soft); text-decoration:none; }
        .kpid-back:hover { color:var(--accent); }
        .kpid-hero { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:22px; }
        .kpid-hero .top { display:flex; align-items:center; gap:10px; }
        .kpid-hero .ic { font-size:26px; }
        .kpid-hero h2 { font-size:18px; font-weight:800; margin:0; }
        .kpid-hero .val { font-size:40px; font-weight:900; margin-top:10px; font-variant-numeric:tabular-nums; line-height:1; }
        .kpid-hero .val small { font-size:18px; font-weight:700; color:var(--soft); }
        .kpid-hero .hint { font-size:13.5px; color:var(--soft); margin-top:10px; }
        .kpid-break { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .kpid-break .b { background:var(--bg); border:1px solid var(--line); border-radius:12px; padding:8px 14px; font-size:13px; }
        .kpid-break .b b { font-weight:800; font-variant-numeric:tabular-nums; }
        .kpid-tablewrap { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:6px; overflow-x:auto; }
        .kpid-empty { padding:26px; text-align:center; color:var(--soft); font-size:14px; }
        table.kpid-table { inline-size:100%; border-collapse:collapse; font-size:13.5px; }
        .kpid-table th, .kpid-table td { padding:11px 12px; text-align:start; border-bottom:1px solid var(--line); white-space:nowrap; }
        .kpid-table th { font-size:12px; font-weight:800; color:var(--soft); }
        .kpid-table tbody tr:last-child td { border-bottom:0; }
        .kpid-table tbody tr:hover { background:var(--bg); }
        .kpid-table .num { font-variant-numeric:tabular-nums; }
        .kpid-pill { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:800; background:color-mix(in srgb,var(--accent) 14%,transparent); color:var(--accent); }
        .kpid-open { font-weight:800; color:var(--accent); text-decoration:none; }
        .kpid-pager { display:flex; align-items:center; gap:12px; justify-content:center; padding:6px 0 2px; }
        .kpid-pg { background:var(--card); border:1px solid var(--line); border-radius:999px; padding:7px 16px; font-size:13px; font-weight:700; color:var(--ink); cursor:pointer; }
        .kpid-pg:hover { border-color:var(--accent); color:var(--accent); }
        .kpid-pg.is-off { opacity:.45; cursor:default; }
        .kpid-pg-info { font-size:12.5px; color:var(--soft); font-variant-numeric:tabular-nums; }
        /* شريط أدوات الجدول + التصدير */
        .kpid-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:6px 8px 10px; }
        .kpid-count { font-size:12.5px; color:var(--soft); font-variant-numeric:tabular-nums; }
        .kpid-export { display:inline-flex; align-items:center; gap:6px; background:var(--accent); color:#fff; border:0; border-radius:999px; padding:8px 16px; font-size:13px; font-weight:800; cursor:pointer; }
        .kpid-export:hover { filter:brightness(1.06); }
        .kpid-export:disabled { opacity:.6; cursor:default; }
        /* عناوين قابلة للفرز */
        .kpid-th { cursor:pointer; user-select:none; white-space:nowrap; }
        .kpid-th:hover { color:var(--accent); }
        .kpid-th.on { color:var(--accent); }
    </style>

    <div class="kpid">
        <a class="kpid-back" href="{{ \App\Filament\Pages\OpsDashboard::getUrl() }}"><span aria-hidden="true">→</span> العودة إلى لوحة العمليات</a>

        {{-- البطاقة الرئيسية: الرقم نفسه المعروض على اللوحة --}}
        <div class="kpid-hero">
            <div class="top">
                <span class="ic" aria-hidden="true">{{ $def['icon'] }}</span>
                <h2>{{ $def['label'] }}@if (! is_null($valueLabel ?? null)): <span style="color:var(--soft)">{{ $valueLabel }}</span>@endif</h2>
            </div>
            <div class="val">
                {{ $money($metric) }}@if ($isMoney) <small>ج.م</small>@endif
            </div>
            <p class="hint">{{ $def['hint'] }}</p>

            {{-- التفصيل يُعرض فقط حين يضيف معلومة تتجاوز الرقم الرئيسي (أكثر من فئة). --}}
            @if (count($breakdown) > 1)
                <div class="kpid-break">
                    @foreach ($breakdown as $b)
                        <span class="b">{{ $b['label'] }}: <b>{{ number_format($b['value']) }}</b></span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- الصفوف الأساسية خلف الرقم --}}
        @php
            // عنوان عمود قابل للفرز (النقر يفرز/يبدّل الاتجاه). التسمية والعمود ثابتان
            // في القالب فالإخراج الخام آمن؛ الفرز نفسه موثّق بقائمة بيضاء خادميًّا.
            $sc = $this->sortCol; $sd = $this->sortDir;
            $th = function (string $col, string $label, string $cls = '') use ($sc, $sd): string {
                $arrow = $sc === $col ? ($sd === 'asc' ? ' ▲' : ' ▼') : '';
                $on = $sc === $col ? ' on' : '';
                return '<th class="kpid-th'.$on.($cls ? ' '.$cls : '').'" wire:click="sort(\''.$col.'\')">'.$label.$arrow.'</th>';
            };
        @endphp

        @unless ($rows->isEmpty())
            <div class="kpid-toolbar">
                <span class="kpid-count">{{ number_format($rows->total()) }} سجلّ · صفحة {{ $rows->currentPage() }}/{{ $rows->lastPage() }}</span>
                <button type="button" class="kpid-export" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                    <span wire:loading.remove wire:target="export">⬇ تصدير CSV</span>
                    <span wire:loading wire:target="export">…يُصدّر</span>
                </button>
            </div>
        @endunless

        <div class="kpid-tablewrap">
            @if ($rows->isEmpty())
                <p class="kpid-empty">لا توجد سجلّات مطابقة لهذا المؤشّر الآن. 🎉</p>
            @elseif ($def['model'] === \App\Models\Book::class)
                <table class="kpid-table">
                    <thead><tr>
                        {!! $th('title', 'الكتاب') !!}<th>الكود</th>{!! $th('stock_quantity', 'المخزون', 'num') !!}{!! $th('stock_status', 'الحالة') !!}<th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $book)
                            <tr>
                                <td>{{ $book->title }}</td>
                                <td class="num">{{ $book->sku ?: '—' }}</td>
                                <td class="num">{{ (int) $book->stock_quantity }}</td>
                                <td>@if ($book->stock_status === 'out_of_stock' || $book->stock_quantity <= 0)<span class="kpid-pill">نافد</span>@else منخفض @endif</td>
                                <td><a class="kpid-open" href="{{ \App\Filament\Resources\BookResource::getUrl('edit', ['record' => $book->getKey()]) }}">فتح ←</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif ($def['model'] === \App\Models\PaymentProof::class)
                <table class="kpid-table">
                    <thead><tr>
                        <th>الطلب</th><th>العميل</th><th class="num">الإجمالي</th>{!! $th('review_status', 'حالة المراجعة') !!}{!! $th('created_at', 'التاريخ') !!}<th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $proof)
                            <tr>
                                <td class="num">{{ $proof->order?->order_number ?? '—' }}</td>
                                <td>{{ $proof->order?->customer_name ?? '—' }}</td>
                                <td class="num">{{ $money($proof->order?->grand_total ?? 0) }} ج.م</td>
                                <td><span class="kpid-pill">{{ \App\Filament\Resources\OrderResource::REVIEW_STATUS_LABELS[$proof->review_status] ?? $proof->review_status }}</span></td>
                                <td class="num">{{ $proof->created_at?->translatedFormat('Y/m/d H:i') }}</td>
                                <td>@if ($proof->order_id)<a class="kpid-open" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $proof->order_id]) }}">فتح ←</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table class="kpid-table">
                    <thead><tr>
                        {!! $th('order_number', 'رقم الطلب', 'num') !!}{!! $th('customer_name', 'العميل') !!}<th>الهاتف</th>{!! $th('governorate', 'المحافظة') !!}{!! $th('status', 'الحالة') !!}{!! $th('payment_status', 'الدفع') !!}{!! $th('grand_total', 'الإجمالي', 'num') !!}{!! $th('created_at', 'التاريخ') !!}<th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $order)
                            <tr>
                                <td class="num">{{ $order->order_number }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td class="num" dir="ltr" style="text-align:start">{{ $order->customer_phone }}</td>
                                <td>{{ $order->governorate ?: '—' }}</td>
                                <td><span class="kpid-pill">{{ \App\Filament\Resources\OrderResource::STATUS_LABELS[$order->status] ?? $order->status }}</span></td>
                                <td>{{ \App\Filament\Resources\OrderResource::PAYMENT_STATUS_LABELS[$order->payment_status] ?? $order->payment_status }}</td>
                                <td class="num">{{ $money($order->grand_total) }} ج.م</td>
                                <td class="num">{{ $order->created_at?->translatedFormat('Y/m/d H:i') }}</td>
                                <td><a class="kpid-open" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order->getKey()]) }}">فتح ←</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($rows->hasPages())
            <div class="kpid-pager">
                @if ($rows->onFirstPage())
                    <span class="kpid-pg is-off">السابق</span>
                @else
                    <button type="button" class="kpid-pg" wire:click="previousPage" wire:loading.attr="disabled">السابق</button>
                @endif
                <span class="kpid-pg-info">صفحة {{ $rows->currentPage() }} من {{ $rows->lastPage() }} · {{ number_format($rows->total()) }} سجلّ</span>
                @if ($rows->hasMorePages())
                    <button type="button" class="kpid-pg" wire:click="nextPage" wire:loading.attr="disabled">التالي</button>
                @else
                    <span class="kpid-pg is-off">التالي</span>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
