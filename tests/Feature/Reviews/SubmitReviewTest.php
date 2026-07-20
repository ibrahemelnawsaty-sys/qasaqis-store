<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Http\Controllers\Storefront\ReviewController;
use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Observers\ReviewObserver;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * مسار إرسال المراجعات العام.
 *
 * الاختبارات ثلاث طبقات:
 *  1. حمولة الإدراج (ReviewRequest) — تعمل الآن، بلا HTTP ولا حارس.
 *  2. ReviewObserver على جدولَي reviews/books الحقيقيين — تعمل الآن.
 *  3. المسار عبر HTTP — **تُتخطّى** حتى يهبط ما يلزمها من عمل وكلاء آخرين:
 *     المسار books.reviews.store، وحارس customer، وموديل Customer، وعمود
 *     orders.customer_id. تُفعّل نفسها تلقائيًا فور اكتمال الربط (بند 1.5).
 */
final class SubmitReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        self::bootstrapWorktreeAutoloader();

        parent::setUp();

        // ReviewObserver غير مسجَّل في AppServiceProvider (ملف خارج ملكية وكيل
        // المراجعات). التسجيل هنا يختبر سلوكه فعليًا، لكنه **لا يغني** عن إضافة
        // Review::observe(ReviewObserver::class) في boot() — بدونها المراقب ميت
        // في الإنتاج والعمودان يبقيان راكدَين على 0.
        Review::observe(ReviewObserver::class);
    }

    // ----- 1) حمولة الإدراج: لا تصعيد امتياز من مدخلات العميل ----------------

    public function test_privileged_columns_are_stripped_from_the_validated_input(): void
    {
        $request = $this->resolveRequest($this->forgedInput());

        foreach (['status', 'is_verified_purchase', 'parent_id', 'user_id', 'has_media', 'replied_by', 'book_id'] as $forbidden) {
            $this->assertArrayNotHasKey(
                $forbidden,
                $request->validated(),
                "الحقل {$forbidden} عبر قائمة التحقق البيضاء — تصعيد امتياز محتمل."
            );
        }

        $allowed = array_keys($request->validated());
        sort($allowed);
        $this->assertSame(['body', 'rating', 'title'], $allowed);
    }

    public function test_forged_status_and_verified_purchase_are_ignored_in_the_payload(): void
    {
        $payload = $this->resolveRequest($this->forgedInput())->toAttributes(
            bookId: 42,
            authorName: 'أم يوسف',
            authorPhone: '1012345678',
            isVerifiedPurchase: false,
        );

        // القيم المزوّرة سقطت كلها؛ الخادم هو من قرّر.
        $this->assertSame('pending', $payload['status']);
        $this->assertFalse($payload['is_verified_purchase']);
        $this->assertNull($payload['parent_id']);
        $this->assertNull($payload['user_id']);
        $this->assertNull($payload['replied_by']);
        $this->assertFalse($payload['has_media']);

        // والقيم الخادمية وصلت كما مُرّرت.
        $this->assertSame(42, $payload['book_id']);
        $this->assertSame('أم يوسف', $payload['author_name']);
        $this->assertSame('1012345678', $payload['author_phone']);
        $this->assertSame(5, $payload['rating']);
    }

    public function test_a_submitted_review_is_persisted_pending_and_does_not_touch_book_aggregates(): void
    {
        $book = Book::factory()->create();

        $review = Review::create($this->resolveRequest($this->forgedInput())->toAttributes(
            bookId: (int) $book->id,
            authorName: 'أم يوسف',
            authorPhone: '1012345678',
            isVerifiedPurchase: false,
        ));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'pending',
            'is_verified_purchase' => 0,
            'parent_id' => null,
            'user_id' => null,
        ]);

        // لا تظهر في صفحة الكتاب: الاستعلام العام يقيّد status=published.
        $this->assertSame('كتاب جميل وطفلي أحبّه كثيرًا.', $review->body);
        $this->get(route('books.show', ['book' => $book->slug]))
            ->assertOk()
            ->assertDontSee($review->body);

        // ولا ترفع عدّاد الكتاب قبل الاعتماد.
        $book->refresh();
        $this->assertSame(0, (int) $book->reviews_count);
        $this->assertEquals(0.0, (float) $book->avg_rating);
    }

    public function test_blank_title_is_normalised_to_null(): void
    {
        $payload = $this->resolveRequest([
            'rating' => 4,
            'title' => '   ',
            'body' => 'نص رأي كافٍ للاختبار.',
        ])->toAttributes(bookId: 1, authorName: 'أم', authorPhone: null, isVerifiedPurchase: false);

        $this->assertNull($payload['title']);
    }

    // ----- 1ب) قواعد التحقق ------------------------------------------------

    public function test_rating_outside_one_to_five_is_rejected(): void
    {
        foreach ([0, 6, -1, 99] as $rating) {
            $errors = $this->validationErrorsFor(['rating' => $rating, 'body' => 'نص رأي كافٍ.']);
            $this->assertArrayHasKey('rating', $errors, "قُبل تقييم خارج المدى: {$rating}");
        }
    }

    public function test_rating_and_body_are_required(): void
    {
        $errors = $this->validationErrorsFor([]);

        $this->assertArrayHasKey('rating', $errors);
        $this->assertArrayHasKey('body', $errors);
    }

    public function test_whitespace_only_body_is_rejected(): void
    {
        // بلا التشذيب في prepareForValidation كانت المسافات تجتاز required.
        $this->assertArrayHasKey('body', $this->validationErrorsFor([
            'rating' => 5,
            'body' => "   \n\t  ",
        ]));
    }

    public function test_validation_messages_are_arabic_not_raw_keys(): void
    {
        // بند 6.4: رسالة إنجليزية أو مفتاح خام يعني ترجمة ناقصة.
        $errors = $this->validationErrorsFor(['rating' => 9, 'body' => 'ن']);

        foreach (['rating', 'body'] as $field) {
            $message = $errors[$field][0];
            $this->assertStringNotContainsString('validation.', $message);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $message);
        }

        // واسم الحقل نفسه جاء من lang/ar/review.php لا من مفتاح خام: لولا هذا
        // التأكيد لمرّت الرسالة «يجب ألا يزيد حقل review.field_rating عن 5».
        $this->assertStringContainsString(__('review.field_rating'), $errors['rating'][0]);
        $this->assertStringNotContainsString('review.field_', $errors['rating'][0]);
    }

    // ----- 2) ReviewObserver: العمودان المشتقّان ----------------------------

    public function test_published_reviews_drive_the_book_aggregates(): void
    {
        $book = Book::factory()->create();

        $this->makeReview($book, rating: 5);
        $this->makeReview($book, rating: 4);

        $book->refresh();
        $this->assertSame(2, (int) $book->reviews_count);
        $this->assertEquals(4.5, (float) $book->avg_rating);
    }

    public function test_pending_hidden_and_spam_reviews_are_excluded(): void
    {
        $book = Book::factory()->create();

        $this->makeReview($book, rating: 5);
        $this->makeReview($book, rating: 1, status: 'pending');
        $this->makeReview($book, rating: 1, status: 'hidden');
        $this->makeReview($book, rating: 1, status: 'spam');

        $book->refresh();
        $this->assertSame(1, (int) $book->reviews_count);
        $this->assertEquals(5.0, (float) $book->avg_rating);
    }

    public function test_staff_replies_and_unrated_rows_never_enter_the_average(): void
    {
        $book = Book::factory()->create();
        $parent = $this->makeReview($book, rating: 4);

        // رد طاقم منشور: rating=null و parent_id مضبوط (نمط ReviewResource).
        $this->makeReview($book, rating: null, parentId: (int) $parent->id);

        $book->refresh();
        $this->assertSame(1, (int) $book->reviews_count);
        $this->assertEquals(4.0, (float) $book->avg_rating);
    }

    public function test_hiding_a_published_review_recomputes_the_aggregates(): void
    {
        $book = Book::factory()->create();
        $review = $this->makeReview($book, rating: 2);
        $this->makeReview($book, rating: 4);

        $review->update(['status' => 'hidden']);

        $book->refresh();
        $this->assertSame(1, (int) $book->reviews_count);
        $this->assertEquals(4.0, (float) $book->avg_rating);
    }

    public function test_deleting_a_published_review_recomputes_the_aggregates(): void
    {
        $book = Book::factory()->create();
        $this->makeReview($book, rating: 5);
        $doomed = $this->makeReview($book, rating: 1);

        $doomed->delete();

        $book->refresh();
        $this->assertSame(1, (int) $book->reviews_count);
        $this->assertEquals(5.0, (float) $book->avg_rating);
    }

    public function test_last_published_review_resets_the_aggregates_to_zero(): void
    {
        $book = Book::factory()->create();
        $only = $this->makeReview($book, rating: 5);

        $only->delete();

        $book->refresh();
        $this->assertSame(0, (int) $book->reviews_count);
        $this->assertEquals(0.0, (float) $book->avg_rating);
    }

    public function test_moving_a_review_between_books_recomputes_both(): void
    {
        $from = Book::factory()->create();
        $to = Book::factory()->create();
        $review = $this->makeReview($from, rating: 5);

        $review->update(['book_id' => $to->id]);

        $from->refresh();
        $to->refresh();
        $this->assertSame(0, (int) $from->reviews_count);
        $this->assertEquals(0.0, (float) $from->avg_rating);
        $this->assertSame(1, (int) $to->reviews_count);
        $this->assertEquals(5.0, (float) $to->avg_rating);
    }

    public function test_average_is_rounded_to_the_single_decimal_the_column_holds(): void
    {
        // avg_rating عمود DECIMAL(2,1): متوسط 4.333… يجب ألا يُقصّ عشوائيًا.
        $book = Book::factory()->create();
        $this->makeReview($book, rating: 5);
        $this->makeReview($book, rating: 4);
        $this->makeReview($book, rating: 4);

        $book->refresh();
        $this->assertSame(3, (int) $book->reviews_count);
        $this->assertEquals(4.3, (float) $book->avg_rating);
    }

    // ----- 3) شارة «شراء موثّق»: تُمنح بالربط لا بالجوال ----------------------

    public function test_badge_is_granted_for_a_linked_fulfilled_order(): void
    {
        foreach (['delivered', 'completed'] as $status) {
            $book = Book::factory()->create();
            $customer = Customer::factory()->create();
            $this->makeOrderWithBook($book, $status, (int) $customer->id);

            $this->assertTrue(
                $this->verifiedPurchaseFor($customer, $book),
                "طلب مربوط بحالة {$status} لم يمنح الشارة."
            );
        }
    }

    public function test_an_unlinked_guest_order_never_grants_the_badge(): void
    {
        // جوهر القاعدة الأمنية: العميلة قد تكون اشترت فعلًا بنفس الرقم كضيف، لكن
        // ما دام الطلب غير مربوط بحسابها (customer_id = NULL) فلا شارة. مطابقة
        // الجوال مرفوضة كأساس لأن تسجيل الحساب لا يثبت ملكية الرقم.
        $book = Book::factory()->create();
        $customer = Customer::factory()->create();

        $this->makeOrderWithBook($book, 'delivered', null);

        $this->assertFalse(
            $this->verifiedPurchaseFor($customer, $book),
            'طلب ضيف غير مربوط منح شارة شراء موثّق.'
        );
    }

    public function test_another_customers_order_never_grants_the_badge(): void
    {
        $book = Book::factory()->create();
        $buyer = Customer::factory()->create();
        $impostor = Customer::factory()->create();

        $this->makeOrderWithBook($book, 'delivered', (int) $buyer->id);

        $this->assertFalse($this->verifiedPurchaseFor($impostor, $book));
    }

    public function test_unfulfilled_statuses_do_not_grant_the_badge(): void
    {
        // شراء لم يصل بعد ليس شراءً موثّقًا؛ والملغى/المرفوض أولى بالرفض.
        foreach (['pending', 'confirmed', 'processing', 'shipped', 'cancelled', 'refused', 'refunded'] as $status) {
            $book = Book::factory()->create();
            $customer = Customer::factory()->create();
            $this->makeOrderWithBook($book, $status, (int) $customer->id);

            $this->assertFalse(
                $this->verifiedPurchaseFor($customer, $book),
                "حالة {$status} منحت شارة شراء موثّق."
            );
        }
    }

    public function test_a_linked_order_for_a_different_book_does_not_grant_the_badge(): void
    {
        $bought = Book::factory()->create();
        $reviewed = Book::factory()->create();
        $customer = Customer::factory()->create();

        $this->makeOrderWithBook($bought, 'delivered', (int) $customer->id);

        $this->assertFalse($this->verifiedPurchaseFor($customer, $reviewed));
    }

    // ----- 4) المسار عبر HTTP (يعمل بعد ربط المنسّق) -------------------------

    public function test_guest_cannot_submit_a_review(): void
    {
        $this->skipUnlessAccountsAreWired();

        $book = Book::factory()->create();

        $this->post(route('books.reviews.store', ['book' => $book->slug]), [
            'rating' => 5,
            'body' => 'محاولة من ضيف.',
        ]);

        // المهم هو الأثر لا رمز الحالة: الوسيط قد يعيد توجيهًا أو 403، لكن يجب
        // ألّا يُكتب صف بأي حال.
        $this->assertSame(0, Review::query()->count(), 'ضيف استطاع كتابة مراجعة.');
    }

    public function test_submitted_review_over_http_is_stored_pending_and_unpublished(): void
    {
        $this->skipUnlessAccountsAreWired();

        $book = Book::factory()->create();
        $body = 'قصة هادئة ساعدت ابني على النوم.';

        $this->actingAs($this->makeCustomer(), 'customer')
            ->post(route('books.reviews.store', ['book' => $book->slug]), [
                'rating' => 5,
                'title' => 'رائع',
                'body' => $body,
            ])
            ->assertRedirect();

        $review = Review::query()->where('book_id', $book->id)->sole();
        $this->assertSame('pending', $review->status);

        $this->get(route('books.show', ['book' => $book->slug]))
            ->assertOk()
            ->assertDontSee($body);
    }

    public function test_forged_privileged_fields_are_ignored_over_http(): void
    {
        $this->skipUnlessAccountsAreWired();

        $book = Book::factory()->create();

        $this->actingAs($this->makeCustomer(), 'customer')
            ->post(route('books.reviews.store', ['book' => $book->slug]), [
                ...$this->forgedInput(),
                'author_name' => 'الأدمن',
            ])
            ->assertRedirect();

        $review = Review::query()->where('book_id', $book->id)->sole();
        $this->assertSame('pending', $review->status);
        $this->assertFalse((bool) $review->is_verified_purchase);
        $this->assertNull($review->parent_id);
        $this->assertNull($review->user_id);
        $this->assertNotSame('الأدمن', $review->author_name);
    }

    public function test_duplicate_review_on_the_same_book_is_rejected(): void
    {
        $this->skipUnlessAccountsAreWired();

        $book = Book::factory()->create();
        $customer = $this->makeCustomer();

        foreach (['الرأي الأول من الأم.', 'الرأي الثاني من نفس الأم.'] as $body) {
            $this->actingAs($customer, 'customer')
                ->post(route('books.reviews.store', ['book' => $book->slug]), ['rating' => 5, 'body' => $body]);
        }

        $this->assertSame(1, Review::query()->where('book_id', $book->id)->count());
    }

    public function test_submission_route_is_rate_limited(): void
    {
        $this->skipUnlessAccountsAreWired();

        $book = Book::factory()->create();
        $customer = $this->makeCustomer();
        $statuses = [];

        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->actingAs($customer, 'customer')
                ->post(route('books.reviews.store', ['book' => $book->slug]), [
                    'rating' => 5,
                    'body' => 'نص الرأي رقم '.$i.' للاختبار.',
                ])->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'مسار إرسال المراجعة بلا throttle (بند 4.6).');
    }

    // ----- أدوات مساعدة ------------------------------------------------------

    /**
     * حلّان لعيبين **بيئيين** في الـ worktree، لا لعيبين في كود المراجعات.
     * كلاهما يصيب كل وكيل يعمل هنا، لا هذه المهمة وحدها — انظر تقرير التسليم.
     *
     * العيب (أ) — مسار التطبيق الأساسي:
     * Application::inferBasePath() يشتقّ الجذر من مجلّد vendor المسجَّل في مُحمِّل
     * Composer. وvendor هنا وصلة رمزية إلى vendor الشجرة الرئيسية، فيعيد
     * «qasaqis-store» بدل الـ worktree. النتيجة أن TestCase::createApplication
     * يُقلع تطبيق **الشجرة الرئيسية**: إعداداتها ومساراتها وقوالبها وترجماتها
     * وهجراتها. لذلك بقي جدول customers غائبًا عن قاعدة الاختبار رغم وجود
     * هجرته هنا، وكان أي اختبار «ناجح» لا يثبت شيئًا عن كود الـ worktree.
     *
     * العيب (ب) — خريطة PSR-4:
     * نفس السبب يجعل «App\» و«Database\» تشيران إلى الشجرة الرئيسية، فلا يرى
     * المحمّل أي صنف جديد يُكتب هنا (ReviewObserver, Customer, CustomerFactory).
     *
     * كلا الإصلاحين يُلغي نفسه في بيئة سليمة. الإصلاح الجذري عند المنسّق:
     * `composer dump-autoload` داخل الـ worktree، أو
     * <env name="APP_BASE_PATH" value="…"/> في phpunit.xml.
     */
    private static function bootstrapWorktreeAutoloader(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        $root = dirname(__DIR__, 3);

        // (أ) يجب أن يسبق أول createApplication: القيمة تُخزَّن في ساكن ولا تتغيّر.
        if (! isset($_ENV['APP_BASE_PATH'])) {
            $_ENV['APP_BASE_PATH'] = $root;
            putenv('APP_BASE_PATH='.$root);
        }

        // (ب) البيئة سليمة: المحمّل القياسي يجد أصناف الـ worktree — لا شيم.
        if (class_exists(ReviewObserver::class)) {
            return;
        }

        // كلا فضاءَي الأسماء مصابان بالعيب نفسه: App\ (الكود) و Database\
        // (المصانع والهجرات) يشيران إلى الشجرة الرئيسية.
        $prefixes = ['App\\' => $root.'/app', 'Database\\' => $root.'/database'];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $dir) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }

                $path = $dir.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (is_file($path)) {
                    require_once $path;
                }

                return;
            }
        }, true, true);
    }

    /**
     * مدخلات تحمل محاولة تزوير صريحة للحقول الإدارية.
     *
     * @return array<string, mixed>
     */
    private function forgedInput(): array
    {
        return [
            'rating' => 5,
            'title' => 'عنوان الرأي',
            'body' => 'كتاب جميل وطفلي أحبّه كثيرًا.',
            // ما يلي كله في Review::$fillable — ويجب أن يسقط قبل الوصول للموديل.
            'status' => 'published',
            'is_verified_purchase' => 1,
            'parent_id' => 99,
            'user_id' => 7,
            'has_media' => 1,
            'replied_by' => 3,
            'book_id' => 123456,
        ];
    }

    /**
     * يبني ReviewRequest ويشغّل دورة التحقق كاملةً (prepareForValidation ثم
     * القواعد) بلا HTTP — نفس ما يفعله الحاوي عند حقن الـ FormRequest.
     *
     * @param  array<string, mixed>  $input
     */
    private function resolveRequest(array $input): ReviewRequest
    {
        $request = ReviewRequest::create('/books/any/reviews', 'POST', $input);
        $request->setContainer($this->app);
        // يلزم عند الفشل: failedValidation يبني رابط العودة من الـ Redirector.
        $request->setRedirector($this->app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, string>>
     */
    private function validationErrorsFor(array $input): array
    {
        try {
            $this->resolveRequest($input);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    private function makeReview(Book $book, ?int $rating, string $status = 'published', ?int $parentId = null): Review
    {
        return Review::create([
            'book_id' => $book->id,
            'parent_id' => $parentId,
            'author_name' => 'أم تجريبية',
            'author_phone' => '1012345678',
            'rating' => $rating,
            'body' => 'نص تجريبي للمراجعة.',
            'status' => $status,
        ]);
    }

    /**
     * يُستعمل CustomerFactory لا Customer::create: تحققتُ (بند 10.3) أن
     * phone_normalized **خارج** Customer::$fillable عمدًا، فإنشاءٌ بالتعبئة
     * الجماعية كان سيُسقطه صامتًا ويخالف قيد NOT NULL.
     */
    private function makeCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    /**
     * طلب يحوي الكتاب، بحالة محددة، مربوط بحساب أو لا.
     *
     * customer_id يُضبط بالاستعلام لا بالتعبئة: تحققتُ أنه ليس في
     * Order::$fillable — وهو قرار سليم (يُكتب خادميًا فقط)، فلا نلتف عليه بتوسيعه.
     */
    private function makeOrderWithBook(Book $book, string $status, ?int $customerId): Order
    {
        $order = OrderFactory::new()->create(['status' => $status]);

        Order::query()->whereKey($order->id)->update(['customer_id' => $customerId]);

        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'book_title' => (string) $book->title,
            'unit_price' => '200.00',
            'quantity' => 1,
            'line_total' => '200.00',
        ]);

        return $order;
    }

    /**
     * يستدعي منطق الشارة مباشرةً عبر الانعكاس.
     *
     * المنطق خاص (private) داخل الـ Controller لأن ملكية ملفات هذه المهمة لا
     * تشمل app/Actions — والبديل (تركه بلا اختبار حتى يربط المنسّق المسارات)
     * يترك أخطر قطعة في الميزة بلا تغطية. التوصية للمنسّق: استخراجه إلى
     * App\Actions\Review\DetermineVerifiedPurchaseAction واختباره مباشرةً.
     */
    private function verifiedPurchaseFor(Customer $customer, Book $book): bool
    {
        $method = new ReflectionMethod(ReviewController::class, 'hasFulfilledPurchase');

        return (bool) $method->invoke(new ReviewController, (int) $customer->id, (int) $book->id);
    }

    private function skipUnlessAccountsAreWired(): void
    {
        if (! Route::has('books.reviews.store')) {
            $this->markTestSkipped('مسار books.reviews.store غير مسجَّل بعد في routes/web.php (المنسّق).');
        }

        if (! filled(config('auth.guards.customer'))) {
            $this->markTestSkipped('حارس customer غير معرَّف في config/auth.php (المنسّق).');
        }

        if (! class_exists(Customer::class)) {
            $this->markTestSkipped('App\Models\Customer غير موجود بعد (وكيل الحسابات).');
        }

        if (! Schema::hasColumn('orders', 'customer_id')) {
            $this->markTestSkipped('عمود orders.customer_id غير موجود بعد — شارة الشراء الموثّق تعتمد عليه.');
        }
    }
}
