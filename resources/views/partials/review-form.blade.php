{{--
    نموذج إرسال رأي على كتاب.

    الاستعمال: @include('partials.review-form') من داخل قسم «آراء الأمهات» في
    resources/views/books/show.blade.php — يتوقّع المتغيّر $book في النطاق.

    الحارس أدناه مقصود وليس زينة: قبل أن يسجّل المنسّق المسار وحارس customer،
    يرمي route() استثناء RouteNotFoundException ويرمي auth()->guard('customer')
    استثناء InvalidArgumentException — أي أن مجرّد إدراج الجزئية كان سيُسقط صفحة
    الكتاب كاملةً بخطأ 500. مع الحارس تُدرَج بأمان في أي وقت وتُفعّل نفسها تلقائيًا
    فور اكتمال الربط.
--}}
@if (\Illuminate\Support\Facades\Route::has('books.reviews.store') && filled(config('auth.guards.customer')))
    @once
        @push('head')
            <style>
                /* رموز التصميم فقط (6.1) وخصائص منطقية للاتجاه (6.2). */
                .rv{ margin-top:20px; background:var(--surface); border:1px solid var(--line); border-radius:var(--r-md); padding:22px; box-shadow:var(--shadow-s); }
                .rv h3{ font-weight:900; font-size:17px; }
                .rv .rv-intro{ font-size:13.5px; color:var(--ink-soft); margin-top:4px; }
                .rv form{ display:grid; gap:14px; margin-top:16px; }
                .rv fieldset{ border:0; padding:0; margin:0; min-inline-size:0; }
                .rv .rv-label{ display:block; font-weight:800; font-size:14px; }
                .rv .rv-opt{ font-weight:600; font-size:12.5px; color:var(--ink-faint); }
                .rv .rv-hint{ font-size:12.5px; color:var(--ink-soft); margin-top:6px; }
                .rv .rv-choices{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
                .rv .rv-choice{ display:inline-flex; align-items:center; gap:8px; min-height:48px; padding-inline:14px; border:1px solid var(--line-2); border-radius:var(--r-pill); background:var(--surface-soft); cursor:pointer; }
                .rv .rv-choice:focus-within{ border-color:var(--purple); box-shadow:0 0 0 3px var(--purple-soft); }
                .rv .rv-choice .rv-stars{ color:var(--gold); letter-spacing:2px; font-size:16px; }
                .rv .rv-input{ inline-size:100%; min-height:48px; padding:12px 14px; margin-top:6px; border:1px solid var(--line-2); border-radius:var(--r-sm); background:var(--surface-soft); color:var(--ink); font:inherit; }
                .rv textarea.rv-input{ min-height:110px; line-height:1.7; resize:vertical; }
                .rv .rv-input:focus-visible{ outline:none; border-color:var(--purple); box-shadow:0 0 0 3px var(--purple-soft); }
                .rv .rv-input.err{ border-color:var(--pink); }
                .rv .rv-err{ color:var(--pink); font-size:12.5px; font-weight:700; margin-top:6px; }
                .rv .rv-alert{ border-radius:var(--r-sm); padding:12px 14px; font-weight:800; font-size:14px; margin-bottom:14px; }
                .rv .rv-alert.ok{ background:color-mix(in srgb,var(--good) 14%,var(--surface)); border:1px solid color-mix(in srgb,var(--good) 40%,transparent); }
                .rv .rv-alert.bad{ background:color-mix(in srgb,var(--pink) 12%,var(--surface)); border:1px solid color-mix(in srgb,var(--pink) 40%,transparent); }
                .rv .rv-cta{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
            </style>
        @endpush
    @endonce

    <div class="rv" id="review-form">
        @if (session('review_success'))
            <p class="rv-alert ok" role="status">✅ {{ __('review.success') }}</p>
        @endif

        @if (session('review_error'))
            <p class="rv-alert bad" role="alert">⚠️ {{ session('review_error') }}</p>
        @endif

        <h3>{{ __('review.form_title') }}</h3>
        <p class="rv-intro">{{ __('review.form_intro') }}</p>

        @auth('customer')
            <form method="post" action="{{ route('books.reviews.store', ['book' => $book->slug]) }}">
                @csrf

                <fieldset aria-describedby="rv-rating-hint">
                    <legend class="rv-label">{{ __('review.field_rating') }}</legend>
                    <p id="rv-rating-hint" class="rv-hint">{{ __('review.rating_hint') }}</p>

                    <div class="rv-choices">
                        @foreach ([5, 4, 3, 2, 1] as $value)
                            <label class="rv-choice" for="rv-rating-{{ $value }}">
                                <input type="radio" id="rv-rating-{{ $value }}" name="rating"
                                       value="{{ $value }}" required @checked((int) old('rating') === $value)>
                                <span class="rv-stars" aria-hidden="true">{{ str_repeat('★', $value) }}{{ str_repeat('☆', 5 - $value) }}</span>
                                <span class="sr-only">{{ __('review.stars_'.$value) }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('rating')
                        <p class="rv-err" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div>
                    <label class="rv-label" for="rv-title">
                        {{ __('review.field_title') }} <span class="rv-opt">{{ __('review.optional') }}</span>
                    </label>
                    <input type="text" id="rv-title" name="title" maxlength="200"
                           value="{{ old('title') }}" placeholder="{{ __('review.title_placeholder') }}"
                           class="rv-input @error('title') err @enderror">
                    @error('title')
                        <p class="rv-err" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="rv-label" for="rv-body">{{ __('review.field_body') }}</label>
                    <textarea id="rv-body" name="body" rows="4" maxlength="2000" required
                              aria-describedby="rv-body-hint" placeholder="{{ __('review.body_placeholder') }}"
                              class="rv-input @error('body') err @enderror">{{ old('body') }}</textarea>
                    <p id="rv-body-hint" class="rv-hint">{{ __('review.body_hint') }}</p>
                    @error('body')
                        <p class="rv-err" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rv-cta">
                    <button type="submit" class="btn btn-primary">{{ __('review.submit') }}</button>
                    <span class="rv-hint">{{ __('review.pending_note') }}</span>
                </div>
            </form>
        @else
            <p class="rv-hint">{{ __('review.login_prompt') }}</p>
            @if (\Illuminate\Support\Facades\Route::has('customer.login.show'))
                <p style="margin-top:12px">
                    <a class="btn btn-primary" href="{{ route('customer.login.show') }}">{{ __('review.login_cta') }}</a>
                </p>
            @endif
        @endauth
    </div>
@endif
