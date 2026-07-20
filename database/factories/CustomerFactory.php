<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Support\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * مصنع حسابات العملاء. الأعمدة متحقَّق منها مقابل هجرة 2026_07_20_000001.
 *
 * الجوال: اللاحقة (8 أرقام) فريدة، فالرقم الكامل فريد مهما تكرّرت بادئة المشغّل —
 * وهو ما يمنع اصطدام القيد UNIQUE على phone_normalized عند إنشاء صفوف كثيرة.
 * البادئات الأربع هي مشغّلو مصر المقبولون في CheckoutRequest::EGYPT_PHONE_REGEX.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** كلمة المرور الافتراضية للاختبارات (تُجزَّأ مرة واحدة لتوفير الوقت). */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phoneNormalized = fake()->randomElement(['10', '11', '12', '15'])
            .fake()->unique()->numerify('########');

        return [
            'name' => fake()->name(),
            'phone_normalized' => $phoneNormalized,
            'phone_e164' => '+20'.$phoneNormalized,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'phone_verified_at' => null,
            'email_verified_at' => null,
            'last_governorate' => null,
            'last_city' => null,
            'last_address_line' => null,
            'last_country_code' => null,
            'orders_count' => 0,
            'total_spent' => '0.00',
            'is_claimed' => false,
        ];
    }

    /**
     * يضبط الجوال من رقم خام بأي بادئة (‎+20 / 0020 / 0 / بلا) عبر المطبِّع نفسه
     * الذي يستعمله مسار التسجيل، فيبقى العمودان متسقين.
     */
    public function withPhone(string $rawPhone): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone_normalized' => PhoneNormalizer::normalize($rawPhone),
            'phone_e164' => PhoneNormalizer::toE164($rawPhone),
        ]);
    }

    /** حساب بلا كلمة مرور — السكيمة تسمح به، ومسار الدخول يجب أن يرفضه. */
    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => null,
        ]);
    }
}
