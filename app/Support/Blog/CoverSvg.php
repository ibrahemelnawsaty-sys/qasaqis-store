<?php

declare(strict_types=1);

namespace App\Support\Blog;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;

/**
 * توليد غلاف مقال تلقائيًّا كـ SVG مضمّن: قالبُ القسم (صورة ثابتة) + عنوان المقال
 * نصًّا يشكّله المتصفّح بخط Lalezar فوق اللوح الزجاجيّ — بلا أيّ مكتبة صور على الخادم،
 * وخفيف (الخط يُحمَّل مرّة واحدة وكسولًا). يُستخدَم للمقالات بلا غلاف مرفوع (coverUrl()==null).
 *
 * السلامة: render() لا يرمي أبدًا — أيّ خطأ يُرجِع '' فيظهر العنصر البديل ولا تنكسر الصفحة.
 * التطابق مع الأغلفة النقطية (الـ33): نفس القالب والخطّ والإحداثيات (لوح موسَّط ثابت).
 */
final class CoverSvg
{
    // إحداثيات مطابقة لمولّد الأغلفة النقطية (coverlib): اللوح الزجاجيّ الثابت.
    private const W = 1200;
    private const H = 750;
    private const PCX = 715;          // مركز اللوح الأفقيّ
    private const CY = 449;           // مركز اللوح الرأسيّ
    private const MAXW = 734;         // أقصى عرض للسطر داخل اللوح
    private const SIZE_HI = 64;
    private const SIZE_LO = 40;

    /** خريطة القسم → مِلفّ القالب (المجهول → parenting افتراضيًّا). */
    private static function slug(?string $category): string
    {
        return match (trim((string) $category)) {
            'نصائح تربوية' => 'parenting',
            'تربية بالقصص' => 'stories',
            'أنشطة وتعليم' => 'activities',
            'مراجعات كتب' => 'reviews',
            default => 'parenting',
        };
    }

    /** رابط قالب القسم (صورة نقطية صالحة لـ og — دائمًا يُرجِع رابطًا). */
    public static function templateUrl(Article $article): string
    {
        return asset('images/blog-templates/blog-tpl-'.self::slug($article->category).'.webp');
    }

    /**
     * SVG الغلاف المضمّن. $style يُمرَّر من القالب (بطاقة المدونة تملأ الإطار،
     * صفحة المقال بعرض كامل). يُرجِع '' عند أيّ خطأ.
     */
    public static function render(Article $article, string $style = ''): string
    {
        try {
            $key = 'blog.cover.svg.'.$article->getKey().'.'
                .optional($article->updated_at)->getTimestamp().'.'.md5($style);

            return (string) Cache::remember($key, now()->addDay(), static fn (): string => self::build($article, $style));
        } catch (\Throwable) {
            try {
                return self::build($article, $style);   // الكاش تعطّل؟ ابنِ مباشرةً.
            } catch (\Throwable) {
                return '';                               // لا نكسر الصفحة أبدًا.
            }
        }
    }

    private static function build(Article $article, string $style): string
    {
        $tpl = self::templateUrl($article);
        $title = self::arabicDigits(trim((string) $article->title));

        if ($title === '') {
            return '';
        }

        [$size, $lines] = self::wrap($title);
        $lh = $size * 1.44;
        $top = self::CY - ($lh * count($lines)) / 2;

        $texts = '';
        foreach ($lines as $i => $line) {
            $y = (int) round($top + $i * $lh + $size * 0.76);
            $esc = htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $texts .= '<text x="'.self::PCX.'" y="'.$y.'" font-family="Lalezar,&apos;Segoe UI&apos;,sans-serif"'
                .' font-size="'.$size.'" fill="#ffffff" text-anchor="middle" direction="rtl"'
                .' xml:space="preserve">'.$esc.'</text>';
        }

        $label = htmlspecialchars((string) $article->title, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $styleAttr = $style !== '' ? ' style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'"' : '';

        return '<svg viewBox="0 0 '.self::W.' '.self::H.'" xmlns="http://www.w3.org/2000/svg"'
            .' xmlns:xlink="http://www.w3.org/1999/xlink"'
            .' preserveAspectRatio="xMidYMid slice" role="img" aria-label="'.$label.'"'.$styleAttr.'>'
            .'<image href="'.$tpl.'" xlink:href="'.$tpl.'" x="0" y="0" width="'.self::W.'" height="'.self::H.'"/>'
            .$texts.'</svg>';
    }

    /**
     * يقسّم العنوان إلى ≤3 أسطر بأكبر حجم يسع اللوح. القياس عبر GD (imagettfbbox)
     * إن توفّر — وهو يُبالغ قليلًا في عرض العربية غير المشكّلة، فيكسر أبكر (آمن ضدّ
     * التجاوز) — وإلا تقدير بعدد المحارف.
     *
     * @return array{0:int,1:array<int,string>}
     */
    private static function wrap(string $title): array
    {
        $words = preg_split('/\s+/u', $title) ?: [$title];

        for ($size = self::SIZE_HI; $size >= self::SIZE_LO; $size -= 2) {
            $lines = self::greedy($words, $size);
            $fits = count($lines) <= 3;
            foreach ($lines as $l) {
                if (self::measure($l, $size) > self::MAXW) {
                    $fits = false;
                    break;
                }
            }
            if ($fits) {
                return [$size, $lines];
            }
        }

        return [self::SIZE_LO, self::greedy($words, self::SIZE_LO)];
    }

    /**
     * @param  array<int,string>  $words
     * @return array<int,string>
     */
    private static function greedy(array $words, int $size): array
    {
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $trial = $cur === '' ? $w : $cur.' '.$w;
            if ($cur === '' || self::measure($trial, $size) <= self::MAXW) {
                $cur = $trial;
            } else {
                $lines[] = $cur;
                $cur = $w;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines;
    }

    private static function measure(string $text, int $size): float
    {
        $ttf = public_path('fonts/lalezar.ttf');
        if (function_exists('imagettfbbox') && is_file($ttf)) {
            $box = @imagettfbbox($size, 0, $ttf, $text);
            if (is_array($box)) {
                return abs($box[2] - $box[0]);
            }
        }

        return mb_strlen($text, 'UTF-8') * $size * 0.55;   // تقدير احتياطيّ
    }

    /** الأرقام اللاتينية → هندية (لتطابق الأغلفة النقطية). */
    private static function arabicDigits(string $text): string
    {
        return strtr($text, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }
}
