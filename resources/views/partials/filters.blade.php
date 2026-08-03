@props([
    'categories',
    'publishers',
    'ageOptions',
    'category' => null,
])

@php
    $selPub = array_map('strval', (array) request('pub', []));
    $selAge = (array) request('age', []);
@endphp

{{-- فلتر القسم انتقل إلى قائمة ملاحة في شريط الأدوات (بجانب الفئة العمرية)؛ القسم
     المختار يُحمَل مخفيًّا في النموذج فيبقى محفوظًا عند تطبيق فلاتر هذه اللوحة. --}}

@once
    @push('head')
        {{-- قائمة دور النشر منسدلة بحثيّة متعدّدة الاختيار (Alpine خفيف بلا مكتبات — بند 5.2).
             أنماط مضمّنة عمدًا (نفس نمط بلوكات القالب) لتُنشَر بـgit pull بلا بناء أصول. --}}
        <style>
            .pub-select__trigger{ width:100%; height:44px; display:flex; align-items:center; justify-content:space-between; gap:8px; border:1.5px solid var(--line); border-radius:var(--r-sm); background:var(--surface-soft); padding-inline:12px; font-family:inherit; font-size:14px; font-weight:700; color:var(--ink); cursor:pointer; }
            .pub-select__trigger:hover{ border-color:var(--purple); }
            .pub-select__trigger:focus-visible{ outline:2px solid var(--purple); outline-offset:2px; }
            .pub-select__chev{ flex:0 0 auto; color:var(--ink-soft); transition:transform .18s; }
            .pub-select__chev.is-open{ transform:rotate(180deg); }
            .pub-select__panel{ margin-top:8px; }
            .pub-select__search{ width:100%; height:40px; border:1.5px solid var(--line); border-radius:var(--r-sm); background:var(--surface); padding-inline:12px; font-family:inherit; font-size:14px; color:var(--ink); }
            .pub-select__search:focus{ outline:none; border-color:var(--purple); }
            .pub-select__list{ max-height:240px; overflow-y:auto; margin-top:6px; padding-inline-end:2px; }
            .pub-select__empty{ font-size:13px; color:var(--ink-faint); padding:8px 2px; margin:0; }
            [x-cloak]{ display:none !important; }
        </style>
    @endpush
@endonce

@if ($publishers->isNotEmpty())
    <h3>{{ __('catalog.facet_publisher') }}</h3>
    <div class="pub-select"
        x-data="{
            open: false,
            q: '',
            count: {{ count($selPub) }},
            names: {{ \Illuminate\Support\Js::from($publishers->pluck('name')->values()->all()) }},
            labelAll: {{ \Illuminate\Support\Js::from(__('catalog.pub_all')) }},
            labelPub: {{ \Illuminate\Support\Js::from(__('catalog.facet_publisher')) }},
            norm(s) { return (s || '').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/[يى]/g, 'ي').replace(/\s+/g, ' ').trim(); },
            matches(s) { return this.q === '' || this.norm(s).includes(this.norm(this.q)); },
            get hasMatch() { return this.names.some(n => this.matches(n)); },
        }"
        @click.outside="open = false" @keydown.escape="open = false">
        <button type="button" class="pub-select__trigger" :aria-expanded="open" aria-haspopup="true"
            @click="open = ! open; if (open) $nextTick(() => $refs.pubq.focus())">
            <span x-text="count > 0 ? labelPub + ' (' + count + ')' : labelAll">{{ count($selPub) > 0 ? __('catalog.facet_publisher') : __('catalog.pub_all') }}</span>
            <svg class="pub-select__chev" :class="{ 'is-open': open }" viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 7.5 5 5 5-5" /></svg>
        </button>

        <div class="pub-select__panel" x-show="open" x-cloak x-transition.opacity>
            <input type="search" class="pub-select__search" x-ref="pubq" x-model="q"
                placeholder="{{ __('catalog.pub_search') }}" aria-label="{{ __('catalog.pub_search') }}" autocomplete="off">
            <div class="pub-select__list">
                @foreach ($publishers as $pub)
                    <label class="filter-row" data-name="{{ $pub->name }}" x-show="matches($el.dataset.name)">
                        <input type="checkbox" name="pub[]" value="{{ $pub->id }}"
                            @checked(in_array((string) $pub->id, $selPub, true))
                            @change="$event.target.checked ? count++ : count--">
                        <span>{{ $pub->name }}</span>
                        <span class="cnt">({{ $pub->books_count }})</span>
                    </label>
                @endforeach
                <p class="pub-select__empty" x-show="! hasMatch" x-cloak>{{ __('catalog.pub_none') }}</p>
            </div>
        </div>
    </div>
@endif

<h3>{{ __('catalog.facet_age') }}</h3>
@foreach ($ageOptions as $opt)
    <label class="filter-row">
        <input type="checkbox" name="age[]" value="{{ $opt['value'] }}"
            @checked(in_array($opt['value'], $selAge, true))>
        <span>{{ $opt['label'] }}</span>
    </label>
@endforeach

<h3>{{ __('catalog.facet_price') }}</h3>
<div class="price-inputs">
    <input type="number" inputmode="numeric" min="0" name="min" value="{{ request('min') }}"
        placeholder="{{ __('catalog.price_min') }}" aria-label="{{ __('catalog.price_min') }}">
    <input type="number" inputmode="numeric" min="0" name="max" value="{{ request('max') }}"
        placeholder="{{ __('catalog.price_max') }}" aria-label="{{ __('catalog.price_max') }}">
</div>

<h3>{{ __('catalog.filters') }}</h3>
<label class="filter-row">
    <input type="checkbox" name="sale" value="1" @checked(request()->boolean('sale'))>
    <span>{{ __('catalog.facet_offers') }}</span>
</label>
<label class="filter-row">
    <input type="checkbox" name="featured" value="1" @checked(request()->boolean('featured'))>
    <span>{{ __('catalog.facet_featured') }}</span>
</label>
<label class="filter-row">
    <input type="checkbox" name="new" value="1" @checked(request()->boolean('new'))>
    <span>{{ __('catalog.facet_new') }}</span>
</label>
<label class="filter-row">
    <input type="checkbox" name="stock" value="1" @checked(request()->boolean('stock'))>
    <span>{{ __('catalog.facet_stock') }}</span>
</label>

<div style="display:flex;gap:8px;margin-top:16px">
    <button type="submit" class="btn btn-primary btn-block">{{ __('catalog.filters_apply') }}</button>
</div>
