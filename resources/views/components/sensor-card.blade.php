@props([
    'parameter' => 'pH Tanah',
    'value' => null,
    'unit' => 'pH',
    'scaleMin' => 0,
    'scaleMax' => 14,
    'idealMin' => 6.0,
    'idealMax' => 7.2,
    'status' => 'optimal', // 'optimal' | 'warning' | 'critical'
    'statusLabel' => 'Optimal',
    'sublabel' => 'Aktivitas ion H⁺',
    'icon' => null,
    'letter' => null,
    'definition' => 'Derajat kemasaman tanah yang menentukan ketersediaan penyerapan ion hara bagi akar tanaman.',
    'idealDesc' => '6.0 – 7.2 pH (Kondisi prima sebagian besar komoditas hortikultura & sayuran daun).',
    'lowRisk' => 'pH < 5.5: Fiksasi fosfor, keracunan Al & Mn.',
    'highRisk' => 'pH > 7.5: Klorosis defisiensi Fe, Zn, Cu.'
])

@php
    $hasValue = $value !== null;
    $val = $hasValue ? (float) $value : 0.0;
    $minVal = (float) $scaleMin;
    $maxVal = (float) $scaleMax;
    $range = $maxVal > $minVal ? ($maxVal - $minVal) : 1;
    $clampedVal = max($minVal, min($maxVal, $val));
    $percent = round((($clampedVal - $minVal) / $range) * 100);

    $idealLeftPercent = round((($idealMin - $minVal) / $range) * 100);
    $idealWidthPercent = round((($idealMax - $idealMin) / $range) * 100);

    $dotColor = match($status) {
        'critical' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        default => 'bg-emerald-600',
    };
@endphp

<div
    x-data="{ flipped: false }"
    @click="flipped = !flipped"
    class="perspective-1000 w-full h-[195px] cursor-pointer select-none group"
    role="button"
    tabindex="0"
    @keydown.enter="flipped = !flipped"
    @keydown.space.prevent="flipped = !flipped"
    :aria-label="'Parameter ' + '{{ $parameter }}' + '. Klik untuk membalik melihat glosarium agronomi.'"
>
    <!-- 3D Flipper Box -->
    <div
        class="relative w-full h-full transform-style-3d transition-transform duration-500 ease-in-out motion-reduce:transition-none"
        :class="flipped ? 'rotate-y-180' : ''"
    >
        <!-- FRONT SIDE: Live Telemetry Metric -->
        <div
            class="absolute inset-0 backface-hidden p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden"
        >
            <!-- Card Header: Icon, Name & Info Icon -->
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if($letter)
                        <div class="w-7 h-7 rounded-lg bg-slate-50 border border-slate-200/80 font-bold font-mono text-xs text-slate-700 flex items-center justify-center shrink-0">
                            {{ $letter }}
                        </div>
                    @else
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                            <i data-lucide="{{ $icon ?? 'gauge' }}" class="w-3.5 h-3.5"></i>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="text-xs font-bold text-slate-800 leading-tight truncate" title="{{ $parameter }}">
                            {{ $parameter }}
                        </h3>
                        <span class="text-[10px] text-slate-400 font-medium block truncate">{{ $sublabel }}</span>
                    </div>
                </div>

                <div class="text-slate-300 group-hover:text-slate-400 transition-colors shrink-0 pt-0.5">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                </div>
            </div>

            <!-- Value Display & Status Line -->
            <div class="space-y-0.5 my-auto">
                <div class="flex items-baseline gap-1.5">
                    @if($value !== null)
                        <span class="font-tabular text-2xl font-extrabold text-slate-900 tracking-tight">
                            {{ number_format((float)$value, 1) }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 font-mono">
                            {{ $unit }}
                        </span>
                    @else
                        <span class="font-tabular text-2xl font-extrabold text-slate-700 tracking-tight">
                            —
                        </span>
                        <span class="text-xs font-medium text-slate-400 font-mono">
                            {{ $unit }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-1.5 text-[11px] font-medium">
                    @if($value !== null)
                        <span class="w-1.5 h-1.5 rounded-full {{ $status === 'critical' ? 'bg-rose-500' : ($status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                        <span class="{{ $status === 'critical' ? 'text-rose-700' : ($status === 'warning' ? 'text-amber-700' : 'text-emerald-700') }}">
                            {{ $statusLabel }}
                        </span>
                    @else
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span class="text-slate-400">
                            Menunggu data
                        </span>
                    @endif
                </div>
            </div>

            <!-- Visual Range Bar Position Indicator -->
            <div class="space-y-1 pt-1 border-t border-slate-100">
                <div class="text-[11px] text-slate-500 font-medium truncate">
                    Rentang ideal {{ $idealMin }}–{{ $idealMax }} {{ $unit }}
                </div>
                <div class="relative w-full h-1.5 rounded-full bg-slate-100 overflow-hidden border border-slate-200/60">
                    <!-- Target Band (Zona Ideal) -->
                    <div
                        class="absolute top-0 bottom-0 bg-emerald-500/80 rounded-full"
                        style="left: {{ max(0, $idealLeftPercent) }}%; width: {{ min(100, $idealWidthPercent) }}%;"
                        title="Zona Ideal: {{ $idealMin }} - {{ $idealMax }} {{ $unit }}"
                    ></div>

                    @if($hasValue)
                        <!-- Indicator Dot Posisi Nilai Riil -->
                        <div
                            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-2.5 h-2.5 rounded-full {{ $dotColor }} border border-white shadow-2xs z-10 transition-all duration-500"
                            style="left: {{ $percent }}%;"
                            title="Nilai Saat Ini: {{ $val }} {{ $unit }}"
                        ></div>
                    @endif
                </div>

                <div class="flex items-center justify-between text-[10px] font-mono text-slate-400">
                    <span>{{ $scaleMin }}</span>
                    <span>{{ $scaleMax }}</span>
                </div>
            </div>
        </div>

        <!-- BACK SIDE: Agronomy Glossary (Dark Green Surface, Legible & Non-Mirrored) -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-3.5 rounded-2xl bg-[#0c3c26] text-white border border-emerald-950 shadow-2xs flex flex-col justify-between overflow-hidden"
        >
            <!-- Back Header -->
            <div class="flex items-center justify-between border-b border-emerald-800/60 pb-1.5">
                <div class="flex items-center gap-1.5 min-w-0">
                    <i data-lucide="book-open" class="w-3 h-3 text-emerald-400 shrink-0"></i>
                    <span class="text-[11px] font-bold text-emerald-300 truncate">Glosarium: {{ $parameter }}</span>
                </div>
                <span class="text-[9px] text-emerald-200/80 font-mono shrink-0 flex items-center gap-0.5">
                    <i data-lucide="rotate-ccw" class="w-2.5 h-2.5"></i>
                    <span>Tutup</span>
                </span>
            </div>

            <!-- Glossary Content -->
            <div class="space-y-1 text-[10px] leading-tight my-auto overflow-hidden">
                <p class="text-emerald-100/90 line-clamp-2">{{ $definition }}</p>
                <div class="px-2 py-1 rounded bg-[#08281a] border border-emerald-800/40 text-[9px] text-emerald-200">
                    <span class="font-bold text-emerald-300">Target Ideal:</span>
                    <span class="font-mono text-white">{{ $idealMin }} – {{ $idealMax }} {{ $unit }}</span>
                </div>
                <div class="text-[9px] space-y-0.5 text-emerald-200/80">
                    <div class="truncate text-amber-200">⚠️ {{ $lowRisk }}</div>
                    <div class="truncate text-rose-200">🚫 {{ $highRisk }}</div>
                </div>
            </div>

            <!-- Back Footer Prompt -->
            <div class="pt-1 border-t border-emerald-800/60 text-center">
                <span class="text-[9px] text-emerald-300/70 block">
                    Klik kartu untuk kembali ke sensor
                </span>
            </div>
        </div>
    </div>
</div>
