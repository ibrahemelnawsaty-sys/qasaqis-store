<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\RelationManagers\StatusHistoryRelationManager;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * سجلّ تغيّر حالة الطلب: السكيمة، سلوك المفاتيح الخارجية، قرارات التصميم
 * (لقطة نصية لا enum، ملحق-فقط)، وبوابة الصلاحية على الـ RelationManager.
 *
 * نطاق هذه المهمة هو الجدول + الموديل + الـ RelationManager فقط. كتابة الصفوف
 * فعليًا من OrderObserver وعلاقة Order::statusHistories() يملكهما المنسّق
 * (ملفّان مشتركان)، ولذلك يوجد أدناه اختبار عقد واحد يُتخطّى صراحةً
 * (markTestSkipped) حتى يربطهما المنسّق — لا يمرّ زورًا (ممنوع 18).
 *
 * Order بلا HasFactory (كما توثّق OrderFactory)، فيُنشأ عبر OrderFactory::new().
 */
final class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return OrderFactory::new()->create($overrides);
    }

    /**
     * تُبذر الأدوار والصلاحيات وتُفرّغ كاش spatie — لازم قبل givePermissionTo /
     * assignRole (نفس نمط FilamentAuthorizationTest).
     */
    private function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function history(Order $order, array $overrides = []): OrderStatusHistory
    {
        return OrderStatusHistory::create(array_merge([
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'processing',
            'note' => null,
            'actor_id' => null,
            'source' => OrderStatusHistory::SOURCE_SYSTEM,
        ], $overrides));
    }

    // ---------------------------------------------------------------- السكيمة

    public function test_table_exposes_exactly_the_contracted_columns(): void
    {
        $this->assertTrue(Schema::hasTable('order_status_histories'));

        foreach (['id', 'order_id', 'from_status', 'to_status', 'note', 'actor_id', 'source', 'created_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('order_status_histories', $column),
                "العمود المفقود: {$column}"
            );
        }
    }

    public function test_table_is_append_only_and_has_no_updated_at_column(): void
    {
        // السجل لا يُعدَّل بعد كتابته؛ وجود updated_at يوحي بعكس ذلك.
        $this->assertFalse(Schema::hasColumn('order_status_histories', 'updated_at'));
        $this->assertNull(OrderStatusHistory::UPDATED_AT);
    }

    public function test_composite_index_on_order_id_and_created_at_exists(): void
    {
        // استبطان السكيمة يستلزم information_schema — لا بديل عبر Eloquent، ولا
        // مدخلات مستخدم في الاستعلام (بند 2.5/4.2).
        $rows = DB::select(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            ['order_status_histories']
        );

        $byIndex = [];

        foreach ($rows as $row) {
            $byIndex[$row->INDEX_NAME][(int) $row->SEQ_IN_INDEX] = $row->COLUMN_NAME;
        }

        $composites = array_filter(
            array_map(static fn (array $cols): array => array_values($cols), $byIndex),
            static fn (array $cols): bool => $cols === ['order_id', 'created_at']
        );

        $this->assertNotEmpty(
            $composites,
            'لا يوجد فهرس مركّب (order_id, created_at). الفهارس الموجودة: '
                .json_encode($byIndex, JSON_UNESCAPED_UNICODE)
        );
    }

    // ------------------------------------------------------- الموديل والعلاقات

    public function test_row_persists_and_resolves_both_relations(): void
    {
        $actor = User::factory()->create();
        $order = $this->order();

        $row = $this->history($order, [
            'actor_id' => $actor->id,
            'source' => OrderStatusHistory::SOURCE_ADMIN,
            'note' => 'تأكيد بعد مكالمة واتساب',
        ]);

        $fresh = OrderStatusHistory::findOrFail($row->id);

        $this->assertTrue($fresh->order->is($order));
        $this->assertTrue($fresh->actor->is($actor));
        $this->assertSame('pending', $fresh->from_status);
        $this->assertSame('processing', $fresh->to_status);
        $this->assertSame('admin', $fresh->source);
        $this->assertSame('تأكيد بعد مكالمة واتساب', $fresh->note);
        $this->assertNotNull($fresh->created_at);
    }

    public function test_system_rows_have_a_null_actor(): void
    {
        $row = $this->history($this->order());

        $this->assertNull($row->actor_id);
        $this->assertNull($row->fresh()->actor);
    }

    public function test_first_entry_may_have_a_null_from_status(): void
    {
        $row = $this->history($this->order(), ['from_status' => null, 'to_status' => 'pending']);

        $this->assertNull($row->fresh()->from_status);
    }

    public function test_saving_never_writes_an_updated_at_column(): void
    {
        // لو لم يُضبط UPDATED_AT = null لحاول Eloquent كتابة عمود غير موجود.
        $row = $this->history($this->order());

        $row->note = 'ملاحظة لاحقة';
        $row->save();

        $this->assertSame('ملاحظة لاحقة', $row->fresh()->note);
    }

    // ------------------------------------------------- المفاتيح الخارجية وحدودها

    public function test_force_deleting_the_order_cascades_its_history(): void
    {
        $order = $this->order();
        $this->history($order);

        $order->forceDelete();

        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_soft_deleting_the_order_keeps_its_history(): void
    {
        // حالة حدّية: orders تستخدم softDeletes، فالحذف الناعم لا يُفعّل الـ cascade.
        $order = $this->order();
        $this->history($order);

        $order->delete();

        $this->assertNotNull($order->fresh()->deleted_at);
        $this->assertDatabaseCount('order_status_histories', 1);
    }

    public function test_force_deleting_the_actor_keeps_the_row_and_nulls_the_actor(): void
    {
        // تاريخ الطلب لا يُمحى بحذف حساب موظف — يبقى الصف بفاعل مجهول.
        $actor = User::factory()->create();
        $order = $this->order();
        $row = $this->history($order, ['actor_id' => $actor->id, 'source' => OrderStatusHistory::SOURCE_ADMIN]);

        $actor->forceDelete();

        $fresh = OrderStatusHistory::find($row->id);

        $this->assertNotNull($fresh, 'حُذف صف التاريخ مع المستخدم — يجب أن يكون nullOnDelete.');
        $this->assertNull($fresh->actor_id);
        $this->assertSame('processing', $fresh->to_status);
    }

    public function test_soft_deleting_the_actor_keeps_the_link(): void
    {
        // User يستخدم softDeletes: الحذف الناعم لا يُفعّل nullOnDelete.
        $actor = User::factory()->create();
        $row = $this->history($this->order(), ['actor_id' => $actor->id]);

        $actor->delete();

        $this->assertSame($actor->id, $row->fresh()->actor_id);
    }

    // --------------------------------------------------------- قيم source والحالة

    public function test_all_three_sources_are_accepted(): void
    {
        $order = $this->order();

        foreach ([
            OrderStatusHistory::SOURCE_ADMIN,
            OrderStatusHistory::SOURCE_SYSTEM,
            OrderStatusHistory::SOURCE_CUSTOMER,
        ] as $source) {
            $row = $this->history($order, ['source' => $source]);

            $this->assertSame($source, $row->fresh()->source);
        }
    }

    public function test_an_unknown_source_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        $this->history($this->order(), ['source' => 'webhook']); // خارج enum.
    }

    public function test_status_columns_survive_a_value_removed_from_the_orders_enum(): void
    {
        // قرار تصميمي متعمَّد: from_status/to_status نصّ لا enum، كي تبقى الصفوف
        // التاريخية صالحة لو حذفت هجرة لاحقة قيمة من enum الخاص بـ orders.status.
        $row = $this->history($this->order(), [
            'from_status' => 'legacy_hold',
            'to_status' => 'legacy_archived',
        ]);

        $fresh = $row->fresh();

        $this->assertSame('legacy_hold', $fresh->from_status);
        $this->assertSame('legacy_archived', $fresh->to_status);
    }

    // --------------------------------------- بوابة الصلاحية على الـ RelationManager

    public function test_relation_manager_is_hidden_from_a_user_without_orders_view(): void
    {
        $this->seedRoles();

        $this->actingAs(User::factory()->create()); // بلا أدوار ولا صلاحيات.

        $this->assertFalse(
            StatusHistoryRelationManager::canViewForRecord($this->order(), ViewOrder::class)
        );
    }

    public function test_relation_manager_is_visible_with_orders_view_only(): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->givePermissionTo('orders.view');

        $this->actingAs($user);

        $this->assertTrue(
            StatusHistoryRelationManager::canViewForRecord($this->order(), ViewOrder::class)
        );
    }

    public function test_relation_manager_is_hidden_from_an_unrelated_permission(): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->givePermissionTo('products.view'); // صلاحية أخرى لا تفتح الطلبات.

        $this->actingAs($user);

        $this->assertFalse(
            StatusHistoryRelationManager::canViewForRecord($this->order(), ViewOrder::class)
        );
    }

    public function test_super_admin_sees_the_relation_manager(): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->assignRole('super_admin'); // عبر Gate::before بلا صلاحية صريحة.

        $this->actingAs($user);

        $this->assertTrue(
            StatusHistoryRelationManager::canViewForRecord($this->order(), ViewOrder::class)
        );
    }

    public function test_guest_cannot_view_the_relation_manager(): void
    {
        $this->assertFalse(
            StatusHistoryRelationManager::canViewForRecord($this->order(), ViewOrder::class)
        );
    }

    public function test_every_orders_status_enum_value_has_an_arabic_label(): void
    {
        // شارتا «من/إلى» تعرضان OrderResource::STATUS_LABELS. أي قيمة في enum
        // الطلبات بلا تسمية ستُعرض للأدمن بالإنجليزية الخام (fallback الشارة)،
        // فهذا حارس على اكتمال التسميات لا على الشارة نفسها.
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['orders', 'status']
        );

        preg_match_all("/'([^']+)'/", (string) $column->type, $matches);
        $enumValues = $matches[1];

        $this->assertNotEmpty($enumValues, 'تعذّرت قراءة enum لعمود orders.status.');

        foreach ($enumValues as $value) {
            $this->assertArrayHasKey(
                $value,
                OrderResource::STATUS_LABELS,
                "قيمة الحالة «{$value}» بلا تسمية عربية في OrderResource::STATUS_LABELS."
            );
        }
    }

    // ------------------------------------- تصيير الـ RelationManager فعليًا

    /**
     * علاقة Order::statusHistories() يملكها المنسّق (Order.php ملف مشترك لا
     * أكتب فيه). تُسجَّل هنا وقت التشغيل عبر Model::resolveRelationUsing —
     * وهي حرفيًا نفس التعريف المطلوب في contractNeeds — كي يُختبر الـ
     * RelationManager تصييرًا حقيقيًا اليوم بدل تأجيله. لو أضاف المنسّق دالة
     * حقيقية فستتقدّم عليها تلقائيًا (__call لا يُستدعى لدالة موجودة).
     */
    private function ensureStatusHistoriesRelation(): void
    {
        if (method_exists(Order::class, 'statusHistories')) {
            return;
        }

        Order::resolveRelationUsing(
            'statusHistories',
            fn (Order $order) => $order->hasMany(OrderStatusHistory::class)
        );
    }

    private function actAsOrdersViewer(): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->givePermissionTo('orders.view');

        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_relation_manager_renders_the_timeline_newest_first(): void
    {
        $this->actAsOrdersViewer();
        $this->ensureStatusHistoriesRelation();

        $order = $this->order();
        $actor = User::factory()->create(['name' => 'مسؤول الطلبات']);

        $first = $this->history($order, ['from_status' => null, 'to_status' => 'pending']);
        $first->forceFill(['created_at' => now()->subDays(2)])->save();

        $second = $this->history($order, [
            'from_status' => 'pending',
            'to_status' => 'shipped',
            'source' => OrderStatusHistory::SOURCE_ADMIN,
            'actor_id' => $actor->id,
        ]);
        $second->forceFill(['created_at' => now()])->save();

        Livewire::test(StatusHistoryRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])
            ->assertSuccessful()
            // defaultSort('created_at', 'desc') — الأحدث أولًا.
            ->assertCanSeeTableRecords([$second, $first], inOrder: true)
            // التسميات العربية تمرّ فعليًا عبر formatStateUsing.
            ->assertSee('تم الشحن')          // STATUS_LABELS['shipped']
            ->assertSee('قيد الانتظار')       // STATUS_LABELS['pending']
            ->assertSee('لوحة التحكم')        // SOURCE_LABELS['admin']
            ->assertSee('مسؤول الطلبات')     // actor.name عبر العلاقة
            // أول قيد بلا حالة سابقة يُعرض شرطة لا فراغًا ولا خطأ نوع.
            ->assertSee('—')
            // فاعل فارغ في القيد الأول يُعرض «النظام» عبر placeholder العمود.
            ->assertSee('النظام');
    }

    public function test_relation_manager_shows_only_the_owner_orders_history(): void
    {
        $this->actAsOrdersViewer();
        $this->ensureStatusHistoriesRelation();

        $order = $this->order();
        $other = $this->order();

        $mine = $this->history($order);
        $theirs = $this->history($other, ['to_status' => 'delivered']);

        Livewire::test(StatusHistoryRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_relation_manager_renders_an_empty_state_without_history(): void
    {
        $this->actAsOrdersViewer();
        $this->ensureStatusHistoriesRelation();

        Livewire::test(StatusHistoryRelationManager::class, [
            'ownerRecord' => $this->order(),
            'pageClass' => ViewOrder::class,
        ])
            ->assertSuccessful()
            ->assertSee('لا يوجد تغيير مسجَّل لحالة هذا الطلب');
    }

    public function test_relation_manager_loads_actors_without_n_plus_one(): void
    {
        // Filament يحمّل علاقة عمود النقطة (actor.name) تلقائيًا في استعلام
        // whereIn واحد، فلا حاجة لـ with() يدوي. هذا الاختبار يثبّت ذلك السلوك
        // ويحرس من انحدار مستقبلي يستبدل العمود بإغلاق يحمّل لكل صف
        // (بند 2.5 / ممنوع 7). خمسة فاعلين مختلفين = استعلام واحد لا خمسة.
        $this->actAsOrdersViewer();
        $this->ensureStatusHistoriesRelation();

        $order = $this->order();

        foreach (range(1, 5) as $i) {
            $this->history($order, [
                'actor_id' => User::factory()->create()->id,
                'source' => OrderStatusHistory::SOURCE_ADMIN,
            ]);
        }

        $userQueries = 0;
        DB::listen(function ($query) use (&$userQueries): void {
            if (str_contains($query->sql, '`users`')) {
                $userQueries++;
            }
        });

        Livewire::test(StatusHistoryRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertSuccessful();

        // استعلام eager loading واحد للفاعلين الخمسة (زائد استعلامات المصادقة
        // العرضية). خمسة صفوف × استعلام لكل صف سيتجاوز هذا الحد بوضوح.
        $this->assertLessThanOrEqual(
            2,
            $userQueries,
            "عدد استعلامات users = {$userQueries} — يبدو أن الفاعلين يُحمَّلون صفًّا صفًّا (N+1)."
        );
    }

    // ------------------------------------------------------------- عقد المنسّق

    public function test_order_status_histories_relation_is_wired_by_the_orchestrator(): void
    {
        if (! method_exists(Order::class, 'statusHistories')) {
            $this->markTestSkipped(
                'Order::statusHistories() لم تُضَف بعد. Order.php ملف مشترك يملكه المنسّق '
                .'— انظر contractNeeds في تسليم هذه المهمة.'
            );
        }

        $order = $this->order();
        $row = $this->history($order);

        $this->assertTrue($order->statusHistories()->first()->is($row));
    }
}
