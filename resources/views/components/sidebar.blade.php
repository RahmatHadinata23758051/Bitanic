@php
    $user = Auth::user();
    $isGuest = ! $user;
    $role = $user?->role ?? 'guest';

    // Role-aware navigation visibility (mirrors route middleware)
    $canViewDashboard = true; // public
    $canViewIoT = true; // public
    $canViewAnalytics = true; // public
    $canViewTelemetry = true; // public
    $canViewModules = true; // public
    $canViewTrainerKits = true; // public
    $canViewRawLogs = true; // public

    $canViewSchedules = $user !== null; // auth required
    $canViewMedia = $user && in_array($role, ['guru', 'superuser', 'admin'], true);
    $canViewReports = $user !== null; // auth required
    $canViewClasses = $user && in_array($role, ['guru', 'superuser', 'admin'], true);
    $canViewAssessment = $user !== null; // auth required
    $canViewUsers = $user && in_array($role, ['guru', 'superuser', 'admin'], true);
    $canViewSettings = $user && in_array($role, ['superuser', 'admin'], true);

    $currentRoute = Route::currentRouteName() ?? '';
@endphp

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 w-72 bg-white border-r border-slate-200 transition-transform duration-200 ease-in-out flex flex-col justify-between"
    role="navigation"
    aria-label="Navigasi utama Bitanic"
    ref="sidebar"
>
    <!-- Backdrop (mobile only) -->
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/40 z-[-1] lg:hidden"
        aria-hidden="true"
    ></div>

    <!-- Brand Header -->
    <div class="h-16 px-4 border-b border-slate-200 flex items-center justify-between shrink-0">
        <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="Bitanic - Kembali ke dashboard">
            <div class="w-9 h-9 rounded-lg bg-emerald-600 flex items-center justify-center text-white shadow-sm shrink-0">
                <i data-lucide="sprout" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold tracking-tight text-slate-900 font-sans truncate">BITANIC</span>
                    <span class="px-2 py-0.5 text-[10px] font-medium rounded bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0 whitespace-nowrap">
                        SMK 4.0
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 font-medium truncate">Precision Agri-LMS</p>
            </div>
        </a>

        <!-- Close button mobile -->
        <button
            @click="sidebarOpen = false"
            class="lg:hidden p-1.5 rounded-md text-slate-500 hover:text-slate-700 hover:bg-slate-100"
            aria-label="Tutup navigasi"
        >
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Navigation Links Container -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5" role="list" aria-label="Menu navigasi">
        <!-- Role Status Chip (authenticated users only) -->
        @if(! $isGuest)
            <div class="px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between" role="status" aria-live="polite">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ in_array($role, ['superuser', 'admin'], true) ? 'bg-amber-500' : ($role === 'guru' ? 'bg-indigo-500' : 'bg-emerald-500') }}" aria-hidden="true"></div>
                    <div class="min-w-0">
                        <span class="text-label text-slate-400 tracking-wide block">Peran</span>
                        <span class="text-sm font-semibold text-slate-800 truncate block">
                            {{ $role === 'guru' ? 'Guru Pembimbing' : (in_array($role, ['superuser', 'admin'], true) ? 'Admin Lab IoT' : 'Siswa Praktikan') }}
                        </span>
                    </div>
                </div>
                <span class="text-supporting px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-600 truncate shrink-0 max-w-36">
                    {{ $user->email }}
                </span>
            </div>
        @endif

        <!-- Section: Pusat Kendali & Telemetri (public) -->
        <div class="space-y-1" role="listitem">
            <p class="px-3 text-[11px] font-semibold text-slate-400 tracking-wider" aria-hidden="true">Telemetri & Workstation</p>

            <a
                href="{{ url('/') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('/') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('/') ? 'page' : 'false' }}"
            >
                <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0 {{ Request::is('/') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>

            <a
                href="{{ url('/iot-monitoring') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('iot*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('iot*') ? 'page' : 'false' }}"
            >
                <i data-lucide="gauge" class="w-4 h-4 shrink-0 {{ Request::is('iot*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Stasiun Meja Praktik</span>
            </a>

            @if($canViewMedia)
                <a
                    href="{{ url('/media') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('media*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('media*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="sprout" class="w-4 h-4 shrink-0 {{ Request::is('media*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Media Tanam & Plot</span>
                </a>
            @endif

            <a
                href="{{ url('/analytics') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('analytics*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('analytics*') ? 'page' : 'false' }}"
            >
                <i data-lucide="line-chart" class="w-4 h-4 shrink-0 {{ Request::is('analytics*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Analitik Agronomi</span>
            </a>

            <a
                href="{{ url('/telemetry/history') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('telemetry/history*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('telemetry/history*') ? 'page' : 'false' }}"
            >
                <i data-lucide="database" class="w-4 h-4 shrink-0 {{ Request::is('telemetry/history*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Riwayat & Ekspor Log</span>
            </a>
        </div>

        <!-- Section: Kurikulum & Pembelajaran (role-filtered) -->
        <div class="space-y-1" role="listitem">
            <p class="px-3 text-[11px] font-semibold text-slate-400 tracking-wider" aria-hidden="true">Kurikulum & Pembelajaran</p>

            <a
                href="{{ url('/modules') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('modules*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('modules*') ? 'page' : 'false' }}"
            >
                <i data-lucide="book-open" class="w-4 h-4 shrink-0 {{ Request::is('modules*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>12 Modul Praktikum</span>
            </a>

            @if($canViewSchedules)
                <a
                    href="{{ url('/schedules') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('schedules*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('schedules*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="calendar" class="w-4 h-4 shrink-0 {{ Request::is('schedules*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Jadwal & Meja Praktik</span>
                </a>
            @endif

            @if($canViewClasses)
                <a
                    href="{{ url('/classes') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('classes*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('classes*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="graduation-cap" class="w-4 h-4 shrink-0 {{ Request::is('classes*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Kelas & Kelompok</span>
                </a>
            @endif

            @if($canViewReports)
                <a
                    href="{{ url('/reports') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('reports*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('reports*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="file-check" class="w-4 h-4 shrink-0 {{ Request::is('reports*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Laporan LKS Siswa</span>
                </a>
            @endif

            @if($canViewAssessment)
                <a
                    href="{{ url('/assessment') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('assessment*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('assessment*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="award" class="w-4 h-4 shrink-0 {{ Request::is('assessment*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Rapor Kompetensi</span>
                </a>
            @endif
        </div>

        <!-- Section: Lab & Perangkat (role-filtered) -->
        <div class="space-y-1" role="listitem">
            <p class="px-3 text-[11px] font-semibold text-slate-400 tracking-wider" aria-hidden="true">Lab & Perangkat</p>

            <a
                href="{{ url('/trainer-kits') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('trainer-kits*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('trainer-kits*') ? 'page' : 'false' }}"
            >
                <i data-lucide="cpu" class="w-4 h-4 shrink-0 {{ Request::is('trainer-kits*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Armada Trainer Kit</span>
            </a>

            <a
                href="{{ url('/raw-logs') }}"
                class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                    {{ Request::is('raw-logs*') || Request::is('rawlogs*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'page' : 'false' }}"
            >
                <i data-lucide="terminal" class="w-4 h-4 shrink-0 {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                <span>Raw RS485 Debugger</span>
            </a>

            @if($canViewUsers)
                <a
                    href="{{ url('/users-data') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('users-data*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('users-data*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="users" class="w-4 h-4 shrink-0 {{ Request::is('users-data*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Data Siswa & Guru</span>
                </a>
            @endif

            @if($canViewSettings)
                <a
                    href="{{ url('/settings') }}"
                    class="text-nav flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors
                        {{ Request::is('settings*') ? 'bg-emerald-100 text-emerald-900 font-semibold' : 'text-slate-700 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('settings*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="settings" class="w-4 h-4 shrink-0 {{ Request::is('settings*') ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>Pengaturan & Audit</span>
                </a>
            @endif
        </div>
    </nav>

    <!-- Sidebar Footer: User Profile only (no fabricated AI quota) -->
    <div class="p-4 border-t border-slate-200 bg-slate-50/50 space-y-3 shrink-0">
        <!-- Profile Bar -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 font-semibold text-sm shrink-0">
                    {{ $isGuest ? 'U' : substr($user->name ?? 'User', 0, 2) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $isGuest ? 'Pengunjung' : ($user->name ?? 'Pengguna Bitanic') }}</p>
                    <p class="text-[12px] text-slate-500 truncate capitalize">{{ $role === 'guru' ? 'Guru Pembimbing' : (in_array($role, ['superuser', 'admin'], true) ? 'Admin Lab IoT' : ($role === 'siswa' ? 'Siswa Praktikan' : 'Pengunjung')) }}</p>
                </div>
            </div>

            @if(Auth::check())
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Keluar"
                        aria-label="Keluar dari Bitanic"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            @else
                <a
                    href="{{ url('/login') }}"
                    class="p-2 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                    title="Masuk"
                    aria-label="Masuk ke Bitanic"
                >
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                </a>
            @endif
        </div>
    </div>
</aside>