<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\OrderStatusHistory;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * الخط الزمني لتغيّر حالة الطلب داخل صفحة عرض الطلب — للقراءة فقط.
 *
 * يظهر تلقائيًا أسفل الـ infolist في ViewOrder لأن Filament\Resources\Pages\ViewRecord
 * يستعمل Concerns\HasRelationManagers (تُحقّق من مصدر الحزمة).
 *
 * للقراءة فقط بالكامل: لا form (المورد لا يُنشئ ولا يعدّل سجلات التاريخ — يكتبها
 * OrderObserver خادميًا)، ولا headerActions ولا actions ولا bulkActions. تعطيلها
 * جميعًا صراحةً لا يكفي كتحكّم أمني، لذا البوابة الحقيقية في canViewForRecord أدناه.
 *
 * الصلاحية (بند 4.4 / ممنوع 13): السلوك الافتراضي لـ
 * RelationManager::canViewForRecord ينادي Filament\authorize('viewAny', $model)،
 * وهذه الدالة — كما في vendor/filament/filament/src/helpers.php سطر 24-43 — تُعيد
 * Response::allow() عندما لا توجد Policy للموديل. ولا توجد OrderStatusHistoryPolicy.
 * أي أن السلوك الافتراضي «سماح صامت» بلا أي فحص. لذلك نتجاوزه بفحص خادمي صريح
 * على orders.view، وهي نفس الصلاحية التي تحرس المورد كله عبر HasResourcePermissions
 * (OrderResource::permissionPrefix() === 'orders'). المرور عبر الـ Gate يجعل تجاوز
 * super_admin (Gate::before في AppServiceProvider سطر 66) يسري تلقائيًا.
 *
 * التسميات العربية مثبّتة كسلاسل هنا اتّباعًا للنمط السائد في موارد Filament
 * القائمة (OrderResource وغيره) — الاستثناء المسموح به في بند 6.4.
 */
class StatusHistoryRelationManager extends RelationManager
{
    /**
     * علاقة hasMany على App\Models\Order.
     *
     * تنبيه تكامل: هذه العلاقة **غير موجودة بعد** في Order.php — الملف مشترك مع
     * وركفلو آخر، فيضيفها المنسّق (انظر contractNeeds في تسليم هذه المهمة).
     * قبل إضافتها لن يُصيّر هذا الـ RelationManager (BadMethodCallException).
     */
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'سجلّ تغيّر الحالة';

    protected static ?string $icon = 'heroicon-o-clock';

    /**
     * فحص خادمي صريح بدل «السماح الصامت» الافتراضي — انظر توثيق الصنف أعلاه.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('orders.view') === true;
    }

    /**
     * لا نموذج: السجل لا يُنشأ ولا يُعدَّل من اللوحة.
     */
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            // الأحدث أولًا: ما يهمّ الأدمن عند فتح الطلب هو آخر تغيير.
            // العمود قابل للفرز فيمكنه قلبه لقراءة القصة من بدايتها.
            ->defaultSort('created_at', 'desc')
            // بلا modifyQueryUsing->with('actor') عمدًا: Filament يحمّل علاقات أعمدة
            // النقطة (actor.name) تلقائيًا في استعلام whereIn واحد. تُحقّق بمراقبة
            // SQL الفعلي أثناء التصيير — استعلام واحد لخمسة فاعلين، لا خمسة
            // (بند 2.5 / ممنوع 7). يحرسه test_relation_manager_loads_actors_without_n_plus_one.
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('from_status')
                    // null = أول قيد للطلب. لا placeholder() هنا: القالب يصلها في
                    // فرع @elseif لا يُبلَغ ما دام formatStateUsing يعيد نصًّا غير فارغ،
                    // فالمعالجة كلها في الإغلاق أدناه.
                    ->label('من')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? '—'
                        : (OrderResource::STATUS_LABELS[$state] ?? $state))
                    ->color(fn (?string $state): string => self::statusColor($state)),
                TextColumn::make('to_status')
                    ->label('إلى')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderResource::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderStatusHistory::SOURCE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        OrderStatusHistory::SOURCE_ADMIN => 'primary',
                        OrderStatusHistory::SOURCE_CUSTOMER => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('actor.name')
                    ->label('الفاعل')
                    // فاعل فارغ = تغيير نظامي (مهمة مجدولة) أو حساب محذوف.
                    ->placeholder('النظام'),
                TextColumn::make('note')
                    ->label('ملاحظة')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            // للقراءة فقط: لا إنشاء، لا تعديل، لا حذف، لا إجراءات جماعية.
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('لا يوجد تغيير مسجَّل لحالة هذا الطلب')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * خريطة ألوان حالات الطلب. منسوخة عن OrderResource::statusColor لأن تلك
     * protected static فلا تُستدعى من خارج تسلسل المورد؛ القيم نفسها حرفيًا.
     */
    protected static function statusColor(?string $state): string
    {
        return match ($state) {
            'pending' => 'warning',
            'confirmed', 'processing' => 'info',
            'shipped' => 'primary',
            'delivered', 'completed' => 'success',
            'cancelled', 'refused' => 'danger',
            default => 'gray',
        };
    }
}
