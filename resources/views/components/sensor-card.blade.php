@props([
    'parameter' => 'pH Tanah',
    'value' => 6.5,
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
    class="perspective-1000 w-full h-[230px]"
>
    <!-- 3D Flipper Box -->
    <div
        class="relative w-full h-full transform-style-3d transition-transform duration-500 ease-in-out"
        :class="flipped ? 'rotate-y-180' : ''"
    >
        <!-- FRONT SIDE: Live Telemetry Metric -->
        <div
            class="absolute inset-0 backface-hidden p-5 rounded-xl bg-white border border-slate-200 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between"
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
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $badgeClass }}">
                        {{ $statusLabel }}
                    </span>
                    <button
                        type="button"
                        @click="flipped = true"
                        class="p-1 rounded-md text-slate-400 hover:text-emerald-700 hover:bg-slate-100 transition-colors"
                        title="Klik untuk melihat Glosarium Agronomi"
                        aria-label="Lihat Glosarium"
                    >
                        <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>

            <!-- Value Display (Tabular Monospace Anti-Layout-Shift) -->
            <div class="my-auto cursor-pointer" @click="flipped = true">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-tabular text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                        {{ number_format((float)$value, 1) }}
                    </span>
                    <span class="text-xs font-bold text-slate-500 uppercase font-mono">
                        {{ $unit }}
                    </span>
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
            class="absolute inset-0 backface-hidden rotate-y-180 p-5 rounded-xl bg-slate-900 text-white border border-slate-800 shadow-sm flex flex-col justify-between"
        >
            <!-- Back Header -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="book-open" class="w-4 h-4 text-emerald-400"></i>
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Glosarium: {{ $parameter }}</span>
                </div>
                <button
                    type="button"
                    @click="flipped = false"
                    class="p-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                    title="Kembali ke Live Sensor"
                    aria-label="Tutup Glosarium"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Glossary Content -->
            <div class="space-y-2 text-[11px] overflow-y-auto pr-1 my-auto leading-relaxed">
                <p class="text-slate-300">{{ $definition }}</p>
                <div class="p-2 rounded bg-slate-800/80 border border-slate-700/60 space-y-1">
                    <div class="text-[10px] text-emerald-300 font-bold uppercase tracking-wider">Target Ideal SMK:</div>
                    <div class="text-slate-200 font-mono text-[10px]">{{ $idealDesc }}</div>
                </div>
                <div class="space-y-1 text-[10px]">
                    <div class="text-amber-400">⚠️ {{ $lowRisk }}</div>
                    <div class="text-rose-400">🚫 {{ $highRisk }}</div>
                </div>
            </div>

            <!-- Back Footer Button -->
            <button
                type="button"
                @click="flipped = false"
                class="w-full py-1.5 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-colors flex items-center justify-center gap-1.5"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Live Sensor</span>
            </button>
        </div>
    </div>
</div>
