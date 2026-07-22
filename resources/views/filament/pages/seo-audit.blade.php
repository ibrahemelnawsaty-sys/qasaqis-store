{{--
    قالب لوحة «تدقيق SEO». عرض فقط: يعرض نواقص SEO مجمّعة حسب الكيان مع رابط تعديل
    لكل عنصر. نصوص عربية inline، CSS خالص يتبع الوضع الداكن (نمط لوحة الأدمن).
--}}
<x-filament-panels::page>
    <style>
        .sa { --card:#fff; --bg:#F7F4FB; --ink:#2E2440; --soft:#6E6280; --faint:#A99FB6; --line:rgba(46,36,64,.12); --accent:#6E2FB0;
              --danger:#DC2626; --warn:#D97706; --info:#2563EB; --ok:#059669; color:var(--ink); display:flex; flex-direction:column; gap:16px; }
        .dark .sa, .fi-theme-dark .sa { --card:#1F1A2E; --bg:#16121F; --ink:#F1E9FA; --soft:#B9A9CE; --faint:#7d6f90; --line:rgba(255,255,255,.12); --accent:#C79BF0;
              --danger:#F87171; --warn:#FBBF24; --info:#60A5FA; --ok:#34D399; }
        .sa-intro { font-size:13.5px; color:var(--soft); line-height:1.7; }
        .sa-stats { display:flex; flex-wrap:wrap; gap:10px; }
        .sa-stat { flex:1 1 130px; background:var(--card); border:1px solid var(--line); border-radius:14px; padding:12px 16px; display:flex; flex-direction:column; gap:2px; }
        .sa-stat .n { font-size:24px; font-weight:900; font-variant-numeric:tabular-nums; }
        .sa-stat .l { font-size:12.5px; color:var(--soft); font-weight:700; }
        .sa-stat.d .n { color:var(--danger); }
        .sa-stat.w .n { color:var(--warn); }
        .sa-stat.i .n { color:var(--info); }
        .sa-group { background:var(--card); border:1px solid var(--line); border-radius:16px; overflow:hidden; }
        .sa-group h3 { margin:0; padding:12px 16px; font-size:14px; font-weight:900; background:var(--bg); border-bottom:1px solid var(--line); display:flex; gap:8px; align-items:center; }
        .sa-group h3 .c { font-size:12px; color:var(--soft); font-weight:700; }
        .sa-row { display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-bottom:1px solid var(--line); }
        .sa-row:last-child { border-bottom:0; }
        .sa-dot { flex:0 0 auto; width:9px; height:9px; border-radius:50%; margin-top:6px; }
        .sa-dot.danger { background:var(--danger); }
        .sa-dot.warning { background:var(--warn); }
        .sa-dot.info { background:var(--info); }
        .sa-body { flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .sa-label { font-size:13.5px; font-weight:800; }
        .sa-issue { font-size:13px; color:var(--soft); line-height:1.6; }
        .sa-fix { flex:0 0 auto; align-self:center; font-size:12.5px; font-weight:800; text-decoration:none; color:var(--accent); white-space:nowrap; border:1px solid var(--line); border-radius:999px; padding:5px 12px; }
        .sa-fix:hover { border-color:var(--accent); }
        .sa-ok { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:40px 20px; text-align:center; color:var(--soft); }
        .sa-ok .em { font-size:2.6rem; }
        .sa-ok p { margin-top:8px; font-weight:800; color:var(--ink); }
    </style>

    <div class="sa">
        <p class="sa-intro">
            فحص تلقائي لكل المحتوى المنشور يُبرز ما يُضعف ظهورك في جوجل ووسائل التواصل.
            <b style="color:var(--danger)">الأحمر</b> الأهمّ (يؤثّر مباشرةً على نتائج البحث والمشاركة)،
            <b style="color:var(--warn)">البرتقالي</b> تحسينات مُوصى بها،
            <b style="color:var(--info)">الأزرق</b> اقتراحات. اضغط «إصلاح» بجانب أي عنصر لفتحه وتعديله.
        </p>

        <div class="sa-stats">
            <div class="sa-stat d"><span class="n">{{ $summary['danger'] }}</span><span class="l">مشاكل مهمّة</span></div>
            <div class="sa-stat w"><span class="n">{{ $summary['warning'] }}</span><span class="l">تحذيرات</span></div>
            <div class="sa-stat i"><span class="n">{{ $summary['info'] }}</span><span class="l">اقتراحات</span></div>
            <div class="sa-stat"><span class="n">{{ $summary['total'] }}</span><span class="l">الإجمالي</span></div>
        </div>

        @forelse ($grouped as $group => $findings)
            <div class="sa-group">
                <h3>{{ $group }} <span class="c">({{ $findings->count() }})</span></h3>
                @foreach ($findings as $finding)
                    <div class="sa-row">
                        <span class="sa-dot {{ $finding->severity }}" aria-hidden="true"></span>
                        <div class="sa-body">
                            <span class="sa-label">{{ $finding->label }}</span>
                            <span class="sa-issue">{{ $finding->issue }}</span>
                        </div>
                        @if ($finding->editUrl)
                            <a class="sa-fix" href="{{ $finding->editUrl }}">إصلاح ←</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <div class="sa-ok">
                <div class="em" aria-hidden="true">✅</div>
                <p>ممتاز! لا نواقص SEO في المحتوى المنشور.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
