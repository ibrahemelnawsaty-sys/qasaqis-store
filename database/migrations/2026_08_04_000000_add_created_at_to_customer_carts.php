<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// «السلة المتروكة» تُحسَب من لحظة إنشاء السلة (أوّل كتاب يُضاف) لا من آخر مزامنة — كي لا
// يتصفّر العدّاد بمجرّد تصفّح العميل (كل تحميل صفحة يُعيد مزامنة السلة فيُحدّث updated_at).
// نضيف created_at يُضبَط مرّةً عند أوّل إدراج ولا يتغيّر (upsert لا يُحدّثه)، ونملأ الصفوف
// القائمة من updated_at كأفضل تقدير لوقت إنشائها.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->timestamp('created_at')->nullable()->after('items');
        });

        DB::table('customer_carts')->whereNull('created_at')->update([
            'created_at' => DB::raw('updated_at'),
        ]);

        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropColumn('created_at');
        });
    }
};
