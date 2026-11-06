@props([
    'activeDevice' => null,
    'devices' => [],
    'criticalAlertsCount' => 0,
])

<header class="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30">
    <!-- Left: Mobile Toggle & Page Context -->
    <div class="flex items-center gap-3 sm:gap-4">
        <button
            @click="sidebarOpen = !sidebarOpen"
            class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 lg:hidden"
            aria-label="Toggle Sidebar"
        >
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <div class="hidden sm:flex items-center gap-2 text-xs font-semibold text-slate-500">
            <span class="text-slate-900 font-bold">SMK Pertanian 4.0</span>
            <span>/</span>
            <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 font-mono">Lab Precision Agritech</span>
        </div>
    </div>

    <!-- Center / Right: Hardware Trainer Kit Status & Quick Actions -->
    <div class="flex items-center gap-2 sm:gap-3">
        <!-- Trainer Kit status reflects a real selected device only. -->
        @if($activeDevice)
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                <span class="font-mono font-bold text-slate-800">{{ $activeDevice->code }}</span>
                <span class="text-slate-300">|</span>
                <span class="hidden md:inline text-slate-600 font-medium">{{ $activeDevice->name }}</span>
            </div>
        @else
            <div class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-500" role="status">
                Tidak ada trainer kit terdaftar
            </div>
        @endif

        <!-- Anomaly Alert Bell if any -->
        @if($criticalAlertsCount > 0)
            <a
                href="#anomalies"
                class="relative p-2 rounded-lg text-rose-600 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors"
                title="{{ $criticalAlertsCount }} Anomali Sensor Memerlukan Tindakan"
            >
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-600 text-white font-mono text-[10px] font-bold flex items-center justify-center">
                    {{ $criticalAlertsCount }}
                </span>
            </a>
        @endif

        <!-- AI Consultant Trigger Button -->
        <button
            type="button"
            @click="$dispatch('open-ai-drawer')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors"
        >
            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
            <span class="hidden sm:inline">Analisis AI Gemini</span>
            <span class="sm:hidden">AI</span>
        </button>
    </div>
</header>
