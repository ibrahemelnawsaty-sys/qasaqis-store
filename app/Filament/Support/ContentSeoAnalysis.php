<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\Seo\ContentAnalyzer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Contracts\Support\Htmlable;

/**
 * قسم «تحليل SEO المباشر» القابل لإعادة الاستخدام (نظير محلّل Yoast في المحرّر):
 * حقل كلمة مفتاحية + لوحة نقاط ملوّنة تتحدّث مع الكتابة. يقرأ حقول النموذج الأخرى
 * عبر Get، ويحسب النتائج عبر ContentAnalyzer، ويصيّرها في قالب.
 *
 * لكي تتحدّث النقاط عند تعديل العنوان/الوصف/النصّ، اضبط تلك الحقول ->live(onBlur: true)
 * في المورد المستضيف؛ حقل الكلمة المفتاحية نفسه حيّ (debounce) للاستجابة الفورية.
 */
final class ContentSeoAnalysis
{
    /**
     * @param  array<int, string>  $bodyFields  حقول النصّ (تُدمَج) لعدّ الكلمات والبحث
     */
    public static function make(
        string $titleField,
        string $descriptionField,
        array $bodyFields,
        string $slugField,
        string $keywordField = 'focus_keyword',
    ): Section {
        return Section::make('تحليل SEO المباشر')
            ->description('اختر كلمة مفتاحية وسنقيّم مدى تحسّن الصفحة لها فورًا (نظير Yoast). النقاط تتحدّث عند الكتابة والانتقال بين الحقول.')
            ->icon('heroicon-o-sparkles')
            ->collapsible()
            ->schema([
                TextInput::make($keywordField)
                    ->label('الكلمة المفتاحية')
                    ->maxLength(120)
                    ->live(debounce: 500)
                    ->helperText('العبارة التي تريد أن يجدك بها الناس في جوجل، مثل «قصص أطفال مصوّرة».'),

                Placeholder::make('seo_analysis_view')
                    ->hiddenLabel()
                    ->content(function (Get $get) use ($titleField, $descriptionField, $bodyFields, $slugField, $keywordField): Htmlable {
                        $body = collect($bodyFields)
                            ->map(fn (string $field): string => (string) $get($field))
                            ->implode(' ');

                        $analyzer = app(ContentAnalyzer::class);
                        $checks = $analyzer->analyze([
                            'keyword' => (string) $get($keywordField),
                            'title' => (string) $get($titleField),
                            'description' => (string) $get($descriptionField),
                            'body' => $body,
                            'slug' => (string) $get($slugField),
                        ]);

                        return view('filament.forms.seo-analysis', [
                            'checks' => $checks,
                            'verdict' => $analyzer->verdict($checks),
                        ]);
                    }),
            ]);
    }
}
