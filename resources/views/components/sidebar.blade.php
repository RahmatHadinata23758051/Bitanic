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
    $userInitials = $isGuest ? 'U' : strtoupper(substr($user->name ?? 'User', 0, 2));
    $userRoleLabel = match ($role) {
        'guru' => 'Guru Pembimbing',
        'superuser', 'admin' => 'Admin Lab IoT',
        'siswa' => 'Siswa Praktikan',
        default => 'Pengunjung',
    };
@endphp

<!-- Backdrop (mobile off-canvas only) -->
<div
    x-show="sidebarOpen"
    @click="sidebarOpen = false"
    x-transition:enter="transition-opacity ease-linear duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-30 lg:hidden"
    aria-hidden="true"
></div>

<!-- Floating Rounded Collapsible Sidebar -->
<aside
    id="app-sidebar"
    :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
        'w-72'
    ]"
    class="fixed z-40 bg-white border border-slate-200/90 shadow-sm flex flex-col justify-between transition-all duration-200 ease-in-out
           inset-y-0 left-0 lg:inset-y-auto lg:top-3 lg:bottom-3 lg:left-3 lg:h-[calc(100vh-1.5rem)] lg:rounded-2xl overflow-hidden"
    role="navigation"
    aria-label="Navigasi utama Bitanic"
    ref="sidebar"
>
    <!-- Brand Header Area -->
    <div class="p-3 border-b border-slate-100 shrink-0">
        <!-- Expanded Desktop / Mobile Brand Box -->
        <div x-show="!sidebarCollapsed" class="p-2.5 rounded-xl bg-[#0c3c26] text-white flex items-center justify-between shadow-xs">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 min-w-0" aria-label="Bitanic - Kembali ke dashboard">
                <img
                    src="{{ asset('logo.png') }}"
                    alt="Bitanic"
                    class="h-6 w-auto max-w-[130px] object-contain"
                />
            </a>

            <div class="flex items-center gap-1">
                <!-- Desktop Collapse Button -->
                <button
                    type="button"
                    @click="toggleSidebarCollapse()"
                    class="hidden lg:flex p-1.5 rounded-lg text-emerald-200/80 hover:text-white hover:bg-white/10 transition-colors"
                    title="Ciutkan navigasi"
                    aria-label="Ciutkan navigasi"
                >
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>

                <!-- Mobile Drawer Close Button -->
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    class="lg:hidden p-1.5 rounded-lg text-emerald-200/80 hover:text-white hover:bg-white/10 transition-colors"
                    aria-label="Tutup navigasi"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Collapsed Desktop Brand Box -->
        <div x-show="sidebarCollapsed" class="hidden lg:flex flex-col items-center gap-2">
            <a href="{{ url('/') }}" class="w-12 h-10 rounded-xl bg-[#0c3c26] flex items-center justify-center p-1.5 shadow-xs" title="Bitanic - Kembali ke dashboard" aria-label="Bitanic - Kembali ke dashboard">
                <img
                    src="{{ asset('logo.png') }}"
                    alt="Bitanic"
                    class="h-4.5 w-auto object-contain"
                />
            </a>
            <button
                type="button"
                @click="toggleSidebarCollapse()"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                title="Perluas navigasi"
                aria-label="Perluas navigasi"
            >
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Official Product Descriptor -->
        <p x-show="!sidebarCollapsed" class="text-[11px] text-slate-500 font-medium tracking-tight mt-1.5 px-1 truncate">
            LMS Vokasi &amp; IoT Pertanian
        </p>
    </div>

    <!-- Navigation Links Container -->
    <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-4" role="list" aria-label="Menu navigasi">
        <!-- Section: Telemetri & Workstation -->
        <div class="space-y-1" role="listitem">
            <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                Telemetri &amp; Workstation
            </p>
            <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

            <!-- Dashboard -->
            <a
                href="{{ url('/') }}"
                title="Dashboard"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('/') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('/') ? 'page' : 'false' }}"
            >
                <i data-lucide="layout-dashboard" class="w-4.5 h-4.5 shrink-0 {{ Request::is('/') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Dashboard</span>
            </a>

            <!-- Stasiun Meja Praktik -->
            <a
                href="{{ url('/iot-monitoring') }}"
                title="Stasiun Meja Praktik"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('iot*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('iot*') ? 'page' : 'false' }}"
            >
                <i data-lucide="gauge" class="w-4.5 h-4.5 shrink-0 {{ Request::is('iot*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Stasiun Meja Praktik</span>
            </a>

            @if($canViewMedia)
                <!-- Media Tanam & Plot -->
                <a
                    href="{{ url('/media') }}"
                    title="Media Tanam & Plot"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('media*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('media*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="sprout" class="w-4.5 h-4.5 shrink-0 {{ Request::is('media*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Media Tanam &amp; Plot</span>
                </a>
            @endif

            <!-- Analitik Agronomi -->
            <a
                href="{{ url('/analytics') }}"
                title="Analitik Agronomi"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('analytics*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('analytics*') ? 'page' : 'false' }}"
            >
                <i data-lucide="line-chart" class="w-4.5 h-4.5 shrink-0 {{ Request::is('analytics*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Analitik Agronomi</span>
            </a>

            <!-- Riwayat & Ekspor Log -->
            <a
                href="{{ url('/telemetry/history') }}"
                title="Riwayat & Ekspor Log"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('telemetry/history*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('telemetry/history*') ? 'page' : 'false' }}"
            >
                <i data-lucide="database" class="w-4.5 h-4.5 shrink-0 {{ Request::is('telemetry/history*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Riwayat &amp; Ekspor Log</span>
            </a>
        </div>

        <!-- Section: Kurikulum & Pembelajaran -->
        <div class="space-y-1" role="listitem">
            <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                Kurikulum &amp; Pembelajaran
            </p>
            <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

            <!-- 12 Modul Praktikum -->
            <a
                href="{{ url('/modules') }}"
                title="12 Modul Praktikum"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('modules*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('modules*') ? 'page' : 'false' }}"
            >
                <i data-lucide="book-open" class="w-4.5 h-4.5 shrink-0 {{ Request::is('modules*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">12 Modul Praktikum</span>
            </a>

            @if($canViewSchedules)
                <!-- Jadwal & Meja Praktik -->
                <a
                    href="{{ url('/schedules') }}"
                    title="Jadwal & Meja Praktik"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('schedules*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('schedules*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="calendar" class="w-4.5 h-4.5 shrink-0 {{ Request::is('schedules*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Jadwal &amp; Meja Praktik</span>
                </a>
            @endif

            @if($canViewClasses)
                <!-- Kelas & Kelompok -->
                <a
                    href="{{ url('/classes') }}"
                    title="Kelas & Kelompok"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('classes*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('classes*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="graduation-cap" class="w-4.5 h-4.5 shrink-0 {{ Request::is('classes*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Kelas &amp; Kelompok</span>
                </a>
            @endif

            @if($canViewReports)
                <!-- Laporan LKS Siswa -->
                <a
                    href="{{ url('/reports') }}"
                    title="Laporan LKS Siswa"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('reports*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('reports*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="file-check" class="w-4.5 h-4.5 shrink-0 {{ Request::is('reports*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Laporan LKS Siswa</span>
                </a>
            @endif

            @if($canViewAssessment)
                <!-- Rapor Kompetensi -->
                <a
                    href="{{ url('/assessment') }}"
                    title="Rapor Kompetensi"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('assessment*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('assessment*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="award" class="w-4.5 h-4.5 shrink-0 {{ Request::is('assessment*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Rapor Kompetensi</span>
                </a>
            @endif
        </div>

        <!-- Section: Lab & Perangkat -->
        <div class="space-y-1" role="listitem">
            <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                Lab &amp; Perangkat
            </p>
            <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

            <!-- Armada Trainer Kit -->
            <a
                href="{{ url('/trainer-kits') }}"
                title="Armada Trainer Kit"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ Request::is('trainer-kits*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ Request::is('trainer-kits*') ? 'page' : 'false' }}"
            >
                <i data-lucide="cpu" class="w-4.5 h-4.5 shrink-0 {{ Request::is('trainer-kits*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Armada Trainer Kit</span>
            </a>

            <!-- Raw RS485 Debugger -->
            <a
                href="{{ url('/raw-logs') }}"
                title="Raw RS485 Debugger"
                :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                class="flex items-center rounded-xl transition-colors group
                    {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                aria-current="{{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'page' : 'false' }}"
            >
                <i data-lucide="terminal" class="w-4.5 h-4.5 shrink-0 {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                <span x-show="!sidebarCollapsed" class="text-sm truncate">Raw RS485 Debugger</span>
            </a>

            @if($canViewUsers)
                <!-- Data Siswa & Guru -->
                <a
                    href="{{ url('/users-data') }}"
                    title="Data Siswa & Guru"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('users-data*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('users-data*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="users" class="w-4.5 h-4.5 shrink-0 {{ Request::is('users-data*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Data Siswa &amp; Guru</span>
                </a>
            @endif

            @if($canViewSettings)
                <!-- Pengaturan & Audit -->
                <a
                    href="{{ url('/settings') }}"
                    title="Pengaturan & Audit"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('settings*') ? 'bg-emerald-600 text-white font-semibold shadow-xs shadow-emerald-600/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('settings*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="settings" class="w-4.5 h-4.5 shrink-0 {{ Request::is('settings*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Pengaturan &amp; Audit</span>
                </a>
            @endif
        </div>
    </nav>

    <!-- Sidebar Footer: Profile & Logout -->
    <div class="p-3 border-t border-slate-100 bg-slate-50/70 shrink-0">
        <!-- Expanded Profile View -->
        <div x-show="!sidebarCollapsed" class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs shrink-0">
                    {{ $userInitials }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-900 truncate">
                        {{ $isGuest ? 'Pengunjung' : ($user->name ?? 'Pengguna Bitanic') }}
                    </p>
                    <p class="text-[11px] text-slate-500 truncate">
                        {{ $userRoleLabel }}
                    </p>
                </div>
            </div>

            @if(Auth::check())
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Keluar"
                        aria-label="Keluar dari Bitanic"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            @else
                <a
                    href="{{ url('/login') }}"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors shrink-0"
                    title="Masuk"
                    aria-label="Masuk ke Bitanic"
                >
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                </a>
            @endif
        </div>

        <!-- Collapsed Profile View -->
        <div x-show="sidebarCollapsed" class="hidden lg:flex flex-col items-center gap-2">
            <div
                class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs shrink-0"
                title="{{ $isGuest ? 'Pengunjung' : ($user->name ?? 'Pengguna Bitanic') }} ({{ $userRoleLabel }})"
            >
                {{ $userInitials }}
            </div>

            @if(Auth::check())
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Keluar"
                        aria-label="Keluar dari Bitanic"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            @else
                <a
                    href="{{ url('/login') }}"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                    title="Masuk"
                    aria-label="Masuk ke Bitanic"
                >
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                </a>
            @endif
        </div>
    </div>
</aside>
