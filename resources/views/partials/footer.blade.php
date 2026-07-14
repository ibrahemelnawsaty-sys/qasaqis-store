@php
    $fb = $storeSettings['facebook_url'] ?? '';
    $ig = $storeSettings['instagram_url'] ?? '';
    $tt = $storeSettings['tiktok_url'] ?? '';
@endphp

<footer class="ft">
    <div class="wrap">
        <div class="ft-grid">
            <div>
                <a class="logo" href="{{ route('home') }}">
                    <span class="mark" aria-hidden="true"><b>ق</b></span>
                    <span class="logo-text">{{ __('common.brand') }}</span>
                </a>
                <p class="ft-about">{{ __('footer.about') }}</p>
                <div class="socials">
                    @if (filled($fb))
                        <a href="{{ $fb }}" target="_blank" rel="noopener" aria-label="{{ __('footer.social_facebook') }}">📘</a>
                    @endif
                    @if (filled($ig))
                        <a href="{{ $ig }}" target="_blank" rel="noopener" aria-label="{{ __('footer.social_instagram') }}">📸</a>
                    @endif
                    @if (filled($tt))
                        <a href="{{ $tt }}" target="_blank" rel="noopener" aria-label="{{ __('footer.social_tiktok') }}">🎵</a>
                    @endif
                    <x-wa-button :class="'socials-wa'" :aria="__('footer.social_whatsapp')" :label="'💬'" />
                </div>
            </div>

            <div>
                <h5>{{ __('footer.shop_heading') }}</h5>
                <div class="ft-links">
                    <a href="{{ route('books.index') }}">{{ __('footer.link_all_books') }}</a>
                    <a href="{{ route('books.offers') }}">{{ __('footer.link_offers') }}</a>
                    <a href="{{ route('search') }}">{{ __('common.search_submit') }}</a>
                </div>
            </div>

            <div>
                <h5>{{ __('nav.categories') }}</h5>
                <div class="ft-links">
                    @foreach ($navCategories as $cat)
                        <a href="{{ route('categories.show', $cat) }}">{{ $cat->name }}</a>
                    @endforeach
                </div>
            </div>

            <div>
                <h5>{{ __('footer.contact_heading') }}</h5>
                <div class="ft-links">
                    <x-wa-button :class="'ft-wa-link'" :label="__('footer.link_whatsapp')" />
                </div>
            </div>
        </div>

        <div class="ft-bottom">
            <span>{{ __('footer.rights', ['year' => date('Y')]) }}</span>
            <span class="dom">{{ __('common.domain') }}</span>
        </div>
    </div>
</footer>
