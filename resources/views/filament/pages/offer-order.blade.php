<x-filament-panels::page>
    <x-filament::section>
        <p style="font-size:13.5px;line-height:1.8;">
            هذه الكتب هي التي تظهر في <strong>صفحة العروض</strong> — أي كتاب له «سعر قبل الخصم».
            رتّب ظهورها بالسحب، أو اكتب رقم الترتيب مباشرةً (الأصغر = الأول). لإضافة/إزالة كتاب من
            العروض، ضَع أو امسح الخصم من صفحة الكتاب نفسه.
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
