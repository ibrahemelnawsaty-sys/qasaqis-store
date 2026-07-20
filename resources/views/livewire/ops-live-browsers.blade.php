{{-- بطاقة «المتصفّحون الآن» — جذر واحد يندرج مباشرةً داخل شبكة .g5 في لوحة العمليات.
     تستطلع نفسها فقط (wire:poll.visible) بدل إعادة تحميل اللوحة كلّها. تُصمَّم عبر أنماط
     .opsd في قالب الصفحة لأنّها تُرسَم داخل حاوية .opsd. --}}
<div class="opsd-card opsd-stat" wire:poll.30s.visible="refresh">
    <div class="opsd-live"><span class="opsd-dot"></span> المتصفّحون الآن</div>
    <div class="v" style="color:var(--success)">{{ $total }}</div>
    <div class="s">{{ $guests }} زائر · {{ $members }} مسجَّل · آخر 5 دقائق</div>
</div>
