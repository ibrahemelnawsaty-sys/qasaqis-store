<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إدارة دفتر العناوين من الملف (M12): تعيين افتراضيّ، حذف (مع ترقية بديل)، وحصر
 * التفويض على عناوين العميلة نفسها (404 لعنوان غيرها).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private function address(Customer $customer, array $overrides = [])
    {
        return $customer->addresses()->create(array_merge([
            'label' => 'المنزل', 'name' => $customer->name, 'phone' => '01012345678',
            'country_code' => 'EG', 'governorate' => 'القاهرة', 'city' => 'مدينة نصر',
            'address_line' => 'شارع 1', 'is_default' => false,
        ], $overrides));
    }

    public function test_the_profile_lists_saved_addresses(): void
    {
        $customer = Customer::factory()->create();
        $this->address($customer, ['label' => 'العمل', 'is_default' => true]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('عناويني المحفوظة', false)
            ->assertSee('العمل', false);
    }

    public function test_a_customer_can_set_an_address_as_default(): void
    {
        $customer = Customer::factory()->create();
        $home = $this->address($customer, ['label' => 'المنزل', 'is_default' => true]);
        $work = $this->address($customer, ['label' => 'العمل', 'is_default' => false]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.addresses.default', ['address' => $work->id]))
            ->assertRedirect();

        $this->assertTrue($work->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);   // عنوان افتراضيّ واحد فقط
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $customer = Customer::factory()->create();
        $default = $this->address($customer, ['is_default' => true]);
        $other = $this->address($customer, ['label' => 'آخر', 'is_default' => false]);

        $this->actingAs($customer, 'customer')
            ->delete(route('customer.addresses.destroy', ['address' => $default->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $default->id]);
        $this->assertTrue($other->fresh()->is_default);   // رُقّي البديل تلقائيًّا
    }

    public function test_a_customer_cannot_manage_another_customers_address(): void
    {
        $me = Customer::factory()->create();
        $her = Customer::factory()->create();
        $herAddress = $this->address($her, ['is_default' => true]);

        $this->actingAs($me, 'customer')
            ->post(route('customer.addresses.default', ['address' => $herAddress->id]))
            ->assertNotFound();

        $this->actingAs($me, 'customer')
            ->delete(route('customer.addresses.destroy', ['address' => $herAddress->id]))
            ->assertNotFound();

        // لم يُمَسّ عنوانها.
        $this->assertDatabaseHas('customer_addresses', ['id' => $herAddress->id, 'is_default' => true]);
    }
}
