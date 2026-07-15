<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\BookController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\SearchController;
use Illuminate\Support\Facades\Route;

/*
| مسارات المتجر العامة (Storefront) — «قصص أطفال»
| kebab-case + أسماء واضحة، وربط النماذج عبر الـ slug.
*/

Route::get('/', HomeController::class)->name('home');

// SEO تقني: خريطة موقع ديناميكية (مخزّنة مؤقتًا ساعة) + روبوتس احتياطي.
// في الإنتاج يخدم public/robots.txt الساكن أولًا؛ يبقى المسار عاملًا حين يغيب.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// التصفح الكامل + الفلاتر (?cat[]=&pub[]=&age[]=&min=&max=&sale=1&sort=)
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// العروض = تصفّح الكتب مع فلتر الخصم مفعّلًا
Route::redirect('/offers', '/books?sale=1')->name('books.offers');

// البحث (نتائج قابلة للمشاركة). throttle لمنع الإساءة.
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('search');

// الاقتراح الفوري (autocomplete) — JSON خفيف، throttle أشد لكثرة النداءات.
Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('search.suggest');

// فهرس البحث الكامل (كل الكتب) — يُحمَّل مرة واحدة ليُفلتره المتصفح لحظيًا.
Route::get('/search/index.json', [SearchController::class, 'indexJson'])
    ->middleware('throttle:120,1')
    ->name('search.index');

// صفحة الكتاب
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// صفحة القسم (تبقى كل الأقسام الستة موجودة حتى الفارغة)
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

// المدونة (المقالات المنشورة). قائمة + صفحة مقال مربوطة بالـ slug؛ المسودّات تُعطي 404.
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{article:slug}', [BlogController::class, 'show'])->name('blog.show');

// صفحات CMS الديناميكية (من نحن، سياسة الشحن…). المنشورة فقط؛ المسودّات تُعطي 404.
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

/*
| السلة والدفع (M5 — منطق تدفّق الطلب)
| الأسعار تُعاد من قاعدة البيانات دائمًا؛ لا تُقرأ من العميل.
*/

// السلة (عرض/تحديث). التخزين في الجلسة كخريطة {book_id: qty}.
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'update'])->name('cart.update');

// نموذج الدفع + إنشاء الطلب. throttle على الإنشاء لمنع الإساءة.
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'place'])
    ->middleware('throttle:20,1')
    ->name('checkout.place');

// تطبيق الكوبون (AJAX) — يُرجع JSON. throttle لكثرة النداءات.
Route::post('/coupon/apply', [CouponController::class, 'apply'])
    ->middleware('throttle:30,1')
    ->name('coupon.apply');

// صفحات الطلب للضيف — محميّة بروابط موقّعة (signed) لمنع تعداد الطلبات.
Route::get('/orders/{order}/payment', [OrderController::class, 'payment'])
    ->middleware('signed')
    ->name('orders.payment');

// رفع إثبات الدفع — موقّع + throttle (بند 4.6).
Route::post('/orders/{order}/proof', [OrderController::class, 'proofStore'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('orders.proof.store');

Route::get('/orders/{order}/thank-you', [OrderController::class, 'thankyou'])
    ->middleware('signed')
    ->name('orders.thankyou');
