{{--
    نافذة منبثقة (Pop-up) يتحكم بها الأدمن عبر CMS — «قصص أطفال».
    البوّابة الخادمية (is_active + الجدولة + استهداف الصفحة) تمّت في PopupService،
    ويُشارَك الناتج كـ $activePopup عبر View composer للتخطيط. هنا تُعالَج الجوانب
    الواجهية فقط: الجهاز المستهدف (بحسب عرض الشاشة)، محفّز الظهور، وتكرار العرض
    (يُتذكَّر الإغلاق عبر localStorage/sessionStorage). لا نص مثبّت — كله من الترجمة/الـCMS.
--}}
@if (! empty($activePopup))
    @php
        /** @var \App\Models\Popup $activePopup */
        $popup = $activePopup;

        // مفتاح التذكّر يتضمّن آخر تحديث؛ أي تعديل من الأدمن يُعيد ظهوره للمستخدم.
        $dismissKey = 'qasaqis-popup-'.$popup->id.'-'.(optional($popup->updated_at)->timestamp ?? 0);

        // صورة على القرص العام (نفس عرف صور الأغلفة: asset('storage/...')).
        $imageUrl = $popup->image_path
            ? asset('storage/'.ltrim($popup->image_path, '/'))
            : null;

        $devices = is_array($popup->target_devices) ? array_values($popup->target_devices) : [];

        $ctaLabel = $popup->cta_label
            ?: ($popup->type === 'survey' ? __('popup.survey_cta_default') : __('popup.cta_default'));

        // اسمح فقط بروابط آمنة (http/https أو مسار داخلي يبدأ بـ /) — يمنع تنفيذ javascript:
        // من محرّر محتوى أقل ثقة. نفس حارس slider.blade.php و block.blade.php (الدستور 4.2).
        $ctaUrl = (filled($popup->cta_url) && \Illuminate\Support\Str::startsWith($popup->cta_url, ['http://', 'https://', '/']))
            ? $popup->cta_url
            : null;
    @endphp

    <template x-teleport="body">
        <div
            x-data="{
                shown: false,
                visible: false,
                key: @js($dismissKey),
                frequency: @js($popup->display_frequency),
                trigger: @js($popup->display_trigger),
                delay: @js((int) ($popup->delay_seconds ?? 0)),
                devices: @js($devices),
                store() {
                    return this.frequency === 'once' ? window.localStorage : window.sessionStorage;
                },
                dismissed() {
                    if (this.frequency === 'always') return false;
                    try { return this.store().getItem(this.key) === '1'; } catch (e) { return false; }
                },
                deviceOk() {
                    if (! this.devices.length) return true;
                    const w = window.innerWidth;
                    const kind = w < 768 ? 'mobile' : (w < 1024 ? 'tablet' : 'desktop');
                    return this.devices.includes(kind);
                },
                reveal() {
                    if (this.shown || this.dismissed() || ! this.deviceOk()) return;
                    this.shown = true;
                    this.visible = true;
                },
                close() {
                    this.visible = false;
                    if (this.frequency === 'always') return;
                    try { this.store().setItem(this.key, '1'); } catch (e) {}
                },
                trapTab(e) {
                    // حبس التركيز داخل النافذة (role=dialog aria-modal): Tab/Shift+Tab
                    // يدوران بين أول وآخر عنصر قابل للتركيز فقط، فلا يتسرّب للخلفية.
                    if (! this.visible || ! this.$refs.dialog) return;
                    const items = Array.from(this.$refs.dialog.querySelectorAll(
                        'a[href], button, input, textarea, select, [tabindex]'
                    )).filter((el) => ! el.disabled && el.tabIndex >= 0
                        && (el.offsetParent !== null || el === document.activeElement));
                    if (! items.length) { e.preventDefault(); return; }
                    const first = items[0];
                    const last = items[items.length - 1];
                    const active = document.activeElement;
                    if (e.shiftKey) {
                        if (active === first || ! this.$refs.dialog.contains(active)) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else if (active === last || ! this.$refs.dialog.contains(active)) {
                        e.preventDefault();
                        first.focus();
                    }
                },
                init() {
                    if (this.dismissed() || ! this.deviceOk()) return;
                    if (this.trigger === 'after_delay') {
                        window.setTimeout(() => this.reveal(), Math.max(0, this.delay) * 1000);
                    } else if (this.trigger === 'on_scroll') {
                        const onScroll = () => {
                            if (window.scrollY > window.innerHeight * 0.4) {
                                window.removeEventListener('scroll', onScroll);
                                this.reveal();
                            }
                        };
                        window.addEventListener('scroll', onScroll, { passive: true });
                    } else if (this.trigger === 'on_exit') {
                        const onLeave = (e) => {
                            if (e.clientY <= 0) {
                                document.removeEventListener('mouseleave', onLeave);
                                this.reveal();
                            }
                        };
                        document.addEventListener('mouseleave', onLeave);
                        // الموبايل بلا نيّة مغادرة بالفأرة: بديل بعد مهلة معقولة.
                        window.setTimeout(() => this.reveal(), 15000);
                    } else {
                        this.$nextTick(() => this.reveal());
                    }
                }
            }"
            x-show="visible"
            x-cloak
            @keydown.escape.window="close()"
            @keydown.tab="trapTab($event)"
            x-effect="visible && $nextTick(() => $refs.closeBtn && $refs.closeBtn.focus())"
            class="qpopup-backdrop"
            x-transition.opacity
            @click.self="close()"
        >
            <div
                class="qpopup"
                role="dialog"
                aria-modal="true"
                aria-labelledby="qpopup-title"
                x-ref="dialog"
                x-transition
            >
                <button
                    type="button"
                    class="qpopup-close"
                    x-ref="closeBtn"
                    @click="close()"
                    aria-label="{{ __('popup.close') }}"
                >&times;</button>

                @if ($imageUrl)
                    <img
                        class="qpopup-media"
                        src="{{ $imageUrl }}"
                        alt="{{ $popup->title }}"
                        loading="lazy"
                    >
                @endif

                <div class="qpopup-body">
                    <h2 id="qpopup-title" class="qpopup-title">{{ $popup->title }}</h2>

                    @if (filled($popup->content))
                        <p class="qpopup-text">{{ $popup->content }}</p>
                    @endif

                    @if ($ctaUrl)
                        <div class="qpopup-cta">
                            <a
                                class="btn btn-primary btn-block"
                                href="{{ $ctaUrl }}"
                                target="_blank"
                                rel="noopener"
                                @click="close()"
                            >{{ $ctaLabel }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>

    {{-- أنماط النافذة: من رموز نظام التصميم (CSS variables) لدعم الثيمين وRTL. --}}
    <style>
        .qpopup-backdrop {
            position: fixed; inset: 0; z-index: 90;
            display: grid; place-items: center; padding: 18px;
            background: rgba(0, 0, 0, .55);
        }
        .qpopup {
            position: relative;
            width: min(92vw, 440px);
            max-height: 88vh; overflow-y: auto;
            background: var(--surface); color: var(--ink);
            border: 1px solid var(--line);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-l);
        }
        .qpopup-media {
            display: block; width: 100%;
            aspect-ratio: 16 / 9; object-fit: cover;
        }
        .qpopup-body { padding: clamp(20px, 4vw, 30px); text-align: center; }
        .qpopup-title {
            font-weight: 900; line-height: 1.2; color: var(--ink);
            font-size: clamp(20px, 3.4vw, 26px); text-wrap: balance;
        }
        .qpopup-text {
            margin-top: 12px; color: var(--ink-soft);
            font-size: 15px; line-height: 1.75; white-space: pre-line;
        }
        .qpopup-cta { margin-top: 20px; }
        .qpopup-close {
            position: absolute; inset-inline-end: 12px; top: 12px; z-index: 2;
            width: 40px; height: 40px; border-radius: 12px;
            border: 1px solid var(--line); background: var(--surface); color: var(--ink);
            display: grid; place-items: center;
            font-size: 22px; line-height: 1; cursor: pointer;
            transition: background .18s, color .18s;
        }
        .qpopup-close:hover { background: var(--purple-soft); color: var(--purple); }

        /* احترام تفضيل تقليل الحركة: !important يتغلّب على أنماط x-transition السطرية. */
        @media (prefers-reduced-motion: reduce) {
            .qpopup-backdrop, .qpopup { transition: none !important; animation: none !important; }
        }
    </style>
@endif
