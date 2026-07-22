<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * محلّل محتوى المحرّر (نظير تحليل Yoast الحيّ): يأخذ الكلمة المفتاحية والعنوان والوصف
 * والنصّ والرابط، ويعيد قائمة فحوص ملوّنة (جيّد/مقبول/يحتاج إصلاح) تُعرض تحت المحرّر
 * وتتحدّث مع الكتابة.
 *
 * منطق خالص بلا حالة (لا يقرأ قاعدة البيانات)، فيُختبَر مستقلًّا وتستدعيه لوحة الأدمن
 * على كل تحديث Livewire. النصوص عربية موجّهة للمالك غير التقنيّ.
 */
class ContentAnalyzer
{
    // حدود العنوان (بالأحرف) — تقريب لمساحة نتائج جوجل (~600px).
    private const TITLE_GOOD_MIN = 30;

    private const TITLE_GOOD_MAX = 60;

    private const TITLE_HARD_MAX = 70;

    // حدود وصف الميتا (بالأحرف).
    private const DESC_GOOD_MIN = 120;

    private const DESC_GOOD_MAX = 160;

    private const DESC_SOFT_MIN = 70;

    // حدود طول المحتوى (بالكلمات) — مخفّضة لتناسب أوصاف المنتجات لا مقالات المدوّنة فقط.
    private const BODY_GOOD_MIN = 50;

    // حدود كثافة الكلمة المفتاحية (%).
    private const DENSITY_MIN = 0.5;

    private const DENSITY_MAX = 3.0;

    /**
     * @param  array{keyword?:string,title?:string,description?:string,body?:string,slug?:string}  $input
     * @return Collection<int, AnalysisCheck>
     */
    public function analyze(array $input): Collection
    {
        $keyword = $this->clean($input['keyword'] ?? '');
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $body = $this->plain((string) ($input['body'] ?? ''));

        $checks = collect();

        $checks->push($this->keywordPresence($keyword));

        if ($keyword !== '') {
            $checks->push($this->keywordIn($keyword, $title, 'العنوان', AnalysisCheck::BAD));
            $checks->push($this->keywordIn($keyword, $description, 'وصف الميتا', AnalysisCheck::BAD));
            $checks->push($this->keywordIn($keyword, $body, 'نصّ المحتوى', AnalysisCheck::BAD));

            if (($slugCheck = $this->keywordInSlug($keyword, $slug)) !== null) {
                $checks->push($slugCheck);
            }

            if (($density = $this->keywordDensity($keyword, $body)) !== null) {
                $checks->push($density);
            }
        }

        $checks->push($this->titleLength($title));
        $checks->push($this->descriptionLength($description));
        $checks->push($this->bodyLength($body));

        return $checks->values();
    }

    /**
     * ملخّص لوني عام من الفحوص (لرأس اللوحة).
     *
     * @param  Collection<int, AnalysisCheck>  $checks
     * @return array{status:string, label:string}
     */
    public function verdict(Collection $checks): array
    {
        $bad = $checks->where('status', AnalysisCheck::BAD)->count();
        $ok = $checks->where('status', AnalysisCheck::OK)->count();

        if ($bad > 0) {
            return ['status' => AnalysisCheck::BAD, 'label' => 'يحتاج تحسينًا'];
        }

        if ($ok > 0) {
            return ['status' => AnalysisCheck::OK, 'label' => 'جيّد — بإمكانه أن يكون أفضل'];
        }

        return ['status' => AnalysisCheck::GOOD, 'label' => 'ممتاز'];
    }

    private function keywordPresence(string $keyword): AnalysisCheck
    {
        return $keyword === ''
            ? new AnalysisCheck(AnalysisCheck::OK, 'لم تُدخل كلمة مفتاحية بعد — أدخل العبارة التي تريد أن يجدك بها الناس في جوجل لبدء التحليل.')
            : new AnalysisCheck(AnalysisCheck::GOOD, 'الكلمة المفتاحية المختارة: «'.$keyword.'».');
    }

    private function keywordIn(string $keyword, string $haystack, string $where, string $failStatus): AnalysisCheck
    {
        $found = $haystack !== '' && str_contains($this->clean($haystack), $keyword);

        return $found
            ? new AnalysisCheck(AnalysisCheck::GOOD, 'الكلمة المفتاحية موجودة في '.$where.'.')
            : new AnalysisCheck($failStatus, 'الكلمة المفتاحية غير موجودة في '.$where.' — أضِفها.');
    }

    /**
     * فحص الرابط يُطبَّق فقط حين يكون للكلمة صورة لاتينية (المحتوى العربي روابطه
     * لاتينية يدويًا، فلا معنى لإجبار العربية في الـslug — يُتخطّى بلا إزعاج).
     */
    private function keywordInSlug(string $keyword, string $slug): ?AnalysisCheck
    {
        // كلمة غير لاتينية (عربية) روابط الموقع لها لاتينية يدويًا، فلا معنى لفحص
        // احتوائها في الـslug — يُتخطّى بلا إزعاج. (Str::slug يحوّل العربية إلى لاتينية
        // فلا يكفي فحص الفراغ وحده.)
        if (preg_match('/[^\x00-\x7F]/u', $keyword) === 1) {
            return null;
        }

        $keywordSlug = Str::slug($keyword);

        if ($keywordSlug === '') {
            return null;
        }

        return str_contains(Str::slug($slug), $keywordSlug)
            ? new AnalysisCheck(AnalysisCheck::GOOD, 'الكلمة المفتاحية موجودة في الرابط (slug).')
            : new AnalysisCheck(AnalysisCheck::OK, 'يُفضّل أن يحتوي الرابط (slug) على الكلمة المفتاحية إن أمكن.');
    }

    private function keywordDensity(string $keyword, string $body): ?AnalysisCheck
    {
        $words = $this->wordCount($body);
        if ($words === 0) {
            return null;
        }

        $occurrences = substr_count($this->clean($body), $keyword);
        if ($occurrences === 0) {
            return null; // مغطّى بفحص «موجودة في نصّ المحتوى».
        }

        $keywordWords = max(1, $this->wordCount($keyword));
        $density = round(($occurrences * $keywordWords) / $words * 100, 1);

        if ($density < self::DENSITY_MIN) {
            return new AnalysisCheck(AnalysisCheck::OK, 'كثافة الكلمة المفتاحية منخفضة ('.$density.'%) — يمكن ذكرها مرّات أكثر قليلًا.');
        }

        if ($density > self::DENSITY_MAX) {
            return new AnalysisCheck(AnalysisCheck::OK, 'كثافة الكلمة المفتاحية مرتفعة ('.$density.'%) — تجنّب حشوها كثيرًا.');
        }

        return new AnalysisCheck(AnalysisCheck::GOOD, 'كثافة الكلمة المفتاحية جيّدة ('.$density.'%).');
    }

    private function titleLength(string $title): AnalysisCheck
    {
        $len = mb_strlen($title);

        if ($len === 0) {
            return new AnalysisCheck(AnalysisCheck::BAD, 'لا يوجد عنوان — أضِف عنوانًا (يظهر كأول سطر في نتائج جوجل).');
        }

        if ($len > self::TITLE_HARD_MAX) {
            return new AnalysisCheck(AnalysisCheck::BAD, 'العنوان طويل جدًا ('.$len.' حرفًا) — سيُبتر في جوجل. الأفضل ≤ '.self::TITLE_GOOD_MAX.'.');
        }

        if ($len >= self::TITLE_GOOD_MIN && $len <= self::TITLE_GOOD_MAX) {
            return new AnalysisCheck(AnalysisCheck::GOOD, 'طول العنوان مناسب ('.$len.' حرفًا).');
        }

        return $len < self::TITLE_GOOD_MIN
            ? new AnalysisCheck(AnalysisCheck::OK, 'العنوان قصير ('.$len.' حرفًا) — يمكن الوصول إلى 40–60 حرفًا لاستغلال مساحة جوجل.')
            : new AnalysisCheck(AnalysisCheck::OK, 'العنوان طويل قليلًا ('.$len.' حرفًا) — قد يُبتر طرفه في جوجل.');
    }

    private function descriptionLength(string $description): AnalysisCheck
    {
        $len = mb_strlen($description);

        if ($len === 0) {
            return new AnalysisCheck(AnalysisCheck::BAD, 'لا يوجد وصف — أضِف وصفًا (يظهر تحت العنوان في نتائج جوجل).');
        }

        if ($len >= self::DESC_GOOD_MIN && $len <= self::DESC_GOOD_MAX) {
            return new AnalysisCheck(AnalysisCheck::GOOD, 'طول الوصف مثاليّ ('.$len.' حرفًا).');
        }

        if ($len > self::DESC_GOOD_MAX) {
            return new AnalysisCheck(AnalysisCheck::OK, 'الوصف طويل ('.$len.' حرفًا) — سيُقتطع عند ~'.self::DESC_GOOD_MAX.' حرفًا في جوجل.');
        }

        return $len < self::DESC_SOFT_MIN
            ? new AnalysisCheck(AnalysisCheck::OK, 'الوصف قصير ('.$len.' حرفًا) — يُفضّل 120–160 حرفًا.')
            : new AnalysisCheck(AnalysisCheck::OK, 'الوصف مقبول ('.$len.' حرفًا) — يمكن الوصول إلى ~150 حرفًا.');
    }

    private function bodyLength(string $body): AnalysisCheck
    {
        $words = $this->wordCount($body);

        if ($words === 0) {
            return new AnalysisCheck(AnalysisCheck::BAD, 'لا يوجد محتوى — أضِف نصًّا يصف المحتوى (يساعد الترتيب في جوجل).');
        }

        return $words >= self::BODY_GOOD_MIN
            ? new AnalysisCheck(AnalysisCheck::GOOD, 'طول المحتوى جيّد ('.$words.' كلمة).')
            : new AnalysisCheck(AnalysisCheck::OK, 'المحتوى قصير ('.$words.' كلمة) — محتوى أطول (وصف، تفاصيل، مخرجات) يحسّن الترتيب.');
    }

    /** تطبيع للمطابقة: تشذيب + توحيد المسافات + تحويل لأحرف صغيرة. */
    private function clean(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_strtolower($value);
    }

    /** يحوّل HTML المحرّر إلى نصّ عاديّ (لعدّ الكلمات والبحث). */
    private function plain(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = (string) preg_replace('/<[^>]+>/u', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text) ?: []);
    }
}
