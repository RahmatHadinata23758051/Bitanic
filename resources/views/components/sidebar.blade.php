@php
    $user = Auth::user();
    $role = $user->role ?? 'siswa';
    $currentRoute = Route::currentRouteName() ?? '';
    // Sisa kuota AI (dari subscription atau default 15)
    $activeSub = $user ? \App\Models\UserSubscription::with('plan')->where('user_id', $user->id)->where('status', 'active')->latest()->first() : null;
    $remainingQuota = $activeSub ? max(0, ($activeSub->plan->quota ?? 50) - $activeSub->used_quota) : 15;
    $maxQuota = $activeSub ? ($activeSub->plan->quota ?? 50) : 15;
    $quotaPercent = $maxQuota > 0 ? min(100, round(($remainingQuota / $maxQuota) * 100)) : 100;
@endphp

<aside
    x-cloak
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 w-72 bg-white border-r border-slate-200 transition-transform duration-200 ease-in-out flex flex-col justify-between"
>
    <!-- Brand Header -->
    <div class="h-16 px-6 border-b border-slate-200 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-600 flex items-center justify-center text-white shadow-sm">
                <i data-lucide="sprout" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-base font-extrabold tracking-tight text-slate-900 font-sans">BITANIC</span>
                    <span class="px-1.5 py-0.5 text-[10px] font-bold uppercase rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                        SMK 4.0
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 font-medium">Precision Agri-LMS</p>
            </div>
        </a>

        <!-- Close button mobile -->
        <button
            @click="sidebarOpen = false"
            class="lg:hidden p-1.5 rounded-md text-slate-500 hover:text-slate-700 hover:bg-slate-100"
            aria-label="Tutup sidebar"
        >
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Navigation Links Container -->
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-6">
        <!-- Role Status Chip -->
        <div class="px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-2.5 h-2.5 rounded-full {{ $role === 'guru' ? 'bg-indigo-500' : ($role === 'superuser' ? 'bg-amber-500' : 'bg-emerald-500') }}"></div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Mode Akses</span>
                    <span class="text-xs font-bold text-slate-800 uppercase">
                        {{ $role === 'guru' ? 'Guru Pembimbing' : ($role === 'superuser' ? 'Admin Lab IoT' : 'Siswa Praktikan') }}
                    </span>
                </div>
            </div>
            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-600 font-medium">
                {{ $user->email ?? 'guest@smk.id' }}
            </span>
        </div>

        <!-- Section 1: Pusat Kendali & Telemetri -->
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Telemetri & Workstation</p>

            <a
                href="{{ url('/') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('/') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="layout-dashboard" class="w-4 h-4 {{ Request::is('/') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Pusat Kendali Lab</span>
            </a>

            <a
                href="{{ url('/iot-monitoring') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('iot*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="gauge" class="w-4 h-4 {{ Request::is('iot*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Stasiun Meja Praktik</span>
            </a>

            <a
                href="{{ url('/analytics') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('analytics*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="line-chart" class="w-4 h-4 {{ Request::is('analytics*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Analitik Agronomi</span>
            </a>

            <a
                href="{{ url('/telemetry/history') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('telemetry/history*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="database" class="w-4 h-4 {{ Request::is('telemetry/history*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Riwayat & Ekspor Log</span>
            </a>
        </div>

        <!-- Section 2: Kurikulum & Pembelajaran SMK -->
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kurikulum & Pembelajaran</p>

            <a
                href="{{ url('/modules') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('modules*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="book-open" class="w-4 h-4 {{ Request::is('modules*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>12 Modul Praktikum</span>
            </a>

            <a
                href="{{ url('/schedules') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('schedules*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="calendar" class="w-4 h-4 {{ Request::is('schedules*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Jadwal & Meja Praktik</span>
            </a>

            <a
                href="{{ url('/reports') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('reports*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="file-check" class="w-4 h-4 {{ Request::is('reports*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Laporan LKS Siswa</span>
            </a>

            <a
                href="{{ url('/assessment') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('assessment*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="award" class="w-4 h-4 {{ Request::is('assessment*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Rapor Kompetensi</span>
            </a>
        </div>

        <!-- Section 3: Manajemen Lab & Hardware Fleet -->
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Lab & Perangkat</p>

            <a
                href="{{ url('/trainer-kits') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('trainer-kits*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="cpu" class="w-4 h-4 {{ Request::is('trainer-kits*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Armada Trainer Kit</span>
            </a>

            <a
                href="{{ url('/raw-logs') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('raw-logs*') || Request::is('rawlogs*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="terminal" class="w-4 h-4 {{ Request::is('raw-logs*') || Request::is('rawlogs*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Raw RS485 Debugger</span>
            </a>

            <a
                href="{{ url('/users-data') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('users-data*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="users" class="w-4 h-4 {{ Request::is('users-data*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Data Siswa & Guru</span>
            </a>

            <a
                href="{{ url('/settings') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ Request::is('settings*') ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-600 font-bold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
            >
                <i data-lucide="settings" class="w-4 h-4 {{ Request::is('settings*') ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                <span>Pengaturan & Audit</span>
            </a>
        </div>
    </div>

    <!-- Sidebar Footer: AI Quota & User Profile -->
    <div class="p-4 border-t border-slate-200 bg-slate-50/50 space-y-4">
        <!-- Kuota AI Gemini Widget -->
        <div class="p-3 rounded-lg bg-white border border-slate-200 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-600 flex items-center gap-1.5">
                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500"></i>
                    <span>Konsultan AI Gemini</span>
                </span>
                <span class="font-mono font-bold text-slate-800">{{ $remainingQuota }} / {{ $maxQuota }}</span>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                <div
                    class="h-full bg-emerald-600 rounded-full transition-all duration-300"
                    style="width: {{ $quotaPercent }}%"
                ></div>
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-500">
                <span>Respon < 15 dtk</span>
                <button type="button" @click="$dispatch('open-ai-drawer')" class="font-semibold text-emerald-700 hover:text-emerald-800">Buka AI →</button>
            </div>
        </div>

        <!-- Profile Bar -->
        <div class="flex items-center justify-between pt-1">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-700 font-bold text-xs uppercase shrink-0">
                    {{ substr($user->name ?? 'User', 0, 2) }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $user->name ?? 'Pengguna Bitanic' }}</p>
                    <p class="text-[11px] text-slate-500 truncate capitalize">{{ $role }}</p>
                </div>
            </div>

            @if(Auth::check())
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="p-1.5 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Keluar"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            @else
                <a
                    href="{{ url('/login') }}"
                    class="p-1.5 rounded-md text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                    title="Masuk"
                >
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                </a>
            @endif
        </div>
    </div>
</aside>
