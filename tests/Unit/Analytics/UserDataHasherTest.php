<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Support\Analytics\UserDataHasher;
use PHPUnit\Framework\TestCase;

/**
 * تطبيع وتجزئة بيانات المستخدم لـ Meta CAPI (M6): بريد lowercase/trim، هاتف أرقام
 * فقط، وnull للفارغ.
 */
final class UserDataHasherTest extends TestCase
{
    public function test_email_is_normalized_then_hashed(): void
    {
        $this->assertSame(hash('sha256', 'mom@example.com'), UserDataHasher::hashEmail('  MOM@Example.com '));
    }

    public function test_phone_is_reduced_to_digits_then_hashed(): void
    {
        $this->assertSame(hash('sha256', '201012345678'), UserDataHasher::hashPhone('+20 10 1234 5678'));
    }

    public function test_null_and_empty_inputs_return_null(): void
    {
        $this->assertNull(UserDataHasher::hashEmail(null));
        $this->assertNull(UserDataHasher::hashEmail(''));
        $this->assertNull(UserDataHasher::hashPhone(null));
        $this->assertNull(UserDataHasher::hashPhone('no-digits'));
    }
}
