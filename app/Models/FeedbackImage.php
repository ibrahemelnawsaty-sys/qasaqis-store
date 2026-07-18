<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * صورة شهادة عميل في معرض الرئيسية. يديرها الأدمن (رفع صور جديدة).
 * image_path قد يكون: صورة ساكنة قديمة (images/…)، أو رفعًا على قرص public (feedback/…)،
 * أو رابطًا خارجيًا. المُلحِق url يحلّها جميعًا لرابط صالح.
 */
class FeedbackImage extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackImageFactory> */
    use HasFactory;

    protected $fillable = [
        'image_path',
        'alt',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * رابط الصورة الجاهز للعرض (يعالج الصور الساكنة والرفوعات والروابط الخارجية).
     */
    public function getUrlAttribute(): string
    {
        $path = (string) $this->image_path;

        if ($path === '') {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // الصور الساكنة القديمة تحت public/images تُخدَم مباشرةً؛ غيرها من قرص التخزين العام.
        if (Str::startsWith($path, ['images/', '/images/'])) {
            return asset(ltrim($path, '/'));
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
