@props([
    'book' => null,     // إن مرّرت كتابًا: رسالة طلب لهذا الكتاب تحديدًا
    'label' => null,
    'aria' => null,
    'icon' => false,    // زر أيقونة (واتساب العائم)
    'class' => 'btn btn-wa',
])

@php
    $number = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));

    if ($book) {
        $text = __('common.wa_order_single', ['title' => $book->title]) . ' ' . route('books.show', $book);
    } else {
        $text = __('common.wa_order_intro');
    }

    $href = ($number ? 'https://wa.me/' . $number : 'https://wa.me/') . '?text=' . rawurlencode($text);
@endphp

<a {{ $attributes->merge(['class' => $class]) }} href="{{ $href }}" target="_blank" rel="noopener noreferrer"
    @if ($aria) aria-label="{{ $aria }}" @endif>
    @if ($icon)
        <span class="ping" aria-hidden="true"></span>
        <svg viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.6-.6-2.8-1.2-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.1.1.3 0 .5-.1.2-.2.4-.3.5l-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.7-.9c.2-.3.4-.2.6-.1.2.1 1.5.7 1.7.9.2.1.4.2.4.3.1.1.1.6-.1 1.1Z"/></svg>
    @else
        {{ $label ?? __('common.order_whatsapp') }}
    @endif
</a>
