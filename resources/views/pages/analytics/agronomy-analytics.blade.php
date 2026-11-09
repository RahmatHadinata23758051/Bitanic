@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header & Context Toolbar -->
    <div class="space-y-3">
        <div class="space-y-0.5">
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Analitik agronomi
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Analisis tren dan hubungan antarparameter tanah pada satu stasiun.
            </p>
        </div>

        <!-- Context Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Stasiun Selector -->
                <form method="GET" action="{{ url('/analytics') }}" class="flex items-center gap-2">
                    @if(!empty($selectedSchoolId))
                        <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                    @endif
                    <input type="hidden" name="range" value="{{ $selectedRange }}">
                    <label for="device_id" class="text-xs font-semibold text-slate-700">Stasiun</label>
                    <select
                        id="device_id"
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer min-w-[220px]"
                    >
                        @forelse($devices as $dev)
                            <option value="{{ $dev->id }}" {{ ($selectedDevice->id ?? null) === $dev->id ? 'selected' : '' }}>
                                {{ $dev->code }} — {{ $dev->bench_location ?? $dev->name }}
                            </option>
                        @empty
                            <option value="">Tidak ada perangkat aktif</option>
                        @endforelse
                    </select>
                </form>

                <!-- Valid readings count badge -->
                <div class="flex items-center gap-1.5 text-xs text-slate-700">
                    <span class="w-2 h-2 rounded-full {{ $readingCount > 0 ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    <span class="font-tabular font-bold text-slate-900">{{ $readingCount }}</span>
                    <span>data telemetri valid</span>
                </div>

                <!-- Latest transmission timestamp -->
                @if($latestTransmissionTime)
                    <span class="text-xs text-slate-400">
                        Transmisi terakhir {{ $latestTransmissionTime }}
                    </span>
                @else
                    <span class="text-xs text-slate-400">
                        Belum ada transmisi data
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <!-- Superuser School Selector -->
                <form method="GET" action="{{ url('/analytics') }}" class="flex items-center gap-1.5">
                    <input type="hidden" name="range" value="{{ $selectedRange }}">
                    @if(!empty($selectedDevice))
                        <input type="hidden" name="device_id" value="{{ $selectedDevice->id }}">
                    @endif
                    <label for="school_id" class="text-xs text-slate-500 font-medium">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-2 py-1 rounded-lg border border-slate-200 bg-white text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                    >
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $sch)
                            <option value="{{ $sch->id }}" {{ ((string) $selectedSchoolId === (string) $sch->id) ? 'selected' : '' }}>
                                {{ $sch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
                @endif

                <!-- Global Period Selector -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold text-slate-700 mr-1">Periode</span>
                    <div class="flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-mono">
                        @foreach(['1h' => '1J', '6h' => '6J', '24h' => '24J', '7d' => '7H'] as $rKey => $rLabel)
                            <a
                                href="{{ request()->fullUrlWithQuery(['range' => $rKey]) }}"
                                class="px-2.5 py-0.5 rounded-md text-[11px] transition-colors {{ $selectedRange === $rKey ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium' }}"
                            >
                                {{ $rLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: Tren Parameter -->
    <div
        class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4"
        x-data="{
            chartMode: 'all', // 'all' | 'single'
            singleTab: 'ph'
        }"
    >
        <!-- Card Header: Title & Switcher Pills -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <i data-lucide="line-chart" class="w-4 h-4 text-emerald-600"></i>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Tren parameter</h2>
                    <p class="text-xs text-slate-500">Dinamika historis telemetri per parameter agronomi.</p>
                </div>
            </div>

            <div class="flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs">
                <button
                    type="button"
                    @click="chartMode = 'all'"
                    :class="chartMode === 'all' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                    class="px-3 py-1 rounded-md text-xs transition-colors"
                >
                    Semua parameter
                </button>
                <button
                    type="button"
                    @click="chartMode = 'single'"
                    :class="chartMode === 'single' ? 'bg-[#0c3c26] text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                    class="px-3 py-1 rounded-md text-xs transition-colors"
                >
                    Satu parameter
                </button>
            </div>
        </div>

        @if($readingCount === 0)
            <!-- Honest Single Empty State -->
            <div class="flex flex-col items-center justify-center h-48 bg-slate-50/60 rounded-xl border border-dashed border-slate-200 text-center p-6 space-y-1.5">
                <div class="p-3 bg-white rounded-lg border border-slate-200/90 shadow-2xs space-y-0.5">
                    <p class="text-xs font-bold text-slate-800">Belum ada rekaman telemetri</p>
                    <p class="text-[11px] text-slate-400">Garis tren muncul setelah stasiun meja praktik mengirim data.</p>
                </div>
            </div>
        @else
            <!-- Mode A: Semua Parameter (4-Column Desktop Grid) -->
            <div x-show="chartMode === 'all'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($parameterPanels as $panel)
                    <div
                        @click="chartMode = 'single'; singleTab = '{{ $panel['key'] }}'"
                        class="p-3.5 rounded-xl border border-slate-200/90 bg-white hover:border-slate-300 transition-colors cursor-pointer space-y-2 group"
                        title="Klik untuk membuka grafik detail parameter ini"
                    >
                        <!-- Panel Header: Name and Unit -->
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-700 group-hover:text-emerald-800 transition-colors">{{ $panel['name'] }}</span>
                            <span class="text-[11px] font-mono text-slate-400">{{ $panel['unit'] }}</span>
                        </div>

                        <!-- Reading and Threshold Status -->
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="font-tabular text-2xl font-extrabold text-slate-900">
                                @if($panel['latest_val'] !== null)
                                    {{ number_format($panel['latest_val'], $panel['decimals'], ',', '.') }}
                                @else
                                    —
                                @endif
                            </span>

                            @if($panel['latest_val'] !== null)
                                @if(($panel['status']['status_code'] ?? '') === 'optimal')
                                    <span class="text-[10px] font-medium text-emerald-800 bg-emerald-50 border border-emerald-200/70 px-1.5 py-0.5 rounded">
                                        Dalam rentang
                                    </span>
                                @elseif(($panel['status']['status_code'] ?? '') === 'high')
                                    <span class="text-[10px] font-medium text-amber-800 bg-amber-50 border border-amber-200/70 px-1.5 py-0.5 rounded">
                                        Di atas rentang
                                    </span>
                                @elseif(($panel['status']['status_code'] ?? '') === 'low')
                                    <span class="text-[10px] font-medium text-amber-800 bg-amber-50 border border-amber-200/70 px-1.5 py-0.5 rounded">
                                        Di bawah rentang
                                    </span>
                                @endif
                            @else
                                <span class="text-[10px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                                    Belum ada data
                                </span>
                            @endif
                        </div>

                        <!-- Sparkline SVG Chart -->
                        <div class="relative w-full h-[62px] pt-1">
                            <svg viewBox="0 0 200 60" class="w-full h-full overflow-visible">
                                <!-- Subtle green reference band -->
                                <rect
                                    x="0"
                                    y="{{ $panel['band_y'] }}"
                                    width="200"
                                    height="{{ $panel['band_height'] }}"
                                    fill="#dcfce7"
                                    opacity="0.65"
                                />

                                <!-- Scale tick labels -->
                                <text x="2" y="9" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($panel['scale_max']) }}</text>
                                <text x="2" y="58" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($panel['scale_min']) }}</text>

                                <!-- Time labels -->
                                <text x="24" y="58" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ $periodAxisLabel }}</text>
                                <text x="198" y="58" text-anchor="end" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">Kini</text>

                                @if(!empty($panel['points']) && count($panel['points']) > 1)
                                    @php
                                        $pts = $panel['points'];
                                        $pathSegments = [];
                                        for ($i = 0; $i < count($pts) - 1; $i++) {
                                            $p1 = $pts[$i];
                                            $p2 = $pts[$i + 1];
                                            $color = ($p2['is_optimal']) ? '#059669' : '#ea580c';
                                            $pathSegments[] = [
                                                'x1' => $p1['x'],
                                                'y1' => $p1['y'],
                                                'x2' => $p2['x'],
                                                'y2' => $p2['y'],
                                                'color' => $color,
                                            ];
                                        }
                                        $lastPt = end($pts);
                                        $lastDotColor = $lastPt['is_optimal'] ? '#059669' : '#ea580c';
                                    @endphp

                                    @foreach($pathSegments as $seg)
                                        <line
                                            x1="{{ $seg['x1'] }}"
                                            y1="{{ $seg['y1'] }}"
                                            x2="{{ $seg['x2'] }}"
                                            y2="{{ $seg['y2'] }}"
                                            stroke="{{ $seg['color'] }}"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                        />
                                    @endforeach

                                    <!-- Endpoint dot -->
                                    <circle
                                        cx="{{ $lastPt['x'] }}"
                                        cy="{{ $lastPt['y'] }}"
                                        r="2.8"
                                        fill="{{ $lastDotColor }}"
                                        stroke="#ffffff"
                                        stroke-width="1"
                                    />
                                @endif
                            </svg>
                        </div>
                    </div>
                @endforeach

                <!-- 8th Panel: Cara Membaca (Reading Guide & Legend) -->
                <div class="p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/60 space-y-2.5 flex flex-col justify-between">
                    <div class="space-y-2">
                        <span class="text-xs font-bold text-slate-800">Cara membaca</span>

                        <div class="space-y-1.5 text-xs text-slate-600">
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-0.5 bg-emerald-600 inline-block rounded-full"></span>
                                <span class="text-[11px]">Nilai terukur</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-0.5 bg-amber-600 inline-block rounded-full"></span>
                                <span class="text-[11px]">Di luar rentang ideal</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3.5 h-3 bg-emerald-100 border border-emerald-300/80 inline-block rounded-xs"></span>
                                <span class="text-[11px]">Rentang ideal</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 leading-snug">
                        Skala tegak berbeda per grafik. Klik grafik untuk melihat detailnya.
                    </p>
                </div>
            </div>

            <!-- Mode B: Satu Parameter (Expanded Detailed Chart) -->
            <div x-show="chartMode === 'single'" x-cloak class="space-y-3">
                <x-telemetry-chart
                    :initial-data="$chartData"
                    :thresholds="$thresholds"
                    heading="Tren telemetri detail"
                    :show-ai-button="false"
                    :enable-range-navigation="true"
                    :active-range="$selectedRange"
                />
            </div>
        @endif
    </div>

    <!-- Section 2: Hubungan Antarparameter -->
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <div class="flex items-center gap-2">
                <i data-lucide="network" class="w-4 h-4 text-emerald-600"></i>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Hubungan antarparameter</h2>
                    <p class="text-xs text-slate-500">Korelasi Pearson r dan sebaran observasi empiris.</p>
                </div>
            </div>
            <span class="text-[11px] font-mono text-slate-400">
                Syarat analisis: minimal 10 observasi berpasangan yang valid
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($correlations as $corr)
                <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-3.5">
                    <!-- Pair Header -->
                    <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
                        <div class="space-y-0.5">
                            <h3 class="text-xs font-bold text-slate-800 tracking-wide">{{ $corr['label'] }}</h3>
                            <p class="text-[11px] text-slate-500 leading-snug">{{ $corr['interpretation'] }}</p>
                        </div>
                        <div class="shrink-0">
                            @if(($corr['status'] ?? '') === 'no_data')
                                <span class="text-xs font-medium text-slate-400">Belum ada data</span>
                            @elseif(($corr['status'] ?? '') === 'zero_variance')
                                <span class="text-xs font-semibold text-amber-700">Variansi nol</span>
                            @elseif(($corr['status'] ?? '') === 'insufficient_samples')
                                <span class="text-xs font-semibold text-amber-700">Data belum cukup</span>
                            @elseif(!empty($corr['is_sufficient']))
                                <span class="text-xs font-semibold text-emerald-700">Data cukup</span>
                            @else
                                <span class="text-xs font-semibold text-amber-700">Data belum cukup</span>
                            @endif
                        </div>
                    </div>

                    <!-- Observation Count Row -->
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-600 font-medium">Observasi berpasangan</span>
                        <div class="flex items-center gap-1 font-mono">
                            <span class="font-bold text-slate-900">{{ $corr['n'] }}</span>
                            <span class="text-slate-400">/ min. 10</span>
                        </div>
                    </div>

                    @if(($corr['status'] ?? '') === 'no_data')
                        <!-- Honest Empty State: No Data (Compact, no axes, no scatter, no regression, no Pearson gauge) -->
                        <div class="py-6 px-4 text-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 space-y-1">
                            <p class="text-xs font-semibold text-slate-700">Belum ada data berpasangan</p>
                            <p class="text-[11px] text-slate-500">Belum ada telemetri berpasangan pada stasiun ini untuk menghitung korelasi.</p>
                        </div>
                    @elseif(($corr['status'] ?? '') === 'zero_variance')
                        <!-- Honest Empty State: Zero Variance (No fake marker at zero) -->
                        <div class="py-6 px-4 text-center rounded-xl border border-amber-200 bg-amber-50/60 space-y-1">
                            <p class="text-xs font-semibold text-amber-900">Korelasi tidak dapat dihitung — nilai parameter konstan</p>
                            <p class="text-[11px] text-amber-800/80">Nilai parameter tidak memiliki variasi pada periode ini sehingga korelasi Pearson tidak dapat ditentukan.</p>
                        </div>
                    @elseif(($corr['status'] ?? '') === 'insufficient_samples')
                        <!-- Honest Empty State: Insufficient Samples (n < 10, no regression, no Pearson slider) -->
                        <div class="py-6 px-4 text-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 space-y-1">
                            <p class="text-xs font-semibold text-slate-700">Data belum cukup</p>
                            <p class="text-[11px] text-slate-500">Tercatat {{ $corr['n'] }} dari minimal 10 observasi berpasangan. Koefisien korelasi dan regresi belum dapat diinterpretasikan secara andal.</p>
                        </div>
                    @else
                        <!-- Sufficient Observations (n >= 10, variance > 0): Full Scatter Plot -->
                        <div class="space-y-1.5">
                            @php
                                $pts = $corr['points'];
                                $plotW = 340;
                                $plotH = 150;
                                $padL = 40;
                                $padR = 16;
                                $padT = 12;
                                $padB = 24;
                                $wArea = $plotW - $padL - $padR;
                                $hArea = $plotH - $padT - $padB;

                                $xMin = $corr['x_min'];
                                $xMax = $corr['x_max'];
                                $yMin = $corr['y_min'];
                                $yMax = $corr['y_max'];
                                $xRange = ($xMax - $xMin) > 0.001 ? ($xMax - $xMin) : 1.0;
                                $yRange = ($yMax - $yMin) > 0.001 ? ($yMax - $yMin) : 1.0;

                                // 2D Ideal Zone Rect Coordinates
                                $xIdealMinClamped = max($xMin, min($xMax, $corr['x_ideal_min']));
                                $xIdealMaxClamped = max($xMin, min($xMax, $corr['x_ideal_max']));
                                $yIdealMinClamped = max($yMin, min($yMax, $corr['y_ideal_min']));
                                $yIdealMaxClamped = max($yMin, min($yMax, $corr['y_ideal_max']));

                                $zoneX = $padL + (($xIdealMinClamped - $xMin) / $xRange) * $wArea;
                                $zoneW = max(4.0, (($xIdealMaxClamped - $xIdealMinClamped) / $xRange) * $wArea);
                                $zoneY = $padT + $hArea - (($yIdealMaxClamped - $yMin) / $yRange) * $hArea;
                                $zoneH = max(4.0, (($yIdealMaxClamped - $yIdealMinClamped) / $yRange) * $hArea);
                            @endphp

                            <svg viewBox="0 0 {{ $plotW }} {{ $plotH }}" class="w-full h-36 bg-slate-50/70 rounded-xl border border-slate-200/90 overflow-visible">
                                <!-- Reference Zone (both variables in ideal range) -->
                                <rect
                                    x="{{ $zoneX }}"
                                    y="{{ $zoneY }}"
                                    width="{{ $zoneW }}"
                                    height="{{ $zoneH }}"
                                    fill="#dcfce7"
                                    stroke="#86efac"
                                    stroke-width="1"
                                    opacity="0.65"
                                />

                                <!-- Axes lines -->
                                <line x1="{{ $padL }}" y1="{{ $padT }}" x2="{{ $padL }}" y2="{{ $padT + $hArea }}" stroke="#cbd5e1" stroke-width="1" />
                                <line x1="{{ $padL }}" y1="{{ $padT + $hArea }}" x2="{{ $padL + $wArea }}" y2="{{ $padT + $hArea }}" stroke="#cbd5e1" stroke-width="1" />

                                <!-- Y-axis ticks -->
                                <text x="{{ $padL - 4 }}" y="{{ $padT + 8 }}" text-anchor="end" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($yMax) }}</text>
                                <text x="{{ $padL - 4 }}" y="{{ $padT + ($hArea / 2) + 3 }}" text-anchor="end" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round(($yMin + $yMax) / 2) }}</text>
                                <text x="{{ $padL - 4 }}" y="{{ $padT + $hArea }}" text-anchor="end" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($yMin) }}</text>

                                <!-- X-axis ticks -->
                                <text x="{{ $padL }}" y="{{ $plotH - 6 }}" text-anchor="start" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($xMin, 1) }}</text>
                                <text x="{{ $padL + ($wArea / 2) }}" y="{{ $plotH - 6 }}" text-anchor="middle" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round(($xMin + $xMax) / 2, 1) }}</text>
                                <text x="{{ $padL + $wArea }}" y="{{ $plotH - 6 }}" text-anchor="end" font-size="7.5" fill="#94a3b8" font-family="JetBrains Mono">{{ round($xMax, 1) }}</text>

                                <!-- Regression Line (if valid) -->
                                @if(!empty($corr['regression']))
                                    @php
                                        $reg = $corr['regression'];
                                        $rx1 = $padL + (($reg['x1'] - $xMin) / $xRange) * $wArea;
                                        $ry1 = $padT + $hArea - (($reg['y1'] - $yMin) / $yRange) * $hArea;
                                        $rx2 = $padL + (($reg['x2'] - $xMin) / $xRange) * $wArea;
                                        $ry2 = $padT + $hArea - (($reg['y2'] - $yMin) / $yRange) * $hArea;
                                        $ry1 = max($padT, min($padT + $hArea, $ry1));
                                        $ry2 = max($padT, min($padT + $hArea, $ry2));
                                    @endphp
                                    <line
                                        x1="{{ $rx1 }}"
                                        y1="{{ $ry1 }}"
                                        x2="{{ $rx2 }}"
                                        y2="{{ $ry2 }}"
                                        stroke="#047857"
                                        stroke-width="1.6"
                                        stroke-dasharray="4,3"
                                    />
                                @endif

                                <!-- Data Points -->
                                @foreach($pts as $pt)
                                    @php
                                        $cx = $padL + (($pt['x'] - $xMin) / $xRange) * $wArea;
                                        $cy = $padT + $hArea - (($pt['y'] - $yMin) / $yRange) * $hArea;
                                        $fill = $pt['is_in_zone'] ? '#059669' : '#ea580c';
                                    @endphp
                                    <circle
                                        cx="{{ $cx }}"
                                        cy="{{ $cy }}"
                                        r="3"
                                        fill="{{ $fill }}"
                                        stroke="#ffffff"
                                        stroke-width="1"
                                    >
                                        <title>{{ $corr['x_name'] }}: {{ $pt['x'] }} {{ $corr['x_unit'] }} | {{ $corr['y_name'] }}: {{ $pt['y'] }} {{ $corr['y_unit'] }} ({{ $pt['is_in_zone'] ? 'Dalam zona ideal' : 'Di luar zona' }})</title>
                                    </circle>
                                @endforeach
                            </svg>

                            <!-- Sub-legend -->
                            <div class="flex items-center justify-between text-[10px] text-slate-500 font-sans flex-wrap gap-1 pt-0.5">
                                <span>Datar: {{ $corr['x_name'] }} ({{ $corr['x_unit'] }}). Tegak: {{ $corr['y_name'] }} ({{ $corr['y_unit'] }}).</span>
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span> Dalam zona ideal</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-600 inline-block"></span> Di luar zona</span>
                                    <span class="text-emerald-700 font-mono">--- Garis regresi</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Pearson r Gauge -->
                        <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-100">
                            <div>
                                <div class="text-[10px] font-mono text-slate-400">Koefisien r</div>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="font-tabular text-2xl font-extrabold text-slate-900">
                                        @if($corr['value'] !== null)
                                            {{ ($corr['value'] > 0 ? '+' : '') . number_format($corr['value'], 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </span>
                                </div>
                                <div class="text-xs font-semibold text-slate-700">
                                    {{ $corr['strength_label'] }}
                                </div>
                            </div>

                            <!-- Horizontal Slider Track: -1 to 0 to +1 -->
                            <div class="space-y-1 w-36 sm:w-48">
                                <div class="relative w-full h-1.5 bg-slate-200 rounded-full">
                                    <!-- Center tick -->
                                    <div class="absolute top-0 bottom-0 left-1/2 w-0.5 bg-slate-300"></div>
                                    <!-- Marker Dot (only rendered when r_percent is non-null) -->
                                    @if($corr['r_percent'] !== null)
                                        <div
                                            class="absolute -top-1 w-3.5 h-3.5 rounded-full bg-slate-900 border-2 border-white shadow-xs -ml-1.5 transition-all"
                                            style="left: {{ $corr['r_percent'] }}%;"
                                        ></div>
                                    @endif
                                </div>
                                <div class="flex justify-between text-[9px] font-mono text-slate-400">
                                    <span>-1</span>
                                    <span>0</span>
                                    <span>+1</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-[11px] text-slate-400 text-center leading-relaxed">
            Korelasi menunjukkan keterikatan pada data yang tercatat, bukan sebab-akibat langsung. Intervensi irigasi dan mikroklimat ikut memengaruhi nilainya.
        </p>
    </div>

    <!-- Section 3: Acuan Parameter Agronomi -->
    <div
        class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4"
        x-data="{ showDetail: false }"
    >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <i data-lucide="book-open-check" class="w-4 h-4 text-emerald-600"></i>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Acuan parameter agronomi</h2>
                    <p class="text-xs text-slate-500">Rentang acuan awal untuk kebutuhan praktikum. Kesesuaian dengan tanaman, media, dan metode pengukuran perlu diverifikasi.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($isCustomizedThresholds)
                    <span class="inline-flex items-center gap-1.5 text-xs text-indigo-700 font-semibold bg-indigo-50 border border-indigo-200/70 px-2.5 py-1 rounded-md">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Disesuaikan Sekolah
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-700 font-medium bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-md">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        Preset Acuan Aplikasi
                    </span>
                @endif
            </div>
        </div>

        <!-- 7-Row Matrix Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600 text-xs font-semibold bg-slate-50/80">
                        <th class="py-2.5 px-3">Parameter dan satuan</th>
                        <th class="py-2.5 px-3">Nilai terakhir</th>
                        <th class="py-2.5 px-3">Rentang acuan</th>
                        <th class="py-2.5 px-3">Indikasi rendah atau defisit</th>
                        <th class="py-2.5 px-3">Indikasi tinggi atau berlebih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @foreach($thresholds as $paramKey => $th)
                        @php
                            $panel = $parameterPanels[$paramKey] ?? null;
                            $latestVal = $panel['latest_val'] ?? null;
                            $statusCode = $panel['status']['status_code'] ?? 'no_data';
                            $statusShort = $panel['status']['short_label'] ?? 'Belum ada data';
                            $scaleMin = (float) ($th['scale_min'] ?? 0);
                            $scaleMax = (float) ($th['scale_max'] ?? 100);
                            $idealMin = (float) ($th['ideal_min'] ?? 0);
                            $idealMax = (float) ($th['ideal_max'] ?? 100);
                            $scaleSpan = max(1.0, $scaleMax - $scaleMin);

                            // Range bar positions
                            $barLeft = max(0.0, min(100.0, (($idealMin - $scaleMin) / $scaleSpan) * 100));
                            $barWidth = max(4.0, min(100.0 - $barLeft, (($idealMax - $idealMin) / $scaleSpan) * 100));
                            $dotPos = $latestVal !== null ? max(0.0, min(100.0, (($latestVal - $scaleMin) / $scaleSpan) * 100)) : null;
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <!-- Parameter dan Satuan -->
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ $th['parameter'] ?? $paramKey }}</div>
                                <div class="text-[11px] text-slate-500 font-normal">{{ $th['sublabel'] ?? '' }}</div>
                            </td>

                            <!-- Nilai Terakhir -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($latestVal !== null)
                                    <div class="font-tabular font-bold text-slate-900">
                                        {{ number_format($latestVal, $panel['decimals'] ?? 1, ',', '.') }} {{ $th['unit'] }}
                                    </div>
                                    @if($statusCode === 'optimal')
                                        <span class="text-[10px] font-medium text-emerald-700">Dalam rentang</span>
                                    @elseif($statusCode === 'high')
                                        <span class="text-[10px] font-medium text-amber-700">Di atas rentang</span>
                                    @elseif($statusCode === 'low')
                                        <span class="text-[10px] font-medium text-amber-700">Di bawah rentang</span>
                                    @endif
                                @else
                                    <span class="font-tabular text-slate-400">—</span>
                                    <div class="text-[10px] text-slate-500">Belum ada data</div>
                                @endif
                            </td>

                            <!-- Rentang Acuan & Mini Bar -->
                            <td class="py-3 px-3 min-w-[130px]">
                                <div class="font-tabular font-semibold text-slate-800 whitespace-nowrap">
                                    {{ $idealMin }} – {{ $idealMax }} <span class="text-[11px] text-slate-500 font-normal">{{ $th['unit'] }}</span>
                                </div>
                                <!-- Range visual bar -->
                                <div class="pt-1.5 space-y-0.5">
                                    <div class="relative w-full h-1.5 bg-slate-100 rounded-full">
                                        <!-- Ideal segment -->
                                        <div
                                            class="absolute top-0 bottom-0 bg-emerald-400/80 rounded-full"
                                            style="left: {{ $barLeft }}%; width: {{ $barWidth }}%;"
                                        ></div>
                                        <!-- Reading Dot Marker (strictly suppressed when latestVal is null) -->
                                        @if($dotPos !== null && $latestVal !== null)
                                            <div
                                                class="absolute -top-0.5 w-2.5 h-2.5 rounded-full {{ $statusCode === 'optimal' ? 'bg-emerald-700' : 'bg-amber-600' }} border border-white shadow-2xs -ml-1"
                                                style="left: {{ $dotPos }}%;"
                                            ></div>
                                        @endif
                                    </div>
                                    <div class="flex justify-between text-[8.5px] text-slate-500">
                                        <span>{{ round($scaleMin) }}</span>
                                        <span>{{ round($scaleMax) }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Indikasi Rendah / Defisit -->
                            <td class="py-3 px-3 text-slate-700 leading-snug max-w-xs">
                                {{ $th['low_implication'] ?? '—' }}
                            </td>

                            <!-- Indikasi Tinggi / Berlebih -->
                            <td class="py-3 px-3 text-slate-700 leading-snug max-w-xs">
                                {{ $th['high_implication'] ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Detail Acuan & Metodologi Pengukuran Disclosure -->
        <div class="pt-2 border-t border-slate-100">
            <button
                type="button"
                @click="showDetail = !showDetail"
                class="flex items-center gap-1.5 text-xs font-semibold text-emerald-800 hover:text-emerald-900 transition-colors"
            >
                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                <span>Detail acuan & metodologi pengukuran sensorik</span>
                <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" :class="showDetail ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="showDetail" x-cloak class="mt-3 p-4 rounded-xl bg-slate-50 border border-slate-200/90 space-y-3 text-xs text-slate-600 leading-relaxed">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($thresholds as $paramKey => $th)
                        <div class="p-2.5 bg-white rounded-lg border border-slate-200/80 space-y-1">
                            <div class="flex items-center justify-between font-bold text-slate-800">
                                <span>{{ $th['parameter'] }}</span>
                                <span class="text-[10px] text-slate-500">{{ $th['verification_status'] ?? 'Preset Aplikasi' }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                <strong>Metode:</strong> {{ $th['measurement_method'] ?? 'Nilai parameter yang dilaporkan perangkat sensor.' }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                <strong>Komoditas:</strong> {{ $th['crop_applicability'] ?? 'Hortikultura' }}
                            </div>
                            @if(!empty($th['measurement_caveat']))
                                <div class="text-[10px] text-slate-600 bg-white p-1.5 rounded border border-slate-200/60 italic">
                                    {{ $th['measurement_caveat'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
