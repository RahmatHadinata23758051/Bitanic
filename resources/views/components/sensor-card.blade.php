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
    'sublabel' => 'Netral Hortikultura',
    'icon' => 'gauge',
    'definition' => 'Derajat kemasaman tanah yang menentukan ketersediaan penyerapan ion hara bagi akar tanaman.',
    'idealDesc' => '6.0 – 7.2 pH (Kondisi prima sebagian besar komoditas hortikultura & sayuran daun).',
    'lowRisk' => 'pH < 5.5: Fiksasi fosfor, keracunan Al & Mn.',
    'highRisk' => 'pH > 7.5: Klorosis defisiensi Fe, Zn, Cu.'
])

@php
    $badgeClass = match($status) {
        'critical' => 'bg-rose-50 text-rose-700 border-rose-200',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
        default => ($value === null ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'),
    };
@endphp

<div
    x-data="{ flipped: false }"
    @click="flipped = !flipped"
    class="perspective-1000 w-full h-[185px] cursor-pointer select-none group"
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
            class="absolute inset-0 backface-hidden p-4 rounded-xl bg-white border border-slate-200 shadow-2xs group-hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden"
        >
            <!-- Card Header: Parameter Name & Status Badge -->
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-slate-800 leading-tight truncate" title="{{ $parameter }}">{{ $parameter }}</h3>
                    <span class="text-[10px] text-slate-400 font-medium block truncate">{{ $sublabel }}</span>
                </div>

                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase border shrink-0 {{ $badgeClass }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <!-- Value Display (Tabular Monospace Anti-Layout-Shift) -->
            <div class="py-1">
                <div class="flex items-baseline gap-1.5">
                    @if($value !== null)
                        <span class="font-tabular text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            {{ number_format((float)$value, 1) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 uppercase font-mono">
                            {{ $unit }}
                        </span>
                    @else
                        <span class="font-tabular text-2xl sm:text-3xl font-extrabold text-slate-300 tracking-tight">
                            —
                        </span>
                        <span class="text-[10px] font-medium text-slate-400 font-mono">
                            Belum Ada Data
                        </span>
                    @endif
                </div>
            </div>

            <!-- Visual Range Bar Position Indicator -->
            <div class="pt-1">
                <x-visual-range-bar
                    :value="$value"
                    :min="$scaleMin"
                    :max="$scaleMax"
                    :idealMin="$idealMin"
                    :idealMax="$idealMax"
                    :unit="$unit"
                    :status="$status"
                />
            </div>
        </div>

        <!-- BACK SIDE: Agronomy Glossary (Dark Green Surface, Legible & Non-Mirrored) -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-3.5 rounded-xl bg-[#0c3c26] text-white border border-emerald-950 shadow-2xs flex flex-col justify-between overflow-hidden"
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
