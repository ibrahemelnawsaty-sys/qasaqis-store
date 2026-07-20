{{--
    عرض احترافي لتعليمات الدفع اليدوي (تحويل/محفظة).
    المدخل: $method (App\Models\PaymentMethod|null).

    المصدر المفضّل هو الحقل المنظّم account_details (رابط الدفع + عنوان الحساب + رقم
    المحفظة). وإن كان الأدمن قد وضع كل شيء نصًّا في «التعليمات» (كما هو الحال الآن)،
    نستخرج منه الرابط والعنوان والرقم تلقائيًّا فيظهر بنفس الشكل بلا إعادة إدخال.

    أمان: كل القيم تُهرَّب بـ {{ }}، ورابط الدفع لا يُقبل إلا http(s) فلا حقن سكربت.
--}}
@php
    // يحوّل روابط النصّ إلى وسوم <a> بأمان: يُهرَّب كل جزء مرّة واحدة فقط (لا ازدواج
    // تهريب يكسر معاملات الرابط)، والرابط لا يُقبل إلا http(s) فلا حقن سكربت.
    $linkify = static function (string $text): string {
        $parts = preg_split('~(https?://[^\s]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ((array) $parts as $i => $part) {
            if ($i % 2 === 1) {
                $url = rtrim((string) $part, '.,،؛');
                $out .= '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>';
            } else {
                $out .= nl2br(e((string) $part));
            }
        }

        return $out;
    };

    $payLink = null;
    $copyItems = [];              // [label => value]
    $usedDetails = false;

    if ($method) {
        // 1) الحقل المنظّم account_details (مفضّل)
        foreach ((array) $method->account_details as $label => $value) {
            $v = is_array($value) ? trim(implode(' ', $value)) : trim((string) $value);
            if ($v === '') {
                continue;
            }
            $usedDetails = true;
            if (\Illuminate\Support\Str::startsWith($v, ['http://', 'https://'])) {
                $payLink ??= $v;
            } else {
                $copyItems[is_string($label) && $label !== '' ? $label : '—'] = $v;
            }
        }

        // 2) احتياطي: استخراج من نصّ التعليمات إن لم يوفّر account_details شيئًا
        $instr = trim((string) $method->instructions);
        if (! $usedDetails && $instr !== '') {
            if (preg_match('~https?://[^\s]+~u', $instr, $m)) {
                $payLink = rtrim($m[0], '.,;،؛');
            }
            // عنوان الحساب (handle/بريد): نمط user@domain
            if (preg_match('~[^\s@]+@[^\s@,،؛]+~u', $instr, $m)) {
                $copyItems[__('checkout.payment.account_handle')] = trim($m[0], '.,،؛');
            }
            // رقم محفظة مصري: 11 خانة تبدأ بـ 01
            if (preg_match('~\b01\d{9}\b~u', $instr, $m)) {
                $copyItems[__('checkout.payment.wallet_number')] = $m[0];
            }
        }

        // النصّ التوضيحي: نُظهره فقط حين تأتي البيانات المنظّمة من account_details
        // (حقل مستقلّ)، لا حين استخرجناها من التعليمات نفسها (تفاديًا للتكرار).
        $prose = ($usedDetails && $instr !== '') ? $instr : null;
    }

    $hasBlock = $payLink !== null || $copyItems !== [];
@endphp

@if ($hasBlock)
    <div class="pay-box">
        <div class="pay-box-head"><span class="ic" aria-hidden="true">💸</span> {{ __('checkout.payment.pay_now') }}</div>

        @if ($payLink)
            <a class="pay-cta" href="{{ $payLink }}" target="_blank" rel="noopener noreferrer">
                <span>{{ __('checkout.payment.pay_via', ['method' => $method->name]) }}</span>
                <span class="arrow" aria-hidden="true">▾</span>
            </a>
        @endif

        @if ($copyItems !== [])
            @if ($payLink)
                <div class="pay-or">{{ __('checkout.payment.or') }}</div>
                <div class="pay-alt-label">{{ __('checkout.payment.via_account') }}</div>
            @endif

            @foreach ($copyItems as $label => $value)
                <div class="pay-row" x-data="{ copied: false }">
                    <span class="pay-row-label">{{ $label }}</span>
                    <span class="pay-row-value co-mono" x-ref="v{{ $loop->index }}">{{ $value }}</span>
                    <button type="button" class="pay-copy" :class="{ 'done': copied }"
                        @click="navigator.clipboard && navigator.clipboard.writeText($refs.v{{ $loop->index }}.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-text="copied ? '{{ __('checkout.payment.copied') }}' : '{{ __('checkout.payment.copy') }}'">{{ __('checkout.payment.copy') }}</span>
                    </button>
                </div>
            @endforeach
        @endif

        @if ($prose)
            <p class="pay-note">{!! $linkify($prose) !!}</p>
        @endif
    </div>
@elseif ($method && filled($method->instructions))
    {{-- لا رابط ولا بيانات مكتشَفة: نعرض التعليمات كما هي (بروابط قابلة للضغط) --}}
    <p class="pay-note" style="margin-top:6px">{!! $linkify((string) $method->instructions) !!}</p>
@endif
