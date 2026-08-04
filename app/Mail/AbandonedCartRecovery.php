<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * بريد استعادة سلة متروكة يراجعه الأدمن ويُرسله من صفحة «سلات متروكة». يُرسَل تزامنيًّا
 * (بلا طابور، كـInquiryReplied) فيعمل دون Queue Worker على الاستضافة المشتركة. العنوان
 * والنصّ يكتبهما/يراجعهما الأدمن؛ القالب يُهرّب أي HTML في النصّ (أمان).
 */
class AbandonedCartRecovery extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-cart-recovery',
            with: ['body' => $this->body],
        );
    }
}
