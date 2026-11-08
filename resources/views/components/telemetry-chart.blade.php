@props([
    'initialData' => [],
    'chartId' => 'telemetryTrendChart'
])

<div
    class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4"
    x-data="{
        initialData: @js($initialData),
        activeTab: 'ph', // 'ph' | 'moisture' | 'ec' | 'temperature' | 'npk'
        activeRange: '24h',
        chartInstance: null,

        parametersMeta: {
            ph: { label: 'pH', unit: 'pH', min: 0, max: 14, idealMin: 6.0, idealMax: 7.2, color: '#16a34a' },
            moisture: { label: 'Lengas', unit: '%', min: 0, max: 100, idealMin: 45, idealMax: 65, color: '#0284c7' },
            ec: { label: 'EC', unit: 'µS/cm', min: 0, max: 3000, idealMin: 1000, idealMax: 2200, color: '#d97706' },
            temperature: { label: 'Suhu', unit: '°C', min: 10, max: 45, idealMin: 22, idealMax: 29, color: '#ea580c' },
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
            if (this.activeTab === 'npk') {
                const n = this.getParamData('nitrogen');
                const p = this.getParamData('phosphorus');
                const k = this.getParamData('potassium');
                return [...n, ...p, ...k].some(pt => pt && pt.y !== null);
            }
            const data = this.getParamData(this.activeTab);
            return data.length > 0 && data.some(pt => pt && pt.y !== null);
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

        getFootnote() {
            if (this.activeTab === 'npk') {
                return 'Satuan mg/kg. Evaluasi dinamika ketersediaan hara makro primer N-P-K.';
            }
            const meta = this.parametersMeta[this.activeTab];
            if (!meta) return '';
            return `Satuan ${meta.unit}. Rentang ideal ${meta.idealMin}–${meta.idealMax} ${meta.unit}.`;
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

        switchTab(tabKey) {
            this.activeTab = tabKey;
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

            if (this.activeTab === 'npk') {
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
            } else {
                const meta = this.parametersMeta[this.activeTab] || { label: this.activeTab, unit: '', min: 0, max: 100, idealMin: 0, idealMax: 100, color: '#16a34a' };
                const points = this.getParamData(this.activeTab);

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
            }

            return {
                chart: {
                    type: 'area',
                    height: 260,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
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
                colors: this.activeTab === 'npk'
                    ? ['#059669', '#ca8a04', '#7c3aed']
                    : [this.parametersMeta[this.activeTab]?.color || '#16a34a'],
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
    <!-- Header: Title & Time Range -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
            <div class="flex items-center gap-2">
                <i data-lucide="activity" class="w-4 h-4 text-emerald-600"></i>
                <h3 class="text-sm font-bold text-slate-800">Tren telemetri lahan</h3>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Inspeksi dinamika unsur kimiawi dan fisik tanah.</p>
        </div>

        <!-- Time Range Selector -->
        <div class="flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-mono">
            <button
                type="button"
                @click="switchRange('1h')"
                :class="activeRange === '1h' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                class="px-2.5 py-0.5 rounded-md text-[11px] transition-colors"
            >1J</button>
            <button
                type="button"
                @click="switchRange('6h')"
                :class="activeRange === '6h' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                class="px-2.5 py-0.5 rounded-md text-[11px] transition-colors"
            >6J</button>
            <button
                type="button"
                @click="switchRange('24h')"
                :class="activeRange === '24h' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                class="px-2.5 py-0.5 rounded-md text-[11px] transition-colors"
            >24J</button>
            <button
                type="button"
                @click="switchRange('7d')"
                :class="activeRange === '7d' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                class="px-2.5 py-0.5 rounded-md text-[11px] transition-colors"
            >7H</button>
        </div>
    </div>

    <!-- Parameter Tabs: [pH] [Lengas] [EC] [Suhu] [N-P-K] -->
    <div class="flex items-center gap-1.5 flex-wrap">
        <button
            type="button"
            @click="switchTab('ph')"
            :class="activeTab === 'ph' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium'"
            class="px-3 py-1 rounded-lg text-xs transition-colors"
        >
            pH
        </button>
        <button
            type="button"
            @click="switchTab('moisture')"
            :class="activeTab === 'moisture' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium'"
            class="px-3 py-1 rounded-lg text-xs transition-colors"
        >
            Lengas
        </button>
        <button
            type="button"
            @click="switchTab('ec')"
            :class="activeTab === 'ec' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium'"
            class="px-3 py-1 rounded-lg text-xs transition-colors"
        >
            EC
        </button>
        <button
            type="button"
            @click="switchTab('temperature')"
            :class="activeTab === 'temperature' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium'"
            class="px-3 py-1 rounded-lg text-xs transition-colors"
        >
            Suhu
        </button>
        <button
            type="button"
            @click="switchTab('npk')"
            :class="activeTab === 'npk' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium'"
            class="px-3 py-1 rounded-lg text-xs transition-colors"
        >
            N-P-K
        </button>
    </div>

    <!-- Chart Canvas or Honest Empty State -->
    <div>
        <div x-show="!hasData()" x-cloak class="flex flex-col items-center justify-center h-[260px] bg-slate-50/50 rounded-xl border border-dashed border-slate-200 text-center p-6 space-y-1.5">
            <div class="p-3 text-center bg-white rounded-lg border border-slate-200/90 shadow-2xs space-y-0.5">
                <p class="text-xs font-bold text-slate-800">Belum ada rekaman telemetri</p>
                <p class="text-[11px] text-slate-400">Garis tren muncul setelah kit mengirim data.</p>
            </div>
        </div>

        <div x-show="hasData()" id="{{ $chartId }}" class="w-full h-[260px]"></div>
    </div>

    <!-- Footnote -->
    <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400 font-sans" x-text="getFootnote()"></div>
</div>
