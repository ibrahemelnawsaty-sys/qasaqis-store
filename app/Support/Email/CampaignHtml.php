<?php

declare(strict_types=1);

namespace App\Support\Email;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * تعقيم HTML الذي يكتبه الأدمن في محرّر الحملة (الباب 4.3 / منع XSS). يعتمد
 * symfony/html-sanitizer المثبّت مسبقًا — لا نُضيف حزمة تنقية أخرى.
 *
 * نسمح فقط بالوسوم التي يُخرِجها RichEditor في Filament (فقرات، تنسيق نصّي، قوائم،
 * عناوين، روابط). كل ما عداه — <script>/<style>/<iframe>/سمات on* وأي جافاسكربت —
 * يُزال. مخططات الروابط مقصورة على https/mailto، وتُرقّى http إلى https.
 *
 * القاعدة: عقّم **عند الحفظ** (في CampaignDispatcher) قبل تخزين body_html، وكذلك في
 * المعاينة والإرسال التجريبي. لذلك تُطبع النتيجة في القالب بـ{!! !!} بأمان — لأنها
 * ناتج المعقِّم لا إدخال خام.
 */
final class CampaignHtml
{
    public static function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('a', ['href', 'title'])
            ->allowElement('p')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('blockquote')
            // http مسموح كي يُرقّيه forceHttpsUrls إلى https بدل حذف الرابط كليًّا؛
            // javascript/data وغيرها تبقى محذوفة. النتيجة: كل الروابط https أو mailto.
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceHttpsUrls();

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
