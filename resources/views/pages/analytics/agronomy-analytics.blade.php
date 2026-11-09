@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header: Unboxed, matching approved Monitoring IoT design -->
    <div class="space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                    Analitik Agronomi
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Analisis tren dan hubungan parameter tanah
                </p>
            </div>

            @if(!empty($isSuperuser) && $schools->isNotEmpty())
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ url('/analytics') }}" class="flex items-center gap-2">
                    <input type="hidden" name="range" value="{{ $selectedRange }}">
                    @if(!empty($selectedDevice))
                        <input type="hidden" name="device_id" value="{{ $selectedDevice->id }}">
                    @endif
                    <label for="school_id" class="text-xs font-medium text-slate-500">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                    >
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $sch)
                            <option value="{{ $sch->id }}" {{ ((string) $selectedSchoolId === (string) $sch->id) ? 'selected' : '' }}>
                                {{ $sch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif
        </div>

        <!-- Context Toolbar: Stasiun, Periode, Dataset Count & Provenance -->
        <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50/80 border border-slate-200/90 rounded-xl">
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ url('/analytics') }}" class="flex items-center gap-2">
                    @if(!empty($selectedSchoolId))
                        <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                    @endif
                    <input type="hidden" name="range" value="{{ $selectedRange }}">
                    <label for="device_id" class="text-xs font-medium text-slate-600">Stasiun:</label>
                    <select
                        id="device_id"
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-2.5 py-1 rounded-lg border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
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

                <span class="text-slate-300 hidden sm:inline">|</span>

                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full {{ $readingCount > 0 ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        <span class="font-tabular font-bold text-slate-800">{{ $readingCount }}</span> data telemetri valid
                    </span>
                    <span class="text-slate-300">•</span>
                    <span class="text-slate-500 text-[11px]">{{ $provenanceSummary }}</span>
                </div>
            </div>

            <!-- Periode Filter Buttons -->
            <div class="flex items-center gap-1.5">
                <span class="text-xs font-medium text-slate-500 mr-1">Periode:</span>
                <div class="flex items-center p-0.5 rounded-lg bg-white border border-slate-200 text-xs font-mono shadow-2xs">
                    @foreach(['1h' => '1J', '6h' => '6J', '24h' => '24J', '7d' => '7H'] as $rKey => $rLabel)
                        <a
                            href="{{ request()->fullUrlWithQuery(['range' => $rKey]) }}"
                            class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold transition-colors {{ $selectedRange === $rKey ? 'bg-[#0c3c26] text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            {{ $rLabel }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Hubungan antarparameter (Correlation Analysis) -->
    <div class="space-y-3">
        <div>
            <h2 class="text-base font-bold text-slate-900">Hubungan antarparameter</h2>
            <p class="text-xs text-slate-500">Korelasi bivariat Pearson r dan visualisasi sebaran observasi empiris</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($correlations as $corr)
                <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <span class="text-xs font-bold text-slate-800 tracking-wide">{{ $corr['label'] }}</span>
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600">
                                n = {{ $corr['n'] }}
                            </span>
                            @if(!empty($corr['is_sufficient']))
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/70">
                                    Observasi Memadai
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-700 border border-amber-200/70">
                                    Min. 10 Observasi
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Metric Value Display -->
                    <div class="space-y-1">
                        @if(!empty($corr['is_sufficient']) && $corr['value'] !== null)
                            <div class="flex items-baseline gap-3">
                                <span class="font-tabular text-3xl font-extrabold text-slate-900">
                                    {{ ($corr['value'] > 0 ? '+' : '') . number_format($corr['value'], 3) }}
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-emerald-800">
                                        {{ $corr['strength_label'] }}
                                    </span>
                                    <p class="text-[10px] text-slate-400 font-mono">Koefisien korelasi Pearson r (-1.0 s/d +1.0)</p>
                                </div>
                            </div>
                        @else
                            <div class="space-y-0.5">
                                <div class="font-tabular text-2xl font-extrabold text-slate-400">
                                    {{ $corr['status_label'] }}
                                </div>
                                <p class="text-xs text-amber-700 font-medium">
                                    Tercatat {{ $corr['n'] }} observasi berpasangan (memerlukan min. 10 observasi valid).
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Neutral Scientific Interpretation -->
                    <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                        {{ $corr['interpretation'] }}
                    </p>

                    <!-- Compact Scatter Plot or Honest Empty State -->
                    <div>
                        @if(!empty($corr['is_sufficient']) && count($corr['points']) > 0)
                            @php
                                $pts = $corr['points'];
                                $xVals = array_column($pts, 'x');
                                $yVals = array_column($pts, 'y');
                                $xMin = count($xVals) ? min($xVals) : 0;
                                $xMax = count($xVals) ? max($xVals) : 1;
                                $yMin = count($yVals) ? min($yVals) : 0;
                                $yMax = count($yVals) ? max($yVals) : 1;
                                $xRange = ($xMax - $xMin) > 0.001 ? ($xMax - $xMin) : 1.0;
                                $yRange = ($yMax - $yMin) > 0.001 ? ($yMax - $yMin) : 1.0;
                                $w = 340; $h = 130;
                                $padL = 44; $padR = 16; $padT = 12; $padB = 26;
                                $plotW = $w - $padL - $padR;
                                $plotH = $h - $padT - $padB;
                            @endphp
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-mono text-slate-400">
                                    <span>Sebaran Titik Observasi</span>
                                    <span>Y: {{ $corr['y_name'] }} ({{ $corr['y_unit'] }}) vs X: {{ $corr['x_name'] }} ({{ $corr['x_unit'] }})</span>
                                </div>
                                <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-32 bg-slate-50/80 rounded-xl border border-slate-200/80 overflow-visible">
                                    <!-- Axes & Grid -->
                                    <line x1="{{ $padL }}" y1="{{ $padT }}" x2="{{ $padL }}" y2="{{ $padT + $plotH }}" stroke="#cbd5e1" stroke-width="1" />
                                    <line x1="{{ $padL }}" y1="{{ $padT + $plotH }}" x2="{{ $padL + $plotW }}" y2="{{ $padT + $plotH }}" stroke="#cbd5e1" stroke-width="1" />

                                    <!-- Grid lines (horizontal mid) -->
                                    <line x1="{{ $padL }}" y1="{{ $padT + ($plotH / 2) }}" x2="{{ $padL + $plotW }}" y2="{{ $padT + ($plotH / 2) }}" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3,3" />

                                    <!-- Axis tick labels -->
                                    <text x="{{ $padL - 4 }}" y="{{ $padT + 8 }}" text-anchor="end" font-size="8" fill="#64748b" font-family="JetBrains Mono">{{ round($yMax, 1) }}</text>
                                    <text x="{{ $padL - 4 }}" y="{{ $padT + $plotH }}" text-anchor="end" font-size="8" fill="#64748b" font-family="JetBrains Mono">{{ round($yMin, 1) }}</text>
                                    <text x="{{ $padL }}" y="{{ $h - 8 }}" text-anchor="start" font-size="8" fill="#64748b" font-family="JetBrains Mono">{{ round($xMin, 1) }}</text>
                                    <text x="{{ $padL + $plotW }}" y="{{ $h - 8 }}" text-anchor="end" font-size="8" fill="#64748b" font-family="JetBrains Mono">{{ round($xMax, 1) }}</text>

                                    <!-- Data Points -->
                                    @foreach($pts as $pt)
                                        @php
                                            $cx = $padL + (($pt['x'] - $xMin) / $xRange) * $plotW;
                                            $cy = $padT + $plotH - (($pt['y'] - $yMin) / $yRange) * $plotH;
                                            $fillColor = match($pt['provenance']) {
                                                'hardware' => '#059669',
                                                'simulation' => '#6366f1',
                                                default => '#64748b',
                                            };
                                        @endphp
                                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3" fill="{{ $fillColor }}" stroke="#ffffff" stroke-width="1.2">
                                            <title>{{ $corr['x_name'] }}: {{ $pt['x'] }} {{ $corr['x_unit'] }} | {{ $corr['y_name'] }}: {{ $pt['y'] }} {{ $corr['y_unit'] }} ({{ ucfirst($pt['provenance']) }})</title>
                                        </circle>
                                    @endforeach
                                </svg>
                            </div>
                        @else
                            <div class="h-28 flex flex-col items-center justify-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200 text-center p-4 space-y-1">
                                <p class="text-xs font-semibold text-slate-700">Scatter Plot Belum Tersedia</p>
                                <p class="text-[11px] text-slate-400 max-w-sm">
                                    Memerlukan minimal 10 observasi berpasangan untuk memetakan sebaran titik data secara valid.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Tren parameter (Historical Trend Section with Time Filtering) -->
    <div class="space-y-3">
        <div>
            <h2 class="text-base font-bold text-slate-900">Tren parameter</h2>
            <p class="text-xs text-slate-500">Inspeksi dinamika historis telemetri per parameter agronomi</p>
        </div>

        <x-telemetry-chart
            :initial-data="$chartData"
            :thresholds="$thresholds"
            heading="Tren parameter"
            :show-ai-button="false"
            :enable-range-navigation="true"
            :active-range="$selectedRange"
        />
    </div>

    <!-- Acuan parameter agronomi (Agronomic Reference Thresholds Matrix) -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Acuan parameter agronomi</h3>
                <p class="text-xs text-slate-500">Rentang acuan fisiologis tanaman dan implikasi agronomi (SMK ATPH)</p>
            </div>
            <div>
                @if($isCustomizedThresholds)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200/70">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        Disesuaikan Sekolah
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Preset Standar (ATPH Hortikultura)
                    </span>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50/80">
                        <th class="py-2.5 px-3">Parameter & Satuan</th>
                        <th class="py-2.5 px-3">Status Acuan</th>
                        <th class="py-2.5 px-3">Rentang Acuan</th>
                        <th class="py-2.5 px-3">Indikasi Rendah / Defisit</th>
                        <th class="py-2.5 px-3">Indikasi Tinggi / Berlebih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @foreach($thresholds as $paramKey => $th)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ $th['parameter'] ?? $paramKey }}</div>
                                <div class="text-[11px] text-slate-400 font-normal">{{ $th['sublabel'] ?? '' }}</div>
                            </td>
                            <td class="py-3 px-3">
                                @if(!empty($th['is_customized']))
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        Disesuaikan Sekolah
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono text-slate-500 bg-slate-100">
                                        Preset Standar
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-emerald-800 whitespace-nowrap">
                                {{ $th['ideal_min'] }} – {{ $th['ideal_max'] }} <span class="text-xs text-slate-500 font-normal">{{ $th['unit'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-slate-600 leading-snug">
                                {{ $th['low_implication'] ?? '—' }}
                            </td>
                            <td class="py-3 px-3 text-slate-600 leading-snug">
                                {{ $th['high_implication'] ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
