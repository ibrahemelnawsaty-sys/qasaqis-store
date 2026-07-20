<x-filament-widgets::widget>
    @php($r = $this->getReport())

    <div class="fin-scope">
        {{-- ===== المؤشّرات النجمة ===== --}}
        <div class="fin-hero">
            @foreach ($r['hero'] as $h)
                <div class="fin-hero-card fin-t-{{ $h['tone'] }}">
                    <div class="fin-hero-label">{{ $h['label'] }}</div>
                    <div class="fin-hero-value">
                        @if ($h['na'])
                            <span class="fin-na">غير متاح</span>
                        @else
                            <span class="fin-num" dir="ltr">{{ $h['num'] }}</span><span class="fin-unit">{{ $h['unit'] }}</span>
                        @endif
                    </div>
                    <div class="fin-hero-sub">{{ $h['sub'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- ===== النطاقات المجمّعة ===== --}}
        @foreach ($r['bands'] as $band)
            <section class="fin-band" aria-label="{{ $band['title'] }}">
                <header class="fin-band-head">
                    <h2 class="fin-band-title">{{ $band['title'] }}</h2>
                    <span class="fin-badge fin-badge-{{ $band['badge_tone'] }}">{{ $band['badge'] }}</span>
                </header>
                <div class="fin-grid">
                    @foreach ($band['cards'] as $c)
                        <div class="fin-card fin-t-{{ $c['tone'] }}">
                            <div class="fin-card-label">{{ $c['label'] }}</div>
                            <div class="fin-card-value">
                                @if ($c['na'])
                                    <span class="fin-na">غير متاح</span>
                                @else
                                    <span class="fin-num" dir="ltr">{{ $c['num'] }}</span>@if ($c['unit'])<span class="fin-unit">{{ $c['unit'] }}</span>@endif
                                @endif
                            </div>
                            @if ($c['sub'])
                                <div class="fin-card-sub">{{ $c['sub'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- ===== قيد التحصيل — سكان مغاير، بطاقة مستقلّة خارج الإيراد المحقّق ===== --}}
        <div class="fin-note fin-t-warn">
            <span class="fin-note-label">قيد التحصيل</span>
            <span class="fin-note-val">
                @if ($r['pipeline']['na'])
                    <span class="fin-na">غير متاح</span>
                @else
                    <span class="fin-num" dir="ltr">{{ $r['pipeline']['num'] }}</span> <span class="fin-unit">{{ $r['pipeline']['unit'] }}</span>
                @endif
            </span>
            <span class="fin-note-sub">طلبات مؤكّدة لم تُحقَّق بعد — خارج الإيراد المحقّق</span>
        </div>

        {{-- ===== جسر الربح: من المساهمة إلى صافي الربح ===== --}}
        @if ($r['bridge'])
            @php($b = $r['bridge'])
            <section class="fin-band fin-bridge" aria-label="من المساهمة إلى صافي الربح">
                <header class="fin-band-head">
                    <h2 class="fin-band-title">من المساهمة إلى صافي الربح</h2>
                    <span class="fin-badge fin-badge-neutral">على الطلبات مكتملة البيانات</span>
                </header>

                <div class="fin-bridge-row fin-bridge-start">
                    <span class="fin-bridge-label">{{ $b['contribution']['label'] }}</span>
                    <span class="fin-bridge-bar"><i style="inline-size:{{ $b['contribution_width'] }}%"></i></span>
                    <span class="fin-bridge-amt fin-t-{{ $b['contribution']['tone'] }}">
                        <span class="fin-num" dir="ltr">{{ $b['contribution']['num'] }}</span> <span class="fin-unit">{{ $b['contribution']['unit'] }}</span>
                    </span>
                </div>

                @foreach ($b['steps'] as $st)
                    <div class="fin-bridge-row">
                        <span class="fin-bridge-label">
                            {{ $st['label'] }}
                            @if (!empty($st['note']))<em class="fin-bridge-note">{{ $st['note'] }}</em>@endif
                        </span>
                        <span class="fin-bridge-bar fin-bridge-bar-neg"><i style="inline-size:{{ $st['width'] }}%"></i></span>
                        <span class="fin-bridge-amt fin-t-neg">
                            <span class="fin-num" dir="ltr">@if ((float) $st['value'] > 0)−@endif{{ number_format((float) $st['value'], 2) }}</span> <span class="fin-unit">ج.م</span>
                        </span>
                    </div>
                @endforeach

                <div class="fin-bridge-row fin-bridge-end">
                    <span class="fin-bridge-label">{{ $b['net']['label'] }}</span>
                    <span class="fin-bridge-bar {{ $b['net_negative'] ? 'fin-bridge-bar-neg' : 'fin-bridge-bar-pos' }}"><i style="inline-size:{{ $b['net_width'] }}%"></i></span>
                    <span class="fin-bridge-amt fin-t-{{ $b['net']['tone'] }}">
                        @if ($b['net']['na'])
                            <span class="fin-na">غير متاح</span>
                        @else
                            <span class="fin-num" dir="ltr">{{ $b['net']['num'] }}</span> <span class="fin-unit">{{ $b['net']['unit'] }}</span>
                        @endif
                    </span>
                </div>
            </section>
        @endif

        {{-- ===== تقسيم الإيراد حسب طريقة الدفع ===== --}}
        @if (!empty($r['breakdown']['rows']))
            <section class="fin-band" aria-label="الإيراد حسب طريقة الدفع">
                <header class="fin-band-head">
                    <h2 class="fin-band-title">الإيراد حسب طريقة الدفع</h2>
                </header>
                <div class="fin-bd">
                    @foreach ($r['breakdown']['rows'] as $row)
                        <div class="fin-bd-row">
                            <span class="fin-bd-label">{{ $row['label'] }}</span>
                            <span class="fin-bd-track"><i style="inline-size:{{ $row['width'] }}%"></i></span>
                            <span class="fin-bd-net"><span class="fin-num" dir="ltr">{{ $row['net'] }}</span> <span class="fin-unit">ج.م</span></span>
                            <span class="fin-bd-orders"><span dir="ltr">{{ $row['orders'] }}</span> طلب</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
