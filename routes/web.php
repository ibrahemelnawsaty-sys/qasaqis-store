<?php

declare(strict_types=1);

use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\EmailVerificationController as CustomerEmailVerificationController;
use App\Http\Controllers\Customer\LoginController as CustomerLoginController;
use App\Http\Controllers\Customer\LogoutController as CustomerLogoutController;
use App\Http\Controllers\Customer\OrderHistoryController as CustomerOrderHistoryController;
use App\Http\Controllers\Customer\OrderLinkController as CustomerOrderLinkController;
use App\Http\Controllers\Customer\PostPurchaseAccountController;
use App\Http\Controllers\Customer\PasswordResetController as CustomerPasswordResetController;
use App\Http\Controllers\Customer\AddressController as CustomerAddressController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Auth\UnifiedLoginController;
use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\TaskRunnerController;
use App\Http\Controllers\Customer\RegisterController as CustomerRegisterController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\BookController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\InquiryController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PaymentCallbackController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ReviewController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SeriesController;
use Illuminate\Support\Facades\Route;

/*
| مسارات المتجر العامة (Storefront) — «قصص أطفال»
| kebab-case + أسماء واضحة، وربط النماذج عبر الـ slug.
*/

Route::get('/', HomeController::class)->name('home');

// تسجيل دخول موحّد لكل المنصة (عميل بالجوال / أدمن بالبريد). نقطة دخول واحدة يوجّهها
// UnifiedLoginController للحارس الصحيح. الاسم `login` كي يصلح كوجهة إعادة توجيه Laravel
// الافتراضية للزوار. throttle على الإرسال (حدّ معدّل باعتدال؛ الرسالة موحّدة).
Route::get('/login', [UnifiedLoginController::class, 'show'])->name('login');
Route::post('/login', [UnifiedLoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');

// إلغاء الاشتراك من الحملات (التوكن السرّي هو المفتاح — لا جلسة). عامّ خارج أي حارس.
// مسار store مُعفى من CSRF في bootstrap/app.php ليخدم نقرة One-Click من Gmail/Yahoo.
Route::get('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'show'])
    ->name('email.unsubscribe.show');
Route::post('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'store'])
    ->name('email.unsubscribe.store');

// مشغّل المهام المجدولة عبر HTTP (بديل cron حين تمنعه الاستضافة). تناديه خدمة نبض
// خارجية كل دقيقة. التوكن السرّي في المسار هو الحارس. بلا جلسة كي لا تتراكم جلسات
// يتيمة من النداء المتكرّر، وبحدّ معدّل يمنع الإغراق.
Route::get('/tasks/run/{token}', TaskRunnerController::class)
    ->middleware('throttle:30,1')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // CSRF يعتمد على الجلسة لكتابة كوكي XSRF؛ يُسقَط هنا (GET لا يحتاجه أصلًا).
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->name('tasks.run');

// SEO تقني: خريطة موقع ديناميكية (مخزّنة مؤقتًا ساعة) + روبوتس احتياطي.
// في الإنتاج يخدم public/robots.txt الساكن أولًا؛ يبقى المسار عاملًا حين يغيب.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// التصفح الكامل + الفلاتر (?cat[]=&pub[]=&age[]=&min=&max=&sale=1&sort=)
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// العروض = تصفّح الكتب مع فلتر الخصم مفعّلًا. مسار 200 حقيقي لا تحويلًا:
// كان Route::redirect إلى /books?sale=1، ووجهته تُصدر canonical=/books لأن
// url()->current() يحذف الـ query string — فكانت الصفحة تُلغي نفسها من الفهرس
// رغم أن عنوانها و<h1> مختلفان. الفلتر يُفرض في BookController::index.
Route::get('/offers', [BookController::class, 'index'])->name('books.offers');

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

// نموذج الاستفسارات العام (استفسار/طلب جملة/سؤال/شكوى) — يُغذّي مورد الاستفسارات
// في لوحة الأدمن. throttle لمنع السبام.
Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('inquiry.store');

// صفحة الكتاب
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// صفحة القسم (تبقى كل الأقسام الستة موجودة حتى الفارغة)
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

// صفحة السلسلة: تعرض كل عناوين السلسلة بالترتيب (السلاسل غير المفعّلة تُعطي 404).
Route::get('/series/{series:slug}', [SeriesController::class, 'show'])->name('series.show');

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

// تتبّع/استرجاع طلب الضيف: مسار ثابت (لا يتعارض مع /orders/{order}/...).
// POST مقيّد بـ throttle لمنع تخمين رقم الطلب + الجوال (بند 4.6). المفتاح على IP
// (REMOTE_ADDR) — سليم على Hostinger المباشر؛ إن وُضع CDN/بروكسي لاحقًا فاضبط
// trustProxies في bootstrap/app.php كي لا ينهار الحد إلى IP البروكسي.
Route::get('/orders/track', [OrderController::class, 'trackForm'])->name('orders.track.show');
Route::post('/orders/track', [OrderController::class, 'track'])
    ->middleware('throttle:6,1')
    ->name('orders.track.lookup');

// صفحات الطلب للضيف — محميّة بروابط موقّعة (signed) لمنع تعداد الطلبات.
Route::get('/orders/{order}/payment', [OrderController::class, 'payment'])
    ->middleware('signed')
    ->name('orders.payment');

// رفع إثبات الدفع — موقّع + throttle (بند 4.6).
Route::post('/orders/{order}/proof', [OrderController::class, 'proofStore'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('orders.proof.store');

// صفحة الدفع الأونلاين المدمجة (كاشير iframe) داخل تصميم المتجر — موقّعة.
Route::get('/orders/{order}/pay', [OrderController::class, 'pay'])
    ->middleware('signed')
    ->name('orders.pay');

Route::get('/orders/{order}/thank-you', [OrderController::class, 'thankyou'])
    ->middleware('signed')
    ->name('orders.thankyou');

// ردّ بوابة كاشير — عودة المتصفّح: لا signed middleware (المصدر طرف ثالث)، الأمان
// عبر التحقّق من توقيع كاشير (HMAC بمفتاح الدفع) داخل المتحكّم.
Route::get('/payments/kashier/callback', [PaymentCallbackController::class, 'kashierCallback'])
    ->name('payments.kashier.callback');

// webhook كاشير (خادم-لخادم): POST بلا CSRF (مُعفى في bootstrap/app.php)، التحقّق بالتوقيع.
Route::post('/payments/kashier/webhook', [PaymentCallbackController::class, 'kashierWebhook'])
    ->name('payments.kashier.webhook');

// تبنّي الطلب من صفحة الشكر بعد التسجيل/الدخول (M8) — موقّع + مطابقة الجوال داخليًا.
Route::post('/orders/{order}/claim', [CustomerOrderLinkController::class, 'claim'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('orders.claim');

// إنشاء حساب من صفحة الشكر بعد الشراء (M10) — موقّع، بخطوة واحدة (كلمة مرور فقط).
Route::post('/orders/{order}/create-account', [PostPurchaseAccountController::class, 'store'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('orders.create-account');

/*
| حساب العميلة (M8) — حارس customer منفصل تمامًا عن لوحة الأدمن. الشراء يبقى كضيف
| بلا تسجيل إجباري؛ الحساب اختياري ولا يُطلب قبل الدفع.
*/

// إرسال مراجعة كتاب — عام، محدود المعدّل. تُحفظ بانتظار الاعتماد (لا تُنشر مباشرة).
Route::post('/books/{book:slug}/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('books.reviews.store');

Route::prefix('account')->name('customer.')->group(function (): void {
    // صفحات الزوّار — كل متحكم يوجّه العميلة المسجّلة دخولًا إلى لوحتها بنفسه
    // (لا نستعمل وسيط guest:customer لأن وجهة توجيهه الافتراضية «/» لا «/account»).
    Route::get('/register', [CustomerRegisterController::class, 'show'])->name('register.show');
    Route::post('/register', [CustomerRegisterController::class, 'store'])
        ->middleware('throttle:10,1')->name('register.store');

    Route::get('/login', [CustomerLoginController::class, 'show'])->name('login.show');
    Route::post('/login', [CustomerLoginController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');

    // استعادة كلمة المرور — throttle على الإرسال لمنع الإساءة (بند 4.6).
    Route::get('/forgot-password', [CustomerPasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [CustomerPasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [CustomerPasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [CustomerPasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');

    // مسجّلات الدخول فقط.
    Route::middleware('auth:customer')->group(function (): void {
        Route::post('/logout', CustomerLogoutController::class)->name('logout');

        Route::get('/', [CustomerDashboardController::class, 'index'])->name('dashboard');

        Route::get('/orders', [CustomerOrderHistoryController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [CustomerOrderHistoryController::class, 'show'])->name('orders.show');
        // ربط/فكّ طلب سابق — رقم الطلب + الجوال (لا مطابقة جوال وحده).
        Route::post('/orders/attach', [CustomerOrderLinkController::class, 'attach'])
            ->middleware('throttle:10,1')->name('orders.attach');
        Route::delete('/orders/{order}', [CustomerOrderLinkController::class, 'detach'])->name('orders.detach');

        Route::get('/profile', [CustomerProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');

        // دفتر العناوين المُسمّى (M12): تعيين افتراضيّ/حذف. الإضافة تتمّ من الدفع.
        // التفويض خادميّ في المتحكّم (العنوان يجب أن يخصّ العميلة نفسها).
        Route::post('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])->name('addresses.default');
        Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');

        // تأكيد البريد بكود (M9). القناة بريد اليوم، وتتبدّل إلى OTP جوال لاحقًا.
        Route::get('/verify-email', [CustomerEmailVerificationController::class, 'show'])->name('verify.show');
        Route::post('/verify-email', [CustomerEmailVerificationController::class, 'verify'])
            ->middleware('throttle:10,1')->name('verify.store');
        Route::post('/verify-email/resend', [CustomerEmailVerificationController::class, 'resend'])
            ->middleware('throttle:4,10')->name('verify.resend');
    });
});
