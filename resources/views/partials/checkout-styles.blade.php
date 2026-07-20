{{-- أنماط صفحات السلة/الدفع/الطلب — تستعمل توكنات التصميم من app.css (الثيمان وRTL مضمونان). --}}
@once
    @push('head')
        <style>
            .co { padding-block: clamp(20px, 4vw, 40px); }
            .co-head { margin-bottom: clamp(16px, 3vw, 26px); }
            .co-head h1 { font-size: clamp(24px, 4vw, 36px); font-weight: 900; letter-spacing: -.5px; line-height: 1.2; }
            .co-head p { color: var(--ink-soft); margin-top: 6px; font-size: 15px; }

            .co-layout { display: grid; grid-template-columns: 1.5fr .9fr; gap: clamp(16px, 3vw, 28px); align-items: start; }
            @media (max-width: 860px) { .co-layout { grid-template-columns: 1fr; } }

            .co-card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); padding: clamp(16px, 3vw, 24px); box-shadow: var(--shadow-s); }
            .co-card + .co-card { margin-top: 16px; }
            .co-card > h2 { font-size: 17px; font-weight: 900; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
            .co-card > h2 .n { width: 28px; height: 28px; border-radius: 50%; background: var(--purple-soft); color: var(--purple); display: grid; place-items: center; font-size: 14px; font-weight: 900; flex: 0 0 auto; }

            /* الحقول */
            .co-field { margin-bottom: 14px; }
            .co-field.half { margin-bottom: 0; }
            .co-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
            @media (max-width: 520px) { .co-grid2 { grid-template-columns: 1fr; } }
            .co-label { display: block; font-weight: 800; font-size: 13.5px; margin-bottom: 7px; color: var(--ink); }
            .co-label .opt { color: var(--ink-faint); font-weight: 600; font-size: 12px; }
            .co-input, .co-select, .co-textarea {
                width: 100%; min-height: 48px; border: 1.5px solid var(--line); background: var(--surface-soft);
                border-radius: var(--r-sm); padding: 12px 14px; font-family: inherit; font-size: 15px; color: var(--ink);
                outline: none; transition: border-color .18s, box-shadow .18s;
            }
            .co-textarea { min-height: 90px; line-height: 1.7; resize: vertical; }
            .co-input:focus, .co-select:focus, .co-textarea:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }
            .co-input.err, .co-select.err, .co-textarea.err { border-color: var(--pink); }
            .co-err { color: var(--pink); font-size: 12.5px; font-weight: 700; margin-top: 6px; }
            .co-input::placeholder, .co-textarea::placeholder { color: var(--ink-faint); }

            /* طرق الدفع */
            .co-methods { display: flex; flex-direction: column; gap: 10px; }
            .co-method { display: flex; align-items: flex-start; gap: 12px; border: 1.5px solid var(--line); border-radius: var(--r-sm); padding: 14px; cursor: pointer; transition: border-color .18s, background .18s; }
            .co-method:hover { border-color: color-mix(in srgb, var(--purple) 35%, transparent); }
            .co-method input { width: 20px; height: 20px; accent-color: var(--purple); margin-top: 2px; flex: 0 0 auto; }
            .co-method:has(input:checked) { border-color: var(--purple); background: var(--purple-soft); }
            .co-method .mt { font-weight: 800; font-size: 15px; }
            .co-method .md { font-size: 12.5px; color: var(--ink-soft); margin-top: 3px; line-height: 1.6; }

            /* ملخّص / أسطر المبالغ */
            .co-summary { position: sticky; top: 86px; }
            .co-line { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; font-size: 14.5px; color: var(--ink-soft); }
            .co-line .v { font-weight: 800; color: var(--ink); }
            .co-line.discount .v { color: var(--good); }
            .co-line.total { border-top: 1.5px dashed var(--line-2); margin-top: 6px; padding-top: 14px; font-size: 17px; color: var(--ink); font-weight: 900; }
            .co-line.total .v { font-size: 22px; color: var(--purple); }
            .co-hint { font-size: 12.5px; color: var(--ink-faint); margin-top: 6px; line-height: 1.6; }

            /* عناصر السلة/الملخّص */
            .co-items { display: flex; flex-direction: column; gap: 12px; }
            .co-item { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--line); border-radius: var(--r-sm); }
            .co-thumb { width: 48px; height: 60px; border-radius: 8px; flex: 0 0 auto; overflow: hidden; position: relative; display: grid; place-items: center; box-shadow: var(--shadow-s); }
            .co-thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .co-thumb-i { font-size: 22px; filter: drop-shadow(0 1px 2px rgba(0,0,0,.25)); }
            .co-item-main { flex: 1; min-width: 0; }
            .co-item-title { font-weight: 800; font-size: 14.5px; color: var(--ink); text-decoration: none; line-height: 1.4; display: block; }
            .co-item-title:hover { color: var(--purple); }
            .co-item-meta { font-size: 12.5px; color: var(--ink-soft); margin-top: 3px; }
            .co-item-price { font-weight: 900; color: var(--purple); white-space: nowrap; font-size: 15px; }

            /* عدّاد الكمية */
            .co-qty { display: inline-flex; align-items: center; border: 1.5px solid var(--line); border-radius: var(--r-pill); overflow: hidden; background: var(--surface-soft); }
            .co-qty button { width: 38px; height: 40px; border: none; background: transparent; color: var(--purple); font-size: 20px; font-weight: 900; cursor: pointer; display: grid; place-items: center; }
            .co-qty button:hover { background: var(--purple-soft); }
            .co-qty input { width: 44px; height: 40px; border: none; background: transparent; text-align: center; font-family: inherit; font-weight: 800; font-size: 15px; color: var(--ink); outline: none; -moz-appearance: textfield; }
            .co-qty input::-webkit-outer-spin-button, .co-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .co-remove { background: none; border: none; color: var(--ink-faint); font-size: 18px; cursor: pointer; width: 40px; height: 40px; border-radius: 10px; flex: 0 0 auto; }
            .co-remove:hover { color: var(--pink); background: var(--pink-soft); }

            /* صندوق الكوبون */
            .co-coupon-row { display: flex; gap: 8px; }
            .co-coupon-row .co-input { min-height: 46px; }
            .co-coupon-row .btn { min-height: 46px; padding-inline: 18px; white-space: nowrap; }
            .co-coupon-msg { font-size: 13px; font-weight: 700; margin-top: 8px; }
            .co-coupon-msg.ok { color: var(--good); }
            .co-coupon-msg.bad { color: var(--pink); }

            /* تنبيهات فلاش */
            .co-alert { border-radius: var(--r-sm); padding: 13px 16px; font-size: 14px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; border: 1px solid transparent; }
            .co-alert .ai { font-size: 18px; flex: 0 0 auto; }
            .co-alert.ok { background: var(--teal-soft); color: var(--teal); border-color: color-mix(in srgb, var(--teal) 30%, transparent); }
            .co-alert.bad { background: var(--pink-soft); color: var(--pink); border-color: color-mix(in srgb, var(--pink) 30%, transparent); }
            .co-alert.notice { background: var(--gold); color: #5a3d00; }

            /* شارات الحالة */
            .co-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 800; padding: 5px 12px; border-radius: var(--r-pill); background: var(--purple-soft); color: var(--purple); }
            .co-badge.paid { background: var(--teal-soft); color: var(--teal); }
            .co-badge.wait { background: var(--gold); color: #5a3d00; }
            .co-badge.bad { background: var(--pink-soft); color: var(--pink); }

            /* بطاقة نجاح كبيرة (شكر) */
            .co-hero { text-align: center; padding: clamp(24px, 5vw, 44px) clamp(16px, 4vw, 32px); background: radial-gradient(120% 140% at 15% 10%, var(--teal-soft), transparent 55%), var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); box-shadow: var(--shadow); }
            .co-hero .em { font-size: 60px; line-height: 1; }
            .co-hero h1 { font-size: clamp(22px, 4vw, 32px); font-weight: 900; margin-top: 12px; }
            .co-hero .onum { display: inline-flex; align-items: center; gap: 8px; margin-top: 14px; font-family: var(--mono); direction: ltr; font-size: 18px; font-weight: 800; color: var(--purple); background: var(--purple-soft); padding: 8px 16px; border-radius: var(--r-pill); }

            /* بيانات التحويل (قائمة تعريف) */
            .co-dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin-top: 8px; }
            .co-dl dt { font-weight: 800; font-size: 13.5px; color: var(--ink-soft); }
            .co-dl dd { font-weight: 800; font-size: 14.5px; color: var(--ink); direction: ltr; text-align: start; word-break: break-word; }

            .co-amount-box { text-align: center; background: var(--purple-soft); border-radius: var(--r-md); padding: 18px; margin: 4px 0 16px; }
            .co-amount-box .lbl { font-size: 13px; font-weight: 700; color: var(--purple); }
            .co-amount-box .val { font-size: clamp(28px, 6vw, 40px); font-weight: 900; color: var(--purple); line-height: 1.1; margin-top: 4px; }

            .co-file { display: block; width: 100%; border: 1.5px dashed var(--line-2); border-radius: var(--r-sm); padding: 14px; background: var(--surface-soft); font-family: inherit; font-size: 14px; color: var(--ink); cursor: pointer; }
            .co-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
            .co-actions .btn { flex: 1 1 auto; }

            /* أدوات مشتركة متكررة (بند 6.1) — بدل الأنماط inline المكررة عبر الصفحات */
            .co-mono { font-family: var(--mono); direction: ltr; }              /* أرقام/مراجع LTR */
            .co-lead { color: var(--ink-soft); line-height: 1.8; }              /* فقرة توضيحية */
            .co-thumb.ph { background: var(--purple-soft); }                    /* مصغّرة بديلة */

            /* ══ تعليمات الدفع: زرّ بارز + نسخ بضغطة (بدل نصّ باهت بلا رابط) ══ */
            .pay-box { margin-top: 4px; border: 1.5px solid color-mix(in srgb, var(--teal) 45%, transparent);
                background: color-mix(in srgb, var(--teal) 7%, var(--surface)); border-radius: var(--r-sm); padding: 15px 15px 16px; }
            .pay-box-head { display: flex; align-items: center; gap: 8px; font-weight: 900; font-size: 14px; color: var(--ink); margin-bottom: 12px; }
            .pay-box-head .ic { width: 26px; height: 26px; border-radius: 8px; background: var(--teal); color: #06312d; display: grid; place-items: center; flex: 0 0 auto; }
            .pay-cta { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; width: 100%;
                background: linear-gradient(135deg, var(--purple), var(--pink)); color: #fff; text-decoration: none;
                font-weight: 900; font-size: 15.5px; padding: 13px 18px 11px; border-radius: var(--r-sm);
                box-shadow: 0 10px 22px -10px color-mix(in srgb, var(--pink) 70%, transparent); transition: transform .15s, box-shadow .15s; }
            .pay-cta:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -10px color-mix(in srgb, var(--pink) 75%, transparent); }
            .pay-cta:focus-visible { outline: 3px solid var(--purple); outline-offset: 2px; }
            .pay-cta .arrow { font-size: 16px; line-height: 1; opacity: .9; animation: payDown 1.6s ease-in-out infinite; }
            @keyframes payDown { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(3px); } }
            .pay-or { display: flex; align-items: center; gap: 10px; margin: 13px 2px 4px; color: var(--ink-faint); font-size: 12.5px; font-weight: 800; }
            .pay-or::before, .pay-or::after { content: ""; flex: 1; height: 1px; background: var(--line-2); }
            .pay-alt-label { font-size: 12.5px; color: var(--ink-soft); text-align: center; margin-bottom: 4px; }
            .pay-row { display: flex; align-items: center; gap: 10px; padding: 11px 12px; margin-top: 9px;
                background: var(--surface); border: 1px solid var(--line); border-radius: 10px; }
            .pay-row-label { font-size: 12.5px; color: var(--ink-soft); font-weight: 700; flex: 0 0 auto; min-width: 82px; }
            .pay-row-value { font-size: 15px; font-weight: 800; color: var(--ink); flex: 1; min-width: 0; overflow-wrap: anywhere; }
            .pay-copy { flex: 0 0 auto; border: 1.5px solid color-mix(in srgb, var(--purple) 30%, transparent); background: var(--surface);
                color: var(--purple); font-weight: 800; font-size: 12.5px; font-family: inherit; padding: 7px 13px; border-radius: 9px; cursor: pointer; transition: background .15s; }
            .pay-copy:hover { background: var(--purple-soft); }
            .pay-copy.done { background: var(--teal-soft); color: var(--teal); border-color: transparent; }
            .pay-note { font-size: 13px; line-height: 1.7; color: var(--ink-soft); margin: 12px 2px 0; }
            .pay-note a { color: var(--purple); font-weight: 700; word-break: break-all; }
            @media (prefers-reduced-motion: reduce) { .pay-cta .arrow { animation: none; } .pay-cta:hover { transform: none; } }
        </style>
    @endpush
@endonce
