<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Mail\CampaignMail;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\Email\CampaignDispatcher;
use App\Support\Email\CampaignAudience;
use App\Support\Email\CampaignHtml;
use App\Support\Email\CampaignTemplateLibrary;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

/**
 * إرسال حملة بريدية للعملاء المسجّلين و/أو فريق العمل و/أو قائمة خارجية. يختار الأدمن
 * قالبًا جاهزًا (تخفيضات/موسم/…)، يعدّل النصّ، يعاين أو يرسل تجريبيًا لنفسه، ثم يُرسل.
 *
 * الأمان (الباب 4.4): الوصول يتطلّب campaigns.view والإرسال يتطلّب campaigns.send —
 * ويُعاد التحقّق خادميًا داخل send() لا الإخفاء وحده. المحتوى يُعقَّم (CampaignHtml)
 * قبل الحفظ والإرسال. عنوان الرسالة يُمنَع من حروف الأسطر (حقن ترويسة).
 */
class SendEmailCampaign extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'إرسال بريد للعملاء';

    protected static ?string $title = 'إرسال حملة بريدية';

    protected static string $view = 'filament.pages.send-email-campaign';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('campaigns.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('المستلمون')
                    ->description('اختَر جمهورًا واحدًا أو أكثر. تُزال العناوين المكرّرة ومن ألغوا اشتراكهم تلقائيًا.')
                    ->schema([
                        Forms\Components\CheckboxList::make('audiences')
                            ->label('الجمهور')
                            ->options(CampaignAudience::labels())
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('external_emails_raw')
                            ->label('قائمة البريد الخارجية')
                            ->helperText('بريد واحد في كل سطر (أو مفصولة بفاصلة). لغير المسجّلين في المنصّة.')
                            ->rows(4)
                            ->visible(fn (Get $get): bool => in_array(CampaignAudience::EXTERNAL, $get('audiences') ?? [], true)),
                        Forms\Components\Placeholder::make('recipient_estimate')
                            ->label('العدد المتوقّع بعد التنقية')
                            ->content(fn (Get $get): string => app(CampaignDispatcher::class)
                                ->estimate($get('audiences') ?? [], $get('external_emails_raw'))),
                    ]),

                Forms\Components\Section::make('المحتوى')
                    ->schema([
                        Forms\Components\Select::make('template_key')
                            ->label('قالب جاهز (اختياري)')
                            ->options(CampaignTemplateLibrary::options())
                            ->placeholder('ابدأ من قالب جاهز…')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $tpl = CampaignTemplateLibrary::get($state);

                                if ($tpl !== null) {
                                    $set('subject', $tpl['subject']);
                                    $set('preheader', $tpl['preheader']);
                                    $set('body_html', $tpl['body_html']);
                                }
                            })
                            ->helperText('اختيار قالب يملأ العنوان والنصّ — وكلها قابلة للتعديل.'),
                        Forms\Components\TextInput::make('subject')
                            ->label('عنوان الرسالة')
                            ->required()
                            ->maxLength(200)
                            // منع حقن ترويسة عبر أسطر جديدة في العنوان.
                            ->rule('regex:/^[^\r\n]+$/'),
                        Forms\Components\TextInput::make('preheader')
                            ->label('نص المعاينة (يظهر في صندوق الوارد)')
                            ->maxLength(200),
                        Forms\Components\RichEditor::make('body_html')
                            ->label('نصّ الرسالة')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'link',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo',
                            ])
                            ->helperText('استخدم {name} ليُستبدَل باسم العميلة. يُدرَج داخل القالب المؤسسي (ترويسة/تذييل) تلقائيًا.'),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('معاينة')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('معاينة الرسالة')
                ->modalContent(fn () => view('filament.pages.campaign-preview', [
                    'html' => $this->renderedPreviewHtml(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق'),
            Action::make('sendTest')
                ->label('إرسال تجريبي لي')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'سيُرسَل نموذج من الرسالة إلى بريدك: ' . (string) auth()->user()?->email)
                ->action(fn () => $this->sendTest()),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('إرسال الحملة')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('تأكيد إرسال الحملة')
                ->modalDescription('سيُرسَل بريد مستقلّ لكل مستلم عبر الطابور. لا يمكن التراجع بعد البدء.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('campaigns.send'))
                ->action(fn () => $this->send()),
        ];
    }

    protected function sendTest(): void
    {
        $subject = trim((string) data_get($this->data, 'subject'));
        $body = (string) data_get($this->data, 'body_html');
        $email = auth()->user()?->email;

        if ($subject === '' || trim(strip_tags($body)) === '' || ! $email) {
            Notification::make()->title('أكمل العنوان والنصّ أولًا')->warning()->send();

            return;
        }

        Mail::to($email)->send(new CampaignMail(
            $subject,
            (string) data_get($this->data, 'preheader') ?: null,
            CampaignHtml::sanitize(str_replace('{name}', (string) auth()->user()?->name, $body)),
            (string) auth()->user()?->name,
            'preview',
        ));

        Notification::make()->title('أُرسلت رسالة تجريبية إلى بريدك')->success()->send();
    }

    public function send(): void
    {
        // إعادة تحقّق خادمي (الباب 4.4) — لا نعتمد على إخفاء الزرّ.
        abort_unless((bool) auth()->user()?->can('campaigns.send'), 403);

        $state = $this->form->getState();

        $dispatcher = app(CampaignDispatcher::class);

        if ($dispatcher->resolveCount($state['audiences'], $state['external_emails_raw'] ?? null) === 0) {
            Notification::make()->title('لا مستلمين مطابقين — راجع الجمهور')->warning()->send();

            return;
        }

        $campaign = $dispatcher->dispatch(
            createdBy: (int) auth()->id(),
            subject: $state['subject'],
            preheader: $state['preheader'] ?? null,
            bodyHtml: $state['body_html'],
            templateKey: $state['template_key'] ?? null,
            audiences: $state['audiences'],
            externalRaw: $state['external_emails_raw'] ?? null,
        );

        Notification::make()
            ->title("تمت جدولة الحملة لـ {$campaign->total_recipients} مستلمًا")
            ->body('تُرسَل تدريجيًا عبر الطابور. تابع التقدّم من «سجل الحملات البريدية».')
            ->success()
            ->send();

        $this->form->fill();
    }

    protected function renderedPreviewHtml(): string
    {
        $body = (string) data_get($this->data, 'body_html');
        $name = (string) (auth()->user()?->name ?? 'صديقتنا');

        return view('emails.campaign', [
            'bodyHtml' => str_replace('{name}', e($name), CampaignHtml::sanitize($body)),
            'preheader' => (string) data_get($this->data, 'preheader'),
            'unsubscribeUrl' => '#',
        ])->render();
    }
}
