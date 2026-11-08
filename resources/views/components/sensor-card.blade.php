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
    'lowRisk' => 'pH < 5.5: Fiksasi fosfor, keracunan aluminium (Al) & mangan (Mn), tanaman kerdil.',
    'highRisk' => 'pH > 7.5: Klorosis akibat defisiensi zat besi (Fe), seng (Zn), dan tembaga (Cu).'
])

@php
    $badgeClass = match($status) {
        'critical' => 'bg-rose-50 text-rose-700 border-rose-200',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
        default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };

    $iconBgClass = match($status) {
        'critical' => 'bg-rose-50 text-rose-600',
        'warning' => 'bg-amber-50 text-amber-600',
        default => 'bg-emerald-50 text-emerald-600',
    };
@endphp

<div
    x-data="{ flipped: false }"
    @click="flipped = !flipped"
    class="perspective-1000 w-full h-[225px] cursor-pointer select-none group"
    role="button"
    tabindex="0"
    @keydown.enter="flipped = !flipped"
    @keydown.space.prevent="flipped = !flipped"
    :aria-label="'Kartu parameter ' + '{{ $parameter }}' + '. Klik untuk membalik glosarium.'"
>
    <!-- 3D Flipper Box -->
    <div
        class="relative w-full h-full transform-style-3d transition-transform duration-500 ease-in-out"
        :class="flipped ? 'rotate-y-180' : ''"
    >
        <!-- FRONT SIDE: Live Telemetry Metric -->
        <div
            class="absolute inset-0 backface-hidden p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs group-hover:border-slate-300 group-hover:shadow-xs transition-all flex flex-col justify-between"
        >
            <!-- Card Header -->
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg {{ $iconBgClass }} flex items-center justify-center shrink-0">
                        <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 leading-tight">{{ $parameter }}</h4>
                        <span class="text-[10px] text-slate-400 font-medium">{{ $sublabel }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $badgeClass }}">
                        {{ $statusLabel }}
                    </span>
                    <span
                        class="p-1 rounded-md text-slate-300 group-hover:text-emerald-600 transition-colors"
                        title="Klik untuk membalik kartu ke Glosarium Agronomi"
                    >
                        <i data-lucide="help-circle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
            </div>

            <!-- Value Display (Tabular Monospace Anti-Layout-Shift) -->
            <div class="my-auto py-1">
                <div class="flex items-baseline gap-1.5">
                    @if($value !== null)
                        <span class="font-tabular text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            {{ number_format((float)$value, 1) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 uppercase font-mono">
                            {{ $unit }}
                        </span>
                    @else
                        <span class="font-tabular text-3xl sm:text-4xl font-extrabold text-slate-300 tracking-tight">
                            —
                        </span>
                        <span class="text-xs font-medium text-slate-400 font-mono">
                            Belum Ada Data
                        </span>
                    @endif
                </div>
            </div>

            <!-- Visual Range Bar Position Indicator -->
            <div>
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

        <!-- BACK SIDE: Agronomy Glossary & Standards -->
        <div
            class="absolute inset-0 backface-hidden rotate-y-180 p-4 sm:p-5 rounded-2xl bg-slate-900 text-white border border-slate-800 shadow-xs flex flex-col justify-between overflow-hidden"
        >
            <!-- Back Header -->
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="book-open" class="w-3.5 h-3.5 text-emerald-400"></i>
                    <span class="text-xs font-bold text-emerald-400 tracking-tight">Glosarium: {{ $parameter }}</span>
                </div>
                <span class="text-[10px] text-slate-400 font-mono flex items-center gap-1">
                    <i data-lucide="rotate-ccw" class="w-3 h-3 text-slate-400"></i>
                    <span>Tutup</span>
                </span>
            </div>

            <!-- Glossary Content -->
            <div class="space-y-2 text-[11px] overflow-y-auto pr-1 my-auto leading-relaxed">
                <p class="text-slate-300 text-[11px] leading-snug">{{ $definition }}</p>
                <div class="p-2 rounded-lg bg-slate-800/80 border border-slate-700/60 space-y-0.5">
                    <div class="text-[10px] text-emerald-300 font-bold uppercase tracking-wider">Target Ideal SMK:</div>
                    <div class="text-slate-200 font-mono text-[10px]">{{ $idealDesc }}</div>
                </div>
                <div class="space-y-1 text-[10px]">
                    <div class="text-amber-300 truncate">⚠️ {{ $lowRisk }}</div>
                    <div class="text-rose-300 truncate">🚫 {{ $highRisk }}</div>
                </div>
            </div>

            <!-- Back Footer Prompt -->
            <div class="pt-1.5 border-t border-slate-800/80 text-center">
                <span class="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                    <i data-lucide="mouse-pointer-click" class="w-3 h-3 text-slate-400"></i>
                    <span>Klik di mana saja untuk kembali</span>
                </span>
            </div>
        </div>
    </div>
</div>
