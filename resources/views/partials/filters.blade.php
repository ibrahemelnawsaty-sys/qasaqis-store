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

@if ($publishers->isNotEmpty())
    <h3>{{ __('catalog.facet_publisher') }}</h3>
    @foreach ($publishers as $pub)
        <label class="filter-row">
            <input type="checkbox" name="pub[]" value="{{ $pub->id }}"
                @checked(in_array((string) $pub->id, $selPub, true))>
            <span>{{ $pub->name }}</span>
            <span class="cnt">({{ $pub->books_count }})</span>
        </label>
    @endforeach
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
