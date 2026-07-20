<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * «المتصفّحون الآن» — عدّاد لحظي معزول عن لوحة العمليات.
 *
 * لماذا مكوّن مستقلّ: كان القالب يستعمل wire:poll على الحاوية كلّها، فيعيد Livewire
 * تنفيذ getViewData بأكمله (‏~16 استعلامًا) كلّ 30 ثانية لمجرّد تحديث رقمٍ واحد.
 * بعزله هنا يقتصر البولّ الدوري على استعلام واحد لجدول الجلسات، وتبقى بقيّة اللوحة
 * ثابتة (بياناتها مخزّنة مؤقّتًا أصلًا) — وهذا هو المكسب الأكبر في خفّة الصفحة.
 *
 * الأمان: نفس صلاحية اللوحة (orders.view) تُفرَض خادميًّا في mount وفي كلّ نداء بولّ،
 * لأنّ نقطة نهاية Livewire قابلة للوصول مباشرة (mount لا يُعاد تشغيله عند التحديث).
 */
class OpsLiveBrowsers extends Component
{
    public int $total = 0;

    public int $guests = 0;

    public int $members = 0;

    public function mount(): void
    {
        $this->guardAccess();
        $this->refresh();
    }

    /** يُستدعى من wire:poll كلّ 30 ثانية. طازج دائمًا (بلا تخزين مؤقّت). */
    public function refresh(): void
    {
        $this->guardAccess();

        $since = now()->subMinutes(5)->getTimestamp();

        // عدّ الكلّ والضيوف في استعلام واحد بدل استعلامَين على نفس المرشِّح.
        $row = DB::table('sessions')
            ->where('last_activity', '>=', $since)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guests')
            ->first();

        $this->total = (int) ($row->total ?? 0);
        $this->guests = (int) ($row->guests ?? 0);
        $this->members = max(0, $this->total - $this->guests);
    }

    /** يُفرَض على كلّ طلب (نقطة نهاية Livewire مكشوفة). */
    protected function guardAccess(): void
    {
        abort_unless((bool) auth()->user()?->can('orders.view'), 403);
    }

    public function render()
    {
        return view('livewire.ops-live-browsers');
    }
}
