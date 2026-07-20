<x-filament-panels::page>
    {{-- أنماط مضمّنة (بلا اعتماد على Tailwind المخصّص، فتُرسَم مهما كان بناء Filament).
         تتبع وضع Filament الداكن عبر .fi-theme-dark / html.dark، وتحترم prefers-reduced-motion. --}}
    <style>
        .opsd { --card:#fff; --bg:#F7F4FB; --ink:#2E2440; --soft:#6E6280; --faint:#A99FB6; --line:rgba(46,36,64,.12);
            --primary:#7C3AED; --pri-soft:#F1EAFB; --success:#1E9E6A; --suc-soft:#E1F5EC; --warning:#E08A00; --warn-soft:#FCEFD6;
            --danger:#DC2F2F; --dan-soft:#FBE4E4; --teal:#12B3A6; display:flex; flex-direction:column; gap:22px; direction:rtl; }
        .dark .opsd, .fi-theme-dark .opsd { --card:#1F1A2E; --bg:#16121F; --ink:#F1E9FA; --soft:#B9A9CE; --faint:#7d6f90; --line:rgba(255,255,255,.12);
            --pri-soft:rgba(124,58,237,.16); --suc-soft:rgba(30,158,106,.16); --warn-soft:rgba(224,138,0,.16); --dan-soft:rgba(220,47,47,.16); }
        .opsd-sec > h2 { font-size:14px; font-weight:800; color:var(--soft); margin:0 0 12px; display:flex; align-items:center; gap:8px; }
        .opsd-grid { display:grid; gap:12px; }
        .g5{ grid-template-columns:repeat(5,1fr);} .g3{ grid-template-columns:repeat(3,1fr);} .g2{ grid-template-columns:repeat(2,1fr);}
        @media(max-width:1024px){ .opsd .g5{ grid-template-columns:repeat(3,1fr);} .opsd .g2{ grid-template-columns:1fr;} }
        @media(max-width:640px){ .opsd .g5,.opsd .g3{ grid-template-columns:repeat(2,1fr);} }
        .opsd-card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px; }
        .opsd-card h3 { font-size:14px; font-weight:800; color:var(--ink); margin:0 0 12px; }
        .opsd-stat .l { font-size:12px; font-weight:600; color:var(--soft); }
        .opsd-stat .v { font-size:24px; font-weight:800; margin-top:3px; font-variant-numeric:tabular-nums; color:var(--ink); }
        .opsd-stat .s { font-size:11.5px; color:var(--faint); }
        .v-success{ color:var(--success)!important; } .v-warning{ color:var(--warning)!important; }
        .opsd-live { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--soft); }
        .opsd-dot { width:8px; height:8px; border-radius:50%; background:var(--success); box-shadow:0 0 0 0 rgba(30,158,106,.5); animation:opsdPulse 1.8s infinite; }
        @keyframes opsdPulse { 0%{ box-shadow:0 0 0 0 rgba(30,158,106,.5);} 70%{ box-shadow:0 0 0 7px rgba(30,158,106,0);} 100%{ box-shadow:0 0 0 0 rgba(30,158,106,0);} }
        .opsd-q { background:var(--card); border:1px solid var(--line); border-inline-start:4px solid var(--c); border-radius:14px; padding:14px 16px; }
        .opsd-q .t { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .opsd-q .t b { font-size:14px; font-weight:800; color:var(--ink); display:flex; align-items:center; gap:8px; }
        .opsd-q .n { font-size:21px; font-weight:800; color:var(--c); font-variant-numeric:tabular-nums; }
        .opsd-q .note { font-size:12px; color:var(--soft); margin:6px 0 0; }
        .fn { display:flex; flex-direction:column; gap:8px; }
        .fn-row { display:grid; grid-template-columns:78px 1fr 42px; align-items:center; gap:9px; font-size:12px; color:var(--soft); }
        .fn-bar { height:20px; border-radius:5px; display:flex; align-items:center; padding-inline-start:7px; color:#fff; font-weight:800; font-size:11px; min-width:22px; }
        .opsd-legend { display:flex; flex-direction:column; gap:5px; font-size:12.5px; color:var(--ink); }
        .opsd-legend i { width:11px; height:11px; border-radius:3px; display:inline-block; margin-inline-end:6px; }
        table.opsd-t { width:100%; border-collapse:collapse; font-size:13px; }
        .opsd-t th { text-align:start; font-size:11px; color:var(--soft); font-weight:600; padding:7px 10px; }
        .opsd-t td { padding:8px 10px; border-top:1px solid var(--line); color:var(--ink); }
        .num { font-variant-numeric:tabular-nums; color:var(--soft); }
        .badge { display:inline-block; font-size:11px; font-weight:800; padding:3px 9px; border-radius:20px; white-space:nowrap; }
        .b-danger{ background:var(--dan-soft); color:var(--danger);} .b-warning{ background:var(--warn-soft); color:var(--warning);}
        .b-success{ background:var(--suc-soft); color:var(--success);} .b-gray{ background:var(--line); color:var(--soft);}
        .b-primary{ background:var(--pri-soft); color:var(--primary);}
        .rank { width:24px; height:24px; border-radius:7px; background:var(--pri-soft); color:var(--primary); display:inline-grid; place-items:center; font-size:12px; font-weight:800; flex:none; }
        .bar-track { height:7px; border-radius:5px; background:var(--line); overflow:hidden; margin-top:4px; }
        .bar-fill { height:100%; border-radius:5px; background:var(--primary); }
        .mrow { display:flex; align-items:center; gap:11px; }
        .mrow .top { display:flex; justify-content:space-between; gap:8px; font-size:13.5px; color:var(--ink); }
        .mbar { display:flex; align-items:flex-end; gap:6px; height:130px; margin-top:12px; }
        .mbar .col { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
        .mbar .col .b { width:100%; border-radius:5px 5px 0 0; background:var(--primary); min-height:4px; }
        .mbar .col small { font-size:10px; color:var(--faint); }
        .rec { display:flex; align-items:flex-start; gap:11px; border:1px solid var(--rc); background:var(--rcs); border-radius:12px; padding:13px 15px; }
        .rec .ic { font-size:20px; flex:none; }
        .rec p { font-size:13.5px; color:var(--ink); margin:5px 0 0; line-height:1.6; }
        .note-box { background:var(--warn-soft); border:1px solid var(--warning); color:var(--ink); border-radius:10px; padding:8px 12px; font-size:12px; }
        @media (prefers-reduced-motion:reduce){ .opsd-dot{ animation:none; } }
    </style>

    <div class="opsd" wire:poll.30s>

        {{-- ══ 1 · نظرة عامة ══ --}}
        <section class="opsd-sec">
            <div class="opsd-grid g5">
                <div class="opsd-card opsd-stat">
                    <div class="opsd-live"><span class="opsd-dot"></span> المتصفّحون الآن</div>
                    <div class="v" style="color:var(--success)">{{ $live['total'] }}</div>
                    <div class="s">{{ $live['guests'] }} زائر · {{ $live['members'] }} مسجَّل · آخر 5 دقائق</div>
                </div>
                <div class="opsd-card opsd-stat"><div class="l">طلبات اليوم</div><div class="v">{{ $overview['today'] ?? 0 }}</div><div class="s">هذا الأسبوع: {{ $overview['week'] ?? 0 }}</div></div>
                <div class="opsd-card opsd-stat"><div class="l">طلبات آخر 30 يومًا</div><div class="v">{{ $overview['month'] ?? 0 }}</div></div>
                @if ($canFin)
                    <div class="opsd-card opsd-stat"><div class="l">الإيراد المحقَّق (30ي)</div><div class="v v-success">{{ number_format($overview['revenue'] ?? 0) }} <small style="font-size:14px;color:var(--soft)">ج.م</small></div><div class="s">مسلَّم/مكتمل</div></div>
                    <div class="opsd-card opsd-stat"><div class="l">متوسّط قيمة الطلب</div><div class="v v-success">{{ number_format($overview['aov'] ?? 0) }} <small style="font-size:14px;color:var(--soft)">ج.م</small></div><div class="s">{{ $overview['realized_n'] ?? 0 }} طلب محقَّق</div></div>
                @else
                    <div class="opsd-card opsd-stat" style="border-style:dashed"><div class="s" style="margin-top:14px">الأرقام المالية لمن يملك صلاحية «عرض الماليات».</div></div>
                @endif
            </div>
        </section>

        {{-- ══ 2 · تحتاج إجراءً ══ --}}
        <section class="opsd-sec">
            <h2><span style="font-size:16px">⚡</span> تحتاج إجراءً الآن</h2>
            <div class="opsd-grid g3">
                @foreach ([
                    ['تنتظر تأكيد واتساب', $queues['confirm'] ?? 0, '📞', 'danger', 'pending بلا تأكيد'],
                    ['إثباتات تنتظر المراجعة', $queues['proofs'] ?? 0, '🧾', 'warning', 'العميل حوّل وينتظر'],
                    ['مؤكّد بلا رقم تتبّع', $queues['no_tracking'] ?? 0, '📦', 'warning', 'جاهز ولم يُشحن'],
                    ['مشحون ولم يُسلَّم', $queues['shipped'] ?? 0, '🚚', 'primary', 'شحنات جارية'],
                    ['دفع يدوي معلّق', number_format($queues['manual_sum'] ?? 0).' ج.م', '💵', 'success', ($queues['manual_n'] ?? 0).' طلب لم يُؤكَّد'],
                    ['مخزون منخفض/نافد', $queues['low_stock'] ?? 0, '📉', 'danger', 'كتب تحتاج تعبئة'],
                ] as [$t, $c, $ic, $tone, $note])
                    <div class="opsd-q" style="--c:var(--{{ $tone }})">
                        <div class="t"><b><span style="font-size:16px">{{ $ic }}</span>{{ $t }}</b><span class="n">{{ $c }}</span></div>
                        <p class="note">{{ $note }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ══ 3 · العمليات ══ --}}
        <section class="opsd-sec">
            <div class="opsd-grid g2">
                {{-- قُمع --}}
                <div class="opsd-card">
                    <h3>قُمع حالات الطلب — آخر 30 يومًا</h3>
                    @php $ft = $funnel['total'] ?? 0; $steps = [['وارد',$ft,'#7C3AED'],['مؤكّد',$funnel['confirmed']??0,'#EC4899'],['قيد التجهيز',$funnel['processing']??0,'#F59E0B'],['مشحون',$funnel['shipped']??0,'#EAB308'],['مسلَّم',$funnel['delivered']??0,'#12B3A6'],['ملغى/مرفوض',$funnel['lost']??0,'#EF4444']]; @endphp
                    @if ($ft === 0)
                        <p class="s" style="color:var(--faint)">لا طلبات في آخر 30 يومًا بعد.</p>
                    @else
                        <div class="fn">
                            @foreach ($steps as [$nm, $n, $col])
                                <div class="fn-row"><span>{{ $nm }}</span><div class="fn-bar" style="width:{{ $ft>0?max(6,round($n/$ft*100)):6 }}%;background:{{ $col }}">{{ $n }}</div><span class="num" style="text-align:start">{{ $ft>0?round($n/$ft*100):0 }}%</span></div>
                            @endforeach
                        </div>
                    @endif
                </div>
                {{-- دونات + توقيت --}}
                <div style="display:flex;flex-direction:column;gap:12px">
                    <div class="opsd-card">
                        <h3>توزيع طرق الدفع — 30 يومًا</h3>
                        @php $pmN=['cod'=>'عند الاستلام','instapay'=>'إنستاباي','vodafone_cash'=>'فودافون كاش','bank_transfer'=>'تحويل بنكي','online_gateway'=>'بوابة أونلاين']; $pmC=['cod'=>'#7C3AED','instapay'=>'#12B3A6','vodafone_cash'=>'#EAB308','bank_transfer'=>'#EC4899','online_gateway'=>'#3B82F6']; $pt=array_sum($payments); $off=25; @endphp
                        @if ($pt === 0)
                            <p class="s" style="color:var(--faint)">لا مدفوعات بعد.</p>
                        @else
                            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                                <svg width="104" height="104" viewBox="0 0 42 42" style="flex:none">
                                    <circle cx="21" cy="21" r="15.9" fill="none" stroke="var(--line)" stroke-width="5"/>
                                    @foreach ($payments as $pm => $n)
                                        @php $pct = round($n/$pt*100); @endphp
                                        <circle cx="21" cy="21" r="15.9" fill="none" stroke="{{ $pmC[$pm]??'#999' }}" stroke-width="5" stroke-dasharray="{{ $pct }} {{ 100-$pct }}" stroke-dashoffset="{{ $off }}" transform="rotate(-90 21 21)"/>
                                        @php $off -= $pct; @endphp
                                    @endforeach
                                    <text x="21" y="22.5" text-anchor="middle" font-size="6" font-weight="800" fill="var(--ink)">{{ $pt }}</text>
                                </svg>
                                <div class="opsd-legend">
                                    @foreach ($payments as $pm => $n)
                                        <span><i style="background:{{ $pmC[$pm]??'#999' }}"></i>{{ $pmN[$pm]??$pm }} · {{ round($n/$pt*100) }}%</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="opsd-grid g3">
                        <div class="opsd-card opsd-stat"><div class="l">زمن التأكيد</div><div class="v">{{ $timing['confirm_n']>0?round(($timing['confirm_min']??0)/60,1).' س':'—' }}</div><div class="s">n={{ $timing['confirm_n'] }}</div></div>
                        <div class="opsd-card opsd-stat"><div class="l">مراجعة الإثبات</div><div class="v">{{ $timing['proof_n']>0?round(($timing['proof_min']??0)/60,1).' س':'—' }}</div><div class="s">n={{ $timing['proof_n'] }}</div></div>
                        <div class="opsd-card opsd-stat"><div class="l">معدّل الإلغاء</div><div class="v {{ $timing['cancel_pct']>=20?'v-warning':'' }}">{{ $timing['cancel_pct'] }}%</div><div class="s">{{ $timing['cancel_lost'] }} من {{ $timing['cancel_total'] }}</div></div>
                    </div>
                </div>
                {{-- محافظات --}}
                @if (! empty($governorates))
                    <div class="opsd-card" style="grid-column:1/-1">
                        <h3>التوزيع الجغرافي — أعلى المحافظات</h3>
                        <div style="overflow-x:auto"><table class="opsd-t">
                            <thead><tr><th>المحافظة</th><th>طلبات</th>@if ($canFin)<th>إيراد</th>@endif<th>مرتجع</th></tr></thead>
                            <tbody>
                                @foreach ($governorates as $g)
                                    <tr><td>{{ $g['name'] }}</td><td class="num">{{ $g['orders'] }}</td>@if ($canFin)<td class="num">{{ number_format($g['revenue']) }}</td>@endif<td><span class="badge {{ $g['lost_pct']>=25?'b-danger':($g['lost_pct']>=15?'b-warning':'b-success') }}">{{ $g['lost_pct'] }}%</span></td></tr>
                                @endforeach
                            </tbody>
                        </table></div>
                    </div>
                @endif
            </div>
        </section>

        {{-- ══ 4 · ذكاء المنتجات ══ --}}
        <section class="opsd-sec">
            <div class="opsd-grid g2">
                <div class="opsd-card">
                    <h3>الأكثر مبيعًا — 30 يومًا</h3>
                    @if (empty($topBooks))
                        <p class="s" style="color:var(--faint)">لا مبيعات بعد.</p>
                    @else
                        @php $mq = max(array_map(fn($r)=>$r['qty'],$topBooks))?:1; @endphp
                        <div style="display:flex;flex-direction:column;gap:10px">
                            @foreach ($topBooks as $i => $b)
                                <div class="mrow"><span class="rank">{{ $i+1 }}</span><div style="flex:1;min-width:0"><div class="top"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $b['title'] }}</span><span class="num" style="flex:none">{{ $b['qty'] }}@if ($canFin) · <b style="color:var(--primary)">{{ number_format($b['revenue']) }} ج.م</b>@endif</span></div><div class="bar-track"><div class="bar-fill" style="width:{{ max(4,round($b['qty']/$mq*100)) }}%"></div></div></div></div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="opsd-card">
                    <h3>تغطية المخزون — متى ينفد</h3>
                    @if (empty($coverage))
                        <p class="s" style="color:var(--faint)">لا كتب تُدار مخزونيًّا.</p>
                    @else
                        <div style="overflow-x:auto"><table class="opsd-t">
                            <thead><tr><th>الكتاب</th><th>مخزون</th><th>بيع 30ي</th><th>تغطية</th><th>الحالة</th></tr></thead>
                            <tbody>
                                @foreach ($coverage as $c)
                                    @php if($c['out']){$lb='نفد';$to='danger';}elseif($c['cover']===null){$lb='راكد';$to='gray';}elseif($c['cover']<=7){$lb='حرج';$to='danger';}elseif($c['cover']<=14){$lb='منخفض';$to='warning';}else{$lb='كافٍ';$to='success';} @endphp
                                    <tr><td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c['title'] }}</td><td class="num">{{ $c['stock'] }}</td><td class="num">{{ $c['sold'] }}</td><td class="num">{{ $c['cover']===null?'—':$c['cover'].' يوم' }}</td><td><span class="badge b-{{ $to }}">{{ $lb }}</span></td></tr>
                                @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
                @if (! empty($categories))
                    <div class="opsd-card" style="grid-column:1/-1">
                        <h3>أداء الأقسام — 30 يومًا</h3>
                        @php $cm = ($canFin?max(array_map(fn($r)=>$r['revenue'],$categories)):max(array_map(fn($r)=>$r['qty'],$categories)))?:1; @endphp
                        <div style="display:flex;flex-direction:column;gap:10px">
                            @foreach ($categories as $c)
                                <div><div class="top"><span style="font-weight:600">{{ $c['name'] }}</span><span class="num">{{ $c['qty'] }} نسخة@if ($canFin) · <b style="color:var(--primary)">{{ number_format($c['revenue']) }} ج.م</b>@endif</span></div><div class="bar-track" style="height:8px"><div class="bar-fill" style="width:{{ max(3,round(($canFin?$c['revenue']:$c['qty'])/$cm*100)) }}%"></div></div></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- ══ 5 · الموسمية ══ --}}
        <section class="opsd-sec">
            <div class="opsd-card">
                <h3>الطلب الشهري — تكشف الموسمية مع تراكم الأشهر</h3>
                @php $mN=['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']; @endphp
                @if (empty($monthly))
                    <p class="s" style="color:var(--faint)">لا طلبات بعد — تظهر الأعمدة مع أوّل الطلبات.</p>
                @else
                    @php $mm = max(array_map(fn($r)=>$r['orders'],$monthly))?:1; @endphp
                    <div class="mbar">
                        @foreach ($monthly as $m)
                            @php [$y,$mo]=explode('-',$m['ym']); @endphp
                            <div class="col"><small>{{ $m['orders'] }}</small><div class="b" style="height:{{ max(4,round($m['orders']/$mm*100)) }}%"></div><small>{{ $mN[(int)$mo]??$mo }}</small></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- ══ 6 · التوصيات ══ --}}
        @if (! empty($recommendations))
            <section class="opsd-sec">
                <h2><span style="font-size:16px">🧠</span> التوصيات الذكية</h2>
                <div style="display:flex;flex-direction:column;gap:10px">
                    @foreach ($recommendations as $r)
                        @php $tn = $r['p']==='urgent'?'danger':($r['p']==='important'?'warning':'primary'); $pl=$r['p']==='urgent'?'عاجل':($r['p']==='important'?'مهمّ':'عادي'); @endphp
                        <div class="rec" style="--rc:var(--{{ $tn }});--rcs:var(--{{ $tn==='danger'?'dan':($tn==='warning'?'warn':'pri') }}-soft)">
                            <span class="ic">{{ $r['icon'] }}</span>
                            <div><span class="badge b-{{ $tn }}">{{ $pl }}</span><p>{{ $r['msg'] }}</p></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</x-filament-panels::page>
