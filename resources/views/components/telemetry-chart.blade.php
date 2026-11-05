@props([
    'initialData' => [],
    'chartId' => 'telemetryTrendChart'
])

<div
    class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4"
    x-data="{
        initialData: @js($initialData),
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

        getParamData(key) {
            if (!this.initialData || typeof this.initialData !== 'object') return [];
            if (this.initialData[key] && Array.isArray(this.initialData[key].values)) {
                return this.initialData[key].values;
            }
            if (key === 'moisture' && this.initialData['humidity'] && Array.isArray(this.initialData['humidity'].values)) {
                return this.initialData['humidity'].values;
            }
            if (key === 'humidity' && this.initialData['moisture'] && Array.isArray(this.initialData['moisture'].values)) {
                return this.initialData['moisture'].values;
            }
            return [];
        },

        hasData() {
            if (this.activeMode === 'single') {
                const data = this.getParamData(this.activeParam);
                return data.length > 0 && data.some(pt => pt && pt.y !== null);
            }
            if (this.activeMode === 'npk') {
                const n = this.getParamData('nitrogen');
                const p = this.getParamData('phosphorus');
                const k = this.getParamData('potassium');
                return [...n, ...p, ...k].some(pt => pt && pt.y !== null);
            }
            if (this.activeMode === 'climate') {
                const m = this.getParamData('moisture');
                const t = this.getParamData('temperature');
                return [...m, ...t].some(pt => pt && pt.y !== null);
            }
            return false;
        },

        formatTimestamp(ts) {
            if (!ts) return '';
            try {
                const d = new Date(ts);
                if (isNaN(d.getTime())) return ts;
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return ts;
            }
        },

        initChart() {
            const el = document.getElementById('{{ $chartId }}');
            if (!el || typeof ApexCharts === 'undefined') return;

            if (!this.hasData()) {
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
                return;
            }

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

        getChartOptions() {
            let series = [];
            let categories = [];
            let yaxis = {};
            let annotations = {};

            if (this.activeMode === 'single') {
                const meta = this.parametersMeta[this.activeParam] || { label: this.activeParam, unit: '', min: 0, max: 100, idealMin: 0, idealMax: 100, color: '#16a34a' };
                const points = this.getParamData(this.activeParam);

                categories = points.map(pt => this.formatTimestamp(pt.x));
                series = [{
                    name: meta.label,
                    data: points.map(pt => pt.y)
                }];

                yaxis = {
                    title: { text: meta.unit, style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } },
                    labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } },
                    min: meta.min,
                    max: meta.max
                };

                if (meta.idealMin !== undefined && meta.idealMax !== undefined) {
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
                }
            } else if (this.activeMode === 'npk') {
                const nPoints = this.getParamData('nitrogen');
                const pPoints = this.getParamData('phosphorus');
                const kPoints = this.getParamData('potassium');

                const refPoints = nPoints.length > 0 ? nPoints : (pPoints.length > 0 ? pPoints : kPoints);
                categories = refPoints.map(pt => this.formatTimestamp(pt.x));

                series = [
                    { name: 'Nitrogen (N)', data: nPoints.map(pt => pt.y) },
                    { name: 'Fosfor (P)', data: pPoints.map(pt => pt.y) },
                    { name: 'Kalium (K)', data: kPoints.map(pt => pt.y) }
                ];

                yaxis = {
                    title: { text: 'mg/kg', style: { fontSize: '11px', fontFamily: 'JetBrains Mono' } },
                    labels: { style: { fontFamily: 'JetBrains Mono', fontSize: '11px' } }
                };
            } else if (this.activeMode === 'climate') {
                const mPoints = this.getParamData('moisture');
                const tPoints = this.getParamData('temperature');

                const refPoints = mPoints.length > 0 ? mPoints : tPoints;
                categories = refPoints.map(pt => this.formatTimestamp(pt.x));

                series = [
                    { name: 'Kadar Lengas (%)', data: mPoints.map(pt => pt.y) },
                    { name: 'Suhu Tanah (°C)', data: tPoints.map(pt => pt.y) }
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
                    categories: categories,
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
                colors: this.activeMode === 'single'
                    ? [this.parametersMeta[this.activeParam]?.color || '#16a34a']
                    : (this.activeMode === 'npk' ? ['#059669', '#ca8a04', '#7c3aed'] : ['#0284c7', '#ea580c']),
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
            const el = document.getElementById('{{ $chartId }}');
            if (!el || typeof ApexCharts === 'undefined') return;

            if (!this.hasData()) {
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
                return;
            }

            const options = this.getChartOptions();
            if (this.chartInstance) {
                this.chartInstance.updateOptions(options, true, true);
            } else {
                this.chartInstance = new ApexCharts(el, options);
                this.chartInstance.render();
            }
        }
    }"
    x-init="initChart()"
>
    <!-- Header: Mode Switcher & Time Range Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
            <div class="flex items-center gap-2">
                <i data-lucide="activity" class="w-4 h-4 text-emerald-600"></i>
                <h4 class="text-xs font-bold text-slate-900">Tren Telemetri Lahan</h4>
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

    <!-- True Honest Empty State when hasData() is false -->
    <div x-show="!hasData()" x-cloak class="flex flex-col items-center justify-center h-[280px] bg-slate-50/50 rounded-lg border border-dashed border-slate-200 text-center p-6 space-y-2">
        <i data-lucide="bar-chart-2" class="w-8 h-8 text-slate-300 mx-auto"></i>
        <p class="text-xs font-semibold text-slate-700">Belum Ada Rekaman Data Telemetri</p>
        <p class="text-[11px] text-slate-400 max-w-sm">Stasiun meja praktik ini belum memiliki riwayat telemetri dalam batas observasi stasiun.</p>
    </div>

    <!-- ApexChart Canvas Container -->
    <div x-show="hasData()" id="{{ $chartId }}" class="w-full h-[280px]"></div>
</div>
