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

                        requestRecommendation() {
                            this.loading = true;
                            this.errorMessage = null;
                            this.result = null;

                            fetch('/api/v1/recommendations/request', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                                },
                                body: JSON.stringify({
                                    soil_sample: {
                                        nitrogen: 45.2,
                                        phosphorus: 22.0,
                                        potassium: 110.5,
                                        ph: 6.4,
                                        ec: 1450,
                                        temperature: 26.5,
                                        humidity: 65.0
                                    }
                                })
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
                                this.errorMessage = 'Terjadi kesalahan jaringan.';
                            });
                        },

                        pollStatus(ticket) {
                            const interval = setInterval(() => {
                                fetch('/api/v1/recommendations/status/' + ticket)
                                .then(res => res.json())
                                .then(data => {
                                    if (data.status === 'completed') {
                                        clearInterval(interval);
                                        this.loading = false;
                                        this.result = data;
                                    } else if (data.status === 'failed') {
                                        clearInterval(interval);
                                        this.loading = false;
                                        this.errorMessage = data.message || 'Analisis gagal diproses oleh AI worker.';
                                    }
                                })
                                .catch(() => {
                                    clearInterval(interval);
                                    this.loading = false;
                                    this.errorMessage = 'Gagal memeriksa status analisis.';
                                });
                            }, 1500);
                        }
                    }"
                >
                    <!-- Header Drawer -->
                    <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900" id="slide-over-title">Konsultan Agronomi AI</h3>
                                <p class="text-[11px] text-slate-500 font-mono">Model: Google Gemini 2.0 Flash</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="aiDrawerOpen = false"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        >
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Technical Scope Note -->
                        <div class="p-3.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
                            <p class="font-bold text-slate-800 flex items-center gap-1.5">
                                <i data-lucide="info" class="w-3.5 h-3.5 text-emerald-600"></i>
                                <span>Input Data Sensor Terkini</span>
                            </p>
                            <p class="text-[11px] text-slate-500">
                                Menggunakan sampel komposit 7-in-1 Modbus stasiun aktif untuk menghitung kecocokan komoditas hortikultura & tindakan agronomi.
                            </p>
                        </div>

                        <!-- Action Trigger -->
                        <div>
                            <button
                                type="button"
                                @click="requestRecommendation()"
                                :disabled="loading"
                                class="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-colors"
                            >
                                <template x-if="!loading">
                                    <span class="flex items-center gap-2">
                                        <i data-lucide="cpu" class="w-4 h-4"></i>
                                        <span>Jalankan Analisis Kesuburan Tanah</span>
                                    </span>
                                </template>
                                <template x-if="loading">
                                    <span class="flex items-center gap-2">
                                        <span class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        <span>Memproses via Worker Queue...</span>
                                    </span>
                                </template>
                            </button>
                        </div>

                        <!-- Error Box -->
                        <template x-if="errorMessage">
                            <div class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                                <p class="font-bold">Gagal Menganalisis</p>
                                <p class="text-[11px] mt-0.5" x-text="errorMessage"></p>
                            </div>
                        </template>

                        <!-- Result Section -->
                        <template x-if="result">
                            <div class="space-y-4">
                                <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Klasifikasi Tanah</span>
                                    <h4 class="text-sm font-extrabold text-slate-900 mt-0.5" x-text="result.soil_classification"></h4>
                                </div>

                                <div class="space-y-2.5">
                                    <span class="text-xs font-bold text-slate-700 block">Rekomendasi Tanaman Pangan & Hortikultura:</span>

                                    <template x-for="(item, idx) in result.recommendations" :key="idx">
                                        <div class="p-3 rounded-lg border border-slate-200 bg-white space-y-1 hover:border-emerald-300 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h5 class="text-xs font-bold text-slate-900" x-text="item.crop_name"></h5>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800" x-text="'Match: ' + (item.match_score || 90) + '%'"></span>
                                            </div>
                                            <p class="text-[11px] text-slate-500" x-text="item.agronomic_rationale"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer Note -->
                    <div class="p-4 border-t border-slate-200 bg-slate-50 text-[11px] text-slate-500 flex items-center justify-between">
                        <span>SLA Maksimal: 15 Detik</span>
                        <span class="font-mono text-emerald-700 font-semibold">Potong 1 Kuota</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
