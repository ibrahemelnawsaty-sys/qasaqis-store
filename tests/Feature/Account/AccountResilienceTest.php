<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * صفحات الحساب المسجّلة يجب ألّا تُسقطها ميزة ثانوية غاب جدولها — كأن يُنشر الكود قبل
 * تشغيل الهجرة على الإنتاج (كود جديد على مخطّط قديم، DEPLOYMENT §12). درسٌ من حادثة
 * /checkout: نفس جدول customer_addresses كان يُقرأ بلا حرس في «بياناتي» أيضًا.
 *
 * تُعيد كلّ حالة الجدول في finally كي لا تُلوّث قاعدة الاختبار المشتركة (إسقاط DDL
 * يُثبَّت فلا يُلغى بتراجع معاملة RefreshDatabase).
 */
final class AccountResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_survives_when_the_address_book_table_is_missing(): void
    {
        $customer = Customer::factory()->create();

        $migration = require database_path('migrations/2026_07_22_000010_create_customer_addresses_table.php');
        Schema::dropIfExists('customer_addresses');

        try {
            $this->actingAs($customer, 'customer')
                ->get(route('customer.profile.edit'))
                ->assertOk();   // «بياناتي» تُصيَّر بلا 500، ودفتر العناوين يتدهور لقائمة فارغة
        } finally {
            $migration->up();
        }
    }

    public function test_order_details_survive_when_the_timeline_table_is_missing(): void
    {
        $customer = Customer::factory()->create();
        $order = OrderFactory::new()->create(['customer_id' => $customer->getKey()]);

        $migration = require database_path('migrations/2026_07_20_000102_create_order_status_histories_table.php');
        Schema::dropIfExists('order_status_histories');

        try {
            $this->actingAs($customer, 'customer')
                ->get(route('customer.orders.show', ['order' => $order->id]))
                ->assertOk();   // تفاصيل الطلب تُصيَّر بلا 500، والخط الزمني يتدهور
        } finally {
            $migration->up();
        }
    }
}
