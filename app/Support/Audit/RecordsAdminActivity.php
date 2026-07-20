<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * يسجّل «من غيّر ماذا ومتى» على أي موديل يستعمله — بلا أي حزمة جديدة (بند 5.6).
 *
 * الاستعمال: `use RecordsAdminActivity;` داخل الموديل. تلتقط Eloquent الدالة
 * bootRecordsAdminActivity() تلقائيًا عبر bootTraits() فلا حاجة لتسجيل مراقب.
 *
 * ما يُسجَّل: created / updated / deleted / restored — وهي بالضبط قيم enum عمود
 * `event` في هجرة admin_activities (بند 1.1).
 *
 * ‎**متى يُسجَّل؟** فقط حين يكون هناك مستخدم موثَّق على حارس `web` يحمل أحد أدوار
 * اللوحة السبعة (User::ADMIN_ROLES). هذا مقصود لسببين:
 *   1) الجدول اسمه «نشاط إداري»: طلب يُنشئه زائر في الشيك آوت ليس فعلًا إداريًا،
 *      ولو سُجِّل لأغرق السجل بسطر لكل طلب.
 *   2) البذر والاختبارات وأوامر الكونسول تعمل بلا مستخدم، فلا تلوّث السجل.
 * أي أن user_id = null في جدول لا يعني «النظام» بل «مستخدم حُذف لاحقًا»
 * (nullOnDelete).
 *
 * ‎**قيود مصرَّح بها (بند 1.5):**
 *   • `Model::withoutEvents()` و`saveQuietly()`/`updateQuietly()` والتحديث الجماعي
 *     `Query\Builder::update()` لا تُطلق أحداث Eloquent — فلا تُسجَّل. (نفس قيد
 *     OrderObserver الموثّق في رأس ذلك الملف.)
 *   • القيم تُقرأ **خامًا** (قبل الـ casts) لتناظر الجديد والقديم ولتجنّب تشغيل
 *     accessors ذات آثار جانبية داخل حدث حفظ.
 *   • كل قيمة تُقصّ عند AdminActivity::MAX_VALUE_LENGTH حرفًا، فالحقول الطويلة
 *     (محتوى صفحة/مقال) تُسجَّل مقتطعة لا كاملة.
 */
trait RecordsAdminActivity
{
    public static function bootRecordsAdminActivity(): void
    {
        static::created(static function (Model $model): void {
            $model->recordAdminActivity(AdminActivity::EVENT_CREATED, $model->auditSnapshot(asNewValues: true));
        });

        static::updated(static function (Model $model): void {
            $diff = $model->auditDiffFromChanges();

            if ($diff === []) {
                return; // حفظ لم يغيّر شيئًا ذا معنى (updated_at فقط مثلًا).
            }

            // ‏SoftDeletes::restore() يمرّ عبر save()، فيُطلق updated بجانب restored.
            // نتجاهل الأول متى كان تغييره الوحيد تصفيرَ عمود الحذف الناعم، وإلا
            // لظهر سطران لعملية استعادة واحدة.
            if (method_exists($model, 'getDeletedAtColumn')) {
                $column = $model->getDeletedAtColumn();

                if (array_keys($diff) === [$column] && $diff[$column]['new'] === null) {
                    return;
                }
            }

            $model->recordAdminActivity(AdminActivity::EVENT_UPDATED, $diff);
        });

        // يغطّي الحذف الناعم والصلب معًا: forceDelete() يُطلق deleted أيضًا.
        static::deleted(static function (Model $model): void {
            $model->recordAdminActivity(AdminActivity::EVENT_DELETED, $model->auditSnapshot(asNewValues: false));
        });

        // حدث restored ومسجّله الساكن معرَّفان داخل SoftDeletes وحدها، لا في
        // HasEvents. استدعاء static::restored() على موديل بلا حذف ناعم (Setting،
        // PaymentMethod…) يُمرَّر إلى الـ query builder فيرمي BadMethodCallException
        // لحظة إقلاع الموديل. لذلك يُسجَّل شرطيًا.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            // ‏restored يقع بعد أن زامنت save() الأصلَ، فقيمة deleted_at السابقة ضاعت
            // بالفعل — الحدث نفسه هو المعلومة، فيُسجَّل بفرق فارغ عمدًا.
            static::restored(static function (Model $model): void {
                $model->recordAdminActivity(AdminActivity::EVENT_RESTORED, []);
            });
        }
    }

    /**
     * حقول إضافية يحجب هذا الموديل قيمَها (تبقى أسماؤها ظاهرة في السجل).
     * نقطة التوسعة الوحيدة المقصودة: يتجاوزها الموديل عند الحاجة، مثل
     * `return $this->is_encrypted ? ['value'] : [];` في Setting.
     *
     * @return list<string>
     */
    protected function auditExcludedAttributes(): array
    {
        return [];
    }

    /**
     * أعمدة تُسقَط من السجل كليًا لأنها ضجيج لا معلومة: المفتاح الأساسي (موجود
     * أصلًا في subject_id) وطابعا الوقت (موجودان في created_at الخاص بالسطر).
     *
     * @return list<string>
     */
    protected function auditIgnoredAttributes(): array
    {
        $columns = array_filter(
            [$this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()],
            static fn (?string $name): bool => $name !== null && $name !== '',
        );

        return array_values(array_map(static fn (string $name): string => Str::lower($name), $columns));
    }

    /**
     * قائمة سوداء صريحة بأسماء أعمدة لا تُكتب قيمتها في أي سجل مهما كان الموديل
     * (بند 4.3): السجل يقرؤه دور it/super_admin، ولا يجوز أن يصير مخزنًا للأسرار.
     *
     * @return list<string>
     */
    protected function auditRedactedAttributes(): array
    {
        return [
            'password', 'password_confirmation', 'current_password', 'password_hash',
            'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
            'api_key', 'api_secret', 'secret', 'secret_key', 'private_key',
            'access_token', 'refresh_token', 'token', 'webhook_secret',
            'hmac_key', 'signature', 'otp', 'otp_code',
            'idempotency_key', 'session_id',
        ];
    }

    /**
     * أنماط جزئية تكمل القائمة السوداء لالتقاط أعمدة مستقبلية لم تُسمَّ بعد
     * (مثل paymob_secret_key). مُختارة بضيق كي لا تبتلع أعمدة مشروعة:
     * `settings.key` مثلًا لا يطابق أيًّا منها ويظل مسجَّلًا.
     *
     * @return list<string>
     */
    protected function auditRedactedPatterns(): array
    {
        return ['password', 'secret', 'token', 'api_key', 'private_key', 'credential', 'passphrase'];
    }

    /**
     * يكتب السطر. لا يرمي أبدًا: فشل التدقيق يجب ألا يُسقط حفظ الأدمن، لكنه
     * **يُسجَّل بصوت مسموع** في اللوج بدل الابتلاع الصامت (بند 1.5 / ممنوع 20).
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    protected function recordAdminActivity(string $event, array $changes): void
    {
        try {
            $actor = $this->auditActor();

            if ($actor === null) {
                return;
            }

            $ip = Request::ip();

            AdminActivity::query()->create([
                'user_id' => $actor->getKey(),
                // getMorphClass لا ::class — يحترم أي morph map يُضاف لاحقًا.
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
                'event' => $event,
                'changes' => $changes,
                // قصّ دفاعي: عمود varchar(45)، وترويسة وكيل مشوّهة يجب ألا تُسقط الحفظ.
                'ip_address' => $ip === null ? null : mb_substr($ip, 0, 45),
            ]);
        } catch (Throwable $e) {
            Log::warning('admin_activity.record_failed', [
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
                'event' => $event,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * الفاعل: مستخدم لوحة موثَّق على حارس `web` حصرًا.
     *
     * الحارس مُسمّى صراحةً لا `auth()` الافتراضي، لأن المستقبل قد يضيف حارس عملاء
     * للواجهة؛ حينها يبقى هذا السجل إداريًا بحتًا. وفحص instanceof يحمي المفتاح
     * الأجنبي admin_activities.user_id → users.id من أي مزوّد آخر.
     */
    protected function auditActor(): ?User
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return null;
        }

        // عميل متجر قد يشارك نفس الحارس؛ الفعل الإداري وحده يُسجَّل.
        return $user->hasAnyRole(User::ADMIN_ROLES) ? $user : null;
    }

    /**
     * لقطة كاملة للسجل — تُستعمل عند الإنشاء (قيم جديدة) وعند الحذف (قيم قديمة).
     * الأعمدة الفارغة تُسقَط: «لم تُملأ» ليست معلومة تستحق سطرًا في الفرق.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function auditSnapshot(bool $asNewValues): array
    {
        $ignored = $this->auditIgnoredAttributes();
        $diff = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (! is_string($key) || $value === null || in_array(Str::lower($key), $ignored, true)) {
                continue;
            }

            $entry = $this->auditPresentValue($key, $value);

            $diff[$key] = $asNewValues
                ? ['old' => null, 'new' => $entry]
                : ['old' => $entry, 'new' => null];
        }

        return $diff;
    }

    /**
     * فرق التعديل: الحقول المتغيّرة فقط، بقيمتها قبل وبعد.
     *
     * getChanges() يعطي القيم الجديدة خامًا، وgetRawOriginal() يعطي القديمة خامًا
     * كذلك — والتناظر مقصود. (getOriginal() المُموضَع يطبّق الـ casts ويُنشئ نسخة
     * جديدة من الموديل عند كل استدعاء، فلا يُستعمل هنا.)
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function auditDiffFromChanges(): array
    {
        $ignored = $this->auditIgnoredAttributes();
        $diff = [];

        foreach ($this->getChanges() as $key => $new) {
            if (! is_string($key) || in_array(Str::lower($key), $ignored, true)) {
                continue;
            }

            $diff[$key] = [
                'old' => $this->auditPresentValue($key, $this->getRawOriginal($key)),
                'new' => $this->auditPresentValue($key, $new),
            ];
        }

        return $diff;
    }

    /**
     * يحوّل قيمة عمود خامًا إلى شيء صالح للتخزين في JSON وللعرض — بعد الحجب.
     * القيمة الفارغة تبقى null حتى للحقول المحجوبة، فيظل الفرق يخبر «كان فارغًا
     * فصار مضبوطًا» دون كشف الجديد.
     */
    protected function auditPresentValue(string $key, mixed $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        if ($this->auditIsRedacted($key)) {
            return AdminActivity::REDACTED_MARK;
        }

        return $this->auditNormalizeValue($value);
    }

    protected function auditIsRedacted(string $key): bool
    {
        $key = Str::lower($key);

        $exact = array_map(
            static fn (string $name): string => Str::lower($name),
            [...$this->auditRedactedAttributes(), ...$this->auditExcludedAttributes()],
        );

        if (in_array($key, $exact, true)) {
            return true;
        }

        foreach ($this->auditRedactedPatterns() as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تطبيع القيمة إلى قياسي صالح لـ JSON، مع قصّ الطول.
     *
     * أعمدة JSON تصل هنا كنص مُرمَّز، فتُفكّ وتُمشَّط لحجب أي مفتاح حسّاس بداخلها
     * (مثل config = {"api_key": …}) قبل إعادة ترميزها — وإلا تسرّب السر داخل نص.
     */
    protected function auditNormalizeValue(mixed $value): string|int|float|bool|null
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $this->auditEncode($this->auditRedactNested($value));
        }

        if (is_object($value) && ! method_exists($value, '__toString')) {
            return $this->auditEncode($this->auditRedactNested((array) $value));
        }

        $string = (string) $value;

        // نص يبدو JSON: افحصه واحجب مفاتيحه الحسّاسة بدل تخزينه كما هو.
        if ($string !== '' && ($string[0] === '{' || $string[0] === '[')) {
            $decoded = json_decode($string, true);

            if (is_array($decoded)) {
                return $this->auditEncode($this->auditRedactNested($decoded));
            }
        }

        return Str::limit($string, AdminActivity::MAX_VALUE_LENGTH, '…');
    }

    /**
     * حجب متكرر لمفاتيح البنى المتداخلة (JSON) بنفس قواعد الأعمدة.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    protected function auditRedactNested(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $item !== null && $this->auditIsRedacted($key)) {
                $out[$key] = AdminActivity::REDACTED_MARK;

                continue;
            }

            $out[$key] = is_array($item) ? $this->auditRedactNested($item) : $item;
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    protected function auditEncode(array $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : Str::limit($encoded, AdminActivity::MAX_VALUE_LENGTH, '…');
    }
}
