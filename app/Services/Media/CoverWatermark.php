<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * يعالج صورة الغلاف للعرض العام: (١) يصغّرها إلى دقّة عرض (يبقى الأصل عالي الدقّة
 * خاصًّا فلا تنفع النسخة المسروقة للطباعة/إعادة البيع)، و(٢) يضع الشعار بشفافية في
 * الزاوية السفلى (حاضر لكن غير مزعج للزائر). GD المضمّن (بلا حزمة).
 *
 * صامد: يعيد null عند أي فشل فيخدم المتحكّم الأصل — الصورة لا تنكسر أبدًا.
 */
class CoverWatermark
{
    /** أقصى ضلع للنسخة العامة (px). العرض على الشاشة أصغر بكثير؛ الأصل يبقى كامل الدقّة. */
    private const MAX_EDGE = 1400;

    /** عرض الشعار كنسبة من عرض الصورة (زاوية صغيرة). */
    private const SCALE = 0.26;

    /** شفافية العلامة (0 = خفيّة، 1 = صريحة) — خفيفة كي لا تُضايق. */
    private const OPACITY = 0.32;

    /** هامش الزاوية كنسبة من العرض. */
    private const MARGIN = 0.03;

    /**
     * يعيد بايتات الصورة المعالَجة (مصغّرة + موسومة): WebP إن توفّر imagewebp (الدستور
     * 5.3، أخفّ ~٢٥–٣٥٪ بنفس الجودة)، وإلا JPEG. null عند تعذّر المعالجة. الامتداد في
     * MediaCache::ext() يتبع الشرط نفسه فيوافق الاسمُ البايتات.
     */
    public function apply(string $sourceAbsPath): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! is_file($sourceAbsPath)) {
            return null;
        }

        $info = @getimagesize($sourceAbsPath);
        if ($info === false) {
            return null;
        }

        $src = $this->load($sourceAbsPath, (string) $info['mime']);
        if ($src === null) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // دقّة الهدف: تصغير إن تجاوز الضلع الأطول MAX_EDGE (يحمي قيمة الأصل عالي الدقّة).
        $targetW = $w;
        $targetH = $h;
        $maxEdge = max($w, $h);
        if ($maxEdge > self::MAX_EDGE) {
            $ratio = self::MAX_EDGE / $maxEdge;
            $targetW = max(1, (int) round($w * $ratio));
            $targetH = max(1, (int) round($h * $ratio));
        }

        // لوحة بيضاء (تسطيح الشفافية) + رسم المصدر بدقّة الهدف.
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, $w, $h);
        imagedestroy($src);

        $this->stampLogo($canvas, $targetW, $targetH);

        // WebP إن توفّر (الدستور 5.3، أخفّ بنفس الجودة)، وإلا سقوط آمن لـJPEG. الشرط
        // نفسه في MediaCache::ext() فيطابق الامتدادُ المكتوبُ في الاسم البايتاتِ.
        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($canvas, null, 80) : imagejpeg($canvas, null, 82);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $ok ? $binary : null;
    }

    private function load(string $path, string $mime): ?\GdImage
    {
        // ملاحظة الصيغ التي يتعذّر على GD رسترتها فتُخدَم بالأصل عبر البديل الصامد:
        // SVG (متجهيّة، لا رسترة)، وWebP/AVIF المتحرّكة أو المتغايرة. AVIF يُعالَج هنا
        // حيث يدعمه GD؛ وإلا يرتدّ getimagesize فوق فيُخدَم الأصل.
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        return $img instanceof \GdImage ? $img : null;
    }

    /**
     * يرسم الشعار الشفّاف في الزاوية السفلى (يمين). أي فشل يُتجاهَل (تبقى الصورة بلا علامة).
     */
    private function stampLogo(\GdImage $canvas, int $w, int $h): void
    {
        $logoPath = public_path('images/logo.png');
        if (! is_file($logoPath)) {
            return;
        }

        $logo = @imagecreatefrompng($logoPath);
        if (! $logo instanceof \GdImage) {
            return;
        }

        $lw = imagesx($logo);
        $lh = imagesy($logo);

        $targetW = max(1, (int) round($w * self::SCALE));
        $targetH = max(1, (int) round($lh * ($targetW / $lw)));

        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetW, $targetH, $lw, $lh);
        imagedestroy($logo);

        // خفض الشفافية إلى OPACITY.
        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $c = imagecolorat($scaled, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $newA = 127 - (int) round((127 - $a) * self::OPACITY);
                $newA = max(0, min(127, $newA));
                imagesetpixel($scaled, $x, $y, imagecolorallocatealpha($scaled, ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $newA));
            }
        }

        // الزاوية السفلى اليمنى بهامش.
        $margin = (int) round($w * self::MARGIN);
        $dx = max(0, $w - $targetW - $margin);
        $dy = max(0, $h - $targetH - $margin);

        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, $dx, $dy, 0, 0, $targetW, $targetH);
        imagedestroy($scaled);
    }
}
