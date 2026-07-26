<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Text\Slug;
use PHPUnit\Framework\TestCase;

/**
 * مولّد الـslug العربي→اللاتيني. منطق خالص بلا قاعدة بيانات ولا حاوية.
 */
class SlugTest extends TestCase
{
    public function test_transliterates_arabic_titles_readably(): void
    {
        $this->assertSame('qsa-alasd-walfar', Slug::fromTitle('قصة الأسد والفأر'));
        $this->assertSame('qss-ma-qbl-alnwm', Slug::fromTitle('قصص ما قبل النوم'));
    }

    public function test_uses_q_for_qaf_and_w_for_waw_not_the_rough_defaults(): void
    {
        $slug = Slug::fromTitle('قصة والقمر');

        $this->assertStringStartsWith('q', $slug);       // ق → q لا k
        $this->assertStringContainsString('w', $slug);   // و → w لا o
    }

    public function test_converts_arabic_indic_digits(): void
    {
        $this->assertSame('123-hkaya-llatfal', Slug::fromTitle('١٢٣ حكاية للأطفال'));
    }

    public function test_strips_diacritics_before_transliterating(): void
    {
        // نفس الناتج مع/بدون تشكيل.
        $this->assertSame(Slug::fromTitle('قصة'), Slug::fromTitle('قِصَّةٌ'));
    }

    public function test_passes_through_latin_and_mixed_input(): void
    {
        $this->assertSame('abc-learning-book', Slug::fromTitle('ABC Learning Book'));
        $this->assertSame('ktab-abc-123', Slug::fromTitle('كتاب ABC ١٢٣'));
    }

    public function test_empty_or_whitespace_returns_empty(): void
    {
        $this->assertSame('', Slug::fromTitle(''));
        $this->assertSame('', Slug::fromTitle('   '));
    }

    public function test_output_is_lowercase_dash_separated_ascii(): void
    {
        $slug = Slug::fromTitle('مغامرات في الغابة الكبيرة');

        $this->assertSame($slug, strtolower($slug));
        $this->assertDoesNotMatchRegularExpression('/\s/', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function test_very_long_title_is_capped(): void
    {
        $slug = Slug::fromTitle(str_repeat('كتاب ', 200));

        $this->assertLessThanOrEqual(200, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }
}
