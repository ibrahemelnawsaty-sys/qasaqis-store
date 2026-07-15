<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * مستقبِلو تنبيهات الأدمن للطلبات/الدفع (M4). يُحدَّدون خادميًا: المستخدمون
 * النشطون الذين يملكون صلاحية orders.view. نستخدم can() (لا permission()) كي
 * يشمل super_admin الذي يتجاوز عبر Gate::before حتى لو لم تُسنَد له الصلاحية
 * مباشرةً. جدول المستخدمين صغير (المتجر ضيف-فقط) فالترشيح في PHP مقبول.
 */
class AdminRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function forOrders(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(static fn (User $user): bool => $user->can('orders.view'))
            ->values();
    }
}
