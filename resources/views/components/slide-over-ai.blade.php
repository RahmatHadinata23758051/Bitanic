<div
    x-cloak
    x-show="aiDrawerOpen"
    class="relative z-50"
    aria-labelledby="slide-over-title"
    role="dialog"
    aria-modal="true"
>
    <!-- Backdrop Blur Minimalis -->
    <div
        x-show="aiDrawerOpen"
        x-transition:enter="ease-in-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in-out duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="aiDrawerOpen = false"
        class="fixed inset-0 bg-slate-900/30 transition-opacity"
    ></div>

    <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div
                    x-show="aiDrawerOpen"
                    x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="pointer-events-auto w-screen max-w-md bg-white shadow-xl border-l border-slate-200 flex flex-col justify-between"
                    x-data="{
                        loading: false,
                        ticketId: null,
                        result: null,
                        errorMessage: null,
                        pollTimer: null,

                        requestRecommendation() {
                            this.loading = true;
                            this.errorMessage = null;
                            this.result = null;

                            fetch('/api/v1/recommendations/request', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                                },
                                body: JSON.stringify({})
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.ticket_id) {
                                    this.ticketId = data.ticket_id;
                                    this.pollStatus(data.ticket_id);
                                } else {
                                    this.loading = false;
                                    this.errorMessage = data.message || 'Gagal memulai analisis AI.';
                                }
                            })
                            .catch(err => {
                                this.loading = false;
                                this.errorMessage = 'Terjadi gangguan jaringan saat menghubungi worker AI.';
                            });
                        },

                        pollStatus(ticket) {
                            if (this.pollTimer) clearInterval(this.pollTimer);

                            this.pollTimer = setInterval(() => {
                                fetch('/api/v1/recommendations/status/' + ticket, {
                                    headers: { 'Accept': 'application/json' }
                                })
                                .then(res => res.json())
                                .then(resJson => {
                                    const payload = resJson.data || resJson;
                                    if (payload.status === 'completed') {
                                        clearInterval(this.pollTimer);
                                        this.loading = false;
                                        this.result = payload;
                                    } else if (payload.status === 'failed') {
                                        clearInterval(this.pollTimer);
                                        this.loading = false;
                                        this.errorMessage = payload.error || payload.message || 'Analisis gagal diproses oleh worker queue.';
                                    }
                                })
                                .catch(() => {
                                    clearInterval(this.pollTimer);
                                    this.loading = false;
                                    this.errorMessage = 'Gagal memeriksa status tiket antrean.';
                                });
                            }, 1500);
                        }
                    }"
                >
                    <!-- Header Drawer -->
                    <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700">
                                <i data-lucide="cpu" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900" id="slide-over-title">Konsultan Agronomi AI Gemini</h3>
                                <p class="text-[11px] text-slate-500 font-mono">Engine: Google Gemini 2.0 Flash</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="aiDrawerOpen = false"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                        >
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-5">
                        <!-- Technical Scope Note -->
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                    <i data-lucide="info" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    <span>Input Telemetri Real-time</span>
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800">
                                    7-in-1 Modbus
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                AI mengevaluasi kesesuaian hara (N-P-K), pH, EC salinitas, lengas, dan suhu tanah stasiun aktif terhadap kebutuhan agronomis tanaman.
                            </p>
                        </div>

                        <!-- Action Trigger Button -->
                        <div>
                            <button
                                type="button"
                                @click="requestRecommendation()"
                                :disabled="loading"
                                class="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-colors"
                            >
                                <template x-if="!loading">
                                    <span class="flex items-center gap-2">
                                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                                        <span>Jalankan Analisis Kesuburan Tanah</span>
                                    </span>
                                </template>
                                <template x-if="loading">
                                    <span class="flex items-center gap-2">
                                        <span class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        <span>Memproses via Background Queue...</span>
                                    </span>
                                </template>
                            </button>
                        </div>

                        <!-- Ticket ID Bar (saat loading) -->
                        <template x-if="loading && ticketId">
                            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-mono text-slate-600 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Status Tiket:</span>
                                    <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold uppercase text-[10px]">Processing</span>
                                </div>
                                <div class="truncate text-slate-800 font-semibold" x-text="'ID: ' + ticketId"></div>
                            </div>
                        </template>

                        <!-- Skeleton Loader Elegan -->
                        <template x-if="loading">
                            <div class="space-y-3 animate-pulse pt-2">
                                <div class="h-16 bg-slate-100 rounded-xl"></div>
                                <div class="h-20 bg-slate-100 rounded-xl"></div>
                                <div class="h-20 bg-slate-100 rounded-xl"></div>
                            </div>
                        </template>

                        <!-- Error Message Box -->
                        <template x-if="errorMessage">
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
                                <div class="flex items-center gap-1.5 font-bold">
                                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                                    <span>Gagal Mendapatkan Rekomendasi</span>
                                </div>
                                <p class="text-[11px] text-rose-700 leading-relaxed" x-text="errorMessage"></p>
                            </div>
                        </template>

                        <!-- Result Section -->
                        <template x-if="result">
                            <div class="space-y-4">
                                <!-- Soil Classification Card -->
                                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Klasifikasi Tanah</span>
                                    <h4 class="text-sm font-extrabold text-slate-900" x-text="result.soil_classification ? (result.soil_classification.kategori || result.soil_classification) : 'Tanah Pertanian Presisi'"></h4>
                                    <p class="text-[11px] text-slate-600 leading-relaxed" x-text="result.soil_classification ? result.soil_classification.deskripsi : ''"></p>
                                </div>

                                <!-- Recommended Crops List -->
                                <div class="space-y-2.5">
                                    <span class="text-xs font-bold text-slate-700 block uppercase tracking-wider">
                                        Rekomendasi Tanaman Pangan &amp; Hortikultura:
                                    </span>

                                    <template x-for="(item, idx) in (result.recommendations || [])" :key="idx">
                                        <div class="p-3.5 rounded-xl border border-slate-200 bg-white space-y-1.5 hover:border-emerald-300 transition-colors shadow-2xs">
                                            <div class="flex items-center justify-between gap-2">
                                                <h5 class="text-xs font-bold text-slate-900" x-text="item.nama || item.crop_name"></h5>
                                                <span
                                                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700"
                                                    x-text="item.kategori || 'Tanaman'"
                                                ></span>
                                            </div>
                                            <p class="text-[11px] text-slate-600 leading-relaxed" x-text="item.alasan || item.agronomic_rationale"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer Info -->
                    <div class="p-4 border-t border-slate-200 bg-slate-50 text-[11px] text-slate-500 flex items-center justify-between font-mono">
                        <span class="flex items-center gap-1">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Timeout SLA: 15s</span>
                        </span>
                        <span class="text-emerald-700 font-bold">Biaya: 1 Kuota AI</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
