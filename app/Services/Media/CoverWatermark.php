<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * يضع علامة العلامة المائية (شعار «قصاقيص أطفال») في وسط صورة الغلاف بشفافية، عبر GD
 * المضمّن (لا حزمة خارجية). يحمي الملكية: حتى لو نُسخت الصورة تبقى موسومة باسمك.
 *
 * صامد للأعطال: يعيد null عند أي فشل (GD مفقود، صورة تالفة، شعار غائب) فيخدم المتحكّم
 * الأصل كما هو — الصورة لا تنكسر أبدًا. الشعار يُحمَّل مرّة ويُعاد استخدامه.
 */
class CoverWatermark
{
    /** عرض الشعار كنسبة من عرض الغلاف. */
    private const SCALE = 0.42;

    /** شفافية العلامة (0 = خفيّة، 1 = صريحة). */
    private const OPACITY = 0.30;

    /**
     * يعيد بايتات JPEG للصورة موسومةً، أو null عند تعذّر المعالجة.
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

        // تسطيح على خلفية بيضاء (أغلفة PNG الشفّافة تخرج نظيفة كـJPEG).
        $canvas = imagecreatetruecolor($w, $h);
        imagefilledrectangle($canvas, 0, 0, $w, $h, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        $this->stampLogo($canvas, $w, $h);

        ob_start();
        $ok = imagejpeg($canvas, null, 82);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $ok ? $binary : null;
    }

    private function load(string $path, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        return $img instanceof \GdImage ? $img : null;
    }

    /**
     * يرسم الشعار الشفّاف في وسط اللوحة. أي فشل يُتجاهَل (تبقى الصورة بلا علامة خير من لا صورة).
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

        // شعار مصغّر بقناة ألفا محفوظة.
        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetW, $targetH, $lw, $lh);
        imagedestroy($logo);

        // خفض الشفافية إلى OPACITY (كل بكسل: نزيد ألفا نحو الشفاف).
        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $c = imagecolorat($scaled, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $newA = 127 - (int) round((127 - $a) * self::OPACITY);
                $newA = max(0, min(127, $newA));
                imagesetpixel($scaled, $x, $y, imagecolorallocatealpha($scaled, ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $newA));
            }
        }

        $dx = (int) round(($w - $targetW) / 2);
        $dy = (int) round(($h - $targetH) / 2);

        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, $dx, $dy, 0, 0, $targetW, $targetH);
        imagedestroy($scaled);
    }
}
