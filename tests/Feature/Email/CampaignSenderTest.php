<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Filament\Pages\SendEmailCampaign;
use App\Filament\Resources\EmailCampaignResource;
use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\EmailCampaign;
use App\Models\EmailRecipient;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Services\Email\CampaignDispatcher;
use App\Support\Email\CampaignHtml;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * المُرسِل الإداري للحملات: التنقية (تكرار/حظر/جمهور)، الإرسال لكل مستلم، إلغاء
 * الاشتراك، وبوّابات الصلاحية. الطابور sync والبريد array (phpunit.xml) فتُنفَّذ
 * المهام مزامنةً وتُلتقَط الرسائل.
 */
class CampaignSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function dispatcher(): CampaignDispatcher
    {
        return app(CampaignDispatcher::class);
    }

    private function marketingUser(): User
    {
        // ثابت وقابل لإعادة الاستدعاء: إرسالات متعددة في اختبار واحد تعيد نفس المستخدم
        // (بريده فريد) بدل خرق قيد users.email.
        $u = User::where('email', 'mkt@qasaqis.store')->first()
            ?? User::factory()->create(['email' => 'mkt@qasaqis.store']);

        if (! $u->hasRole('marketing')) {
            $u->assignRole('marketing');
        }

        return $u;
    }

    // ----- الصلاحيات --------------------------------------------------------

    public function test_campaign_permissions_are_seeded_and_scoped(): void
    {
        $marketing = $this->marketingUser();
        $this->assertTrue($marketing->can('campaigns.view'));
        $this->assertTrue($marketing->can('campaigns.send'));

        $support = User::factory()->create();
        $support->assignRole('support');
        $this->assertFalse($support->can('campaigns.view'));
        $this->assertFalse($support->can('campaigns.send'));
    }

    public function test_page_and_resource_access_require_view_permission(): void
    {
        $this->actingAs($this->marketingUser());
        $this->assertTrue(SendEmailCampaign::canAccess());
        $this->assertTrue(EmailCampaignResource::canViewAny());

        $support = User::factory()->create();
        $support->assignRole('support');
        $this->actingAs($support);
        $this->assertFalse(SendEmailCampaign::canAccess());
        $this->assertFalse(EmailCampaignResource::canViewAny());
        // السجلّ للقراءة فقط مهما كان الدور.
        $this->assertFalse(EmailCampaignResource::canCreate());
    }

    // ----- التعقيم ----------------------------------------------------------

    public function test_sanitizer_strips_scripts_and_keeps_safe_markup(): void
    {
        $clean = CampaignHtml::sanitize(
            '<p>نص <strong>مهم</strong></p><script>x()</script>'
            . '<a href="javascript:e()">سيّئ</a><a href="http://qasaqis.store/a" onclick="e()">رابط</a>'
        );

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        // http يُرقّى إلى https لا يُحذف.
        $this->assertStringContainsString('https://qasaqis.store/a', $clean);
    }

    // ----- التنقية والإرسال -------------------------------------------------

    public function test_dispatch_dedupes_suppresses_and_sends_one_per_recipient(): void
    {
        Mail::fake();

        // المُرسِل (createdBy) نفسه مستخدم لوحة نشِط ببريد — فيدخل ضمن جمهور panel_users.
        $sender = $this->marketingUser(); // mkt@qasaqis.store

        // عملاء: مُوثّق + غير مُوثّق + عميل بريده سيُحظَر.
        Customer::factory()->withPhone('01000000001')->create(['email' => 'a@x.test', 'email_verified_at' => now()]);
        Customer::factory()->withPhone('01000000002')->create(['email' => 'b@x.test', 'email_verified_at' => null]);
        Customer::factory()->withPhone('01000000003')->create(['email' => 'c@x.test', 'email_verified_at' => null]);
        // مستخدم لوحة ببريد مكرّر مع عميل (يجب أن يفوز مصدر العميل بترتيب الدمج).
        User::factory()->create(['email' => 'b@x.test']);

        EmailSuppression::create(['email' => 'c@x.test', 'reason' => 'unsubscribe']);

        $external = "b@x.test\nnew@ext.test\nنص-ليس-بريدًا\nc@x.test";

        $campaign = $this->dispatcher()->dispatch(
            createdBy: $sender->id,
            subject: 'عرض العيد',
            preheader: 'خصم خاص',
            bodyHtml: '<p>أهلًا {name} — <a href="http://qasaqis.store">تسوّقي</a></p>',
            templateKey: 'sale',
            audiences: ['all_customers', 'panel_users', 'external'],
            externalRaw: $external,
        );

        // a@x + b@x (عميل، فاز على اللوحة والخارجي) + mkt (مستخدم اللوحة/المُرسِل) + new@ext = 4.
        // c@x محظور، والنص غير الصالح مُستبعَد.
        $this->assertSame(4, $campaign->total_recipients);
        $this->assertSame(4, EmailRecipient::where('email_campaign_id', $campaign->id)->count());

        $emails = EmailRecipient::where('email_campaign_id', $campaign->id)->pluck('email')->sort()->values()->all();
        $this->assertSame(['a@x.test', 'b@x.test', 'mkt@qasaqis.store', 'new@ext.test'], $emails);

        // مصدر b@x يجب أن يكون customer لا panel_user/external (فوز ترتيب الدمج).
        $this->assertSame('customer', EmailRecipient::where('email_campaign_id', $campaign->id)->where('email', 'b@x.test')->value('source'));

        // توكن سرّي 48 حرفًا لكل مستلم، والكل أُرسل (sync).
        $this->assertSame(4, EmailRecipient::where('email_campaign_id', $campaign->id)->where('status', 'sent')->count());
        $this->assertSame(48, strlen((string) EmailRecipient::where('email_campaign_id', $campaign->id)->first()->getRawOriginal('token')));

        // رسالة مستقلّة لكل مستلم (لا BCC).
        Mail::assertSent(CampaignMail::class, 4);

        // اكتملت الحملة عبر ردّ الدفعة finally.
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(4, $campaign->fresh()->sent_count);
        // المحتوى مخزَّن معقَّمًا.
        $this->assertStringNotContainsString('<script', $campaign->body_html);
    }

    public function test_verified_only_audience_limits_recipients(): void
    {
        Mail::fake();
        Customer::factory()->withPhone('01000000011')->create(['email' => 'v@x.test', 'email_verified_at' => now()]);
        Customer::factory()->withPhone('01000000012')->create(['email' => 'u@x.test', 'email_verified_at' => null]);

        $campaign = $this->dispatcher()->dispatch(
            createdBy: $this->marketingUser()->id,
            subject: 'للموثّقين', preheader: null,
            bodyHtml: '<p>مرحبًا {name}</p>', templateKey: null,
            audiences: ['verified_customers'], externalRaw: null,
        );

        $this->assertSame(1, $campaign->total_recipients);
        $this->assertSame('v@x.test', EmailRecipient::where('email_campaign_id', $campaign->id)->value('email'));
    }

    public function test_empty_audience_completes_without_recipients(): void
    {
        Mail::fake();
        $campaign = $this->dispatcher()->dispatch(
            createdBy: $this->marketingUser()->id,
            subject: 'لا أحد', preheader: null,
            bodyHtml: '<p>x</p>', templateKey: null,
            audiences: ['external'], externalRaw: 'invalid-only',
        );

        $this->assertSame(0, $campaign->total_recipients);
        $this->assertSame('sent', $campaign->fresh()->status);
        Mail::assertNothingSent();
    }

    // ----- إلغاء الاشتراك ---------------------------------------------------

    public function test_unsubscribe_show_then_confirm_suppresses_and_excludes_next_time(): void
    {
        Mail::fake();
        Customer::factory()->withPhone('01000000021')->create(['email' => 'bye@x.test', 'email_verified_at' => now()]);

        $campaign = $this->dispatcher()->dispatch(
            createdBy: $this->marketingUser()->id,
            subject: 'حملة', preheader: null,
            bodyHtml: '<p>{name}</p>', templateKey: null,
            audiences: ['all_customers'], externalRaw: null,
        );
        $token = EmailRecipient::where('email_campaign_id', $campaign->id)->first()->getRawOriginal('token');

        // صفحة التأكيد (GET).
        $this->get("/email/unsubscribe/{$token}")
            ->assertOk()
            ->assertSee('bye@x.test');

        // التنفيذ (POST) — يخدم أيضًا نقرة One-Click.
        $this->post("/email/unsubscribe/{$token}")
            ->assertOk()
            ->assertSee('تم إلغاء');

        $this->assertDatabaseHas('email_suppressions', ['email' => 'bye@x.test']);
        $this->assertSame('unsubscribed', EmailRecipient::where('token', $token)->value('status'));

        // حملة لاحقة لا تصل إليه.
        $next = $this->dispatcher()->dispatch(
            createdBy: $this->marketingUser()->id,
            subject: 'حملة 2', preheader: null,
            bodyHtml: '<p>{name}</p>', templateKey: null,
            audiences: ['all_customers'], externalRaw: null,
        );
        $this->assertSame(0, $next->total_recipients);
    }

    public function test_unsubscribe_bad_token_is_not_found(): void
    {
        $this->get('/email/unsubscribe/'.str_repeat('z', 48))->assertNotFound();
    }
}
