<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * بريد يُرسَل للعميل عند ردّ الدعم/الأدمن على استفساره.
 * يُرسَل تزامنيًا (بلا طابور) حتى يعمل دون Queue Worker على الاستضافة المشتركة.
 */
class InquiryReplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ردّ على استفسارك — قصاقيص أطفال',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiry-replied',
            with: ['inquiry' => $this->inquiry],
        );
    }
}
