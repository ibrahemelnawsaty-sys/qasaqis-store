<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * الأسطح التي يستطيع الأدمن إسناد نقش خلفية لها.
 *
 * نوعان:
 *   - Page:    صفحة كاملة (النقش على <body>). المفتاح مشتق من اسم القالب.
 *   - Section: قسم داخل الصفحة الرئيسية (النقش على شريط يلفّ القسم).
 *
 * القيم تُخزَّن في جدول settings بمفتاح "pattern.{value}"، لذا تغييرها
 * يفقد اختيارات الأدمن المحفوظة — عاملها كعقد ثابت.
 *
 * الافتراضات هنا هي نفس الإسناد الأصلي قبل نقله للـCMS، فالموقع يبدو كما
 * هو تمامًا حتى يغيّر الأدمن شيئًا (لا قفزة بصرية عند النشر).
 */
enum PatternSurface: string
{
    // ===== صفحات كاملة =====
    case PageHome = 'page.home';
    case PageCatalog = 'page.catalog';
    case PageBook = 'page.book';
    case PageBlog = 'page.blog';
    case PageStatic = 'page.static';
    case PageCart = 'page.cart';
    case PageCheckout = 'page.checkout';
    case PageOrderPayment = 'page.order_payment';
    case PageOrderTrack = 'page.order_track';
    case PageThankYou = 'page.thankyou';

    // ===== أقسام الصفحة الرئيسية =====
    case SectionHero = 'section.hero';
    case SectionTrust = 'section.trust';
    case SectionCategories = 'section.categories';
    case SectionFeatured = 'section.featured';
    case SectionBestsellers = 'section.bestsellers';
    case SectionPromo = 'section.promo';
    case SectionLatest = 'section.latest';
    case SectionWhy = 'section.why';
    case SectionFeedback = 'section.feedback';
    case SectionBulk = 'section.bulk';
    case SectionReviews = 'section.reviews';
    case SectionBlogLatest = 'section.blog_latest';

    public function label(): string
    {
        return match ($this) {
            self::PageHome => 'الصفحة الرئيسية',
            self::PageCatalog => 'كل الكتب والأقسام والبحث',
            self::PageBook => 'صفحة الكتاب',
            self::PageBlog => 'المدوّنة',
            self::PageStatic => 'الصفحات الثابتة (افتراضي)',
            self::PageCart => 'السلة',
            self::PageCheckout => 'الدفع',
            self::PageOrderPayment => 'دفع الطلب',
            self::PageOrderTrack => 'تتبّع الطلب',
            self::PageThankYou => 'تأكيد الطلب',

            self::SectionHero => 'الهيرو (الترويسة)',
            self::SectionTrust => 'شريط الثقة',
            self::SectionCategories => 'اختاري القسم المناسب',
            self::SectionFeatured => 'قصص اختارتها الأمهات',
            self::SectionBestsellers => 'الأكثر مبيعًا',
            self::SectionPromo => 'شريط العرض',
            self::SectionLatest => 'أحدث ما أضفناه',
            self::SectionWhy => 'ليه الأمهات بيحبونا',
            self::SectionFeedback => 'عملاء سعداء',
            self::SectionBulk => 'طلبات بالجملة',
            self::SectionReviews => 'تجارب حقيقية',
            self::SectionBlogLatest => 'أحدث المقالات',
        };
    }

    /**
     * النقش الافتراضي — يطابق الإسناد الذي كان مثبّتًا في القوالب.
     */
    public function default(): BackgroundPattern
    {
        return match ($this) {
            self::PageHome => BackgroundPattern::StorybookLattice,
            self::PageCatalog => BackgroundPattern::BookFans,
            self::PageBook => BackgroundPattern::ScissorsTrails,
            self::PageBlog, self::PageStatic => BackgroundPattern::CalligraphicCurls,
            self::PageCart, self::PageCheckout,
            self::PageOrderPayment, self::PageOrderTrack => BackgroundPattern::DotsAndArcs,
            self::PageThankYou => BackgroundPattern::ScrapsConfetti,

            // الأقسام بلا نقش افتراضًا: نقش الصفحة يمرّ خلفها، وإضافة شرائط
            // متتالية بلا داعٍ تُتعب العين وتخالف انحياز الخفّة (1.6).
            default => BackgroundPattern::None,
        };
    }

    public function isSection(): bool
    {
        return str_starts_with($this->value, 'section.');
    }

    /**
     * مفتاح الصف في جدول settings.
     */
    public function settingKey(): string
    {
        return 'pattern.'.$this->value;
    }

    /**
     * @return array<int, self>
     */
    public static function pages(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => ! $s->isSection()));
    }

    /**
     * @return array<int, self>
     */
    public static function sections(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => $s->isSection()));
    }
}
