<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\AdminActivityResource;
use App\Models\AdminActivity;
use App\Models\Page;
use App\Models\User;
use App\Support\Audit\RecordsAdminActivity;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * سجل «من غيّر ماذا ومتى»: التقاط الأحداث، حجب الحسّاس، وقفل المورد على القراءة.
 *
 * الموديلات الثلاثة في ذيل الملف موديلات اختبار فقط: تجلس على جداول قائمة
 * (settings / pages / users) وتستعمل الـ trait، فتُختبر آليةُ التسجيل نفسها دون
 * انتظار أن يربط المنسّق الـ trait بموديلات الإنتاج. أحداث Eloquent مفهرَسة باسم
 * الصنف، فالموديل الأصلي (Page مثلًا) يبقى غير مُدقَّق تمامًا — وهو ما يجعل تهيئة
 * الاختبار عبر Page::factory() لا تلوّث السجل.
 */
final class AdminActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ----- متى يُسجَّل ومتى لا يُسجَّل -----------------------------------------

    public function test_nothing_is_recorded_without_an_authenticated_user(): void
    {
        AuditTestSetting::query()->create(['key' => 'probe_guest', 'value' => 'x']);

        $this->assertSame(0, AdminActivity::query()->count());
    }

    public function test_nothing_is_recorded_for_a_user_without_an_admin_role(): void
    {
        // مستخدم بلا أي دور من أدوار اللوحة السبعة (عميل متجر مثلًا): فعله ليس
        // نشاطًا إداريًا ولا يجوز أن يغرق السجل.
        $this->actingAs(User::factory()->create());

        AuditTestSetting::query()->create(['key' => 'probe_customer', 'value' => 'x']);

        $this->assertSame(0, AdminActivity::query()->count());
    }

    public function test_creation_is_recorded_with_actor_subject_and_ip(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        app('request')->server->set('REMOTE_ADDR', '203.0.113.9');

        $setting = AuditTestSetting::query()->create([
            'key' => 'probe_created',
            'value' => 'قيمة عربية',
            'group' => 'general',
            'type' => 'string',
        ]);

        $activity = AdminActivity::query()->sole();

        $this->assertSame((int) $admin->id, (int) $activity->user_id);
        $this->assertSame(AuditTestSetting::class, $activity->subject_type);
        $this->assertSame((int) $setting->id, (int) $activity->subject_id);
        $this->assertSame(AdminActivity::EVENT_CREATED, $activity->event);
        $this->assertSame('203.0.113.9', $activity->ip_address);

        $changes = $activity->changes;
        $this->assertSame('قيمة عربية', $changes['value']['new']);
        $this->assertNull($changes['value']['old']);
        $this->assertSame('probe_created', $changes['key']['new']);

        // ضجيج مستبعَد: المفتاح الأساسي وطابعا الوقت موجودون أصلًا في السطر نفسه.
        $this->assertArrayNotHasKey('id', $changes);
        $this->assertArrayNotHasKey('created_at', $changes);
        $this->assertArrayNotHasKey('updated_at', $changes);
    }

    public function test_update_records_only_the_changed_fields_with_old_and_new(): void
    {
        $setting = AuditTestSetting::query()->create(['key' => 'probe_update', 'value' => 'قديم']);

        $this->actingAs($this->admin());

        $setting->forceFill(['value' => 'جديد'])->save();

        $activity = AdminActivity::query()->sole();

        $this->assertSame(AdminActivity::EVENT_UPDATED, $activity->event);
        $this->assertSame(['value'], $activity->changedFields());
        $this->assertSame('قديم', $activity->changes['value']['old']);
        $this->assertSame('جديد', $activity->changes['value']['new']);
    }

    public function test_a_save_that_only_touches_the_timestamp_records_nothing(): void
    {
        $setting = AuditTestSetting::query()->create(['key' => 'probe_touch', 'value' => 'ثابت']);

        $this->actingAs($this->admin());

        // updated_at وحده يتغيّر → الفرق يخرج فارغًا بعد الترشيح → لا سطر.
        $setting->forceFill(['updated_at' => now()->addMinute()])->save();

        $this->assertSame(0, AdminActivity::query()->count());
    }

    public function test_deletion_is_recorded(): void
    {
        $setting = AuditTestSetting::query()->create(['key' => 'probe_delete', 'value' => 'يُحذف']);

        $this->actingAs($this->admin());

        $setting->delete();

        $activity = AdminActivity::query()->sole();

        $this->assertSame(AdminActivity::EVENT_DELETED, $activity->event);
        $this->assertSame('يُحذف', $activity->changes['value']['old']);
        $this->assertNull($activity->changes['value']['new']);
    }

    public function test_restoring_a_soft_deleted_record_writes_one_row_not_two(): void
    {
        // ‏SoftDeletes::restore() يمرّ عبر save() فيُطلق updated بجانب restored؛
        // بلا إسقاط الأول تظهر عملية استعادة واحدة كسطرين.
        // الموديل الأصلي غير مُدقَّق → تهيئة نظيفة بلا أسطر سجل.
        // (‏Page لا يستعمل HasFactory، فالإنشاء مباشر بحقول fillable المتحقَّق منها.)
        $page = Page::query()->create([
            'title' => 'صفحة اختبار التدقيق',
            'slug' => 'audit-restore-probe',
            'content' => '<p>محتوى</p>',
            'is_published' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin());

        $audited = AuditTestPage::query()->findOrFail($page->id);
        $audited->delete();
        $audited->restore();

        $this->assertSame(
            [AdminActivity::EVENT_DELETED, AdminActivity::EVENT_RESTORED],
            AdminActivity::query()->orderBy('id')->pluck('event')->all(),
        );
    }

    // ----- حجب الحقول الحسّاسة (بند 4.3) ------------------------------------

    public function test_password_and_token_values_are_masked_never_stored(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin());

        $audited = AuditTestUser::query()->findOrFail($target->id);
        $audited->forceFill([
            'password' => 'plain-text-secret-value',
            'remember_token' => 'raw-remember-token-value',
            'name' => 'اسم جديد',
        ])->save();

        $activity = AdminActivity::query()->sole();
        $encoded = json_encode($activity->changes, JSON_UNESCAPED_UNICODE);

        $this->assertSame(AdminActivity::REDACTED_MARK, $activity->changes['password']['new']);
        $this->assertSame(AdminActivity::REDACTED_MARK, $activity->changes['remember_token']['new']);

        // لا القيمة الخام ولا تجزئتها تصل إلى الجدول.
        $this->assertStringNotContainsString('plain-text-secret-value', (string) $encoded);
        $this->assertStringNotContainsString('raw-remember-token-value', (string) $encoded);
        $this->assertStringNotContainsString('$2y$', (string) $encoded);

        // الحقول غير الحسّاسة تبقى مقروءة — الحجب انتقائي لا شامل.
        $this->assertSame('اسم جديد', $activity->changes['name']['new']);
    }

    public function test_secrets_nested_inside_a_json_value_are_masked_too(): void
    {
        $setting = AuditTestSetting::query()->create(['key' => 'probe_json', 'value' => '{}']);

        $this->actingAs($this->admin());

        $setting->forceFill([
            'value' => '{"api_key":"live_ABC123","provider":"paymob"}',
        ])->save();

        $activity = AdminActivity::query()->sole();
        $stored = (string) $activity->changes['value']['new'];

        $this->assertStringNotContainsString('live_ABC123', $stored);
        $this->assertStringContainsString(AdminActivity::REDACTED_MARK, $stored);
        $this->assertStringContainsString('paymob', $stored); // البقية تبقى مفيدة.
    }

    // ----- الموديل ----------------------------------------------------------

    public function test_changes_column_is_readable_despite_the_eloquent_property_clash(): void
    {
        // العمود `changes` يشترك في الاسم مع الخاصية الداخلية HasAttributes::$changes.
        // هذا الاختبار يثبت أن القراءة من خارج الصنف تعطي قيمة العمود لا الخاصية.
        $activity = AdminActivity::query()->create([
            'user_id' => null,
            'subject_type' => 'App\\Models\\Order',
            'subject_id' => 7,
            'event' => AdminActivity::EVENT_UPDATED,
            'changes' => ['status' => ['old' => 'pending', 'new' => 'processing']],
            'ip_address' => null,
        ]);

        $fresh = AdminActivity::query()->findOrFail($activity->getKey());

        $this->assertSame(['status' => ['old' => 'pending', 'new' => 'processing']], $fresh->changes);
        $this->assertSame(['status'], $fresh->changedFields());
        $this->assertSame('طلب', AdminActivityResource::subjectLabel('App\\Models\\Order'));
        $this->assertSame('Widget', AdminActivityResource::subjectLabel('App\\Models\\Widget')); // نوع غير معروف.
    }

    // ----- الصلاحيات (بند 4.4 / ممنوع 13) -----------------------------------

    public function test_viewing_the_log_requires_the_system_logs_view_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(AdminActivityResource::canViewAny());

        $user->givePermissionTo('system.logs.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh());

        $this->assertTrue(AdminActivityResource::canViewAny());
    }

    public function test_the_it_role_already_carries_the_required_permission(): void
    {
        // لا صلاحية جديدة مطلوبة: system.logs.view مبذورة أصلًا ويملكها دور it.
        $it = User::factory()->create();
        $it->assignRole('it');

        $this->actingAs($it);

        $this->assertTrue(AdminActivityResource::canViewAny());
    }

    public function test_the_log_is_read_only_even_for_super_admin(): void
    {
        $super = User::factory()->create();
        $super->assignRole('super_admin');

        $this->actingAs($super);

        $activity = AdminActivity::query()->create([
            'user_id' => $super->id,
            'subject_type' => 'App\\Models\\Book',
            'subject_id' => 1,
            'event' => AdminActivity::EVENT_CREATED,
            'changes' => [],
            'ip_address' => null,
        ]);

        // Gate::before يمنح super_admin كل شيء — لذلك تُغلق البوابات صراحةً.
        $this->assertTrue(AdminActivityResource::canViewAny());
        $this->assertFalse(AdminActivityResource::canCreate());
        $this->assertFalse(AdminActivityResource::canEdit($activity));
        $this->assertFalse(AdminActivityResource::canDelete($activity));
        $this->assertFalse(AdminActivityResource::canDeleteAny());
        $this->assertFalse(AdminActivityResource::canRestore($activity));
        $this->assertFalse(AdminActivityResource::canRestoreAny());
        $this->assertFalse(AdminActivityResource::canForceDelete($activity));
        $this->assertFalse(AdminActivityResource::canForceDeleteAny());
    }

    // ----- تشغيل فعلي للشاشتين (المرحلة د) ----------------------------------

    public function test_an_authorised_user_can_open_the_list_and_the_detail_screens(): void
    {
        // تشغيل حقيقي لا فحص دوال: يمرّ على أعمدة الجدول والمرشّحات (بما فيها
        // استعلام خيارات subject_type) وعلى infolist الفرق كاملة.
        //
        // ‏is_active صريحة: المصنع لا يضبطها، وcreate() لا يعيد القراءة من القاعدة،
        // فتبقى null في الذاكرة وتُسقط User::canAccessPanel() بـ403 قبل أن يُفحص
        // المورد أصلًا — أي اختبار يمرّ لسبب خاطئ.
        $it = User::factory()->create(['is_active' => true]);
        $it->assignRole('it');

        $activity = AdminActivity::query()->create([
            'user_id' => $it->id,
            'subject_type' => 'App\\Models\\Order',
            'subject_id' => 3,
            'event' => AdminActivity::EVENT_UPDATED,
            'changes' => ['status' => ['old' => 'pending', 'new' => 'processing']],
            'ip_address' => '198.51.100.4',
        ]);

        $this->actingAs($it)
            ->get(AdminActivityResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($it)
            ->get(AdminActivityResource::getUrl('view', ['record' => $activity]))
            ->assertOk();
    }

    public function test_a_panel_user_without_the_permission_is_refused_the_log_screen(): void
    {
        // دور marketing يدخل اللوحة لكنه لا يملك system.logs.view — الرفض خادمي
        // لا مجرد إخفاء عنصر تنقّل (بند 4.4 / ممنوع 13).
        $marketing = User::factory()->create(['is_active' => true]);
        $marketing->assignRole('marketing');

        // يُثبَت أولًا أنه يدخل اللوحة فعلًا، وإلا لجاء الـ403 من بوابة اللوحة
        // فمرّ الاختبار لسبب خاطئ (ممنوع 18).
        $this->assertTrue($marketing->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($marketing)
            ->get(AdminActivityResource::getUrl('index'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}

/**
 * موديل اختبار على جدول settings القائم — أبسط جدول يغطي إنشاء/تعديل/حذف.
 */
final class AuditTestSetting extends Model
{
    use RecordsAdminActivity;

    protected $table = 'settings';

    protected $guarded = [];
}

/**
 * موديل اختبار على جدول pages القائم — يحتاج SoftDeletes لتغطية restored.
 * يرث الجدول والـ casts من Page، وأحداثه مفهرَسة باسمه هو فلا يتأثر Page نفسه.
 */
final class AuditTestPage extends Page
{
    use RecordsAdminActivity;

    // بدونه تشتق Eloquent الجدول من اسم الصنف (audit_test_pages).
    protected $table = 'pages';
}

/**
 * موديل اختبار على جدول users القائم — يحمل password و remember_token لاختبار الحجب.
 */
final class AuditTestUser extends User
{
    use RecordsAdminActivity;

    // بدونه تشتق Eloquent الجدول من اسم الصنف (audit_test_users).
    protected $table = 'users';
}
