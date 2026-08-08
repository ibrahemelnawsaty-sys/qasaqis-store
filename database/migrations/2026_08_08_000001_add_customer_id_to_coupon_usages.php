<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// تتبّع استخدام الكوبون بالعميل (customer_id) لا بمستخدم اللوحة (user_id → users) الذي
// كان دائمًا null للمتسوّقين — فحدّ «مرّة لكل عميل» لم يكن مُفعَّلًا. نضيف customer_id
// كي يُحسَب الحدّ بالعميل الحقيقيّ ويُصان استهداف «عميل محدّد». user_id يبقى (توافق).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table): void {
            // constrained() يُنشئ فهرسًا على customer_id تلقائيًّا (لا حاجة لفهرس صريح).
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
