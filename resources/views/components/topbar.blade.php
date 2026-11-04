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
        <!-- Trainer Kit Active Selector / Pulse -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
            <div class="flex items-center gap-1.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="font-mono font-bold text-slate-800">
                    {{ $activeDevice->code ?? 'BITANIC-TK-01' }}
                </span>
            </div>

            <span class="text-slate-300">|</span>

            <span class="hidden md:inline text-slate-600 font-medium">
                {{ $activeDevice->name ?? 'Meja Praktik 01' }}
            </span>

            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                Live
            </span>
        </div>

        <!-- Telemetry Signal & Battery (Clean Micro-indicators) -->
        <div class="hidden lg:flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 text-xs font-mono">
            <span class="flex items-center gap-1" title="Signal Strength">
                <i data-lucide="wifi" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>-54 dBm</span>
            </span>
            <span class="text-slate-300">|</span>
            <span class="flex items-center gap-1" title="Kit Battery Level">
                <i data-lucide="battery-charging" class="w-3.5 h-3.5 text-slate-700"></i>
                <span>94%</span>
            </span>
        </div>

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
