<x-filament-panels::page>
    {{-- أنماط القسم المالي — مُعرّفة مرّة على الصفحة وتتوارثها كل الودجت داخلها.
         مبنية على متغيّرات CSS تنقلب بين الوضعين، وتفي بتباين AA (الدستور 6.3)
         وتستعمل الخصائص المنطقية (inline/block) لتوافق RTL (6.2). --}}
    <style>
        .fin-scope{
            --card:#ffffff; --ring:rgba(17,24,39,.09); --ring-strong:rgba(17,24,39,.14);
            --text:#111827; --muted:#566274; --band:rgba(110,47,176,.04);
            --accent:#6E2FB0; --pos:#047857; --neg:#b91c1c; --warn:#9a4708;
            --track:rgba(17,24,39,.07); --warn-bg:rgba(180,83,9,.12);
            display:flex; flex-direction:column; gap:1.1rem; color:var(--text);
        }
        .dark .fin-scope{
            --card:#1f2937; --ring:rgba(255,255,255,.09); --ring-strong:rgba(255,255,255,.16);
            --text:#f9fafb; --muted:#a3adba; --band:rgba(167,116,224,.07);
            --accent:#c9a7ec; --pos:#34d399; --neg:#f87171; --warn:#fbbf24;
            --track:rgba(255,255,255,.09); --warn-bg:rgba(251,191,36,.15);
        }

        .fin-num{font-variant-numeric:tabular-nums; font-weight:700; letter-spacing:-.01em}
        .fin-unit{font-size:.72em; font-weight:600; color:var(--muted); margin-inline-start:.15rem}
        .fin-na{color:var(--muted); font-weight:600; font-size:.9rem}

        .fin-t-pos .fin-num{color:var(--pos)}
        .fin-t-neg .fin-num{color:var(--neg)}
        .fin-t-warn .fin-num{color:var(--warn)}
        .fin-t-accent .fin-num{color:var(--accent)}

        /* روابط النقر: كل بطاقة تنقل لموضع إدخال/تعديل مصدرها */
        .fin-link{text-decoration:none; color:inherit; cursor:pointer}
        .fin-card.fin-link:hover, .fin-hero-card.fin-link:hover, .fin-note.fin-link:hover{border-color:var(--accent)}
        .fin-bridge-row.fin-link{border-radius:.5rem; transition:background .15s ease}
        .fin-bridge-row.fin-link:hover{background:var(--band)}
        .fin-link:focus-visible{outline:2px solid var(--accent); outline-offset:2px}
        .fin-cta{font-size:.66rem; font-weight:700; color:var(--accent); margin-block-start:.45rem}
        .fin-card.fin-link:hover .fin-cta, .fin-hero-card.fin-link:hover .fin-cta{text-decoration:underline}

        /* ألوان الفترة/الأبطال */
        .fin-hero{display:grid; grid-template-columns:repeat(3,1fr); gap:.9rem}
        @media (max-width:900px){.fin-hero{grid-template-columns:1fr}}
        .fin-hero-card{
            position:relative; display:block; background:var(--card); border:1px solid var(--ring);
            border-radius:1rem; padding:1.1rem 1.2rem; overflow:hidden;
            box-shadow:0 1px 2px rgba(17,24,39,.04); transition:border-color .15s ease;
        }
        .fin-hero-card::before{content:""; position:absolute; inset-block:0; inset-inline-start:0; inline-size:4px; background:var(--muted); opacity:.5}
        .fin-hero-card.fin-t-pos::before{background:var(--pos); opacity:1}
        .fin-hero-card.fin-t-neg::before{background:var(--neg); opacity:1}
        .fin-hero-card.fin-t-accent::before{background:var(--accent); opacity:1}
        .fin-hero-label{font-size:.82rem; color:var(--muted); font-weight:600; margin-block-end:.45rem}
        .fin-hero-value{font-size:1.85rem; line-height:1.1; display:flex; align-items:baseline; gap:.1rem; flex-wrap:wrap}
        .fin-hero-sub{font-size:.72rem; color:var(--muted); margin-block-start:.5rem}

        /* النطاقات */
        .fin-band{background:var(--card); border:1px solid var(--ring); border-radius:1rem; padding:1.05rem 1.1rem}
        .fin-band-head{display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; margin-block-end:.85rem}
        .fin-band-title{font-size:.95rem; font-weight:700; color:var(--text); margin:0}
        .fin-badge{font-size:.7rem; font-weight:600; padding:.22rem .6rem; border-radius:999px; background:var(--track); color:var(--muted); line-height:1.4}
        .fin-badge-warn{background:var(--warn-bg); color:var(--warn)}

        .fin-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:.65rem}
        @media (max-width:900px){.fin-grid{grid-template-columns:repeat(2,1fr)}}
        .fin-card{display:block; background:var(--band); border:1px solid var(--ring); border-radius:.8rem; padding:.7rem .8rem; transition:border-color .15s ease}
        .fin-card-label{font-size:.75rem; color:var(--muted); font-weight:600; margin-block-end:.35rem}
        .fin-card-value{font-size:1.15rem; line-height:1.15; display:flex; align-items:baseline; gap:.05rem; flex-wrap:wrap}
        .fin-card-sub{font-size:.68rem; color:var(--muted); margin-block-start:.35rem}

        /* جسر الربح */
        .fin-bridge-row{display:grid; grid-template-columns:9.5rem 1fr 8.5rem; align-items:center; gap:.8rem; padding-block:.5rem}
        @media (max-width:700px){.fin-bridge-row{grid-template-columns:6.5rem 1fr auto; gap:.5rem}}
        .fin-bridge-label{font-size:.83rem; font-weight:600; color:var(--text)}
        .fin-bridge-note{display:block; font-size:.64rem; color:var(--muted); font-style:normal; font-weight:500; margin-block-start:.1rem}
        .fin-bridge-bar{block-size:.55rem; border-radius:999px; background:var(--track); overflow:hidden}
        .fin-bridge-bar i{display:block; block-size:100%; border-radius:999px; background:var(--accent)}
        .fin-bridge-bar-neg i{background:var(--neg)}
        .fin-bridge-bar-pos i{background:var(--pos)}
        .fin-bridge-amt{font-size:.95rem; text-align:start; white-space:nowrap}
        .fin-bridge-amt.fin-t-neg{color:var(--neg)}
        .fin-bridge-amt.fin-t-pos{color:var(--pos)}
        .fin-bridge-amt.fin-t-neutral{color:var(--muted)}
        .fin-bridge-start{border-block-end:1px solid var(--ring); margin-block-end:.15rem; padding-block-end:.6rem}
        .fin-bridge-end{border-block-start:2px solid var(--ring-strong); margin-block-start:.25rem; padding-block-start:.7rem}
        .fin-bridge-end .fin-bridge-label{font-weight:800; font-size:1rem}
        .fin-bridge-end .fin-bridge-amt{font-size:1.05rem}
        .fin-bridge-end .fin-bridge-amt .fin-num{font-weight:800}

        /* تقسيم الدفع */
        .fin-bd{display:flex; flex-direction:column; gap:.6rem}
        .fin-bd-row{display:grid; grid-template-columns:8rem 1fr 7rem 5rem; align-items:center; gap:.7rem}
        @media (max-width:700px){.fin-bd-row{grid-template-columns:6rem 1fr auto} .fin-bd-orders{display:none}}
        .fin-bd-label{font-size:.8rem; font-weight:600}
        .fin-bd-track{block-size:.5rem; background:var(--track); border-radius:999px; overflow:hidden}
        .fin-bd-track i{display:block; block-size:100%; background:var(--accent); border-radius:999px}
        .fin-bd-net{font-size:.85rem; text-align:start}
        .fin-bd-orders{font-size:.72rem; color:var(--muted); text-align:start}

        /* الجدول اليومي — جدول دلاليّ (thead/th scope + tbody/td) */
        table.fin-day{inline-size:100%; border-collapse:collapse; font-size:.83rem}
        .fin-day th{font-size:.72rem; color:var(--muted); font-weight:600; text-align:start; padding:.5rem .35rem; border-block-end:1px solid var(--ring); white-space:nowrap}
        .fin-day td{padding:.5rem .35rem; border-block-end:1px solid var(--track); vertical-align:middle}
        /* إشارة «يوم فارغ» بخلفية خفيفة لا بتعتيم يكسر التباين (النص يبقى AA) */
        .fin-day tr.is-empty{background:var(--band)}
        .fin-day-date{color:var(--muted); font-weight:600}
        .fin-day-net-cell{display:flex; align-items:center; gap:.6rem}
        .fin-day-bar{flex:1; block-size:.45rem; background:var(--track); border-radius:999px; overflow:hidden; min-inline-size:2.5rem}
        .fin-day-bar i{display:block; block-size:100%; background:var(--accent); border-radius:999px}
        .fin-day-net-cell .fin-num{min-inline-size:5rem; text-align:start}
        .fin-day-empty{padding-block:1.5rem; text-align:center; color:var(--muted); font-size:.85rem}
        @media (max-width:700px){ .fin-day-col-day{display:none} }

        /* بطاقة «قيد التحصيل» — سكان مغاير (طلبات لم تُحقَّق) خارج نطاق الإيراد */
        .fin-note{display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; background:var(--card); border:1px solid var(--ring); border-radius:1rem; padding:.85rem 1.1rem}
        .fin-note-label{font-size:.82rem; font-weight:700}
        .fin-note-val{font-size:1.1rem}
        .fin-note-sub{font-size:.72rem; color:var(--muted); margin-inline-start:auto; text-align:start}

        /* شريط الفترة */
        .fin-period{
            display:flex; align-items:center; gap:.7rem; flex-wrap:wrap;
            padding:.55rem .9rem; border-radius:.8rem;
            background:rgba(110,47,176,.06); border:1px solid rgba(110,47,176,.14);
            font-size:.8rem;
        }
        .dark .fin-period{background:rgba(167,116,224,.08); border-color:rgba(167,116,224,.2)}
        .fin-period-label{font-weight:700; color:#6E2FB0}
        .dark .fin-period-label{color:#c9a7ec}
        .fin-period-range{font-variant-numeric:tabular-nums; color:#566274; font-weight:600}
        .dark .fin-period-range{color:#9aa4b2}
        .fin-period-days{margin-inline-start:auto; color:#566274; font-weight:600}
        .dark .fin-period-days{color:#9aa4b2}
    </style>

    {{ $this->filtersForm }}

    @php($p = $this->periodSummary())
    <div class="fin-period">
        <span class="fin-period-label">{{ $p['label'] }}</span>
        <span class="fin-period-range" dir="ltr">{{ $p['from'] }} → {{ $p['to'] }}</span>
        <span class="fin-period-days">{{ $p['days'] }} يوم</span>
    </div>

    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="1"
        :data="['filters' => $this->filters ?? []]"
    />
</x-filament-panels::page>
