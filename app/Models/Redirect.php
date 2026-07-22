<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * تحويل URL دائم/مؤقّت. المصدر «auto» يُنشأ تلقائيًا عند تغيير slug (يمنع 404 عند
 * تغيير رابط كتاب/مقال/صفحة)، و«manual» يضيفه الأدمن. يُطبَّق فقط عند وصول 404
 * (معالج الاستثناءات في bootstrap/app.php) فلا حِمل على المسارات الموجودة.
 */
class Redirect extends Model
{
    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'is_active',
        'hits',
        'last_hit_at',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * يطبّع مسارًا للمطابقة: يبدأ بشرطة مائلة، بلا شرطة زائدة، بلا سلسلة استعلام.
     */
    public static function normalizePath(string $path): string
    {
        $path = strtok($path, '?') ?: $path;
        $path = '/' . ltrim(trim($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
