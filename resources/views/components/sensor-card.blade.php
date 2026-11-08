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
    'icon' => null,
    'letter' => null,
    'definition' => 'Derajat kemasaman tanah yang menentukan kelarutan ion hara bagi penyerapan perakaran tanaman.',
    'importance' => 'Mengatur ketersediaan hara makro dan menjaga aktivitas mikroorganisme tanah yang menguntungkan.',
    'riskNote' => 'Nilai terlalu rendah mengikat fosfor dan memicu keracunan aluminium, sedangkan nilai tinggi menghambat hara mikro.',
    'sublabel' => '', // backward compatibility
    'lowRisk' => '',  // backward compatibility
    'highRisk' => '', // backward compatibility
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

    // Clean text without any emoji
    $importanceText = $importance ?: ($sublabel ?: 'Menentukan efisiensi serapan nutrisi dan metabolisme perakaran tanaman.');
    $rawRisk = $riskNote ?: trim(($lowRisk ? $lowRisk . '. ' : '') . ($highRisk ?: ''));
    $cleanRisk = trim(str_replace(['⚠️', '🚫', '🕒', '✨', '•'], '', $rawRisk));
    $riskText = $cleanRisk ?: 'Penyimpangan di luar rentang acuan dapat mengganggu metabolisme dan pertumbuhan komoditas.';
@endphp

<div
    x-data="{ flipped: false }"
    @click="flipped = !flipped"
    class="perspective-1000 w-full h-[200px] cursor-pointer select-none group"
    role="button"
    tabindex="0"
    @keydown.enter="flipped = !flipped"
    @keydown.space.prevent="flipped = !flipped"
    :aria-label="'Parameter ' + '{{ $parameter }}' + '. Klik untuk melihat glosarium agronomi.'"
>
    <!-- 3D Flipper Box -->
    <div
        class="relative w-full h-full transform-style-3d transition-transform duration-500 ease-in-out motion-reduce:transition-none"
        :class="flipped ? 'rotate-y-180' : ''"
    >
        <!-- FRONT SIDE: Live Telemetry Metric (Clean White Card) -->
        <div
            class="absolute inset-0 backface-hidden p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden"
        >
            <!-- Card Header: Icon, Parameter Name & Info Indicator -->
            <div class="flex items-center justify-between gap-2">
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
                    <h3 class="text-xs font-semibold text-slate-800 leading-tight truncate" title="{{ $parameter }}">
                        {{ $parameter }}
                    </h3>
                </div>

                <div class="text-slate-300 group-hover:text-slate-500 transition-colors shrink-0">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                </div>
            </div>

            <!-- Value Display & Single Concise Status -->
            <div class="space-y-1 my-auto">
                <div class="flex items-baseline gap-1.5">
                    @if($hasValue)
                        <span class="font-tabular text-2xl font-bold text-slate-900 tracking-tight">
                            {{ number_format((float)$value, 1) }}
                        </span>
                        <span class="text-xs font-medium text-slate-500 font-mono">
                            {{ $unit }}
                        </span>
                    @else
                        <span class="font-tabular text-2xl font-bold text-slate-300 tracking-tight">
                            —
                        </span>
                        <span class="text-xs font-medium text-slate-400 font-mono">
                            {{ $unit }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-1.5 text-xs">
                    @if($hasValue)
                        <span class="w-1.5 h-1.5 rounded-full {{ $status === 'critical' ? 'bg-rose-500' : ($status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                        <span class="font-medium {{ $status === 'critical' ? 'text-rose-700' : ($status === 'warning' ? 'text-amber-700' : 'text-emerald-700') }}">
                            {{ $statusLabel }}
                        </span>
                    @else
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                        <span class="text-slate-400 font-medium">
                            Belum ada data
                        </span>
                    @endif
                </div>
            </div>

            <!-- Compact Reference Range Visualization -->
            <div class="space-y-1 pt-1.5 border-t border-slate-100">
                <div class="text-[11px] text-slate-500 font-normal truncate">
                    Rentang ideal: <span class="font-medium text-slate-700">{{ $idealMin }}–{{ $idealMax }} {{ $unit }}</span>
                </div>
                <div class="relative w-full h-1.5 rounded-full bg-slate-100 overflow-hidden border border-slate-200/60">
                    <!-- Target Band (Zona Ideal) -->
                    <div
                        class="absolute top-0 bottom-0 bg-emerald-500/70 rounded-full"
                        style="left: {{ max(0, $idealLeftPercent) }}%; width: {{ min(100, $idealWidthPercent) }}%;"
                        title="Rentang ideal: {{ $idealMin }} - {{ $idealMax }} {{ $unit }}"
                    ></div>

                    @if($hasValue)
                        <!-- Indicator Dot Posisi Nilai Riil (Hanya muncul jika ada data valid) -->
                        <div
                            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-2.5 h-2.5 rounded-full {{ $dotColor }} border border-white shadow-2xs z-10 transition-all duration-500"
                            style="left: {{ $percent }}%;"
                            title="Nilai: {{ $val }} {{ $unit }}"
                        ></div>
                    @endif
                </div>

                <div class="flex items-center justify-between text-[10px] font-mono text-slate-400">
                    <span>{{ $scaleMin }}</span>
                    <span>{{ $scaleMax }}</span>
                </div>
            </div>
        </div>

        <!-- BACK SIDE: Agronomy Glossary (WHITE ON BOTH SIDES, No Emojis, No Truncation) -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden text-slate-800"
        >
            <!-- Back Header: Title & Clean Glosarium Badge -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                <h4 class="text-xs font-semibold text-slate-900 truncate" title="{{ $parameter }}">
                    {{ $parameter }}
                </h4>
                <span class="text-[10px] font-medium text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60 shrink-0">
                    Glosarium
                </span>
            </div>

            <!-- Back Content: Educational Points (No Emoji, Concise, Legible) -->
            <div class="space-y-1.5 text-xs text-slate-600 my-auto">
                <p class="text-[11px] text-slate-600 leading-snug">
                    {{ $definition }}
                </p>

                <div class="space-y-0.5">
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Mengapa penting</span>
                    <p class="text-[11px] text-slate-700 leading-snug">
                        {{ $importanceText }}
                    </p>
                </div>

                <div class="space-y-0.5">
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Perlu diperhatikan</span>
                    <p class="text-[11px] text-slate-700 leading-snug">
                        {{ $riskText }}
                    </p>
                </div>
            </div>

            <!-- Back Footer: Reference Range Strip -->
            <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
                <span class="text-slate-500 font-medium">Rentang acuan:</span>
                <span class="font-semibold text-emerald-800 font-mono">{{ $idealMin }} – {{ $idealMax }} {{ $unit }}</span>
            </div>
        </div>
    </div>
</div>
