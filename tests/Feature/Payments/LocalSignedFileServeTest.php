<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * يعيد إنتاج آليّة خدمة إثبات الدفع بالضبط: رابط موقّت موقّع (temporaryUrl) لملفّ على
 * القرص الخاصّ (local، serve=true) ثم طلبه — كي نتحقّق هل التوليد+التحقّق يعملان
 * (200) أم أن هناك خللًا يُنتج 404. لا يمسّ DB.
 */
final class LocalSignedFileServeTest extends TestCase
{
    public function test_temporary_url_for_private_local_file_serves_ok(): void
    {
        $disk = Storage::disk('local');
        $path = 'payment-proofs/test/'.bin2hex(random_bytes(6)).'.jpg';
        $disk->put($path, 'FAKE-JPEG-BYTES');

        try {
            $url = $disk->temporaryUrl($path, now()->addHour());
            $parts = parse_url($url);
            $relative = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');

            $this->get($relative)->assertOk();
        } finally {
            $disk->delete($path);
        }
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $disk = Storage::disk('local');
        $path = 'payment-proofs/test/'.bin2hex(random_bytes(6)).'.jpg';
        $disk->put($path, 'FAKE-JPEG-BYTES');

        try {
            $url = $disk->temporaryUrl($path, now()->addHour());
            $parts = parse_url($url);
            // نُفسِد التوقيع: يجب ألا يُخدَم (403 محليًّا / 404 في الإنتاج).
            $relative = $parts['path'].'?'.$parts['query'].'0';

            $this->get($relative)->assertStatus(403);
        } finally {
            $disk->delete($path);
        }
    }
}
