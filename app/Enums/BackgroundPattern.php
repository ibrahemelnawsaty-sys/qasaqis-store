<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نقوش الخلفية المستخرجة من شعار «قصص أطفال».
 *
 * هذا هو المصدر الوحيد لقائمة النقوش: تقرؤه شاشات الأدمن، وخدمة العرض،
 * واختبارٌ يثبت أن لكل حالة هنا قاعدة CSS مقابلة في app.css. إضافة نقش
 * جديد تعني إضافة حالة هنا + قاعدتَي CSS (نهارية وليلية) — لا أقلّ.
 */
enum BackgroundPattern: string
{
    case None = 'none';
    case StorybookLattice = 'storybook-lattice';
    case BookFans = 'book-fans';
    case ScissorsTrails = 'scissors-trails';
    case CalligraphicCurls = 'calligraphic-curls';
    case DotsAndArcs = 'dots-and-arcs';
    case ScrapsConfetti = 'scraps-confetti';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بلا نقش',
            self::StorybookLattice => 'شبكة القصص — كتاب ومقص وتموّجة في شبكة معيّنات',
            self::BookFans => 'مروحة الصفحات — الكتاب المفتوح بصفحاته الملوّنة',
            self::ScissorsTrails => 'أثر المقص — خطوط قصّ منقّطة ومقصّات صغيرة',
            self::CalligraphicCurls => 'تموّجات الخط — تموّجات عربية خفيفة بلا أشكال مصمتة',
            self::DotsAndArcs => 'نقاط وأقواس — الأكثر تجريدًا وهدوءًا',
            self::ScrapsConfetti => 'نُثار القصاقيص — قصاصات ورق متناثرة (احتفالي)',
        };
    }

    /**
     * فئة CSS المقابلة في app.css، أو null حين لا نقش.
     */
    public function cssClass(): ?string
    {
        return $this === self::None ? null : 'pat-'.$this->value;
    }

    /**
     * خيارات قوائم Filament المنسدلة: القيمة => التسمية العربية.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * يحوّل قيمة محفوظة (قد تكون null أو مفتاحًا قديمًا حُذف) إلى حالة صالحة.
     * لا يرمي استثناءً: نقش مفقود يجب أن يعني «بلا نقش» لا صفحة مكسورة.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }
}
