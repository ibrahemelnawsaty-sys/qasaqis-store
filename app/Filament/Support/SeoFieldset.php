<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

/**
 * قسم SEO موحّد لكيانات علاقة seo (morphOne على seo_meta): القسم والسلسلة.
 *
 * كل الحقول اختيارية؛ ما يُترك فارغًا يُشتقّ تلقائيًا من محتوى الكيان عبر
 * App\Support\Seo\SeoDefaults — وهو نفسه مصدر الـplaceholder المعروض للأدمن.
 *
 * الكتاب والصفحة يحتفظان بقسمَيهما الحاليَّين (بحقول أوفى)؛ هذا المصنع للكيانات
 * التي لم يكن لها قسم SEO أصلًا حتى لا يتكرّر التعريف بينها.
 */
final class SeoFieldset
{
    public static function make(): Section
    {
        return Section::make(__('seo.admin.section'))
            ->description(__('seo.admin.section_hint'))
            ->collapsible()
            ->collapsed()
            ->schema([
                // relationship() على Group هو أسلوب Filament v3 الموثّق لحفظ علاقة
                // morphOne. القيم تُخزَّن في seo_meta المشترك بلا عمود ولا هجرة.
                Group::make()
                    ->relationship('seo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('عنوان الميتا')
                            ->maxLength(255)
                            ->placeholder(fn ($livewire): string => SeoPlaceholder::title($livewire)),

                        Select::make('robots')
                            ->label('توجيه الروبوتات')
                            ->options([
                                'index,follow' => 'index,follow',
                                'noindex,follow' => 'noindex,follow',
                                'index,nofollow' => 'index,nofollow',
                                'noindex,nofollow' => 'noindex,nofollow',
                            ])
                            ->default('index,follow'),

                        Textarea::make('meta_description')
                            ->label('وصف الميتا')
                            ->maxLength(320)
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder(fn ($livewire): string => SeoPlaceholder::description($livewire)),

                        TextInput::make('canonical_url')
                            ->label('الرابط الأساسي (canonical)')
                            ->url()
                            ->maxLength(300),

                        TextInput::make('og_title')
                            ->label('عنوان OpenGraph')
                            ->maxLength(255)
                            ->placeholder(fn ($livewire): string => SeoPlaceholder::title($livewire)),

                        Textarea::make('og_description')
                            ->label('وصف OpenGraph')
                            ->maxLength(320)
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder(fn ($livewire): string => SeoPlaceholder::description($livewire)),

                        FileUpload::make('og_image_path')
                            ->label('صورة المشاركة (OG)')
                            ->image()
                            ->disk('public')
                            ->directory('seo/og')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
