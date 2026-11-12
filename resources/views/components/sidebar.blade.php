@php
    $user = Auth::user();
    $isGuest = ! $user;
    $role = $user?->role ?? 'guest';

    $isSuperuser = $role === 'superuser';
    $isGuru = $role === 'guru';
    $isSchoolAdmin = in_array($role, ['admin', 'admin_sekolah'], true);
    $isSiswa = $role === 'siswa';

    // Role-aware navigation visibility (mirrors route middleware & POV levels)
    $canViewDashboard = true; // public
    $canViewIoT = ! $isSuperuser; // Superuser accesses devices via Fleet Kit or School Hub
    $canViewAnalytics = ! $isSuperuser && ! $isSiswa;
    $canViewTelemetry = ! $isSuperuser && ! $isSiswa;
    $canViewModules = ! $isSuperuser; // Educational curriculum for school actors
    $canViewTrainerKits = ! $isSuperuser && ! $isSiswa; // Lab kit manager for teachers & school admins
    $canViewRawLogs = ! $isSiswa; // Debugger tool for tech/admin actors
    $canViewSchoolLicense = $isSchoolAdmin; // Status Lisensi Sekolah (Admin Sekolah)

    $canViewSchedules = $user !== null && ! $isSuperuser; // School lab schedules
    $canViewMedia = $user && in_array($role, ['guru', 'admin', 'admin_sekolah'], true);
    $canViewReports = $user !== null && ! $isSuperuser; // Student practicum reports
    $canViewClasses = $user && in_array($role, ['guru', 'admin', 'admin_sekolah'], true);
    $canViewAssessment = $user !== null && ! $isSuperuser; // Student assessments
    $canViewUsers = $user && in_array($role, ['guru', 'admin', 'admin_sekolah'], true);
    $canViewSettings = $user && in_array($role, ['admin', 'admin_sekolah'], true);
    $canViewDataMaster = $user && $isSuperuser;

    $currentRoute = Route::currentRouteName() ?? '';
    $userInitials = $isGuest ? 'U' : strtoupper(substr($user->name ?? 'User', 0, 2));
    $userRoleLabel = match ($role) {
        'guru' => 'Guru Pembimbing',
        'superuser' => 'Superuser Platform',
        'admin_sekolah', 'admin' => 'Admin Sekolah',
        'siswa' => 'Siswa Praktikan',
        default => 'Pengunjung',
    };
@endphp

{{-- Real-Time System Clock (Outside Floating Sidebar - Desktop Only) --}}
<div
    x-data="{
        timeString: '',
        dateString: '',
        shortDateString: '',
        timer: null,
        timeFormatter: null,
        dateFormatter: null,
        shortDateFormatter: null,
        initClock() {
            try {
                this.timeFormatter = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Jakarta',
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                this.dateFormatter = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                this.shortDateFormatter = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    day: 'numeric',
                    month: 'short'
                });
            } catch (e) {
                // Fallback if Intl not available
            }
            this.update();
            this.timer = setInterval(() => this.update(), 1000);
        },
        destroyClock() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        update() {
            const now = new Date();
            if (this.timeFormatter && this.dateFormatter) {
                this.timeString = this.timeFormatter.format(now);
                this.dateString = this.dateFormatter.format(now);
                this.shortDateString = this.shortDateFormatter.format(now);
            } else {
                const pad = (n) => String(n).padStart(2, '0');
                this.timeString = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
                this.dateString = now.toLocaleDateString('id-ID');
                this.shortDateString = `${now.getDate()}/${now.getMonth() + 1}`;
            }
        }
    }"
    x-init="initClock()"
    x-on:unmount.window="destroyClock()"
    :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'"
    class="hidden lg:flex fixed z-40 left-3 top-3 h-16 items-center justify-center bg-white border border-slate-200/90 rounded-2xl shadow-xs transition-all duration-200 ease-in-out px-3 select-none"
    role="timer"
    aria-label="Waktu dan tanggal sistem real-time (WIB)"
>
    <!-- Expanded Clock Display -->
    <div x-show="!sidebarCollapsed" class="flex flex-col items-center justify-center text-center">
        <div class="flex items-baseline gap-1.5 justify-center">
            <span class="font-mono text-base font-bold tracking-tight text-slate-800 font-tabular" x-text="timeString">--:--:--</span>
            <span class="text-[11px] font-semibold text-slate-500 tracking-wide">WIB</span>
        </div>
        <span class="text-xs font-medium text-slate-500 tracking-normal mt-0.5" x-text="dateString">---------</span>
    </div>

    <!-- Collapsed Clock Display -->
    <div x-show="sidebarCollapsed" class="flex flex-col items-center justify-center text-center">
        <span class="font-mono text-xs font-bold tracking-tight text-slate-800 font-tabular" x-text="timeString ? timeString.substring(0, 5) : '--:--'">--:--</span>
        <span class="text-[10px] font-medium text-slate-500 leading-tight" x-text="shortDateString">-- ---</span>
        <span class="text-[9px] font-bold text-slate-400 tracking-wider">WIB</span>
    </div>
</div>

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
           inset-y-0 left-0 lg:inset-y-auto lg:top-[5.25rem] lg:bottom-3 lg:left-3 lg:h-[calc(100vh-6rem)] lg:rounded-2xl overflow-hidden"
    role="navigation"
    aria-label="Navigasi utama Bitanic"
    ref="sidebar"
>
    <!-- Brand Header Area (Clean Centered Logo Composition, No Background Panel) -->
    <div class="p-3 border-b border-slate-100 shrink-0 relative">
        <!-- Mobile Drawer Close Button -->
        <button
            type="button"
            @click="sidebarOpen = false"
            class="lg:hidden absolute top-3 right-3 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            aria-label="Tutup navigasi"
        >
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

        <!-- Expanded Desktop / Mobile Brand View (Centered) -->
        <div x-show="!sidebarCollapsed" class="relative flex flex-col items-center justify-center text-center py-1 px-4">
            <!-- Desktop Collapse Button (Only inside expanded view) -->
            <button
                type="button"
                @click="toggleSidebarCollapse()"
                class="hidden lg:flex absolute top-0 -right-2 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                title="Ciutkan navigasi"
                aria-label="Ciutkan navigasi"
            >
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>

            <a href="{{ url('/') }}" class="inline-flex items-center justify-center" aria-label="Bitanic - Kembali ke dashboard">
                <img
                    src="{{ asset('logo-dark.png') }}"
                    alt="Bitanic"
                    class="h-9 w-auto max-w-[145px] object-contain"
                />
            </a>
            <p class="text-[11px] text-slate-500 font-medium tracking-tight mt-1 truncate">
                LMS Vokasi &amp; IoT Pertanian
            </p>
        </div>

        <!-- Collapsed Desktop Brand View -->
        <div x-show="sidebarCollapsed" class="hidden lg:flex flex-col items-center gap-2 py-1">
            <a href="{{ url('/') }}" class="w-10 h-10 rounded-xl flex items-center justify-center hover:bg-slate-100 transition-colors" title="Bitanic - Kembali ke dashboard" aria-label="Bitanic - Kembali ke dashboard">
                <img
                    src="{{ asset('logo-dark.png') }}"
                    alt="Bitanic"
                    class="h-5 w-auto object-contain"
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
    </div>

    <!-- Navigation Links Container -->
    <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-4" role="list" aria-label="Menu navigasi">
        @if($isSuperuser)
            {{-- ============================================================== --}}
            {{-- SUPERUSER PLATFORM NAVIGATION (HQ Macro View)                  --}}
            {{-- ============================================================== --}}
            <!-- Section: Platform Overview -->
            <div class="space-y-1" role="listitem">
                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                    Platform Pusat
                </p>
                <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                <!-- Dashboard Pusat -->
                <a
                    href="{{ url('/') }}"
                    title="Dashboard Pusat"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('/') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('/') ? 'page' : 'false' }}"
                >
                    <i data-lucide="layout-dashboard" class="w-4.5 h-4.5 shrink-0 {{ Request::is('/') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Dashboard Pusat</span>
                </a>
            </div>

            <!-- Section: Ekosistem Sekolah Mitra -->
            <div class="space-y-1" role="listitem">
                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                    Sekolah &amp; Lisensi
                </p>
                <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                <!-- Sekolah Mitra -->
                <a
                    href="{{ route('master.schools.index') }}"
                    title="Sekolah Mitra"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('master/schools*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('master/schools*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="building-2" class="w-4.5 h-4.5 shrink-0 {{ Request::is('master/schools*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Sekolah Mitra</span>
                </a>

                <!-- Master Wilayah (Peta Sebaran) -->
                <a
                    href="{{ route('master.regions.index') }}"
                    title="Master Wilayah"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('master/regions*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('master/regions*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="map-pin" class="w-4.5 h-4.5 shrink-0 {{ Request::is('master/regions*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Wilayah &amp; Peta</span>
                </a>

                <!-- Master Lisensi LMS -->
                <a
                    href="{{ route('master.licenses.index') }}"
                    title="Master Lisensi LMS"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('master/licenses*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('master/licenses*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="shield-check" class="w-4.5 h-4.5 shrink-0 {{ Request::is('master/licenses*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Lisensi LMS</span>
                </a>
            </div>

            <!-- Section: Hardware & Sistem -->
            <div class="space-y-1" role="listitem">
                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                    Hardware &amp; Sistem
                </p>
                <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                <!-- Master Trainer Kit Fleet -->
                <a
                    href="{{ route('master.trainer-kits.index') }}"
                    title="Master Trainer Kit"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('master/trainer-kits*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('master/trainer-kits*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="cpu" class="w-4.5 h-4.5 shrink-0 {{ Request::is('master/trainer-kits*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Fleet Trainer Kit</span>
                </a>

                <!-- Debugger RS485 & Raw Logs -->
                <a
                    href="{{ url('/raw-logs') }}"
                    title="Debugger RS485"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'page' : 'false' }}"
                >
                    <i data-lucide="terminal" class="w-4.5 h-4.5 shrink-0 {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Debugger RS485</span>
                </a>

                <!-- Pengaturan & Audit Trail -->
                <a
                    href="{{ url('/settings') }}"
                    title="Pengaturan &amp; Audit"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('settings*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('settings*') ? 'page' : 'false' }}"
                >
                    <i data-lucide="settings" class="w-4.5 h-4.5 shrink-0 {{ Request::is('settings*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Pengaturan &amp; Audit</span>
                </a>
            </div>
        @else
            {{-- ============================================================== --}}
            {{-- SCHOOL OPERATIONAL & GUEST NAVIGATION                          --}}
            {{-- ============================================================== --}}
            <!-- Section: IoT & Data -->
            <div class="space-y-1" role="listitem">
                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                    IoT &amp; Data
                </p>
                <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                <!-- Dashboard -->
                <a
                    href="{{ url('/') }}"
                    title="Dashboard"
                    :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                    class="flex items-center rounded-xl transition-colors group
                        {{ Request::is('/') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                    aria-current="{{ Request::is('/') ? 'page' : 'false' }}"
                >
                    <i data-lucide="layout-dashboard" class="w-4.5 h-4.5 shrink-0 {{ Request::is('/') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                    <span x-show="!sidebarCollapsed" class="text-sm truncate">Dashboard</span>
                </a>

                @if($canViewIoT)
                    <!-- Monitoring IoT -->
                    <a
                        href="{{ url('/iot-monitoring') }}"
                        title="Monitoring IoT"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('iot*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('iot*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="gauge" class="w-4.5 h-4.5 shrink-0 {{ Request::is('iot*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Monitoring IoT</span>
                    </a>
                @endif

                @if($canViewMedia)
                    <!-- Media Tanam -->
                    <a
                        href="{{ url('/media') }}"
                        title="Media Tanam"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('media*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('media*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="sprout" class="w-4.5 h-4.5 shrink-0 {{ Request::is('media*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Media Tanam</span>
                    </a>
                @endif

                @if($canViewAnalytics)
                    <!-- Analitik Agronomi -->
                    <a
                        href="{{ url('/analytics') }}"
                        title="Analitik Agronomi"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('analytics*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('analytics*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="line-chart" class="w-4.5 h-4.5 shrink-0 {{ Request::is('analytics*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Analitik Agronomi</span>
                    </a>
                @endif

                @if($canViewTelemetry)
                    <!-- Riwayat Telemetri -->
                    <a
                        href="{{ url('/telemetry/history') }}"
                        title="Riwayat Telemetri"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('telemetry/history*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('telemetry/history*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="database" class="w-4.5 h-4.5 shrink-0 {{ Request::is('telemetry/history*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Riwayat Telemetri</span>
                    </a>
                @endif
            </div>

            <!-- Section: Akademik & Pembelajaran -->
            <div class="space-y-1" role="listitem">
                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                    Akademik
                </p>
                <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                @if($canViewModules)
                    <!-- Modul Praktikum -->
                    <a
                        href="{{ url('/modules') }}"
                        title="Modul Praktikum"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('modules*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('modules*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="book-open" class="w-4.5 h-4.5 shrink-0 {{ Request::is('modules*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Modul Praktikum</span>
                    </a>
                @endif

                @if($canViewSchedules)
                    <!-- Jadwal Praktikum -->
                    <a
                        href="{{ url('/schedules') }}"
                        title="Jadwal Praktikum"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('schedules*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('schedules*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="calendar" class="w-4.5 h-4.5 shrink-0 {{ Request::is('schedules*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Jadwal Praktikum</span>
                    </a>
                @endif

                @if($canViewClasses)
                    <!-- Kelas -->
                    <a
                        href="{{ url('/classes') }}"
                        title="Kelas"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('classes*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('classes*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="graduation-cap" class="w-4.5 h-4.5 shrink-0 {{ Request::is('classes*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">Kelas</span>
                    </a>
                @endif

                @if($canViewReports)
                    <!-- Laporan LKS -->
                    <a
                        href="{{ url('/reports') }}"
                        title="{{ $isSiswa ? 'LKS Saya' : 'Laporan LKS' }}"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('reports*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('reports*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="file-check" class="w-4.5 h-4.5 shrink-0 {{ Request::is('reports*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">{{ $isSiswa ? 'LKS Saya' : 'Laporan LKS' }}</span>
                    </a>
                @endif

                @if($canViewAssessment)
                    <!-- Rapor Kompetensi -->
                    <a
                        href="{{ url('/assessment') }}"
                        title="{{ $isSiswa ? 'Rapor Saya' : 'Rapor Kompetensi' }}"
                        :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                        class="flex items-center rounded-xl transition-colors group
                            {{ Request::is('assessment*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                        aria-current="{{ Request::is('assessment*') ? 'page' : 'false' }}"
                    >
                        <i data-lucide="award" class="w-4.5 h-4.5 shrink-0 {{ Request::is('assessment*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                        <span x-show="!sidebarCollapsed" class="text-sm truncate">{{ $isSiswa ? 'Rapor Saya' : 'Rapor Kompetensi' }}</span>
                    </a>
                @endif
            </div>

            @if($canViewTrainerKits || $canViewRawLogs || $canViewUsers || $canViewSettings || $canViewSchoolLicense)
                <!-- Section: Laboratorium & Administrasi -->
                <div class="space-y-1" role="listitem">
                    <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider" aria-hidden="true">
                        {{ $isSchoolAdmin ? 'Administrasi Sekolah' : 'Laboratorium' }}
                    </p>
                    <div x-show="sidebarCollapsed" class="hidden lg:block w-8 h-px bg-slate-200 mx-auto my-2" aria-hidden="true"></div>

                    @if($canViewTrainerKits)
                        <!-- Trainer Kit -->
                        <a
                            href="{{ url('/trainer-kits') }}"
                            title="Trainer Kit"
                            :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                            class="flex items-center rounded-xl transition-colors group
                                {{ Request::is('trainer-kits*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                            aria-current="{{ Request::is('trainer-kits*') ? 'page' : 'false' }}"
                        >
                            <i data-lucide="cpu" class="w-4.5 h-4.5 shrink-0 {{ Request::is('trainer-kits*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                            <span x-show="!sidebarCollapsed" class="text-sm truncate">Trainer Kit</span>
                        </a>
                    @endif

                    @if($canViewSchoolLicense)
                        <!-- Status Lisensi Sekolah -->
                        <a
                            href="{{ route('school.license.show') }}"
                            title="Status Lisensi Sekolah"
                            :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                            class="flex items-center rounded-xl transition-colors group
                                {{ Request::is('school/license*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                            aria-current="{{ Request::is('school/license*') ? 'page' : 'false' }}"
                        >
                            <i data-lucide="shield-check" class="w-4.5 h-4.5 shrink-0 {{ Request::is('school/license*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                            <span x-show="!sidebarCollapsed" class="text-sm truncate">Status Lisensi</span>
                        </a>
                    @endif

                    @if($canViewRawLogs)
                        <!-- Debugger RS485 -->
                        <a
                            href="{{ url('/raw-logs') }}"
                            title="Debugger RS485"
                            :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                            class="flex items-center rounded-xl transition-colors group
                                {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                            aria-current="{{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'page' : 'false' }}"
                        >
                            <i data-lucide="terminal" class="w-4.5 h-4.5 shrink-0 {{ (Request::is('raw-logs*') || Request::is('rawlogs*')) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                            <span x-show="!sidebarCollapsed" class="text-sm truncate">Debugger RS485</span>
                        </a>
                    @endif

                    @if($canViewUsers)
                        <!-- Direktori -->
                        <a
                            href="{{ url('/users-data') }}"
                            title="Direktori"
                            :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                            class="flex items-center rounded-xl transition-colors group
                                {{ Request::is('users-data*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                            aria-current="{{ Request::is('users-data*') ? 'page' : 'false' }}"
                        >
                            <i data-lucide="users" class="w-4.5 h-4.5 shrink-0 {{ Request::is('users-data*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                            <span x-show="!sidebarCollapsed" class="text-sm truncate">Direktori</span>
                        </a>
                    @endif

                    @if($canViewSettings)
                        <!-- Pengaturan -->
                        <a
                            href="{{ url('/settings') }}"
                            title="Pengaturan"
                            :class="sidebarCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                            class="flex items-center rounded-xl transition-colors group
                                {{ Request::is('settings*') ? 'bg-[#0c3c26] text-white font-semibold shadow-xs shadow-[#0c3c26]/20' : 'text-slate-600 font-medium hover:bg-slate-100 hover:text-slate-900' }}"
                            aria-current="{{ Request::is('settings*') ? 'page' : 'false' }}"
                        >
                            <i data-lucide="settings" class="w-4.5 h-4.5 shrink-0 {{ Request::is('settings*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" aria-hidden="true"></i>
                            <span x-show="!sidebarCollapsed" class="text-sm truncate">Pengaturan</span>
                        </a>
                    @endif
                </div>
            @endif
        @endif
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
