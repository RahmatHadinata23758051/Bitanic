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
                    @elseif($icon === 'ph')
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0" title="pH">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M 216,243 L 209,241 L 200,240 L 190,241 L 179,245 L 173,241 L 162,241 L 157,244 L 153,250 L 153,373 L 156,378 L 162,382 L 173,382 L 178,379 L 182,373 L 185,332 L 190,334 L 209,334 L 216,332 L 230,324 L 239,314 L 244,304 L 246,297 L 246,278 L 242,266 L 236,257 L 230,251 Z M 195,271 L 204,271 L 207,272 L 210,274 L 213,277 L 215,280 L 216,283 L 216,292 L 215,295 L 211,300 L 207,303 L 204,304 L 195,304 L 192,303 L 187,299 L 184,295 L 183,292 L 183,283 L 184,280 L 186,277 L 189,274 L 192,272 Z M 268,197 L 265,202 L 265,325 L 269,331 L 274,334 L 285,334 L 291,330 L 294,325 L 294,288 L 296,280 L 304,272 L 316,271 L 322,274 L 325,277 L 328,283 L 328,319 L 330,327 L 333,331 L 338,334 L 349,334 L 355,330 L 358,325 L 358,278 L 354,266 L 348,257 L 342,251 L 328,243 L 311,240 L 302,241 L 297,243 L 294,242 L 294,202 L 291,197 L 285,193 L 274,193 Z M 101,44 L 64,88 L 41,134 L 45,170 L 66,199 L 51,233 L 43,264 L 43,331 L 61,389 L 88,432 L 114,459 L 144,481 L 179,498 L 220,509 L 291,509 L 349,490 L 376,508 L 411,511 L 443,497 L 460,478 L 470,451 L 469,426 L 450,389 L 467,337 L 470,278 L 465,250 L 450,208 L 430,171 L 400,128 L 333,55 L 281,10 L 263,0 L 238,4 L 153,80 L 117,41 Z M 400,375 L 406,381 L 421,400 L 435,422 L 440,434 L 440,447 L 438,454 L 434,462 L 431,466 L 425,472 L 416,478 L 407,481 L 392,481 L 383,478 L 375,473 L 368,466 L 365,462 L 361,454 L 359,448 L 359,434 L 364,422 L 378,400 L 393,381 Z M 112,79 L 118,85 L 133,104 L 147,126 L 152,138 L 152,152 L 150,158 L 145,167 L 140,173 L 132,179 L 126,182 L 120,184 L 103,184 L 97,182 L 88,177 L 83,173 L 78,167 L 73,158 L 71,151 L 71,138 L 76,126 L 90,104 L 105,85 Z M 254,30 L 272,42 L 320,85 L 383,156 L 406,190 L 423,222 L 433,248 L 440,280 L 439,322 L 428,360 L 405,337 L 394,337 L 389,340 L 352,384 L 333,417 L 329,430 L 329,451 L 333,464 L 305,475 L 274,481 L 237,481 L 206,475 L 167,459 L 138,439 L 109,409 L 89,377 L 78,349 L 71,314 L 73,267 L 92,214 L 132,212 L 148,205 L 165,191 L 178,170 L 182,155 L 182,134 L 171,106 L 208,69 Z" />
                            </svg>
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
                    @if($idealMin !== null && $idealMax !== null)
                        Rentang ideal: <span class="font-medium text-slate-700">{{ $idealMin }}–{{ $idealMax }} {{ $unit }}</span>
                    @else
                        <span class="text-slate-400">Rentang belum dikonfigurasi</span>
                    @endif
                </div>
                <div class="relative w-full h-1.5 rounded-full bg-slate-100 overflow-hidden border border-slate-200/60">
                    <!-- Target Band (Zona Ideal) -->
                    @if($idealMin !== null && $idealMax !== null)
                        <div
                            class="absolute top-0 bottom-0 bg-emerald-500/70 rounded-full"
                            style="left: {{ max(0, $idealLeftPercent) }}%; width: {{ min(100, $idealWidthPercent) }}%;"
                            title="Rentang ideal: {{ $idealMin }} - {{ $idealMax }} {{ $unit }}"
                        ></div>
                    @endif

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

        <!-- BACK SIDE: Agronomy Glossary (Ultra-Concise, No Scrolling) -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-3.5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden text-slate-800"
        >
            <!-- Back Header: Title & Clean Glosarium Badge -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 shrink-0">
                <h4 class="text-xs font-semibold text-slate-900 truncate" title="{{ $parameter }}">
                    {{ $parameter }}
                </h4>
                <span class="text-[10px] font-medium text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60 shrink-0">
                    Glosarium
                </span>
            </div>

            <!-- Back Content: Ultra-Concise Agronomic Points (Fits 200px Without Scroll) -->
            <div class="space-y-2 text-xs text-slate-600 my-auto">
                <div class="space-y-0.5">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Fungsi</span>
                    <p class="text-[11px] text-slate-700 leading-snug">
                        {{ $definition }}
                    </p>
                </div>

                <div class="space-y-0.5">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Risiko deviasi</span>
                    <p class="text-[11px] text-slate-700 leading-snug">
                        {{ $riskText }}
                    </p>
                </div>
            </div>

            <!-- Back Footer: Reference Range Strip -->
            <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px] shrink-0">
                <span class="text-slate-500 font-medium">Rentang acuan:</span>
                @if($idealMin !== null && $idealMax !== null)
                    <span class="font-semibold text-emerald-800 font-mono">{{ $idealMin }} – {{ $idealMax }} {{ $unit }}</span>
                @else
                    <span class="font-medium text-slate-400">Rentang belum dikonfigurasi</span>
                @endif
            </div>
        </div>
    </div>
</div>
