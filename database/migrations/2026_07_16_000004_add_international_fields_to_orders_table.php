<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول الشحن الدولي على الطلب (M5). country_code افتراضي EG (الطلبات القائمة كلها
 * مصرية)، state_province للعنوان الدولي (بدل المحافظة)، shipping_zone_code لقطة
 * منطقة الشحن. governorate يصبح nullable (الطلب الدولي بلا محافظة).
 *
 * قيد العكس (down): إعادة governorate إلى NOT NULL تفشل إن وُجدت طلبات دولية
 * بمحافظة NULL — يتطلب معالجة يدوية. لا truncate. نسخة احتياطية قبل التشغيل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->char('country_code', 2)->default('EG')->after('governorate');
            $table->string('state_province', 100)->nullable()->after('city');
            $table->string('shipping_zone_code', 50)->nullable()->after('shipping_total');
            $table->index('country_code');
        });

        // منفصلة: تغيير عمود قائم (Laravel 11 يدعم change أصليًا).
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('governorate', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('governorate', 50)->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['country_code']);
            $table->dropColumn(['country_code', 'state_province', 'shipping_zone_code']);
        });
    }
};
