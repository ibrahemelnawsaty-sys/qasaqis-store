{{--
    أساس تصميم منطقة حساب العميلة (M12). فئات جديدة تُبنى على توكنات app.css
    حصريًا (بند 6.1 — لا ألوان عشوائية) لتنقلب مع الوضع الليلي/النهاري تلقائيًا.
    @once فلا تتكرّر إن ضُمّت في أكثر من شاشة. تُكمِّل فئات co-* في checkout-styles.
    الهدف: تُشعر الأم أن هذا حسابها هي — دفء وشخصنة ووضوح، موبايل أولًا.
--}}
@once
    <style>
        /* الهيرو الشخصي: تحية بالاسم + مونوغرام */
        .acc-hero{ text-align:center; border-radius:var(--r-lg); padding:22px 16px 18px;
            background:radial-gradient(130% 120% at 50% 0%, var(--purple-soft), var(--surface));
            border:1px solid var(--line); box-shadow:var(--shadow-s); }
        .acc-mono{ width:60px; height:60px; border-radius:50%; display:grid; place-items:center;
            margin:0 auto 10px; color:#fff; font-weight:900; font-size:26px;
            background:linear-gradient(135deg,var(--purple),var(--pink)); box-shadow:var(--shadow); }
        .acc-hero h1{ font-size:1.4rem; margin:.1rem 0 .35rem; font-weight:900; line-height:1.25; }
        .acc-hero p{ margin:0; color:var(--ink-soft); font-size:.92rem; }
        .acc-memb{ display:inline-block; margin-top:8px; font-size:.74rem; font-weight:800;
            color:var(--gold-ink); background:color-mix(in srgb,var(--gold) 22%, transparent);
            padding:.2rem .7rem; border-radius:var(--r-pill); }

        /* شريط الإحصائيات: توظيف بيانات الحساب */
        .acc-stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:9px; margin-top:14px; }
        .acc-stat{ background:var(--surface); border:1px solid var(--line); border-radius:var(--r-sm);
            padding:12px 6px; text-align:center; }
        .acc-stat b{ display:block; font-size:1.25rem; font-weight:900; color:var(--purple);
            font-variant-numeric:tabular-nums; line-height:1.1; }
        .acc-stat span{ display:block; font-size:.7rem; color:var(--ink-soft); margin-top:3px; }

        /* شبكة تنقّل بطاقات */
        .acc-nav{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .acc-nav-item{ display:block; background:var(--surface); border:1px solid var(--line);
            border-radius:var(--r-md); padding:15px 10px; text-align:center; text-decoration:none;
            color:var(--ink); transition:transform .12s ease, border-color .12s ease, box-shadow .12s ease; }
        .acc-nav-item:hover{ transform:translateY(-2px); border-color:var(--purple);
            box-shadow:var(--shadow-s); color:var(--purple); }
        .acc-nav-item .ic{ font-size:1.5rem; line-height:1; }
        .acc-nav-item b{ display:block; font-size:.86rem; font-weight:800; margin-top:5px; }
        .acc-nav-item span{ display:block; font-size:.72rem; color:var(--ink-faint); margin-top:1px; }

        /* بطاقة آخر طلب — بغلاف وحالة بشرية */
        .acc-lastcard{ display:flex; gap:12px; align-items:center; }
        .acc-cover{ width:52px; height:68px; border-radius:10px; flex:none; display:grid; place-items:center;
            font-size:1.5rem; background:linear-gradient(135deg,var(--purple-soft),var(--pink-soft));
            box-shadow:var(--shadow-s); position:relative; overflow:hidden; }
        .acc-cover img{ width:100%; height:100%; object-fit:cover; }
        .acc-cover .cnt{ position:absolute; inset-block-end:0; inset-inline-start:0; background:var(--purple);
            color:#fff; font-size:.6rem; font-weight:900; border-start-end-radius:8px; padding:1px 6px; }
        .acc-last-main{ flex:1; min-width:0; }
        .acc-last-main .ot{ font-weight:900; font-size:1rem; }
        .acc-last-main .meta{ color:var(--ink-faint); font-size:.74rem; font-variant-numeric:tabular-nums; }
        .acc-status{ font-size:.85rem; font-weight:800; margin-top:3px; display:inline-flex; gap:5px; align-items:center; }
        .acc-status.ok{ color:var(--teal); } .acc-status.wait{ color:var(--gold-ink); } .acc-status.bad{ color:#e23c3c; }

        /* حقل كلمة مرور بزر عين */
        .acc-passwrap{ position:relative; }
        .acc-eye{ position:absolute; inset-inline-end:8px; top:50%; transform:translateY(-50%);
            background:none; border:0; cursor:pointer; color:var(--ink-faint); padding:6px; font-size:1.05rem;
            line-height:1; }
        .acc-passwrap input{ padding-inline-end:40px; }

        /* فاصل «أو» */
        .acc-sep{ display:flex; align-items:center; gap:.7rem; color:var(--ink-faint); font-size:.78rem; margin:16px 0; }
        .acc-sep::before,.acc-sep::after{ content:""; height:1px; background:var(--line); flex:1; }

        /* حالة فارغة جميلة */
        .acc-empty{ text-align:center; padding:22px 12px; }
        .acc-empty .em{ font-size:2.4rem; line-height:1; }
        .acc-empty h3{ margin:.5rem 0 .3rem; font-size:1.05rem; font-weight:900; }
        .acc-empty p{ margin:0 0 14px; color:var(--ink-soft); font-size:.88rem; }
    </style>
@endonce
