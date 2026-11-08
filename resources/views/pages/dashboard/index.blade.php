@extends('layouts.app')

@section('content')
<div class="space-y-6">

    @php
        $isGuest = ! $user;
        $isSiswa = $user?->isSiswa();
        $isGuru = $user?->isGuru();
        $isSuperuser = $user?->isSuperuser();
        $isAdmin = in_array($user?->role ?? '', ['superuser', 'admin'], true);

        // Page title per role
        $pageTitle = match (true) {
            $isGuest => 'Selamat datang di Bitanic',
            $isSiswa => 'Dashboard Pembelajaran',
            $isGuru => 'Dashboard Pengajaran',
            $isAdmin => 'Dashboard',
            default => 'Dashboard',
        };

        // Section titles per role
        $heroIntro = match (true) {
            $isGuest => 'Platform pembelajaran vokasi terintegrasi untuk SMK Pertanian 4.0 — menggabungkan kurikulum praktikum, jadwal kelas, IoT Trainer Kit, dan analitik agronomi dalam satu ekosistem.',
            $isSiswa => 'Lacak progres praktikum, tugas LKS, dan jadwal kelas Anda.',
            $isGuru => 'Kelola jadwal mengajar, tinjau LKS praktikan, dan pantau kesiapan lab.',
            $isAdmin => 'Ringkasan operasional laboratorium dan akademik sekolah.',
        };

        $primaryCtaText = match (true) {
            $isGuest => 'Jelajahi Platform',
            $isSiswa => 'Lihat Modul Praktikum',
            $isGuru => 'Buka Jadwal Mengajar',
            $isAdmin => 'Kelola Armada Kit',
            default => 'Mulai',
        };

        $primaryCtaUrl = match (true) {
            $isGuest => '/modules',
            $isSiswa => '/modules',
            $isGuru => '/schedules',
            $isAdmin => '/trainer-kits',
            default => '/modules',
        };
    @endphp

    <!-- PAGE HERO: First Viewport -->
    <header class="p-6 lg:p-8 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="space-y-2">
            @if($isGuest)
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        SMK PERTANIAN 4.0
                    </span>
                    <span class="text-sm text-slate-400">•</span>
                    <span class="text-sm font-medium text-slate-600">Laboratorium IoT Agronomi & Hidroponik</span>
                    <span class="text-sm text-slate-400">•</span>
                    <time class="text-sm text-slate-500 font-mono">{{ now()->translatedFormat('l, d F Y') }}</time>
                </div>
            @else
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $user->school?->name ?? 'Laboratorium' }}
                    </span>
                    <span class="text-sm text-slate-400">•</span>
                    <span class="text-sm font-medium text-slate-600">{{ $isSiswa ? 'Siswa Praktikan' : ($isGuru ? 'Guru Pembimbing' : 'Admin Lab IoT') }}</span>
                    <span class="text-sm text-slate-400">•</span>
                    <time class="text-sm text-slate-500 font-mono">{{ now()->translatedFormat('l, d F Y') }}</time>
                    @if($averageAttendance !== null && ($isGuru || $isSuperuser))
                        <span class="text-sm text-slate-400">•</span>
                        <span class="text-sm font-semibold text-slate-600 flex items-center gap-1 text-supporting">
                            <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                            <span>Kehadiran: {{ number_format($averageAttendance, 1) }}%</span>
                        </span>
                    @endif
                </div>
            @endif

            <h1 class="text-page-title text-slate-900">{{ $pageTitle }}</h1>
            <p class="text-body-lg text-slate-600 max-w-3xl">{{ $heroIntro }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <a
                href="{{ $primaryCtaUrl }}"
                class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold flex items-center gap-2 shadow-sm transition-colors"
            >
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                <span>{{ $primaryCtaText }}</span>
            </a>

            @if($isSuperuser)
                <label class="text-label text-slate-600" for="dashboard-school-filter">Sekolah</label>
                <select id="dashboard-school-filter" name="school_id" form="dashboard-school-form"
                        onchange="this.form.submit()"
                        class="max-w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-700">
                    <option value="">Semua Sekolah</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchoolId == $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
                <form id="dashboard-school-form" method="GET" action="{{ url('/') }}" class="hidden"></form>
            @endif

            @if(!$isGuest)
                <a
                    href="{{ url('/iot-monitoring') }}{{ $isSuperuser && $selectedSchoolId ? '?school_id=' . $selectedSchoolId : '' }}"
                    class="px-4 py-2.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-sm font-semibold flex items-center gap-2 transition-colors"
                >
                    <i data-lucide="gauge" class="w-4 h-4 text-slate-500"></i>
                    <span>Stasiun Meja Praktik</span>
                </a>
                <a
                    href="{{ url('/schedules') }}"
                    class="px-4 py-2.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-sm font-semibold flex items-center gap-2 transition-colors"
                >
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                    <span>Jadwal Lab</span>
                </a>
            @endif
        </div>
    </header>

    @if($isGuest)
        <!-- GUEST DASHBOARD: Product Introduction -->
        <section class="space-y-6" aria-labelledby="capabilities-heading">
            <div class="flex items-center justify-between">
                <div>
                    <h2 id="capabilities-heading" class="text-section-title text-slate-900">Apa yang Bisa Dilakukan dengan Bitanic</h2>
                    <p class="text-supporting text-slate-500 mt-1">Kemampuan inti platform untuk siswa, guru, dan administrator lab.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Capability 1: Learning Modules -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="book-open" class="w-5 h-5 text-emerald-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">12 Modul Praktikum Terstruktur</h3>
                        <p class="text-supporting text-slate-600">Kurikulum lengkap pertanian presisi: sensor tanah, hidrolponik, otomatisasi irigasi, analisis data, hingga laporan kompetensi.</p>
                    </div>
                    <a href="{{ url('/modules') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 mt-2">
                        <span>Jelajahi Modul</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                <!-- Capability 2: IoT Trainer Kits -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="cpu" class="w-5 h-5 text-indigo-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">IoT Trainer Kit Meja Praktik</h3>
                        <p class="text-supporting text-slate-600">Perangkat keras 7-in-1 sensor tanah (pH, EC, N, P, K, suhu, kelembaban) + aktuator pompa irigasi, terhubung via RS485 Modbus.</p>
                    </div>
                    <a href="{{ url('/trainer-kits') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800 inline-flex items-center gap-1 mt-2">
                        <span>Lihat Armada</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                <!-- Capability 3: Scheduling -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5 text-amber-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">Jadwal Kelas & Meja Praktik</h3>
                        <p class="text-supporting text-slate-600">Penjadwalan terintegrasi: alokasi meja Trainer Kit, guru pengampu, kelompok siswa, dan agenda praktikum per pertemuan.</p>
                    </div>
                    <a href="{{ url('/schedules') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800 inline-flex items-center gap-1 mt-2">
                        <span>Lihat Jadwal</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                <!-- Capability 4: LKS & Assessment -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center">
                        <i data-lucide="file-check" class="w-5 h-5 text-rose-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">LKS & Rapor Kompetensi</h3>
                        <p class="text-supporting text-slate-600">Lembar Kerja Siswa digital dengan alur: kumpulkan → guru review → nilai/revisi → rapor kompetensi akhir semester.</p>
                    </div>
                    <a href="{{ url('/reports') }}" class="text-sm font-semibold text-rose-700 hover:text-rose-800 inline-flex items-center gap-1 mt-2">
                        <span>Lihat Hub LKS</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                <!-- Capability 5: Analytics -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
                        <i data-lucide="line-chart" class="w-5 h-5 text-violet-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">Analitik Agronomi & Telemetri</h3>
                        <p class="text-supporting text-slate-600">Visualisasi tren sensor, deteksi anomali batas ambang (pH, EC, kelembaban), dan rekomendasi tindakan lapangan berbasis data.</p>
                    </div>
                    <a href="{{ url('/analytics') }}" class="text-sm font-semibold text-violet-700 hover:text-violet-800 inline-flex items-center gap-1 mt-2">
                        <span>Buka Analitik</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                <!-- Capability 6: Classes & Groups -->
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-200 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-slate-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-body font-semibold text-slate-900">Kelas, Kelompok & Profil Siswa</h3>
                        <p class="text-supporting text-slate-600">Manajemen kelas praktikum, pembagian kelompok, profil siswa (NISN, kehadiran, nilai), dan impor data massal.</p>
                    </div>
                    <a href="{{ url('/classes') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-800 inline-flex items-center gap-1 mt-2">
                        <span>Lihat Kelas</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>
            </div>

            <!-- CTA to Login/Register -->
            <div class="p-6 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <h3 class="text-body font-semibold text-slate-900">Siap Memulai?</h3>
                <p class="text-supporting text-slate-600 mt-1 max-w-xl mx-auto">Masuk sebagai siswa, guru, atau admin untuk mengakses dashboard peran, jadwal, LKS, dan armada Trainer Kit sekolah Anda.</p>
                <div class="flex flex-wrap items-center justify-center gap-3 mt-4">
                    <a href="{{ url('/login') }}" class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Masuk ke Bitanic</a>
                    <a href="{{ url('/modules') }}" class="px-5 py-2.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-sm font-semibold">Jelajahi Modul Publik</a>
                </div>
            </div>
        </section>

    @else
        <!-- AUTHENTICATED DASHBOARD: Role-Specific Workflows -->

        <!-- OPERATIONAL SUMMARY: Compact, Truthful -->
        <section class="p-4 lg:p-5 rounded-xl bg-white border border-slate-200 shadow-sm" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">Ringkasan Operasional</h2>
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div class="space-y-1.5">
                    <dt class="text-label text-slate-400 tracking-wide">Armada Trainer Kit</dt>
                    <dd class="flex items-baseline gap-2">
                        <span class="font-tabular text-2xl font-bold text-slate-900">{{ $onlineKitsCount }}</span>
                        <span class="text-sm font-medium text-slate-500">/ {{ $totalKitsCount }} Meja Online</span>
                    </dd>
                    <dd class="text-supporting {{ $totalKitsCount === 0 ? 'text-slate-500' : ($onlineKitsCount === $totalKitsCount ? 'text-emerald-700' : 'text-amber-600') }}">
                        {{ $totalKitsCount === 0 ? 'Belum Ada Meja Terdaftar' : ($onlineKitsCount === $totalKitsCount ? 'Semua Siap Pakai' : ($totalKitsCount - $onlineKitsCount) . ' Perlu Periksa') }}
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt class="text-label text-slate-400 tracking-wide">Jadwal Terdekat</dt>
                    <dd class="font-semibold text-sm text-slate-900 line-clamp-1">
                        {{ $todaySchedule?->title ?? 'Tidak ada jadwal berikutnya' }}
                    </dd>
                    <dd class="text-supporting text-slate-500 font-mono">
                        @if($todaySchedule)
                            {{ $todaySchedule->activity_date?->format('d/m/Y') }} · {{ substr($todaySchedule->start_time ?? '', 0, 5) }}–{{ substr($todaySchedule->end_time ?? '', 0, 5) }} WIB
                            @if($todaySchedule->lead_teacher)
                                • {{ $todaySchedule->lead_teacher->name }}
                            @endif
                        @else
                            Belum ada jadwal terdaftar
                        @endif
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt class="text-label text-slate-400 tracking-wide">
                        @if($isSiswa)
                            LKS Praktikum Saya
                        @else
                            LKS Menunggu Review
                        @endif
                    </dt>
                    <dd class="flex items-baseline gap-2">
                        <span class="font-tabular text-2xl font-bold {{ $isSiswa ? 'text-indigo-700' : 'text-amber-600' }}">{{ $isSiswa ? $studentOwnReportsCount : $pendingReportsCount }}</span>
                        <span class="text-sm font-medium text-slate-500">
                            {{ $isSiswa ? 'Laporan Saya' : 'Laporan Praktikan' }}
                        </span>
                    </dd>
                    @if($isSiswa && $studentReportsNeedingRevisionCount)
                        <dd class="text-supporting text-rose-600">{{ $studentReportsNeedingRevisionCount }} perlu revisi</dd>
                    @endif
                </div>

                <div class="space-y-1.5">
                    <dt class="text-label text-slate-400 tracking-wide">Peringatan Anomali</dt>
                    <dd class="flex items-baseline gap-2">
                        <span class="font-tabular text-2xl font-bold {{ count($anomalies) > 0 ? 'text-rose-600' : ($hasFreshHardwareTelemetry ? 'text-emerald-700' : 'text-slate-500') }}">
                            {{ $affectedStationsCount }}
                        </span>
                        <span class="text-sm font-medium text-slate-500">Meja Terdampak</span>
                    </dd>
                    <dd class="text-supporting text-slate-500">
                        @if($hasFreshHardwareTelemetry)
                            <span class="font-semibold text-slate-700">{{ count($anomalies) }}</span> parameter melewati ambang pada data terbaru
                        @else
                            Belum ada data hardware segar untuk diperiksa
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <!-- MIDDLE: Role-Relevant Schedule & Tasks -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upcoming Practicum Schedule -->
            <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="schedule-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="schedule-heading" class="text-section-title text-slate-900">Agenda Praktikum Terdekat</h3>
                        <p class="text-supporting text-slate-500">Jadwal penggunaan meja laboratorium SMK</p>
                    </div>
                    <a href="{{ url('/schedules') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Kalender Lengkap</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="space-y-3">
                    @forelse($upcomingSchedules as $sched)
                        <div class="p-4 rounded-lg border border-slate-200 bg-slate-50 flex items-start justify-between gap-4">
                            <div class="space-y-1.5 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-indigo-100 text-indigo-800 font-mono shrink-0">
                                        {{ $sched->classroom?->name ?? $sched->studentGroup?->name ?? 'Kelas' }}
                                    </span>
                                    <span class="text-sm font-semibold text-slate-900 truncate">{{ $sched->title }}</span>
                                </div>
                                <p class="text-supporting text-slate-600 truncate">{{ $sched->agenda ?? 'Praktikum sensor tanah presisi' }}</p>
                                <div class="flex items-center gap-3 text-supporting text-slate-500 font-mono">
                                    <span><i data-lucide="calendar" class="w-3.5 h-3.5 inline mr-1"></i>{{ $sched->activity_date ? $sched->activity_date->format('d/m/Y') : '-' }}</span>
                                    <span><i data-lucide="clock" class="w-3.5 h-3.5 inline mr-1"></i>{{ substr($sched->start_time ?? '', 0, 5) }} - {{ substr($sched->end_time ?? '', 0, 5) }}</span>
                                </div>
                            </div>
                            <span class="shrink-0 px-3 py-1 rounded-md text-xs font-semibold {{ $sched->status === 'in_progress' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $sched->status === 'in_progress' ? 'Berjalan' : 'Terjadwal' }}
                            </span>
                        </div>
                    @empty
                        <div class="py-8 text-center space-y-2">
                            <div class="w-10 h-10 rounded-full bg-slate-100 mx-auto flex items-center justify-center">
                                <i data-lucide="calendar" class="w-5 h-5 text-slate-400"></i>
                            </div>
                            <p class="text-supporting font-semibold text-slate-600">Belum Ada Jadwal Praktikum</p>
                            <p class="text-supporting text-slate-500">Jadwal akan muncul di sini setelah ditambahkan oleh guru atau admin.</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <!-- Role-Relevant Academic Tasks -->
            <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="tasks-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="tasks-heading" class="text-section-title text-slate-900">
                            @if($isSiswa)
                                Tugas LKS Saya
                            @elseif($isGuru || $isAdmin)
                                Laporan Menunggu Review
                            @else
                                Aktivitas Akademik
                            @endif
                        </h3>
                        <p class="text-supporting text-slate-500">
                            @if($isSiswa)
                                Lembar kerja praktikum yang perlu dikumpulkan atau direvisi
                            @elseif($isGuru || $isAdmin)
                                Laporan praktikan yang menunggu penilaian
                            @else
                                Ringkasan aktivitas pembelajaran
                            @endif
                        </p>
                    </div>
                    <a href="{{ url('/reports') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Lihat Semua</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                @if($isSiswa)
                    @if($studentOwnReportsCount > 0)
                        <ul class="space-y-2">
                            <li class="p-4 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
                                <div class="space-y-0.5 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $studentOwnReportsCount }} Laporan Terkumpul</p>
                                    <p class="text-supporting text-slate-500">{{ $studentReportsNeedingRevisionCount }} perlu revisi, sisanya dikumpulkan</p>
                                </div>
                                <a href="{{ url('/reports') }}" class="shrink-0 px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Buka</a>
                            </li>
                        </ul>
                    @else
                        <div class="py-8 text-center space-y-2">
                            <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center">
                                <i data-lucide="file-check" class="w-5 h-5"></i>
                            </div>
                            <p class="text-supporting font-semibold text-slate-800">Belum Ada LKS Praktikum</p>
                            <p class="text-supporting text-slate-500">Mulai praktikum untuk menghasilkan laporan.</p>
                        </div>
                    @endif
                @elseif($isGuru || $isAdmin)
                    @if($pendingReportsCount > 0)
                        <ul class="space-y-2">
                            <li class="p-4 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
                                <div class="space-y-0.5 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $pendingReportsCount }} Laporan Menunggu</p>
                                    <p class="text-supporting text-slate-500">Perlu dinilai atau diminta revisi</p>
                                </div>
                                <a href="{{ url('/reports') }}" class="shrink-0 px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Review</a>
                            </li>
                        </ul>
                    @else
                        <div class="py-6 text-center space-y-2">
                            <p class="text-supporting text-slate-600">Belum ada laporan LKS yang menunggu penilaian.</p>
                        </div>
                    @endif
                @else
                    <div class="py-8 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-slate-100 mx-auto flex items-center justify-center">
                            <i data-lucide="help-circle" class="w-5 h-5 text-slate-400"></i>
                        </div>
                        <p class="text-supporting font-semibold text-slate-800">Data Tidak Tersedia</p>
                        <p class="text-supporting text-slate-500">Login sebagai siswa atau guru untuk melihat tugas akademik.</p>
                    </div>
                @endif
            </section>
        </div>

        <!-- LOWER: Trainer Kit Fleet (Desktop) + Anomalies -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Trainer Kit Fleet Table -->
            <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4 lg:col-span-2 min-w-0" aria-labelledby="fleet-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="fleet-heading" class="text-section-title text-slate-900">Status Meja Praktikum IoT (Hardware Fleet)</h3>
                        <p class="text-supporting text-slate-500">Kondisi telemetri terkini pada masing-masing kit meja siswa</p>
                    </div>
                    <a href="{{ url('/trainer-kits') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Kelola Armada Kit</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[680px]" role="table">
                        <thead>
                            <tr class="text-left text-label text-slate-400 tracking-wider border-b border-slate-200">
                                <th class="pb-3">Meja</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-center">pH</th>
                                <th class="pb-3 text-center">Lengas</th>
                                <th class="pb-3 text-center">EC</th>
                                <th class="pb-3">Terakhir</th>
                                <th class="pb-3">Sumber</th>
                                <th class="pb-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($devices as $kit)
                                @php
                                    $latest = $kit->latestReading;
                                    $isOnline = (bool) ($kit->is_online ?? false);
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3">
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono text-xs font-bold text-slate-900">{{ $kit->code }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $isOnline ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                                    {{ $kit->status ?? 'offline' }}
                                                </span>
                                                @if($isSuperuser && $kit->school)
                                                    <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-600 font-sans border border-slate-200">
                                                        {{ $kit->school->name }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-supporting text-slate-600 font-medium mt-1">{{ $kit->bench_location ?? $kit->name }}</p>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $isOnline ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                            {{ $isOnline ? 'Online' : 'Offline' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-center font-mono text-xs font-bold {{ $latest && (float)$latest->ph < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                        {{ $latest && $latest->ph !== null ? number_format((float)$latest->ph, 2) : '—' }}
                                    </td>
                                    <td class="py-3 text-center font-mono text-xs font-bold {{ $latest && (float)$latest->humidity < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                        {{ $latest && $latest->humidity !== null ? number_format((float)$latest->humidity, 1) . '%' : '—' }}
                                    </td>
                                    <td class="py-3 text-center font-mono text-xs font-bold text-slate-800">
                                        {{ $latest && $latest->ec !== null ? number_format((float)$latest->ec, 0) : '—' }}
                                    </td>
                                    <td class="py-3 text-supporting text-slate-400 font-mono">
                                        {{ $latest?->recorded_at ? $latest->recorded_at->diffForHumans() : 'Belum Ada Transmisi Data' }}
                                    </td>
                                    <td class="py-3">
                                        @if($latest?->provenance)
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold font-mono uppercase {{ $latest->provenance === 'hardware' ? 'bg-emerald-50 text-emerald-700' : ($latest->provenance === 'simulation' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600') }}">
                                                {{ $latest->provenance === 'hardware' ? 'Hardware' : ($latest->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                            </span>
                                        @else
                                            <span class="text-supporting text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        <a
                                            href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                                            class="px-3 py-1.5 rounded bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-semibold flex items-center gap-1 transition-colors justify-end"
                                        >
                                            <span>Stasiun Meja</span>
                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-10 text-center space-y-1">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 mx-auto flex items-center justify-center">
                                            <i data-lucide="cpu" class="w-6 h-6 text-slate-400"></i>
                                        </div>
                                        <p class="text-supporting font-semibold text-slate-600">Belum Ada Meja Praktikum IoT Terdaftar</p>
                                        <p class="text-supporting text-slate-500">Stasiun meja praktikum SMK akan tampil di sini setelah didaftarkan.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Anomalies with Truthful Empty State -->
            <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4 lg:col-span-2" aria-labelledby="anomalies-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="anomalies-heading" class="text-section-title text-slate-900">Anomali Lahan & Tindakan Lapangan</h3>
                        <p class="text-supporting text-slate-500">Deteksi otomatis batas ambang toleransi agronomi (hanya telemetri hardware segar)</p>
                    </div>
                    @if(count($anomalies) > 0)
                        <span class="px-2 py-1 rounded text-label font-semibold bg-rose-100 text-rose-800">Perlu Intervensi</span>
                    @elseif($totalKitsCount === 0)
                        <span class="px-2 py-1 rounded text-label font-medium bg-slate-100 text-slate-700">Belum Ada Meja</span>
                    @elseif($onlineKitsCount === 0)
                        <span class="px-2 py-1 rounded text-label font-medium bg-slate-100 text-slate-700">Data Tidak Tersedia</span>
                    @else
                        <span class="px-2 py-1 rounded text-label font-medium bg-slate-100 text-slate-700">Tidak Ada Anomali Terdeteksi</span>
                    @endif
                </div>

                <div class="space-y-3">
                    @forelse($anomalies as $ano)
                        <div class="p-4 rounded-lg border {{ $ano['severity'] === 'critical' ? 'border-rose-200 bg-rose-50/60' : 'border-amber-200 bg-amber-50/60' }} space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-slate-900">{{ $ano['device'] }} — {{ $ano['bench'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $ano['severity'] === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $ano['parameter'] }}: {{ $ano['value'] }}
                                </span>
                            </div>
                            <p class="text-supporting text-slate-700 font-medium">{{ $ano['message'] }}</p>
                            <div class="text-supporting text-slate-600 flex items-start gap-2 pt-2 border-t border-slate-200/60">
                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                                <span><strong>Rekomendasi:</strong> {{ $ano['action'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center space-y-2">
                            @if($totalKitsCount === 0)
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 mx-auto flex items-center justify-center">
                                    <i data-lucide="info" class="w-6 h-6"></i>
                                </div>
                                <p class="text-supporting font-semibold text-slate-800">Belum Ada Data Pemantauan</p>
                                <p class="text-supporting text-slate-500 max-w-md mx-auto">Belum ada meja praktikum terdaftar. Data anomali akan muncul setelah armada dikonfigurasi dan telemetri hardware diterima.</p>
                            @elseif($onlineKitsCount === 0)
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 mx-auto flex items-center justify-center">
                                    <i data-lucide="wifi-off" class="w-6 h-6"></i>
                                </div>
                                <p class="text-supporting font-semibold text-slate-800">Data Pemantauan Tidak Tersedia</p>
                                <p class="text-supporting text-slate-500 max-w-md mx-auto">Semua meja praktikum sedang offline. Tidak ada telemetri segar untuk dievaluasi.</p>
                            @elseif(!$hasFreshHardwareTelemetry)
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 mx-auto flex items-center justify-center">
                                    <i data-lucide="alert-circle" class="w-6 h-6"></i>
                                </div>
                                <p class="text-supporting font-semibold text-slate-800">Data Pemantauan Tidak Tersedia</p>
                                <p class="text-supporting text-slate-500 max-w-md mx-auto">Belum ada pengukuran hardware terbaru yang dapat digunakan untuk memeriksa anomali.</p>
                            @else
                                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center">
                                    <i data-lucide="check" class="w-6 h-6"></i>
                                </div>
                                <p class="text-supporting font-semibold text-slate-800">Tidak Ada Anomali Terdeteksi</p>
                                <p class="text-supporting text-slate-500 max-w-md mx-auto">Tidak ada defisiensi unsur hara makro atau anomali kelembaban tanah pada data hardware terbaru.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

    @endif
</div>
@endsection