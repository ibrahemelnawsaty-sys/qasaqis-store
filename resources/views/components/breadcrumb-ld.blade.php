@props(['items'])

{{-- BreadcrumbList لمسار التنقّل. يستعيد «مسار الفتات» في نتيجة البحث (يحلّ محلّ
     الرابط العاري فوق العنوان). كان موجودًا على مقالات المدوّنة فقط، بينما تعرض
     صفحات الكتب والأقسام والصفحات الثابتة مسارًا مرئيًا بلا أي وسم يقابله.

     $items: مصفوفة ['name' => ..., 'url' => ...] بالترتيب من الرئيسية إلى الصفحة الحالية.

     ملاحظة: نعرّف أعلام JSON هنا ولا نعتمد $seoJsonFlags من التخطيط — فهو متغيّر
     محلي داخل كتلة PHP في التخطيط وغير متاح للقوالب الفرعية. --}}
@php
    $breadcrumbItems = array_values(array_filter(
        $items,
        static fn ($item): bool => is_array($item) && filled($item['name'] ?? null) && filled($item['url'] ?? null),
    ));

    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(
            static fn (int $index, array $item): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ],
            array_keys($breadcrumbItems),
            $breadcrumbItems,
        ),
    ];

    $breadcrumbFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp

@if (count($breadcrumbItems) > 1)
    <script type="application/ld+json">{!! json_encode($breadcrumbLd, $breadcrumbFlags) !!}</script>
@endif
