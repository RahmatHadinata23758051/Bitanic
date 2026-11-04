@props([
    'initialData' => [],
    'chartId' => 'telemetryTrendChart'
])

<div
    class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4"
    x-data="{
        activeMode: 'single', // 'single' | 'npk' | 'climate'
        activeParam: 'ph',
        activeRange: '24h',
        chartInstance: null,

        parametersMeta: {
            ph: { label: 'pH Tanah', unit: 'pH', min: 0, max: 14, idealMin: 6.0, idealMax: 7.2, color: '#16a34a' },
            moisture: { label: 'Kadar Lengas (VWC)', unit: '%', min: 0, max: 100, idealMin: 45, idealMax: 65, color: '#0284c7' },
            ec: { label: 'EC Nutrisi', unit: 'µS/cm', min: 0, max: 3000, idealMin: 1200, idealMax: 2000, color: '#d97706' },
            nitrogen: { label: 'Nitrogen (N)', unit: 'mg/kg', min: 0, max: 200, idealMin: 40, idealMax: 80, color: '#059669' },
            phosphorus: { label: 'Fosfor (P)', unit: 'mg/kg', min: 0, max: 100, idealMin: 20, idealMax: 50, color: '#ca8a04' },
            potassium: { label: 'Kalium (K)', unit: 'mg/kg', min: 0, max: 250, idealMin: 100, idealMax: 180, color: '#7c3aed' },
            temperature: { label: 'Suhu Tanah', unit: '°C', min: 10, max: 45, idealMin: 24, idealMax: 30, color: '#ea580c' },
        },

        initChart() {
            const el = document.getElementById('{{ $chartId }}');
            if (!el || typeof ApexCharts === 'undefined') return;

            const options = this.getChartOptions();
            this.chartInstance = new ApexCharts(el, options);
            this.chartInstance.render();
        },

        switchParam(paramKey) {
            this.activeMode = 'single';
            this.activeParam = paramKey;
            this.updateChart();
        },

        switchMode(modeKey) {
            this.activeMode = modeKey;
            this.updateChart();
        },

        switchRange(rangeKey) {
            this.activeRange = rangeKey;
            this.updateChart();
        },

        getSampleTimestamps() {
            const times = [];
            const now = new Date();
            for (let i = 11; i >= 0; i--) {
                const d = new Date(now.getTime() - i * 60 * 60 * 1000);
                times.push(d.getHours().toString().padStart(2, '0') + ':00');
            }
            return times;
        },

        getChartOptions() {
            const times = this.getSampleTimestamps();
            let series = [];
            let yaxis = {};
            let annotations = {};

            if (this.activeMode === 'single') {
                const meta = this.parametersMeta[this.activeParam];
                const mockVals = {
                    ph: [6.4, 6.5, 6.4, 6.6, 6.5, 6.7, 6.5, 6.4, 6.5, 6.6, 6.5, 6.5],
                    moisture: [48, 47, 45, 44, 42, 40, 58, 55, 52, 50, 48, 46],
                    ec: [1420, 1430, 1400, 1410, 1450, 1440, 1500, 1480, 1460, 1430, 1420, 1420],
                    nitrogen: [65, 66, 64, 65, 68, 67, 72, 70, 69, 68, 68, 68],
                    phosphorus: [32, 33, 33, 34, 35, 34, 36, 35, 35, 34, 34, 34],
                    potassium: [140, 142, 141, 145, 144, 146, 148, 145, 144, 145, 145, 145],
                    temperature: [24.5, 24.8, 25.2, 26.0, 27.5, 28.2, 27.8, 27.0, 26.2, 25.5, 25.0, 24.8]
                };

                series = [{
                    name: meta.label,
                    data: mockVals[this.activeParam] || [6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5]
                }];

                yaxis = {
                    title: { text: meta.unit, style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } },
                    labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } },
                    min: meta.min,
                    max: meta.max
                };

                annotations = {
                    yaxis: [{
                        y: meta.idealMin,
                        y2: meta.idealMax,
                        fillColor: '#22c55e',
                        opacity: 0.12,
                        label: {
                            text: 'Zona Ideal: ' + meta.idealMin + '-' + meta.idealMax + ' ' + meta.unit,
                            style: { color: '#15803d', background: '#dcfce7', fontSize: '10px', fontWeight: 600 }
                        }
                    }]
                };
            } else if (this.activeMode === 'npk') {
                series = [
                    { name: 'Nitrogen (N)', data: [65, 66, 64, 65, 68, 67, 72, 70, 69, 68, 68, 68] },
                    { name: 'Fosfor (P)', data: [32, 33, 33, 34, 35, 34, 36, 35, 35, 34, 34, 34] },
                    { name: 'Kalium (K)', data: [140, 142, 141, 145, 144, 146, 148, 145, 144, 145, 145, 145] }
                ];
                yaxis = {
                    title: { text: 'mg/kg', style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } },
                    labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } }
                };
            } else if (this.activeMode === 'climate') {
                series = [
                    { name: 'Kadar Lengas (%)', data: [48, 47, 45, 44, 42, 40, 58, 55, 52, 50, 48, 46] },
                    { name: 'Suhu Tanah (°C)', data: [24.5, 24.8, 25.2, 26.0, 27.5, 28.2, 27.8, 27.0, 26.2, 25.5, 25.0, 24.8] }
                ];
                yaxis = [
                    { title: { text: 'Lengas (%)', style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } }, labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } } },
                    { opposite: true, title: { text: 'Suhu (°C)', style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } }, labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } } }
                ];
            }

            return {
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    zoom: { enabled: false },
                    animations: { speed: 400 }
                },
                series: series,
                xaxis: {
                    categories: times,
                    labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px', colors: '#64748b' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: yaxis,
                annotations: annotations,
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 95, 100] }
                },
                colors: this.activeMode === 'single' ? [this.parametersMeta[this.activeParam].color] : ['#059669', '#ca8a04', '#7c3aed'],
                dataLabels: { enabled: false },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 3,
                    padding: { left: 10, right: 10, top: 0, bottom: 0 }
                },
                tooltip: {
                    theme: 'light',
                    x: { show: true },
                    style: { fontSize: '11px', fontFamily: 'JetBrains Mono' }
                }
            };
        },

        updateChart() {
            if (!this.chartInstance) return;
            const options = this.getChartOptions();
            this.chartInstance.updateOptions(options, true, true);
        }
    }"
    x-init="initChart()"
>
    <!-- Header: Mode Switcher & Time Range Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
            <div class="flex items-center gap-2">
                <i data-lucide="activity" class="w-4 h-4 text-emerald-600"></i>
                <h4 class="text-xs font-bold text-slate-900">Tren Telemetri Real-Time</h4>
            </div>
            <p class="text-[10px] text-slate-500">Inspeksi Dinamika Unsur Kimiawi & Fisik Tanah</p>
        </div>

        <!-- Mode Presets & Time Range -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Mode Toggle: Presets vs Focus -->
            <div class="flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs">
                <button
                    type="button"
                    @click="switchMode('single')"
                    :class="activeMode === 'single' ? 'bg-white text-emerald-700 shadow-sm font-bold' : 'text-slate-600 font-medium'"
                    class="px-2.5 py-1 rounded-md text-[11px] transition-all"
                >
                    Fokus 1 Parameter
                </button>
                <button
                    type="button"
                    @click="switchMode('npk')"
                    :class="activeMode === 'npk' ? 'bg-white text-emerald-700 shadow-sm font-bold' : 'text-slate-600 font-medium'"
                    class="px-2.5 py-1 rounded-md text-[11px] transition-all"
                >
                    Preset N-P-K
                </button>
                <button
                    type="button"
                    @click="switchMode('climate')"
                    :class="activeMode === 'climate' ? 'bg-white text-emerald-700 shadow-sm font-bold' : 'text-slate-600 font-medium'"
                    class="px-2.5 py-1 rounded-md text-[11px] transition-all"
                >
                    Lengas vs Suhu
                </button>
            </div>

            <!-- Time Range Filter -->
            <div class="flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-mono">
                <button
                    type="button"
                    @click="switchRange('1h')"
                    :class="activeRange === '1h' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500'"
                    class="px-2 py-0.5 rounded text-[10px] transition-all"
                >1J</button>
                <button
                    type="button"
                    @click="switchRange('6h')"
                    :class="activeRange === '6h' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500'"
                    class="px-2 py-0.5 rounded text-[10px] transition-all"
                >6J</button>
                <button
                    type="button"
                    @click="switchRange('24h')"
                    :class="activeRange === '24h' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500'"
                    class="px-2 py-0.5 rounded text-[10px] transition-all"
                >24J</button>
                <button
                    type="button"
                    @click="switchRange('7d')"
                    :class="activeRange === '7d' ? 'bg-white text-slate-900 shadow-sm font-bold' : 'text-slate-500'"
                    class="px-2 py-0.5 rounded text-[10px] transition-all"
                >7H</button>
            </div>
        </div>
    </div>

    <!-- Parameter Filter Chips (Hanya muncul jika Mode Single) -->
    <template x-if="activeMode === 'single'">
        <div class="flex items-center gap-1.5 flex-wrap">
            <template x-for="(meta, key) in parametersMeta" :key="key">
                <button
                    type="button"
                    @click="switchParam(key)"
                    :class="activeParam === key ? 'bg-emerald-600 text-white font-bold border-emerald-600' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-slate-200 font-medium'"
                    class="px-2.5 py-1 rounded-md text-[11px] border transition-colors flex items-center gap-1.5"
                >
                    <span x-text="meta.label"></span>
                    <span
                        :class="activeParam === key ? 'text-emerald-100' : 'text-slate-400'"
                        class="text-[9px] font-mono"
                        x-text="'(' + meta.unit + ')'"
                    ></span>
                </button>
            </template>
        </div>
    </template>

    <!-- ApexChart Canvas Container -->
    <div id="{{ $chartId }}" class="w-full h-[280px]"></div>
</div>
