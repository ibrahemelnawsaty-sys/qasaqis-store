<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\BackgroundPattern;
use App\Enums\PatternSurface;
use App\Models\Setting;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\Cms\BackgroundPatternService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * اختيار نقش خلفية لكل صفحة، ولكل قسم من أقسام الصفحة الرئيسية (الدستور 0.8).
 *
 * التخزين: صف في جدول settings لكل سطح بمفتاح pattern.{surface} ضمن المجموعة
 * 'appearance'. القيمة الفارغة تعني «اتبع الافتراضي»، بينما 'none' اختيار صريح
 * بإزالة النقش — والفرق بينهما محفوظ عمدًا.
 *
 * الصلاحيات (الدستور 4.4): الدخول يتطلب settings.view، والحفظ يتطلب
 * settings.general.edit — وهما الصلاحيتان الموجودتان فعلًا في الكتالوج
 * (RolePermissionSeeder)، فلم أخترع صلاحية جديدة (1.1).
 */
class ManageBackgroundPatterns extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'نقوش الخلفية';

    protected static ?string $title = 'نقوش الخلفية';

    protected static string $view = 'filament.pages.manage-background-patterns';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $stored = app(BackgroundPatternService::class)->stored();

        $state = [];
        foreach (PatternSurface::cases() as $surface) {
            // الحقل الفارغ = لم يُحفظ اختيار بعد ⇒ يعرض الافتراضي كـ placeholder.
            $state[$this->fieldName($surface)] = $stored[$surface->settingKey()] ?? null;
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('نقش كل صفحة')
                    ->description('النقش يظهر خلف محتوى الصفحة كلها. اتركي الحقل فارغًا لاستخدام النقش الافتراضي.')
                    ->schema($this->selectsFor(PatternSurface::pages()))
                    ->columns(2),

                Forms\Components\Section::make('نقش أقسام الصفحة الرئيسية')
                    ->description('كل قسم تختارين له نقشًا يظهر كشريط بخلفية كريمية خفيفة تفصله عمّا حوله. '
                        .'الأقسام بلا نقش يمرّ خلفها نقش الصفحة. الأفضل ألّا يتجاوز عدد الشرائط ثلاثة حتى لا تتعب العين.')
                    ->schema($this->selectsFor(PatternSurface::sections()))
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    /**
     * @param  array<int, PatternSurface>  $surfaces
     * @return array<int, Forms\Components\Select>
     */
    protected function selectsFor(array $surfaces): array
    {
        return array_map(
            fn (PatternSurface $surface): Forms\Components\Select => Forms\Components\Select::make($this->fieldName($surface))
                ->label($surface->label())
                ->options(BackgroundPattern::options())
                ->placeholder('الافتراضي — '.$surface->default()->label())
                ->native(false)
                ->searchable(false),
            $surfaces,
        );
    }

    public function save(): void
    {
        // تحقّق خادمي عند نقطة الفعل، لا إخفاء زر فقط (الدستور 4.4 / ممنوع 13).
        abort_unless((bool) auth()->user()?->can('settings.general.edit'), 403);

        $state = $this->form->getState();

        DB::transaction(function () use ($state): void {
            foreach (PatternSurface::cases() as $surface) {
                $value = $state[$this->fieldName($surface)] ?? null;

                // قائمة بيضاء: أي قيمة خارج الـenum تُرفض ولا تُخزَّن (الدستور 4.1).
                $value = $value === null || $value === ''
                    ? null
                    : (BackgroundPattern::tryFrom((string) $value)?->value);

                Setting::updateOrCreate(
                    ['key' => $surface->settingKey()],
                    [
                        'group' => 'appearance',
                        'value' => $value,
                        'type' => 'string',
                        'is_encrypted' => false,
                        'autoload' => true,
                    ],
                );
            }
        });

        // بدون هذا يبقى الاختيار غير مرئي حتى انتهاء عمر الكاش (الدستور 5.4).
        app(BackgroundPatternService::class)->flush();

        Notification::make()
            ->title('تم حفظ النقوش')
            ->body('امسحي كاش القوالب على الخادم إن لم يظهر التغيير فورًا.')
            ->success()
            ->send();
    }

    /**
     * اسم الحقل في النموذج: النقطة محجوزة للمسارات المتشعّبة في Livewire،
     * فنستبدلها بشرطة سفلية حتى لا تُفسَّر pattern.page.home كمسار متداخل.
     */
    protected function fieldName(PatternSurface $surface): string
    {
        return str_replace('.', '_', $surface->value);
    }

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('حفظ')
                ->submit('save')
                ->visible(fn (): bool => (bool) auth()->user()?->can('settings.general.edit')),
        ];
    }
}
