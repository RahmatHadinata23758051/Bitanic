@props([
    'parameter' => 'pH Tanah',
    'value' => null,
    'unit' => 'pH',
    'scaleMin' => 0,
    'scaleMax' => 14,
    'idealMin' => 6.0,
    'idealMax' => 7.2,
    'status' => 'optimal', // 'optimal' | 'warning' | 'critical'
    'statusLabel' => 'Dalam rentang acuan',
    'definition' => 'Tingkat kemasaman tanah yang menentukan kelarutan ion nutrisi dalam larutan tanah.',
    'importance' => 'Menjaga pH seimbang memastikan tanaman dapat menyerap unsur hara secara maksimal.',
    'riskNote' => '',
    'sublabel' => '',
    'lowRisk' => '',
    'highRisk' => '',
])

@php
    $hasValue = $value !== null;
    $val = $hasValue ? (float) $value : 0.0;
    $importanceText = $importance ?: 'Menjaga keseimbangan nutrisi dan metabolisme perakaran tanaman.';
@endphp

<div
    x-data="{ flipped: false }"
    @click="flipped = !flipped"
    class="perspective-1000 w-full h-[180px] cursor-pointer select-none group"
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
        <!-- FRONT SIDE: Minimalist Live Metric (Max 3 Information Elements) -->
        <div
            class="absolute inset-0 backface-hidden p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden"
        >
            <!-- 1. Parameter Name -->
            <div>
                <h3 class="text-xs font-semibold text-slate-700 truncate" title="{{ $parameter }}">
                    {{ $parameter }}
                </h3>
            </div>

            <!-- 2. Primary Sensor Reading and Unit -->
            <div class="my-auto py-1">
                @if($hasValue)
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-tabular text-3xl font-bold text-slate-900 tracking-tight">
                            {{ number_format((float)$value, 1) }}
                        </span>
                        <span class="text-xs font-medium text-slate-500 font-mono">
                            {{ $unit }}
                        </span>
                    </div>
                @else
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-tabular text-3xl font-bold text-slate-300 tracking-tight">
                            —
                        </span>
                        <span class="text-xs font-medium text-slate-400 font-mono">
                            {{ $unit }}
                        </span>
                    </div>
                @endif
            </div>

            <!-- 3. One Concise Status -->
            <div>
                @if($hasValue)
                    <span class="text-xs font-medium {{ $status === 'critical' ? 'text-rose-700' : ($status === 'warning' ? 'text-amber-700' : 'text-emerald-700') }}">
                        {{ $statusLabel }}
                    </span>
                @else
                    <span class="text-xs text-slate-400 font-normal">
                        Belum ada data
                    </span>
                @endif
            </div>
        </div>

        <!-- BACK SIDE: Minimal Educational Glossary (White Background, Concise, Readable) -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden text-slate-800"
        >
            <!-- Parameter Name & Subtle Glossary Tag -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                <h4 class="text-xs font-semibold text-slate-900 truncate" title="{{ $parameter }}">
                    {{ $parameter }}
                </h4>
                <span class="text-[10px] text-slate-400 font-normal">
                    Glosarium
                </span>
            </div>

            <!-- Short definition & brief practical explanation -->
            <div class="text-xs text-slate-600 space-y-1.5 my-auto leading-relaxed">
                <p>
                    {{ $definition }}
                </p>
                <p class="text-slate-700">
                    {{ $importanceText }}
                </p>
            </div>

            <!-- Verified Reference Range -->
            <div class="pt-1.5 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between">
                <span>Rentang acuan:</span>
                <span class="font-semibold text-emerald-800 font-mono">{{ $idealMin }} – {{ $idealMax }} {{ $unit }}</span>
            </div>
        </div>
    </div>
</div>
