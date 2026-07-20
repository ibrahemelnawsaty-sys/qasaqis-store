<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * سطر واحد في سجل «من غيّر ماذا ومتى» (جدول admin_activities).
 *
 * ‏append-only: يُكتب مرة واحدة من App\Support\Audit\RecordsAdminActivity ولا يُعدَّل
 * ولا يُحذف. لذلك UPDATED_AT = null (لا عمود updated_at في الهجرة) والمورد في
 * اللوحة يمنع الإنشاء/التعديل/الحذف صراحةً.
 *
 * ⚠ تحذير للمطوّر — العمود اسمه `changes` وهو **يتعارض اسميًا** مع الخاصية الداخلية
 * `protected $changes` في Illuminate\...\Concerns\HasAttributes (التي تغذّي
 * getChanges()). الوصول من خارج الصنف (Filament/Blade/الاختبارات) يمرّ عبر __get
 * لأن الخاصية protected، فيعيد قيمة العمود سليمة — وهذا مغطّى باختبار صريح.
 * لكن **داخل هذا الصنف** يجب استعمال `$this->getAttribute('changes')` حصرًا؛
 * كتابة `$this->changes` هنا تقرأ الخاصية الداخلية لا العمود.
 */
class AdminActivity extends Model
{
    /** السطر لا يُعدَّل أبدًا — لا عمود updated_at في الجدول. */
    public const UPDATED_AT = null;

    /** أقصى طول محفوظ لكل قيمة داخل changes (يمنع تضخّم longText داخل JSON). */
    public const MAX_VALUE_LENGTH = 500;

    /** علامة محايدة لغويًا تحلّ محلّ أي قيمة حسّاسة (كلمة مرور/مفتاح/توكن). */
    public const REDACTED_MARK = '••••••';

    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    public const EVENT_RESTORED = 'restored';

    protected $table = 'admin_activities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ----- العلاقات ---------------------------------------------------------

    /** الفاعل. قد يكون null إن حُذف المستخدم بعد تسجيل الأثر (nullOnDelete). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * السجل المتأثّر. **لا تُحمَّل في القوائم**: morphTo عبر أنواع مختلطة يعني استعلامًا
     * لكل نوع، والسجل قد يكون محذوفًا نهائيًا فيعود null. القائمة تعرض النوع والمعرّف
     * نصًّا بلا أي استعلام إضافي (بند 2.5 — لا N+1).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ----- قراءة الفرق ------------------------------------------------------

    /**
     * الفرق المخزَّن بشكل مُطبَّع: ['field' => ['old' => …, 'new' => …]].
     * دفاعي عمدًا: عمود JSON قد يحوي سطرًا قديمًا أو تالفًا، ولا يجوز أن تُسقط
     * صفحةَ السجل بنيةٌ غير متوقعة.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changesArray(): array
    {
        $raw = $this->getAttribute('changes'); // لا $this->changes — انظر تحذير الصنف.

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $field => $pair) {
            if (! is_string($field) || ! is_array($pair)) {
                continue;
            }

            $out[$field] = [
                'old' => $pair['old'] ?? null,
                'new' => $pair['new'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * أسماء الحقول المتغيّرة فقط — لعمود ملخّص في الجدول بلا فكّ الفرق كاملًا.
     *
     * @return list<string>
     */
    public function changedFields(): array
    {
        return array_keys($this->changesArray());
    }
}
