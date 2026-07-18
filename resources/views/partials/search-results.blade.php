{{-- نتائج البحث الفوري (كتب): غلاف + عنوان + ناشر/مؤلف + السعر. تُشارَك بين شريط
     سطح المكتب وشاشة الموبايل عبر $store.search. صور الغلاف هي «الأيقونات الفاخرة». --}}
<template x-for="(item, i) in $store.search.items" :key="item.u + '-' + i">
    <a :href="item.u" role="option" class="s-res"
        :class="{ 'is-active': i === $store.search.active }"
        :aria-selected="(i === $store.search.active).toString()"
        @mouseenter="$store.search.active = i">
        <span class="s-res__thumb">
            <template x-if="item.img">
                <img :src="item.img" :alt="item.t" loading="lazy" decoding="async">
            </template>
            <template x-if="!item.img">
                <span class="s-res__ph" aria-hidden="true" x-text="item.t ? item.t.slice(0, 1) : ''"></span>
            </template>
        </span>
        <span class="s-res__body">
            <span class="s-res__title" x-text="item.t"></span>
            <span class="s-res__sub" x-show="item.p || item.a" x-text="item.p || item.a"></span>
        </span>
        <span class="s-res__price" x-show="item.pr" x-text="item.pr"></span>
    </a>
</template>

{{-- لا نتائج (بعد اكتمال تحميل الفهرس وكتابة نص) --}}
<template x-if="$store.search.loaded && $store.search.q.trim() && ! $store.search.items.length">
    <div class="s-empty">
        <x-ui-icon name="search" :size="24" />
        <span>{{ __('search.live_no_results') }}</span>
    </div>
</template>
