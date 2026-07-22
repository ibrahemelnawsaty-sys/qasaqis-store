<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Services\Seo\AnalysisCheck;
use App\Services\Seo\ContentAnalyzer;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * محلّل محتوى المحرّر (نظير تحليل Yoast): فحوص الكلمة المفتاحية والطول والكثافة.
 * منطق خالص بلا قاعدة بيانات.
 */
class ContentAnalyzerTest extends TestCase
{
    private ContentAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ContentAnalyzer();
    }

    /**
     * @param  Collection<int, AnalysisCheck>  $checks
     */
    private function withMessage(Collection $checks, string $needle): ?AnalysisCheck
    {
        return $checks->first(fn (AnalysisCheck $c): bool => str_contains($c->message, $needle));
    }

    public function test_without_keyword_it_prompts_and_still_checks_lengths(): void
    {
        $checks = $this->analyzer->analyze([
            'title' => str_repeat('ا', 40),
            'description' => str_repeat('ب', 130),
            'body' => str_repeat('كلمة ', 60),
        ]);

        $prompt = $checks->first();
        $this->assertSame(AnalysisCheck::OK, $prompt->status);
        $this->assertStringContainsString('لم تُدخل كلمة مفتاحية', $prompt->message);

        // لا فحوص كلمة مفتاحية بلا كلمة.
        $this->assertNull($this->withMessage($checks, 'موجودة في العنوان'));

        // فحوص الطول تعمل: كلها جيّدة هنا.
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'طول العنوان مناسب')?->status);
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'طول الوصف مثاليّ')?->status);
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'طول المحتوى جيّد')?->status);
    }

    public function test_keyword_present_in_all_fields_is_good(): void
    {
        $checks = $this->analyzer->analyze([
            'keyword' => 'قصص أطفال',
            'title' => 'قصص أطفال ممتعة ومفيدة تُنمّي الخيال لدى الصغار',
            'description' => 'مجموعة قصص أطفال مصوّرة تعلّم القيم والأخلاق بأسلوب مشوّق ولغة بسيطة تناسب مرحلة ما قبل المدرسة وبداية القراءة.',
            'body' => str_repeat('قصص أطفال رائعة ومحتوى تعليمي هادف. ', 20),
            'slug' => 'kids-stories',
        ]);

        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'موجودة في العنوان')?->status);
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'موجودة في وصف الميتا')?->status);
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'موجودة في نصّ المحتوى')?->status);
    }

    public function test_keyword_missing_from_title_is_bad(): void
    {
        $checks = $this->analyzer->analyze([
            'keyword' => 'زرافة',
            'title' => 'كتاب جميل للأطفال',
            'description' => 'قصة زرافة',
            'body' => 'زرافة لطيفة',
        ]);

        $this->assertSame(AnalysisCheck::BAD, $this->withMessage($checks, 'غير موجودة في العنوان')?->status);
    }

    public function test_title_too_long_is_bad_and_short_is_ok(): void
    {
        $long = $this->analyzer->analyze(['title' => str_repeat('ا', 75)]);
        $this->assertSame(AnalysisCheck::BAD, $this->withMessage($long, 'العنوان طويل جدًا')?->status);

        $short = $this->analyzer->analyze(['title' => 'قصير']);
        $this->assertSame(AnalysisCheck::OK, $this->withMessage($short, 'العنوان قصير')?->status);
    }

    public function test_empty_fields_flag_bad(): void
    {
        $checks = $this->analyzer->analyze([]);

        $this->assertSame(AnalysisCheck::BAD, $this->withMessage($checks, 'لا يوجد عنوان')?->status);
        $this->assertSame(AnalysisCheck::BAD, $this->withMessage($checks, 'لا يوجد وصف')?->status);
        $this->assertSame(AnalysisCheck::BAD, $this->withMessage($checks, 'لا يوجد محتوى')?->status);
    }

    public function test_arabic_keyword_skips_slug_check_but_latin_keyword_checks_it(): void
    {
        $arabic = $this->analyzer->analyze([
            'keyword' => 'زرافة',
            'slug' => 'giraffe-book',
            'title' => 'زرافة',
            'description' => 'زرافة',
            'body' => 'زرافة',
        ]);
        $this->assertNull($this->withMessage($arabic, 'الرابط (slug)'));

        $latin = $this->analyzer->analyze([
            'keyword' => 'giraffe',
            'slug' => 'giraffe-book',
            'title' => 'giraffe fun for kids',
            'description' => 'giraffe',
            'body' => 'giraffe text',
        ]);
        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($latin, 'موجودة في الرابط')?->status);
    }

    public function test_html_body_is_counted_as_plain_text(): void
    {
        $checks = $this->analyzer->analyze([
            'body' => '<p>'.str_repeat('<b>كلمة</b> ', 60).'</p>',
        ]);

        $this->assertSame(AnalysisCheck::GOOD, $this->withMessage($checks, 'طول المحتوى جيّد')?->status);
    }

    public function test_verdict_reflects_the_worst_status(): void
    {
        $bad = $this->analyzer->analyze(['keyword' => 'زرافة', 'title' => 'بلا كلمة']);
        $this->assertSame(AnalysisCheck::BAD, $this->analyzer->verdict($bad)['status']);

        $good = $this->analyzer->analyze([
            'keyword' => 'giraffe',
            'title' => 'giraffe fun adventures for curious little readers today',
            'description' => str_repeat('giraffe ', 20),
            'body' => str_repeat('giraffe story text here ', 20),
            'slug' => 'giraffe-fun',
        ]);
        $this->assertContains($this->analyzer->verdict($good)['status'], [AnalysisCheck::GOOD, AnalysisCheck::OK]);
    }
}
