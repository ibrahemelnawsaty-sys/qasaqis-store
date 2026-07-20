<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\PickList;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Export\OrderCsvExporter;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * تصدير الطلبات CSV + قائمة التجهيز.
 *
 * يغطّي: شكل الملف (BOM/CRLF/عربي)، صف لكل بند، الطلب بلا بنود، تحييد حقن الصيغ،
 * الأموال كنصوص decimal، والفرض الخادمي لصلاحية orders.export و orders.view
 * (استدعاء الإجراء مباشرةً رغم إخفائه — بند 4.4 / ممنوع 13).
 */
final class OrderExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ---------------------------------------------------------------- شكل الملف

    public function test_csv_starts_with_a_utf8_bom_and_keeps_arabic_readable(): void
    {
        $order = $this->orderWith(
            ['customer_name' => 'أم محمد', 'status' => 'processing'],
            [['book_title' => 'أنا لستُ شقيًا!', 'quantity' => 2]],
        );

        $csv = $this->csvFor([$order->id]);

        $this->assertStringStartsWith(OrderCsvExporter::BOM, $csv);
        $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
        $this->assertStringContainsString('أم محمد', $csv);
        $this->assertStringContainsString('أنا لستُ شقيًا!', $csv);
        // نهايات سطور CRLF — ما يتوقعه إكسل على ويندوز.
        $this->assertStringContainsString("\r\n", $csv);
    }

    public function test_header_row_matches_the_exporter_contract(): void
    {
        $order = $this->orderWith([], [['book_title' => 'كتاب']]);

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertSame((new OrderCsvExporter)->headers(), $rows[0]);
        // آخر أربعة أعمدة هي أعمدة البند.
        $this->assertSame(['الكتاب', 'سعر الوحدة', 'الكمية', 'إجمالي البند'], array_slice($rows[0], -4));
    }

    public function test_every_order_item_becomes_its_own_row_with_the_order_columns_repeated(): void
    {
        $order = $this->orderWith(
            ['order_number' => 'QSQ-2026-000777'],
            [
                ['book_title' => 'كتاب أول', 'quantity' => 1],
                ['book_title' => 'كتاب ثانٍ', 'quantity' => 3],
            ],
        );

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertCount(3, $rows); // ترويسة + بندان.
        $this->assertSame('QSQ-2026-000777', $rows[1][0]);
        $this->assertSame('QSQ-2026-000777', $rows[2][0]);
        $this->assertSame('كتاب أول', $rows[1][23]);
        $this->assertSame('كتاب ثانٍ', $rows[2][23]);
        $this->assertSame('1', $rows[1][25]);
        $this->assertSame('3', $rows[2][25]);
    }

    public function test_an_order_without_items_still_produces_one_row(): void
    {
        // حالة حدّية: طلب بلا بنود يجب ألا يختفي من التصدير بصمت.
        $order = $this->orderWith(['order_number' => 'QSQ-2026-000001'], []);

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertCount(2, $rows);
        $this->assertSame('QSQ-2026-000001', $rows[1][0]);
        $this->assertSame(['', '', '', ''], array_slice($rows[1], -4));
    }

    public function test_enum_values_are_written_as_arabic_labels(): void
    {
        $order = $this->orderWith([
            'status' => 'processing',
            'payment_status' => 'pending_review',
            'payment_method' => 'instapay',
        ], [['book_title' => 'كتاب']]);

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertSame(OrderResource::STATUS_LABELS['processing'], $rows[1][2]);
        $this->assertSame(OrderResource::PAYMENT_STATUS_LABELS['pending_review'], $rows[1][3]);
        $this->assertSame(OrderResource::PAYMENT_METHOD_LABELS['instapay'], $rows[1][4]);
    }

    public function test_money_columns_stay_decimal_strings(): void
    {
        $order = $this->orderWith([
            'subtotal' => '150.50',
            'discount_total' => '10.25',
            'shipping_total' => '0.00',
            'grand_total' => '140.25',
        ], [['book_title' => 'كتاب', 'unit_price' => '150.50', 'quantity' => 1, 'line_total' => '150.50']]);

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertSame('150.50', $rows[1][18]);
        $this->assertSame('10.25', $rows[1][19]);
        $this->assertSame('0.00', $rows[1][20]);
        $this->assertSame('140.25', $rows[1][21]);
        $this->assertSame('150.50', $rows[1][24]);
    }

    public function test_formula_injection_from_customer_input_is_neutralised(): void
    {
        // اسم/عنوان/ملاحظة يكتبها العميل: خلية تبدأ بـ = أو + أو @ ينفّذها إكسل.
        $order = $this->orderWith([
            'customer_name' => '=HYPERLINK("http://evil.example","اضغط")',
            'address_line' => '+1+1',
            'customer_note' => '@SUM(A1:A9)',
        ], [['book_title' => "\tكتاب"]]);

        $rows = $this->parse($this->csvFor([$order->id]));

        $this->assertSame('\'=HYPERLINK("http://evil.example","اضغط")', $rows[1][5]);
        $this->assertSame("'+1+1", $rows[1][13]);
        $this->assertSame("'@SUM(A1:A9)", $rows[1][22]);
        $this->assertSame("'\tكتاب", $rows[1][23]);
    }

    public function test_negative_numbers_are_not_quoted_as_text(): void
    {
        // الشرطة تُستثنى للأرقام كي لا تتحول القيم الرقمية إلى نصوص في إكسل.
        $exporter = new OrderCsvExporter;
        $method = new \ReflectionMethod($exporter, 'neutraliseFormula');

        $this->assertSame('-5.00', $method->invoke($exporter, '-5.00'));
        $this->assertSame("'-cmd", $method->invoke($exporter, '-cmd'));
        $this->assertSame('', $method->invoke($exporter, ''));
        $this->assertSame('200.00', $method->invoke($exporter, '200.00'));
    }

    // ------------------------------------------------- صلاحية التصدير (خادميًا)

    public function test_a_user_without_orders_export_cannot_export(): void
    {
        $order = $this->orderWith([], [['book_title' => 'كتاب']]);

        $user = $this->adminUser('it'); // دور IT: يملك orders.view ولا يملك orders.export.
        $this->actingAs($user);

        $this->assertTrue($user->can('orders.view'));
        $this->assertFalse($user->can('orders.export'));
        $this->assertFalse(OrderResource::canExport());

        // الرفض خادمي وليس إخفاءً فقط: Filament\Actions\Concerns\CanBeDisabled::isDisabled()
        // يعيد true لأي إجراء مخفي، و HasBulkActions::mountTableBulkAction() يخرج
        // عند isDisabled(). فمحاولة الاستدعاء المباشر لا تُنفّذ الإجراء أصلًا،
        // و abort_unless داخل ->action() طبقة ثانية للحماية من تعديل لاحق.
        Livewire::test(ListOrders::class)
            ->assertTableBulkActionHidden('exportCsv')
            ->call('mountTableBulkAction', 'exportCsv', [$order->id])
            ->assertNoFileDownloaded();
    }

    public function test_the_two_bulk_actions_have_independent_gates(): void
    {
        // نفس المستخدم: قائمة التجهيز ظاهرة (orders.view) والتصدير مخفي (orders.export).
        $this->actingAs($this->adminUser('it'));

        Livewire::test(ListOrders::class)
            ->assertTableBulkActionHidden('exportCsv')
            ->assertTableBulkActionVisible('pickList');
    }

    public function test_orders_manager_can_export_and_receives_a_csv_download(): void
    {
        $order = $this->orderWith(['customer_name' => 'أم يوسف'], [['book_title' => 'كتاب']]);

        $this->actingAs($this->exporterUser()); // orders_manager: يملك orders.export.

        $this->assertTrue(OrderResource::canExport());

        Livewire::test(ListOrders::class)
            ->assertTableBulkActionVisible('exportCsv')
            ->callTableBulkAction('exportCsv', [$order])
            ->assertFileDownloaded();
    }

    public function test_super_admin_can_export_without_an_explicit_permission(): void
    {
        $this->actingAs($this->adminUser('super_admin'));

        $this->assertTrue(OrderResource::canExport());
    }

    public function test_the_downloaded_file_carries_the_bom_and_the_order_data(): void
    {
        $order = $this->orderWith(['customer_name' => 'أم سلمى'], [['book_title' => 'حكاية']]);

        $this->actingAs($this->exporterUser());

        $component = Livewire::test(ListOrders::class)
            ->callTableBulkAction('exportCsv', [$order]);

        $download = $component->effects['download'] ?? null;

        $this->assertIsArray($download);
        $this->assertStringEndsWith('.csv', (string) $download['name']);

        $content = base64_decode((string) $download['content'], true);

        $this->assertIsString($content);
        $this->assertStringStartsWith(OrderCsvExporter::BOM, $content);
        $this->assertStringContainsString('أم سلمى', $content);
        $this->assertStringContainsString('حكاية', $content);
    }

    public function test_export_is_refused_when_the_selection_exceeds_the_limit(): void
    {
        $this->actingAs($this->exporterUser());

        $ids = $this->seedBareOrders(OrderCsvExporter::MAX_ORDERS + 1);

        $this->assertCount(OrderCsvExporter::MAX_ORDERS + 1, $ids);

        Livewire::test(ListOrders::class)
            ->call('mountTableBulkAction', 'exportCsv', $ids)
            ->assertOk()
            ->assertNoFileDownloaded();
    }

    public function test_soft_deleted_orders_are_excluded_from_the_export(): void
    {
        $kept = $this->orderWith(['order_number' => 'QSQ-2026-000010'], [['book_title' => 'باقٍ']]);
        $trashed = $this->orderWith(['order_number' => 'QSQ-2026-000011'], [['book_title' => 'محذوف']]);
        $trashed->delete();

        $this->actingAs($this->exporterUser());

        $component = Livewire::test(ListOrders::class)
            ->call('mountTableBulkAction', 'exportCsv', [$kept->id, $trashed->id]);

        $content = base64_decode((string) ($component->effects['download']['content'] ?? ''), true);

        $this->assertIsString($content);
        $this->assertStringContainsString('QSQ-2026-000010', $content);
        $this->assertStringNotContainsString('QSQ-2026-000011', $content);
    }

    // ------------------------------------------------------------ قائمة التجهيز

    public function test_pick_list_is_forbidden_without_orders_view(): void
    {
        // دور تسويق: يدخل اللوحة (User::canAccessPanel) لكنه لا يملك orders.view،
        // فالرفض يأتي من canAccess() لا من بوّابة اللوحة.
        $marketer = $this->adminUser('marketing');
        $this->actingAs($marketer);

        $this->assertFalse($marketer->can('orders.view'));
        $this->assertFalse(PickList::canAccess());
        $this->get($this->pickListUrl())->assertForbidden();
    }

    public function test_pick_list_access_is_denied_for_a_user_with_no_permissions_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(PickList::canAccess());
    }

    public function test_pick_list_is_reachable_with_orders_view(): void
    {
        $this->actingAs($this->exporterUser());

        $this->assertTrue(PickList::canAccess());
        $this->get($this->pickListUrl())
            ->assertOk()
            ->assertSee(PickList::labels()['empty']); // بلا اختيار: إرشاد لا خطأ.
    }

    public function test_pick_list_groups_quantities_by_book_across_orders(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب مشترك', 'sku' => 'SKU-SHARED']);

        $orderA = $this->orderWith(['order_number' => 'QSQ-2026-000101'], [
            ['book_id' => $book->id, 'book_title' => 'كتاب مشترك', 'quantity' => 2],
        ]);
        $orderB = $this->orderWith(['order_number' => 'QSQ-2026-000102'], [
            ['book_id' => $book->id, 'book_title' => 'كتاب مشترك', 'quantity' => 3],
            ['book_id' => null, 'book_title' => 'كتاب منفرد', 'quantity' => 1],
        ]);

        $this->actingAs($this->exporterUser());

        $page = Livewire::withQueryParams(['orders' => $orderA->id.','.$orderB->id])
            ->test(PickList::class);
        $summary = $page->viewData('summary');

        $this->assertSame(
            [
                ['title' => 'كتاب مشترك', 'sku' => 'SKU-SHARED', 'quantity' => 5, 'orders_count' => 2],
                ['title' => 'كتاب منفرد', 'sku' => '', 'quantity' => 1, 'orders_count' => 1],
            ],
            $summary,
        );
    }

    public function test_pick_list_renders_a_packing_slip_per_selected_order(): void
    {
        $orderA = $this->orderWith(
            ['order_number' => 'QSQ-2026-000201', 'customer_name' => 'أم ليلى', 'city' => 'الجيزة'],
            [['book_title' => 'كتاب أ', 'quantity' => 1]],
        );
        $orderB = $this->orderWith(
            ['order_number' => 'QSQ-2026-000202', 'customer_name' => 'أم آدم'],
            [['book_title' => 'كتاب ب', 'quantity' => 2]],
        );
        $other = $this->orderWith(['order_number' => 'QSQ-2026-000203'], [['book_title' => 'كتاب ج']]);

        $this->actingAs($this->exporterUser());

        $this->get($this->pickListUrl($orderA->id.','.$orderB->id))
            ->assertOk()
            ->assertSee('QSQ-2026-000201')
            ->assertSee('QSQ-2026-000202')
            ->assertSee('أم ليلى')
            ->assertSee('الجيزة')
            ->assertSee(PickList::labels()['summary_heading'])
            // الطلب غير المُختار لا يظهر.
            ->assertDontSee('QSQ-2026-000203');
        $this->assertNotNull($other);
    }

    public function test_pick_list_shows_the_amount_to_collect_for_unpaid_cod_only(): void
    {
        $cod = $this->orderWith(
            ['payment_method' => 'cod', 'payment_status' => 'unpaid', 'grand_total' => '275.00'],
            [['book_title' => 'كتاب']],
        );
        $paid = $this->orderWith(
            ['payment_method' => 'instapay', 'payment_status' => 'paid', 'grand_total' => '99.00'],
            [['book_title' => 'كتاب']],
        );

        $this->assertSame('275.00', PickList::amountToCollect($cod));
        $this->assertNull(PickList::amountToCollect($paid));
    }

    public function test_pick_list_ignores_ids_that_are_not_positive_integers(): void
    {
        // مدخل خارجي: قائمة بيضاء صارمة، بلا حقن ولا ثقة (بند 4.1).
        $this->assertSame([12, 7], PickList::parseIds('12, 7 ,abc,-3,0,,9.5,7'));
        $this->assertSame([], PickList::parseIds(''));
        $this->assertSame([], PickList::parseIds("1' OR '1'='1"));
    }

    public function test_pick_list_truncates_a_selection_larger_than_its_limit(): void
    {
        $this->actingAs($this->exporterUser());

        $raw = implode(',', range(1, PickList::MAX_ORDERS + 5));

        $page = Livewire::withQueryParams(['orders' => $raw])->test(PickList::class);

        $this->assertCount(PickList::MAX_ORDERS, $page->get('orderIds'));
        $this->assertSame(PickList::MAX_ORDERS + 5, $page->get('requestedCount'));
        $this->assertTrue($page->viewData('truncated'));
    }

    public function test_pick_list_bulk_action_redirects_to_a_page_that_lists_the_selection(): void
    {
        // الرحلة كاملة: تحديد في جدول الطلبات ← إعادة توجيه ← صفحة طباعة صحيحة.
        $order = $this->orderWith(
            ['order_number' => 'QSQ-2026-000501', 'customer_name' => 'أم هدى'],
            [['book_title' => 'كتاب الرحلة']],
        );

        $this->actingAs($this->exporterUser());

        $component = Livewire::test(ListOrders::class)
            ->callTableBulkAction('pickList', [$order])
            ->assertRedirectContains('orders=');

        $this->get((string) $component->effects['redirect'])
            ->assertOk()
            ->assertSee('QSQ-2026-000501')
            ->assertSee('أم هدى')
            ->assertSee('كتاب الرحلة');
    }

    public function test_pick_list_falls_back_to_the_snapshot_title_when_the_book_is_deleted(): void
    {
        // book_id يبقى بعد الحذف الناعم لكن العلاقة تعود null (SoftDeletes على books):
        // العنوان يأتي من لقطة order_items، والكود يُترك فارغًا بدل الانهيار.
        $book = Book::factory()->create(['sku' => 'SKU-GONE']);

        $order = $this->orderWith(['order_number' => 'QSQ-2026-000601'], [
            ['book_id' => $book->id, 'book_title' => 'عنوان محفوظ', 'quantity' => 4],
        ]);

        $book->delete();

        $this->actingAs($this->exporterUser());

        $summary = Livewire::withQueryParams(['orders' => (string) $order->id])
            ->test(PickList::class)
            ->viewData('summary');

        $this->assertSame(
            [['title' => 'عنوان محفوظ', 'sku' => '', 'quantity' => 4, 'orders_count' => 1]],
            $summary,
        );
    }

    public function test_a_user_without_orders_view_cannot_reach_the_orders_table_at_all(): void
    {
        // قائمة التجهيز والجدول يتشاركان orders.view، فمن لا يملكها لا يصل للجدول
        // أصلًا — الحاجز الأول قبل أي إجراء جماعي.
        $marketer = $this->adminUser('marketing');
        $this->actingAs($marketer);

        $this->assertFalse(OrderResource::canViewAny());
        $this->assertFalse(PickList::canAccess());
    }

    /**
     * الملخّص والبوالص يبنيان من استعلامات ثابتة العدد مهما كثرت الطلبات
     * (طلبات + بنود + كتب = 3)، لا استعلام داخل حلقة (بند 2.5 / ممنوع 7).
     */
    public function test_pick_list_does_not_produce_n_plus_one_queries(): void
    {
        $book = Book::factory()->create(['sku' => 'SKU-N1']);
        $ids = [];

        for ($i = 1; $i <= 8; $i++) {
            $ids[] = $this->orderWith(
                ['order_number' => 'QSQ-2026-00090'.$i],
                [
                    ['book_id' => $book->id, 'book_title' => 'كتاب مشترك', 'quantity' => 2],
                    ['book_id' => null, 'book_title' => 'كتاب بلا مرجع '.$i, 'quantity' => 1],
                ],
            )->id;
        }

        $this->actingAs($this->exporterUser());

        DB::enableQueryLog();
        $this->get($this->pickListUrl(implode(',', $ids)))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $dataQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => (bool) preg_match('/from `(orders|order_items|books)`/', $sql),
        ));

        // 3 فقط: الطلبات، بنودها، كتبها — بلا استعلام لكل طلب.
        // (استعلامات count على orders تخصّ شارة التنقّل القائمة، مستثناة أدناه.)
        $dataQueries = array_values(array_filter(
            $dataQueries,
            static fn (string $sql): bool => ! str_contains($sql, 'count(*)'),
        ));

        $this->assertCount(3, $dataQueries, implode("\n", $dataQueries));
    }

    // --------------------------------------------------------------- مساعدات

    /** مستخدم يملك orders.export و orders.view معًا. */
    private function exporterUser(): User
    {
        return $this->adminUser('orders_manager');
    }

    /**
     * مستخدم لوحة فعّال بدور محدد.
     *
     * is_active يُمرَّر صراحةً: قيمته الافتراضية في السكيمة لا تُحمَّل في نسخة
     * الموديل التي يعيدها create()، فتبقى null، و User::canAccessPanel() يقارن
     * بـ === true فيرفض الدخول بـ 403 قبل الوصول إلى أي فحص صلاحية.
     */
    private function adminUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function pickListUrl(?string $orders = null): string
    {
        return '/admin/'.PickList::getSlug().($orders === null ? '' : '?orders='.urlencode($orders));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    private function orderWith(array $attributes, array $items): Order
    {
        $order = OrderFactory::new()->create($attributes);

        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'book_id' => $item['book_id'] ?? null,
                'book_title' => $item['book_title'] ?? 'كتاب',
                'unit_price' => $item['unit_price'] ?? '100.00',
                'quantity' => $item['quantity'] ?? 1,
                'line_total' => $item['line_total'] ?? '100.00',
            ]);
        }

        return $order->refresh();
    }

    /**
     * طلبات هيكلية سريعة (إدراج واحد) لاختبار السقف دون تكلفة factory لكل صف.
     *
     * @return list<int>
     */
    private function seedBareOrders(int $count): array
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'order_number' => 'QSQ-BULK-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'customer_name' => 'عميل',
                'customer_phone' => '01000000000',
                'governorate' => 'القاهرة',
                'address_line' => 'عنوان',
                'subtotal' => '100.00',
                'discount_total' => '0.00',
                'shipping_total' => '0.00',
                'grand_total' => '100.00',
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Order::query()->insert($chunk);
        }

        return Order::query()->where('order_number', 'like', 'QSQ-BULK-%')->pluck('id')->all();
    }

    /**
     * @param  list<int>  $ids
     */
    private function csvFor(array $ids): string
    {
        $orders = Order::query()->whereKey($ids)->with('items')->orderBy('id')->get();

        $handle = fopen('php://temp', 'r+b');
        (new OrderCsvExporter)->write($handle, $orders);
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return list<list<string>>
     */
    private function parse(string $csv): array
    {
        $body = str_starts_with($csv, OrderCsvExporter::BOM)
            ? substr($csv, strlen(OrderCsvExporter::BOM))
            : $csv;

        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, $body);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
