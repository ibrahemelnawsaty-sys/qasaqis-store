{{-- مكوّن Alpine خفيف لمعاينة الكوبون عبر AJAX (coupon.apply). لا يثق بأي مبلغ من العميل:
     السلة تُقرأ وتُسعّر من الخادم، والعميل يرسل الكود فقط (بند 4.1). --}}
@once
    @push('scripts')
        <script>
            function couponBox(config) {
                return {
                    code: config.code || '',
                    applied: config.applied || null,
                    loading: false,
                    valid: null,
                    message: '',
                    fmt(v) { return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(parseFloat(v || 0)); },
                    async apply() {
                        this.code = (this.code || '').trim();
                        if (!this.code) { return; }
                        this.loading = true;
                        this.valid = null;
                        this.message = '';
                        try {
                            const res = await fetch(config.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                                body: JSON.stringify({ coupon: this.code }),
                            });
                            const data = await res.json();
                            this.valid = !!data.valid;
                            this.message = data.message || '';
                            this.applied = data.valid
                                ? {
                                    code: data.code,
                                    discount: data.discount,
                                    free_shipping: !!data.free_shipping,
                                    preview_total: data.preview_total,
                                    subtotal: data.subtotal,
                                }
                                : null;
                        } catch (e) {
                            this.valid = false;
                            this.message = config.errorText;
                            this.applied = null;
                        } finally {
                            this.loading = false;
                        }
                    },
                    clear() {
                        this.applied = null;
                        this.code = '';
                        this.valid = null;
                        this.message = '';
                    },
                };
            }
        </script>
    @endpush
@endonce
