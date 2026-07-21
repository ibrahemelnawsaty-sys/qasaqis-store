<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * زر «دفع الإرسال الآن»: يعالج الطابور فورًا داخل الطلب (queue:work محدود بالزمن)
 * بدل انتظار نبضة زيارة الموقع. مشترك بين صفحة الإرسال وسجلّ الحملات.
 *
 * محدود بـ--max-time وبـ--stop-when-empty فلا يعلق الطلب طويلًا؛ يعالج المتاح ثم
 * يعود. المهام المؤجَّلة (حدّ الإرسال المتدرّج) تبقى لدورات لاحقة كما هو مقصود.
 * محميّ بصلاحية campaigns.send (خادميًا).
 */
final class FlushQueueAction
{
    public static function make(): Action
    {
        return Action::make('flushQueue')
            ->label('دفع الإرسال الآن')
            ->icon('heroicon-o-bolt')
            ->color('warning')
            ->visible(fn (): bool => (bool) auth()->user()?->can('campaigns.send'))
            ->requiresConfirmation()
            ->modalHeading('دفع الطابور الآن')
            ->modalDescription('يعالج الرسائل المنتظرة فورًا (حملات + تأكيدات طلبات) دون انتظار زيارة للموقع. قد يستغرق ثوانٍ.')
            ->modalSubmitActionLabel('نعم، ادفع الآن')
            ->action(function (): void {
                abort_unless((bool) auth()->user()?->can('campaigns.send'), 403);

                @set_time_limit(0);

                $before = DB::table('jobs')->count();

                Artisan::call('queue:work', [
                    '--queue' => 'campaigns,default',
                    '--stop-when-empty' => true,
                    '--tries' => 3,
                    '--max-time' => 20,
                ]);

                $processed = max(0, $before - DB::table('jobs')->count());

                Notification::make()
                    ->title('تمت معالجة الطابور')
                    ->body($processed > 0
                        ? "عولجت {$processed} رسالة. حدّث الصفحة لرؤية تحديث الحالة."
                        : 'لا رسائل منتظرة الآن (أو مجدولة لاحقًا ضمن حدّ الإرسال المتدرّج).')
                    ->success()
                    ->send();
            });
    }
}
