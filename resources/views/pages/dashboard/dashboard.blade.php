@extends('layouts.app')

@section('content')
<div class="space-y-6">

    @php
        $isGuest = ! $user;
        $isSiswa = (bool) $user?->isSiswa();
        $isGuru = (bool) $user?->isGuru();
        $isSuperuser = (bool) $user?->isSuperuser();
        $isSchoolAdmin = (bool) ($user && $user->isAdmin());
        $isAdmin = $isSuperuser || $isSchoolAdmin;

        $offlineKitsCount = max(0, $totalKitsCount - $onlineKitsCount);

        $rolePillLabel = match (true) {
            $isGuru => 'Guru Pembimbing',
            $isSuperuser => 'Superuser Platform',
            $isSchoolAdmin => 'Admin Sekolah',
            $isSiswa => 'Siswa Praktikan',
            default => 'Pengunjung',
        };

        $welcomeGreeting = $isGuest
            ? 'Selamat datang di Bitanic.'
            : 'Selamat datang, ' . ($user->name ?? 'Pengguna Bitanic') . '.';

        $welcomeCondition = match (true) {
            $isGuest => 'Informasi pembelajaran vokasi, jadwal praktikum, dan perangkat IoT pertanian presisi.',
            $totalKitsCount === 0 => 'Belum ada meja praktikum terdaftar pada laboratorium.',
            $offlineKitsCount === $totalKitsCount => $offlineKitsCount . ' dari ' . $totalKitsCount . ' meja praktik belum mengirim telemetri. Periksa koneksi kit agar siap dipakai.',
            $offlineKitsCount > 0 => $offlineKitsCount . ' dari ' . $totalKitsCount . ' meja praktik sedang offline. Segera lakukan pengecekan stasiun.',
            default => 'Semua ' . $totalKitsCount . ' meja praktik aktif terhubung dan siap digunakan untuk kegiatan praktikum.',
        };
    @endphp

    {{-- PAGE HEADER --}}
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isSuperuser && ! $selectedSchoolId ? 'Pusat kendali eksekutif, sebaran geografis sekolah mitra, lisensi institusional, dan armada IoT nasional.' : 'Ringkasan aktivitas akademik dan kondisi laboratorium.' }}
            </p>
        </div>

        @if($isSuperuser && $schools->isNotEmpty())
            <form id="dashboard-school-form" method="GET" action="{{ url('/') }}" class="w-full sm:w-auto sm:min-w-56 shrink-0">
                <label class="text-xs font-medium text-slate-600 block mb-1" for="dashboard-school-filter">Cakupan sekolah</label>
                <div class="relative">
                    <select
                        id="dashboard-school-filter"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="w-full sm:max-w-64 px-3 py-1.5 pr-8 rounded-lg bg-white border border-slate-200 text-sm text-slate-800 shadow-2xs focus:outline-none focus:ring-2 focus:ring-emerald-600"
                    >
                        <option value="">Semua sekolah (Tampilan Makro)</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected($selectedSchoolId == $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        @elseif($user && $user->school)
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 shadow-2xs">
                    <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-500"></i>
                    {{ $user->school->name }}
                </span>
            </div>
        @endif
    </header>

    @if($isGuest)
        {{-- GUEST DASHBOARD: Product Introduction & Public Workflows --}}
        <section class="space-y-6" aria-labelledby="capabilities-heading">
            {{-- Welcome Banner for Guest --}}
            <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200 p-6 shadow-2xs">
                <div class="max-w-2xl space-y-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Smart Precision Agriculture LMS
                    </span>
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                        {{ $welcomeGreeting }}
                    </h2>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        {{ $welcomeCondition }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <h2 id="capabilities-heading" class="text-lg font-bold text-slate-900">Apa yang Bisa Dilakukan dengan Bitanic</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kemampuan inti platform untuk siswa, guru, dan administrator lab.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Capability 1: Learning Modules --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-emerald-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="book-open" class="w-5 h-5 text-emerald-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">12 Modul Praktikum Terstruktur</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Kurikulum lengkap pertanian presisi: sensor tanah, hidroponik, otomatisasi irigasi, analisis data, hingga laporan kompetensi.</p>
                    </div>
                    <a href="{{ url('/modules') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Jelajahi Modul</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                {{-- Capability 2: IoT Trainer Kits --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-indigo-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="cpu" class="w-5 h-5 text-indigo-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">IoT Trainer Kit Meja Praktik</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Perangkat keras 7-in-1 sensor tanah (pH, EC, N, P, K, suhu, kelembaban) + aktuator pompa irigasi, terhubung via RS485 Modbus.</p>
                    </div>
                    <a href="{{ url('/trainer-kits') }}" class="text-xs font-semibold text-indigo-700 hover:text-indigo-800 inline-flex items-center gap-1">
                        <span>Armada Trainer Kit</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                {{-- Capability 3: Scheduling --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-amber-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5 text-amber-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">Jadwal Kelas &amp; Meja Praktik</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Penjadwalan terintegrasi: alokasi meja Trainer Kit, guru pengampu, kelompok siswa, dan agenda praktikum per pertemuan.</p>
                    </div>
                    <a href="{{ url('/schedules') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800 inline-flex items-center gap-1">
                        <span>Lihat Jadwal</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                {{-- Capability 4: LKS & Assessment --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-rose-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center">
                        <i data-lucide="file-check" class="w-5 h-5 text-rose-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">LKS &amp; Rapor Kompetensi</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Lembar Kerja Siswa digital dengan alur: kumpulkan → guru review → nilai/revisi → rapor kompetensi akhir semester.</p>
                    </div>
                    <a href="{{ url('/reports') }}" class="text-xs font-semibold text-rose-700 hover:text-rose-800 inline-flex items-center gap-1">
                        <span>Lihat Hub LKS</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                {{-- Capability 5: Analytics --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-violet-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
                        <i data-lucide="line-chart" class="w-5 h-5 text-violet-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">Analitik Agronomi &amp; Telemetri</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Visualisasi tren sensor, deteksi anomali batas ambang (pH, EC, kelembaban), dan rekomendasi tindakan lapangan berbasis data.</p>
                    </div>
                    <a href="{{ url('/analytics') }}" class="text-xs font-semibold text-violet-700 hover:text-violet-800 inline-flex items-center gap-1">
                        <span>Buka Analitik</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>

                {{-- Capability 6: Classes & Groups --}}
                <article class="p-5 rounded-xl bg-white border border-slate-200 space-y-4 hover:border-slate-300 transition-colors shadow-2xs">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-slate-700"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900">Kelas, Kelompok &amp; Profil Siswa</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">Manajemen kelas praktikum, pembagian kelompok, profil siswa (NISN, kehadiran, nilai), dan impor data massal.</p>
                    </div>
                    <a href="{{ url('/classes') }}" class="text-xs font-semibold text-slate-700 hover:text-slate-800 inline-flex items-center gap-1">
                        <span>Lihat Kelas</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </article>
            </div>

            {{-- CTA to Login --}}
            <div class="p-6 rounded-2xl bg-white border border-slate-200 text-center shadow-2xs">
                <h3 class="text-base font-bold text-slate-900">Siap Menggunakan Workstation Laboratorium?</h3>
                <p class="text-xs text-slate-600 mt-1 max-w-xl mx-auto">Masuk menggunakan akun sekolah untuk mengakses stasiun telemetri meja, pengisian LKS digital, dan penilaian kompetensi.</p>
                <div class="flex flex-wrap items-center justify-center gap-3 mt-4">
                    <a href="{{ url('/login') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors shadow-xs">
                        Masuk ke Bitanic
                    </a>
                    <a href="{{ url('/modules') }}" class="px-5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-semibold transition-colors">
                        Jelajahi Modul Publik
                    </a>
                </div>
            </div>
        </section>

    @elseif($isSuperuser && ! $selectedSchoolId)
        {{-- SUPERUSER MACRO PLATFORM OVERVIEW (HQ / NATIONAL LEVEL) --}}
        
        {{-- 1. WELCOME BANNER CARD WITH CONTOUR ARTWORK --}}
        <section class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="welcome-heading">
            <div class="absolute right-0 top-0 bottom-0 w-1/2 pointer-events-none opacity-40 hidden sm:block" aria-hidden="true">
                <svg class="w-full h-full text-slate-300" viewBox="0 0 500 200" fill="none" preserveAspectRatio="none">
                    <path d="M50,20 C150,70 300,10 500,50" stroke="currentColor" stroke-width="1.2" stroke-dasharray="2 3"/>
                    <path d="M0,60 C120,110 320,40 500,90" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M0,100 C140,150 330,70 500,130" stroke="currentColor" stroke-width="1.2" stroke-dasharray="2 3"/>
                    <path d="M0,140 C160,190 350,110 500,170" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M0,180 C180,220 380,140 500,200" stroke="currentColor" stroke-width="1.2"/>
                </svg>
            </div>

            <div class="relative z-10 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                        Superuser Platform HQ
                    </span>
                    <span class="text-xs text-slate-500 font-medium">Bitanic Precision Agritech v2.0</span>
                </div>

                <div class="space-y-1">
                    <h2 id="welcome-heading" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                        Pusat Kendali Eksekutif &amp; Infrastruktur Nasional
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Monitoring makro sebaran sekolah mitra pertanian presisi, integrasi lisensi LMS institusional, dan ketersediaan armada IoT Trainer Kit di seluruh Indonesia.
                    </p>
                </div>

                <div class="pt-2 flex flex-wrap items-center gap-2 text-xs">
                    <a
                        href="{{ route('master.schools.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                    >
                        <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Direktori Sekolah</span>
                    </a>
                    <a
                        href="{{ route('master.licenses.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                    >
                        <i data-lucide="key" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Lisensi LMS</span>
                    </a>
                    <a
                        href="{{ url('/trainer-kits') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                    >
                        <i data-lucide="cpu" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Fleet Trainer Kit</span>
                    </a>
                    <a
                        href="{{ url('/raw-logs') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                    >
                        <i data-lucide="terminal" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Debugger RS485</span>
                    </a>
                </div>
            </div>
        </section>

        {{-- 2. MACRO 4-KPI CARDS GRID --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" aria-labelledby="kpi-heading">
            <h2 id="kpi-heading" class="sr-only">Metrik Platform Makro</h2>

            {{-- 1. Sekolah Mitra --}}
            <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Sekolah Mitra</span>
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div>
                        <span class="font-tabular text-3xl font-bold tracking-tight text-slate-900">{{ $macroMetrics['total_schools'] }}</span>
                        <span class="text-xs text-slate-500 ml-1">Institusi SMK</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ count($mapPins) }} terverifikasi GIS</span>
                    <a href="{{ route('master.schools.index') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Direktori</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>

            {{-- 2. Status Lisensi LMS --}}
            <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Lisensi LMS</span>
                        <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="font-tabular text-3xl font-bold tracking-tight text-emerald-700">{{ $macroMetrics['active_licenses'] }}</span>
                        <span class="text-xs font-medium text-slate-500">Aktif dari {{ $macroMetrics['total_schools'] }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-medium">
                        @if($macroMetrics['pending_licenses'] > 0)
                            <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">
                                {{ $macroMetrics['pending_licenses'] }} Menunggu
                            </span>
                        @endif
                        @if($macroMetrics['expired_licenses'] > 0)
                            <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                                {{ $macroMetrics['expired_licenses'] }} Kedaluwarsa
                            </span>
                        @endif
                        @if($macroMetrics['uninitialized_licenses'] > 0)
                            <span class="px-1.5 py-0.5 rounded bg-slate-50 text-slate-600 border border-slate-200">
                                {{ $macroMetrics['uninitialized_licenses'] }} Belum Aktif
                            </span>
                        @endif
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Hak Akses Lab</span>
                    <a href="{{ route('master.licenses.index') }}" class="font-semibold text-indigo-700 hover:text-indigo-800 inline-flex items-center gap-1">
                        <span>Kelola Lisensi</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>

            {{-- 3. Total Siswa Bitanic --}}
            <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Total Siswa Terdaftar</span>
                        <span class="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center">
                            <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div>
                        <span class="font-tabular text-3xl font-bold tracking-tight text-slate-900">{{ $macroMetrics['total_students'] }}</span>
                        <span class="text-xs text-slate-500 ml-1">Siswa Praktikan</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ $macroMetrics['total_teachers'] }} Guru Pembimbing</span>
                    <span class="inline-flex items-center gap-1 text-emerald-700 font-medium">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        <span>Terdata</span>
                    </span>
                </div>
            </div>

            {{-- 4. Global Fleet Kit --}}
            <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Global Fleet Kit</span>
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="cpu" class="w-4 h-4"></i>
                        </span>
                    </div>
                    <div>
                        <span class="font-tabular text-3xl font-bold tracking-tight text-slate-900">{{ $macroMetrics['total_devices'] }}</span>
                        <span class="text-xs text-slate-500 ml-1">Unit Perangkat</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Beredar di Laboratorium</span>
                    <a href="{{ url('/trainer-kits') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>Fleet Manager</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>
        </section>

        {{-- 3. INTERACTIVE LEAFLET GIS MAP SECTION --}}
        <section class="rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs" aria-labelledby="map-heading">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 id="map-heading" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4.5 h-4.5 text-emerald-600"></i>
                        <span>Sebaran Geografis &amp; Status Lisensi Sekolah Mitra</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pemetaan sebaran institusi pendidikan mitra dengan indikator status lisensi dan akses satu klik ke School Workspace Hub.
                    </p>
                </div>

                {{-- Map Legend --}}
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-medium">
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                        <span>Aktif</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <span>Menunggu</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                        <span>Kedaluwarsa</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-slate-50 text-slate-600 border border-slate-200">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                        <span>Belum Aktif</span>
                    </span>
                </div>
            </div>

            <div class="relative w-full rounded-xl overflow-hidden border border-slate-200">
                <div id="macro-distribution-map" class="h-[460px] md:h-[520px] w-full z-0"></div>

                @if(count($mapPins) > 0)
                    {{-- Floating Interactive GIS Controls (Top Right) --}}
                    <div class="absolute top-3 right-3 z-10 flex flex-col gap-1 bg-white/95 backdrop-blur-xs p-1.5 rounded-xl border border-slate-200/90 shadow-md">
                        <button
                            type="button"
                            id="btn-map-zoom-in"
                            title="Perbesar Peta (Zoom In)"
                            aria-label="Perbesar peta"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 active:scale-95 transition-all focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30"
                        >
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <button
                            type="button"
                            id="btn-map-zoom-out"
                            title="Perkecil Peta (Zoom Out)"
                            aria-label="Perkecil peta"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 active:scale-95 transition-all focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30"
                        >
                            <i data-lucide="minus" class="w-4 h-4"></i>
                        </button>
                        <div class="my-0.5 border-t border-slate-200"></div>
                        <button
                            type="button"
                            id="btn-map-fit-bounds"
                            title="Pusatkan ke Semua Sekolah Mitra"
                            aria-label="Pusatkan ke semua sekolah mitra"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 active:scale-95 transition-all focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30"
                        >
                            <i data-lucide="maximize-2" class="w-4 h-4"></i>
                        </button>
                        <button
                            type="button"
                            id="btn-map-reset-indonesia"
                            title="Pandangan Nasional (Seluruh Indonesia)"
                            aria-label="Lihat seluruh Indonesia"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 active:scale-95 transition-all focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30"
                        >
                            <i data-lucide="compass" class="w-4 h-4"></i>
                        </button>
                    </div>

                    {{-- Interactive Floating Info Badge (Bottom Right) --}}
                    <div class="absolute bottom-3 right-3 z-10 flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900/80 backdrop-blur-xs text-white text-[11px] font-medium shadow-md pointer-events-none">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="mouse-pointer" class="w-3.5 h-3.5 text-emerald-400"></i>
                            <span class="hidden sm:inline">Scroll roda mouse atau tombol +/- untuk zoom</span>
                            <span class="sm:hidden">Pinch / geser untuk zoom</span>
                        </span>
                        <span class="text-slate-500">|</span>
                        <span id="map-zoom-level-badge" class="font-mono text-emerald-300 font-semibold">Zoom 6x</span>
                    </div>
                @endif

                @if(count($mapPins) === 0)
                    <div class="absolute inset-0 bg-slate-50/80 backdrop-blur-2xs flex flex-col items-center justify-center p-6 text-center z-10">
                        <i data-lucide="map-pin-off" class="w-8 h-8 text-slate-400 mb-2"></i>
                        <p class="text-sm font-semibold text-slate-700">Belum Ada Titik Koordinat Sekolah Terverifikasi</p>
                        <p class="text-xs text-slate-500 max-w-sm mt-1">Lakukan verifikasi koordinat lintang dan bujur sekolah pada menu Direktori Sekolah agar pin muncul pada peta.</p>
                        <a href="{{ route('master.schools.index') }}" class="mt-3 px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold transition-colors">
                            Buka Direktori Sekolah
                        </a>
                    </div>
                @endif
            </div>
        </section>

        {{-- 4. DIREKTORI SEKOLAH MITRA & WORKSPACE HUB ACCESS --}}
        <section class="rounded-2xl bg-white border border-slate-200/90 overflow-hidden shadow-2xs" aria-labelledby="school-directory-heading">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 id="school-directory-heading" class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="building-2" class="w-4.5 h-4.5 text-emerald-600"></i>
                        <span>Direktori Sekolah Mitra &amp; School Workspace Hub</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pilih sekolah mitra untuk membuka Workspace Hub lengkap (profil, kelas, siswa, guru, jadwal, LKS, dan Trainer Kit).
                    </p>
                </div>
                <a
                    href="{{ route('master.schools.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold transition-colors self-start sm:self-auto"
                >
                    <span>Kelola Semua Sekolah</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-700 font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="py-3 px-4">Nama Sekolah &amp; NPSN</th>
                            <th scope="col" class="py-3 px-4">Wilayah</th>
                            <th scope="col" class="py-3 px-4">Status Lisensi</th>
                            <th scope="col" class="py-3 px-4">Data Akademik</th>
                            <th scope="col" class="py-3 px-4">Trainer Kit</th>
                            <th scope="col" class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($schoolCards as $schoolItem)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3.5 px-4">
                                    <a href="{{ $schoolItem['workspace_url'] }}" class="group block" title="Buka Workspace Hub {{ $schoolItem['name'] }}">
                                        <div class="font-semibold text-slate-900 text-sm group-hover:text-emerald-700 transition-colors flex items-center gap-1.5">
                                            <span>{{ $schoolItem['name'] }}</span>
                                            <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 opacity-0 group-hover:opacity-100 transition-all"></i>
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono mt-0.5">NPSN: {{ $schoolItem['npsn'] }}</div>
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-800 font-medium">{{ $schoolItem['city'] }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $schoolItem['province'] }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @php
                                        $badgeColor = match($schoolItem['license_status']) {
                                            'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                                            'expired' => 'bg-rose-50 text-rose-800 border-rose-200',
                                            default => 'bg-slate-50 text-slate-600 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold border {{ $badgeColor }}">
                                        {{ $schoolItem['license_status_label'] }}
                                    </span>
                                    @if($schoolItem['days_remaining'] !== null && $schoolItem['license_status'] === 'active')
                                        <div class="text-[10px] text-slate-400 mt-0.5">Sisa {{ $schoolItem['days_remaining'] }} hari</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <span class="font-medium">{{ $schoolItem['classrooms_count'] }} Kelas</span>
                                        <span class="text-slate-300">•</span>
                                        <span class="font-medium">{{ $schoolItem['students_count'] }} Siswa</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ $schoolItem['teachers_count'] }} Guru Pembimbing</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="inline-flex items-center gap-1.5 font-semibold text-slate-800">
                                        <i data-lucide="cpu" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-tabular">{{ $schoolItem['devices_count'] }}</span>
                                        <span class="text-slate-400 font-normal">Unit</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($schoolItem['has_coordinates'])
                                            <button
                                                type="button"
                                                onclick="window.focusSchoolOnDashboardMap && window.focusSchoolOnDashboardMap({{ $schoolItem['id'] }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold border border-emerald-200 transition-colors"
                                                title="Fokuskan ke Peta"
                                            >
                                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                <span class="hidden sm:inline">Peta</span>
                                            </button>
                                        @endif
                                        <a
                                            href="{{ $schoolItem['workspace_url'] }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#0a3120] text-white text-xs font-semibold shadow-2xs transition-colors"
                                        >
                                            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                                            <span>Buka Hub Sekolah</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 px-4 text-center text-slate-500">
                                    <i data-lucide="building" class="w-6 h-6 mx-auto mb-2 text-slate-300"></i>
                                    <p class="font-medium">Belum ada sekolah mitra terdaftar.</p>
                                    <a href="{{ route('master.schools.create') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                        <span>Daftarkan Sekolah Baru</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 5. RECENT STUDENTS & PLATFORM SUMMARY --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" aria-labelledby="activity-heading">
            <h2 id="activity-heading" class="sr-only">Aktivitas Pendaftaran Siswa &amp; Infrastruktur</h2>

            {{-- Recent Students (col-span-7) --}}
            <div class="lg:col-span-7 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-4 h-4 text-emerald-600"></i>
                            <span>Siswa Praktikan Terdaftar Terbaru</span>
                        </h3>
                        <span class="text-[11px] text-slate-400">Lintas Sekolah</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Pendaftaran siswa praktikan terakhir di platform Bitanic.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($recentStudents as $student)
                        <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-xs">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 truncate">{{ $student->name }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">
                                        {{ $student->school?->name ?? 'Tanpa Sekolah' }}
                                        @if($student->profile?->identifier_number)
                                            · NISN: {{ $student->profile->identifier_number }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <span class="text-[10px] text-slate-400 shrink-0">
                                {{ $student->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-slate-400">
                            Belum ada data siswa terdaftar.
                        </div>
                    @endforelse
                </div>

                <div class="pt-3 border-t border-slate-100 text-right">
                    <span class="text-xs text-slate-500">Total terdata: <strong class="font-tabular text-slate-800">{{ $macroMetrics['total_students'] }}</strong> siswa</span>
                </div>
            </div>

            {{-- Platform Diagnostics & Quick Links (col-span-5) --}}
            <div class="lg:col-span-5 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="server" class="w-4 h-4 text-emerald-600"></i>
                        <span>Operasional Hardware &amp; Gateway RS485</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Infrastruktur komunikasi sensor dan pengawasan stasiun.</p>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="font-semibold text-slate-800">Protokol Sensor Modbus</span>
                            <p class="text-[11px] text-slate-500">7-in-1 Soil Multi-Parameter RS485</p>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            Standar RTU
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="font-semibold text-slate-800">Pemeriksaan Raw Frame</span>
                            <p class="text-[11px] text-slate-500">Log heksadesimal CRC16 &amp; status buffer</p>
                        </div>
                        <a href="{{ url('/raw-logs') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 text-[11px]">
                            <span>Buka Terminal</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="font-semibold text-slate-800">Audit Trail &amp; Keamanan</span>
                            <p class="text-[11px] text-slate-500">Log aktivitas otorisasi &amp; modifikasi data</p>
                        </div>
                        <a href="{{ url('/settings') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 text-[11px]">
                            <span>Pengaturan</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Status Jaringan: Normal</span>
                    <span class="inline-flex items-center gap-1 text-emerald-700 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Gateway Siap</span>
                    </span>
                </div>
            </div>
        </section>

    @else
        {{-- AUTHENTICATED DASHBOARD (Teacher, Student, Admin) --}}

        {{-- 1. WELCOME BANNER CARD WITH CONTOUR ARTWORK --}}
        <section class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="welcome-heading">
            {{-- Subtle Topographic Contour Lines SVG (decorative backdrop on right) --}}
            <div class="absolute right-0 top-0 bottom-0 w-1/2 pointer-events-none opacity-40 hidden sm:block" aria-hidden="true">
                <svg class="w-full h-full text-slate-300" viewBox="0 0 500 200" fill="none" preserveAspectRatio="none">
                    <path d="M50,20 C150,70 300,10 500,50" stroke="currentColor" stroke-width="1.2" stroke-dasharray="2 3"/>
                    <path d="M0,60 C120,110 320,40 500,90" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M0,100 C140,150 330,70 500,130" stroke="currentColor" stroke-width="1.2" stroke-dasharray="2 3"/>
                    <path d="M0,140 C160,190 350,110 500,170" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M0,180 C180,220 380,140 500,200" stroke="currentColor" stroke-width="1.2"/>
                </svg>
            </div>

            <div class="relative z-10 space-y-4">
                {{-- Academic School Scope --}}
                @if($user?->school)
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>{{ $user->school->name }}</span>
                    </div>
                @endif

                {{-- Greeting & Condition --}}
                <div class="space-y-1">
                    <h2 id="welcome-heading" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                        {{ $welcomeGreeting }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-600 max-w-2xl leading-relaxed">
                        {{ $welcomeCondition }}
                    </p>
                </div>

                {{-- Quick Action Shortcuts --}}
                <div class="pt-2 flex flex-wrap items-center gap-2 text-xs">
                    @if($isSiswa)
                        <a
                            href="{{ url('/iot-monitoring') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="gauge" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Monitoring IoT</span>
                        </a>
                        <a
                            href="{{ url('/modules') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="book-open" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Modul Praktikum</span>
                        </a>
                        <a
                            href="{{ url('/reports') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="file-check" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>LKS Saya</span>
                        </a>
                        <a
                            href="{{ url('/assessment') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="award" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Rapor Saya</span>
                        </a>
                    @else
                        <a
                            href="{{ url('/iot-monitoring') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="gauge" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Monitoring IoT</span>
                        </a>
                        <a
                            href="{{ url('/trainer-kits') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="cpu" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Trainer Kit</span>
                        </a>
                        <a
                            href="{{ url('/raw-logs') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="terminal" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Debugger RS485</span>
                        </a>
                        <a
                            href="{{ url('/telemetry/history') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors font-medium"
                        >
                            <i data-lucide="database" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Riwayat Telemetri</span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        {{-- 2. SUMMARY METRICS ROW (1 Hero Dark Card + 2x2 White Cards Grid) --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">Ringkasan Operasional Laboratorium</h2>

            {{-- Left Featured Card: Dark Forest Green (#0c3c26) --}}
            <div class="lg:col-span-4 rounded-2xl bg-[#0c3c26] text-white p-5 flex flex-col justify-between shadow-xs">
                <div class="space-y-4">
                    {{-- Title --}}
                    <div class="flex items-center gap-2 text-emerald-200 text-xs font-semibold">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400"></i>
                        <span>Armada meja praktik</span>
                    </div>

                    {{-- Big Counter --}}
                    <div>
                        <span class="text-xs text-emerald-300 block mb-0.5">Meja Online</span>
                        <div class="flex items-baseline gap-2">
                            <span class="font-tabular text-4xl sm:text-5xl font-bold tracking-tight text-white">{{ $onlineKitsCount }}</span>
                            <span class="text-sm font-medium text-emerald-200/80">/ {{ $totalKitsCount }}</span>
                            <span class="text-xs text-emerald-300/80">
                                @if($totalKitsCount === 0)
                                    Belum ada meja terdaftar
                                @elseif($onlineKitsCount === $totalKitsCount)
                                    Armada siap pakai
                                @else
                                    {{ $offlineKitsCount }} tidak terhubung
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Status Breakdown Bar --}}
                    <div class="flex items-center gap-3 text-[11px] font-medium border-t border-white/10 pt-3">
                        <span class="inline-flex items-center gap-1 text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            Online: {{ $onlineKitsCount }}
                        </span>
                        <span class="inline-flex items-center gap-1 text-amber-300">
                            <span class="w-2 h-2 rounded-xs bg-amber-400"></span>
                            Peringatan: {{ count($anomalies) }}
                        </span>
                        <span class="inline-flex items-center gap-1 text-slate-300">
                            <span class="w-2 h-2 rounded-xs bg-slate-400"></span>
                            Offline: {{ $offlineKitsCount }}
                        </span>
                    </div>
                </div>

                {{-- Individual Kit Status Chips (Grid 3x2) --}}
                <div class="mt-5 grid grid-cols-3 gap-2">
                    @forelse($devices->take(6) as $kit)
                        @php $kitOnline = (bool) ($kit->is_online ?? false); @endphp
                        <a
                            href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                            class="px-2 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 border border-white/10 flex items-center gap-1.5 text-[11px] font-medium transition-colors"
                            title="{{ $kit->code }}: {{ $kitOnline ? 'Online' : 'Offline' }}"
                        >
                            <span class="w-1.5 h-1.5 rounded-full {{ $kitOnline ? 'bg-emerald-400' : 'bg-slate-400' }}"></span>
                            <span class="font-mono text-white truncate">{{ $kit->code }}</span>
                        </a>
                    @empty
                        <div class="col-span-3 text-center py-2 text-xs text-emerald-200/60">
                            Belum ada meja terdaftar
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Right 2x2 White Metric Cards Grid --}}
            <div class="lg:col-span-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- 1. Jadwal Terdekat --}}
                <div class="p-4 rounded-xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-1">
                        <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Jadwal terdekat</span>
                        </dt>
                        <dd class="text-sm font-bold text-slate-900 mt-1">
                            {{ $todaySchedule?->title ?? 'Tidak ada jadwal berikutnya' }}
                        </dd>
                    </div>
                    <dd class="text-xs text-slate-500 mt-3">
                        @if($todaySchedule)
                            {{ $todaySchedule->activity_date?->format('d/m/Y') }} · {{ substr($todaySchedule->start_time ?? '', 0, 5) }}–{{ substr($todaySchedule->end_time ?? '', 0, 5) }} WIB
                            @if($todaySchedule->participants->first()?->user)
                                · {{ $todaySchedule->participants->first()->user->name }}
                            @endif
                        @else
                            Belum ada jadwal terdaftar untuk {{ $totalKitsCount }} meja praktik
                        @endif
                    </dd>
                </div>

                {{-- 2. LKS Menunggu Review / Tugas LKS Saya --}}
                <div class="p-4 rounded-xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-1">
                        <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                            <i data-lucide="file-check" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>{{ $isSiswa ? 'Tugas LKS Saya' : 'LKS Menunggu Review' }}</span>
                        </dt>
                        <dd class="flex items-baseline gap-1.5 mt-1">
                            <span class="font-tabular text-2xl font-bold {{ $isSiswa && $studentReportsNeedingRevisionCount ? 'text-indigo-700' : ($isSiswa ? 'text-slate-900' : ($pendingReportsCount > 0 ? 'text-amber-700' : 'text-slate-900')) }}">{{ $isSiswa ? $studentOwnReportsCount : $pendingReportsCount }}</span>
                            <span class="text-xs font-medium text-slate-500">{{ $isSiswa ? 'laporan terkumpul' : 'laporan praktikan' }}</span>
                        </dd>
                    </div>
                    <dd class="text-xs text-slate-500 mt-3">
                        @if($isSiswa)
                            @if($studentOwnReportsCount === 0)
                                Belum ada laporan dikumpulkan.
                            @elseif($studentReportsNeedingRevisionCount > 0)
                                {{ $studentReportsNeedingRevisionCount }} laporan perlu revisi.
                            @else
                                Semua laporan telah direview.
                            @endif
                        @else
                            {{ $pendingReportsCount > 0 ? 'Laporan praktikan menunggu' : 'Antrean penilaian kosong.' }}
                        @endif
                    </dd>
                </div>

                {{-- 3. Peringatan Anomali --}}
                <div class="p-4 rounded-xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-1">
                        <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-500"></i>
                            <span>Peringatan Anomali</span>
                        </dt>
                        <dd class="flex items-baseline gap-1.5 mt-1">
                            <span class="font-tabular text-2xl font-bold {{ count($anomalies) > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $affectedStationsCount }}</span>
                            <span class="text-xs font-medium text-slate-500">meja terdampak</span>
                        </dd>
                    </div>
                    <dd class="text-xs text-slate-500 mt-3">
                        @if($hasFreshHardwareTelemetry && count($anomalies) > 0)
                            <span class="text-rose-600 font-medium">{{ count($anomalies) }} parameter melewati ambang pada data terbaru</span>
                        @elseif($totalKitsCount === 0)
                            Belum ada meja terdaftar untuk diperiksa
                        @elseif($onlineKitsCount === 0)
                            <span class="text-amber-700 font-medium">{{ $offlineKitsCount }} meja offline</span> belum dievaluasi karena tidak ada data hardware segar.
                        @else
                            Belum ada data hardware segar untuk diperiksa
                        @endif
                    </dd>
                </div>

                {{-- 4. Kehadiran Praktikum (Role-aware: Siswa vs Guru/Admin) --}}
                <div class="p-4 rounded-xl bg-white border border-slate-200/90 shadow-2xs flex flex-col justify-between">
                    <div class="space-y-1">
                        <dt class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                            <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>{{ $isSiswa ? 'Kehadiran Praktikum Saya' : 'Rata-rata Kehadiran Siswa' }}</span>
                        </dt>
                        <dd class="flex items-center gap-2 mt-1">
                            <div class="relative w-7 h-7 shrink-0 text-emerald-600" aria-hidden="true">
                                <svg class="w-7 h-7 transform -rotate-90" viewBox="0 0 36 36">
                                    <path class="text-slate-100" stroke-width="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="text-emerald-600" stroke-dasharray="{{ $attendanceDisplayPercent ?? 0 }}, 100" stroke-width="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                </svg>
                            </div>
                            <span class="font-tabular text-2xl font-bold text-slate-900">
                                {{ $attendanceDisplayPercent !== null ? number_format($attendanceDisplayPercent, 1) : '—' }}%
                            </span>
                        </dd>
                    </div>
                    <dd class="text-xs text-slate-500 mt-3">
                        @if($isSiswa)
                            @if($attendanceDisplayPercent !== null)
                                Kehadiran individual praktikum ATPH Anda semester ini.
                            @else
                                Belum ada rekam kehadiran praktikum Anda.
                            @endif
                        @else
                            @if($attendanceDisplayPercent !== null)
                                Rata-rata seluruh siswa pada cakupan sekolah. Sisa {{ number_format(100 - $attendanceDisplayPercent, 1) }}% tidak hadir.
                            @else
                                Belum ada data kehadiran siswa tercatat.
                            @endif
                        @endif
                    </dd>
                </div>
            </div>
        </section>

        {{-- 3. MIDDLE ROW (Agenda Praktikum Terdekat + Kesiapan Laboratorium Checklist) --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" aria-labelledby="agenda-heading">
            {{-- Left: Agenda Praktikum Terdekat (Timetable Grid) --}}
            <div class="lg:col-span-8 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="agenda-heading" class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-500"></i>
                            <span>Agenda Praktikum Terdekat</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pemakaian meja laboratorium SMK per jam.</p>
                    </div>
                    <a href="{{ url('/schedules') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 transition-colors">
                        <span>Kalender lengkap</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                {{-- Timetable Grid --}}
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-xs text-center border-collapse min-w-[560px]">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-500 font-mono text-[11px]">
                                <th class="w-16 py-2 px-2 text-left font-sans font-semibold text-slate-700">Meja</th>
                                @foreach(range(6, 17) as $hour)
                                    <th class="py-2 px-1 border-l border-slate-200 font-medium">{{ sprintf('%02d', $hour) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @forelse($devices->take(6) as $dev)
                                <tr>
                                    <td class="py-2.5 px-2 text-left font-semibold text-slate-800 bg-slate-50/40 whitespace-nowrap">
                                        {{ $dev->code }}
                                    </td>
                                    @foreach(range(6, 17) as $hour)
                                        @php
                                            $allocated = $hourlyAllocations[$dev->id][$hour] ?? null;
                                        @endphp
                                        <td class="py-2.5 px-1 border-l border-slate-100 text-slate-300">
                                            @if($allocated)
                                                <span class="inline-block w-full h-4 rounded bg-emerald-600/80 hover:bg-emerald-600 transition-colors cursor-pointer" title="{{ $allocated['title'] }} ({{ sprintf('%02d:00', $hour) }})"></span>
                                            @else
                                                <span class="inline-block w-full h-4 rounded bg-transparent"></span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="py-6 text-center text-slate-400 font-sans">
                                        Belum Ada Jadwal Praktikum
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Schedule Caption (Clock centralized to widget) --}}
                <div class="space-y-1 text-xs text-slate-500">
                    <p class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ $todaySchedulesCount > 0 ? 'bg-emerald-600' : 'bg-slate-400' }} shrink-0"></span>
                        @if($todaySchedulesCount > 0)
                            <span>{{ $todaySchedulesCount }} alokasi sesi praktikum aktif hari ini pada rentang jam operasional (06.00–18.00).</span>
                        @else
                            <span>Sesi praktikum belum ada jadwal. Sesi yang ditambahkan guru atau admin tampil sebagai balok di baris mejanya.</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Right: Kesiapan Laboratorium Checklist --}}
            <div class="lg:col-span-4 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                        <span>Kesiapan laboratorium</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Ringkasan kondisi saat ini.</p>
                </div>

                <div class="divide-y divide-slate-100 space-y-2 text-xs">
                    {{-- 1. Kit Terhubung --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <i data-lucide="{{ $onlineKitsCount > 0 ? 'check-circle' : 'alert-triangle' }}" class="w-4 h-4 shrink-0 mt-0.5 {{ $onlineKitsCount > 0 ? 'text-emerald-600' : 'text-amber-500' }}"></i>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Kit terhubung</p>
                                <p class="text-[11px] text-slate-400 truncate">Meja yang mengirim telemetri</p>
                            </div>
                        </div>
                        <span class="font-semibold {{ $onlineKitsCount === 0 ? 'text-amber-700' : 'text-emerald-700' }} shrink-0">
                            {{ $onlineKitsCount }} dari {{ $totalKitsCount }}
                        </span>
                    </div>

                    {{-- 2. Telemetri Segar --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <i data-lucide="{{ $hasFreshHardwareTelemetry ? 'check-circle' : 'alert-triangle' }}" class="w-4 h-4 shrink-0 mt-0.5 {{ $hasFreshHardwareTelemetry ? 'text-emerald-600' : 'text-amber-500' }}"></i>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Telemetri segar</p>
                                <p class="text-[11px] text-slate-400 truncate">Data hardware terbaru</p>
                            </div>
                        </div>
                        <span class="font-semibold {{ $hasFreshHardwareTelemetry ? 'text-emerald-700' : 'text-slate-500' }} shrink-0">
                            {{ $hasFreshHardwareTelemetry ? 'Tersedia' : 'Tidak ada' }}
                        </span>
                    </div>

                    {{-- 3. Jadwal Praktikum --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <i data-lucide="calendar" class="w-4 h-4 shrink-0 mt-0.5 {{ $todaySchedulesCount > 0 ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Jadwal praktikum</p>
                                <p class="text-[11px] text-slate-400 truncate">Sesi terdaftar hari ini</p>
                            </div>
                        </div>
                        <span class="font-semibold {{ $todaySchedulesCount > 0 ? 'text-emerald-700' : 'text-slate-500' }} shrink-0">
                            {{ $todaySchedulesCount > 0 ? $todaySchedulesCount . ' sesi aktif' : 'Belum ada' }}
                        </span>
                    </div>

                    {{-- 4. Antrean Penilaian LKS --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <i data-lucide="file-check" class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600"></i>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Antrean penilaian LKS</p>
                                <p class="text-[11px] text-slate-400 truncate">Laporan praktikan masuk</p>
                            </div>
                        </div>
                        <span class="font-semibold {{ $pendingReportsCount > 0 ? 'text-amber-700' : 'text-emerald-700' }} shrink-0">
                            {{ $pendingReportsCount > 0 ? $pendingReportsCount . ' laporan' : 'Kosong' }}
                        </span>
                    </div>
                </div>

                <div class="pt-2">
                    <a
                        href="{{ url('/schedules') }}"
                        class="w-full py-2 px-3 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
                    >
                        <span>Lihat Manajemen Jadwal</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        </section>

        {{-- 4. HARDWARE FLEET TABLE (Status Meja Praktikum IoT) --}}
        <section class="rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs" aria-labelledby="fleet-heading">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 id="fleet-heading" class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="cpu" class="w-4 h-4 text-emerald-700"></i>
                        <span>Status meja praktikum IoT</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Kondisi telemetri terkini pada masing-masing kit meja siswa.</p>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1 text-slate-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ $onlineKitsCount }} online
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-600">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        {{ $offlineKitsCount }} offline
                    </span>
                    @if(! $isSiswa)
                        <a href="{{ url('/trainer-kits') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 transition-colors">
                            <span>Kelola armada kit</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    @else
                        <a href="{{ url('/iot-monitoring') }}" class="font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1 transition-colors">
                            <span>Buka Monitoring IoT</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Scroll Cue for Mobile --}}
            <p class="flex items-center gap-1.5 text-xs text-slate-500 lg:hidden">
                <i data-lucide="chevrons-right" class="w-3.5 h-3.5 shrink-0" aria-hidden="true"></i>
                <span>Geser tabel ke samping untuk melihat semua kolom</span>
            </p>

            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-xs min-w-[840px]" role="table">
                    <thead>
                        <tr class="text-left font-semibold text-slate-500 bg-slate-50/70 border-b border-slate-200 whitespace-nowrap">
                            <th class="py-3 px-3 w-44 min-w-[150px]">Meja</th>
                            <th class="py-3 px-3 w-28 min-w-[90px] text-center">Status</th>
                            <th class="py-3 px-3 w-28 min-w-[90px] text-center">pH</th>
                            <th class="py-3 px-3 w-28 min-w-[90px] text-center">Lengas (%)</th>
                            <th class="py-3 px-3 w-24 min-w-[80px] text-right">EC (µS/cm)</th>
                            <th class="py-3 px-3 w-32 min-w-[110px]">Terakhir</th>
                            <th class="py-3 px-3 w-24 min-w-[80px] text-center">Sumber</th>
                            <th class="py-3 px-3 w-28 min-w-[90px] text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($devices as $kit)
                            @php
                                $latest = $kit->latestReading;
                                $isOnline = (bool) ($kit->is_online ?? false);
                                $phVal = $latest && $latest->ph !== null ? (float) $latest->ph : null;
                                $humVal = $latest && $latest->humidity !== null ? (float) $latest->humidity : null;
                                $ecVal = $latest && $latest->ec !== null ? (float) $latest->ec : null;
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                {{-- Meja --}}
                                <td class="py-3 px-3">
                                    <div>
                                        <span class="font-mono text-xs font-bold text-slate-900">{{ $kit->code }}</span>
                                        <p class="text-xs text-slate-700 font-medium mt-0.5 truncate">{{ $kit->bench_location ?? $kit->name }}</p>
                                        @if($isSuperuser && $kit->school)
                                            <p class="text-[11px] text-slate-400 mt-0.5 truncate max-w-[200px]">{{ $kit->school->name }}</p>
                                        @endif
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="py-3 px-3 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold {{ $isOnline ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $isOnline ? 'bg-emerald-600' : 'bg-slate-400' }}" aria-hidden="true"></span>
                                        {{ $isOnline ? 'Online' : 'Offline' }}
                                    </span>
                                </td>

                                {{-- pH with Range Bar --}}
                                <td class="py-3 px-3 text-center">
                                    <div class="space-y-1">
                                        <span class="font-tabular text-xs font-semibold {{ $phVal !== null && $phVal < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                            {{ $phVal !== null ? number_format($phVal, 2) : '—' }}
                                        </span>
                                        <div class="w-20 mx-auto">
                                            <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                                <div class="w-[39%] bg-rose-200" title="0-5.5 (Masam)"></div>
                                                <div class="w-[21%] bg-emerald-300" title="5.5-7.5 (Optimal)"></div>
                                                <div class="w-[40%] bg-amber-200" title="7.5-14 (Basa)"></div>
                                            </div>
                                            <div class="flex justify-between text-[9px] text-slate-400 mt-0.5 font-mono">
                                                <span>0</span>
                                                <span>14</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Lengas with Range Bar --}}
                                <td class="py-3 px-3 text-center">
                                    <div class="space-y-1">
                                        <span class="font-tabular text-xs font-semibold {{ $humVal !== null && $humVal < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                            {{ $humVal !== null ? number_format($humVal, 1) : '—' }}
                                        </span>
                                        <div class="w-20 mx-auto">
                                            <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                                <div class="w-[25%] bg-rose-200" title="0-25% (Kering)"></div>
                                                <div class="w-[45%] bg-emerald-300" title="25-70% (Optimal)"></div>
                                                <div class="w-[30%] bg-amber-200" title="70-100% (Basah)"></div>
                                            </div>
                                            <div class="flex justify-between text-[9px] text-slate-400 mt-0.5 font-mono">
                                                <span>0</span>
                                                <span>100</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- EC --}}
                                <td class="py-3 px-3 text-right">
                                    <span class="font-tabular text-xs font-semibold text-slate-800">
                                        {{ $ecVal !== null ? number_format($ecVal, 0) : '—' }}
                                    </span>
                                </td>

                                {{-- Terakhir --}}
                                <td class="py-3 px-3 text-slate-500 text-xs">
                                    {{ $latest?->recorded_at ? $latest->recorded_at->diffForHumans() : 'Belum Ada Transmisi Data' }}
                                </td>

                                {{-- Sumber (Provenance) --}}
                                <td class="py-3 px-3 text-center">
                                    @if($latest?->provenance)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $latest->provenance === 'hardware' ? 'bg-emerald-50 text-emerald-700' : ($latest->provenance === 'simulation' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600') }}">
                                            {{ $latest->provenance === 'hardware' ? 'Hardware' : ($latest->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="py-3 px-3 text-right">
                                    <a
                                        href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold transition-colors"
                                        aria-label="Lihat detail stasiun meja {{ $kit->code }}"
                                    >
                                        <span>Stasiun</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center space-y-1">
                                    <p class="text-xs font-semibold text-slate-700">Belum Ada Meja Praktikum IoT Terdaftar</p>
                                    <p class="text-xs text-slate-500">Stasiun meja praktikum akan tampil di sini setelah didaftarkan.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 5. BOTTOM ROW (Tren Telemetri 24 Jam + Anomali Lahan) --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" aria-labelledby="tren-heading">
            {{-- Left: Tren Telemetri 24 Jam --}}
            <div class="lg:col-span-7 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 id="tren-heading" class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-slate-500"></i>
                            <span>Tren telemetri 24 jam</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Rata-rata seluruh meja pada cakupan sekolah.</p>
                    </div>

                    {{-- Legend --}}
                    <div class="flex items-center gap-3 text-[11px] font-medium text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="w-3 h-0.5 bg-emerald-600 rounded-full"></span>
                            pH
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-3 h-0.5 bg-teal-500 rounded-full"></span>
                            Lengas (%)
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-3 h-0.5 bg-amber-500 rounded-full"></span>
                            EC (µS/cm)
                        </span>
                    </div>
                </div>

                {{-- Chart Canvas with Empty State Overlay --}}
                <div class="relative h-48 w-full border border-slate-100 rounded-xl bg-slate-50/40 p-4 flex flex-col justify-between">
                    {{-- Y Axis Grid Lines --}}
                    <div class="absolute inset-x-4 inset-y-4 flex flex-col justify-between pointer-events-none opacity-40">
                        <div class="border-b border-slate-200 flex justify-between text-[9px] text-slate-400 font-mono"><span>100</span></div>
                        <div class="border-b border-slate-200 flex justify-between text-[9px] text-slate-400 font-mono"><span>75</span></div>
                        <div class="border-b border-slate-200 flex justify-between text-[9px] text-slate-400 font-mono"><span>50</span></div>
                        <div class="border-b border-slate-200 flex justify-between text-[9px] text-slate-400 font-mono"><span>25</span></div>
                        <div class="border-b border-slate-200 flex justify-between text-[9px] text-slate-400 font-mono"><span>0</span></div>
                    </div>

                    {{-- Empty State Center Message --}}
                    <div class="relative z-10 m-auto text-center space-y-1 py-4 px-6 rounded-xl bg-white/90 border border-slate-200/80 shadow-2xs">
                        <i data-lucide="wifi-off" class="w-5 h-5 text-slate-400 mx-auto"></i>
                        <p class="text-xs font-semibold text-slate-700">Menunggu transmisi pertama</p>
                        <p class="text-[11px] text-slate-500">Grafik terisi setelah kit mengirim data.</p>
                    </div>

                    {{-- X Axis Time Labels --}}
                    <div class="relative z-10 flex justify-between text-[10px] text-slate-400 font-mono px-2 pt-2 border-t border-slate-200">
                        <span>00.00</span>
                        <span>04.00</span>
                        <span>08.00</span>
                        <span>12.00</span>
                        <span>16.00</span>
                        <span>20.00</span>
                        <span>23.00</span>
                    </div>
                </div>
            </div>

            {{-- Right: Anomali Lahan dan Tindakan Lapangan --}}
            <div class="lg:col-span-5 rounded-2xl bg-white border border-slate-200/90 p-5 space-y-4 shadow-2xs flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
                        <span>Anomali lahan dan tindakan lapangan</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Deteksi otomatis batas ambang toleransi agronomi.</p>
                </div>

                <div class="divide-y divide-slate-100 space-y-2 text-xs">
                    {{-- 1. pH Parameter Status --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <i data-lucide="flask-conical" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Keasaman (pH)</p>
                                <p class="text-[11px] text-slate-400 truncate">Batas ambang tanah dan larutan</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 shrink-0">
                            Tidak dievaluasi
                        </span>
                    </div>

                    {{-- 2. Kelembapan Media Status --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <div class="w-6 h-6 rounded-md bg-teal-50 text-teal-700 flex items-center justify-center shrink-0">
                                <i data-lucide="droplet" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Kelembapan media (lengas)</p>
                                <p class="text-[11px] text-slate-400 truncate">Batas ambang kadar air</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 shrink-0">
                            Tidak dievaluasi
                        </span>
                    </div>

                    {{-- 3. Konduktivitas EC Status --}}
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                                <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">Konduktivitas (EC)</p>
                                <p class="text-[11px] text-slate-400 truncate">Batas ambang nutrisi terlarut</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 shrink-0">
                            Tidak dievaluasi
                        </span>
                    </div>
                </div>

                {{-- Truthful Anomaly Disclaimer / Detailed Active Anomalies --}}
                @if(count($anomalies) > 0)
                    <div class="space-y-2 pt-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800">Perlu Intervensi</span>
                        @foreach($anomalies as $ano)
                            <div class="p-2.5 rounded-lg border border-rose-200 bg-rose-50/50 text-[11px] space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-bold text-slate-900">{{ $ano['device'] }}</span>
                                    <span class="font-semibold text-rose-700">{{ $ano['parameter'] }}: {{ $ano['value'] }}</span>
                                </div>
                                <p class="text-slate-600">{{ $ano['message'] }}</p>
                                <p class="font-medium text-emerald-800">Tindakan: {{ $ano['action'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pt-2 text-[11px] text-slate-500 leading-relaxed border-t border-slate-100">
                        @if($totalKitsCount === 0)
                            <p class="text-slate-500">Data Pemantauan Tidak Tersedia. Belum ada meja terdaftar.</p>
                        @elseif($onlineKitsCount === 0)
                            <p class="text-slate-500">Data Pemantauan Tidak Tersedia. Semua meja praktikum sedang offline, jadi belum ada telemetri segar untuk dievaluasi. Hanya data hardware segar yang dihitung.</p>
                        @elseif(!$hasFreshHardwareTelemetry)
                            <p class="text-slate-500">Data Pemantauan Tidak Tersedia. Belum ada data hardware segar.</p>
                        @else
                            <p class="text-emerald-700 font-medium">Tidak Ada Anomali Terdeteksi pada data hardware terbaru.</p>
                        @endif
                    </div>
                @endif
            </div>
        </section>

    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('macro-distribution-map');
    if (!mapElement || typeof window.L === 'undefined') return;

    const pins = @json($mapPins);
    if (!pins || pins.length === 0) return;

    // Inisialisasi peta Leaflet dengan interaktivitas zoom & navigasi penuh
    const map = L.map('macro-distribution-map', {
        zoomControl: false, // Gunakan tombol kontrol melayang Bitani GIS yang serasi
        scrollWheelZoom: true,
        smoothWheelZoom: true,
        doubleClickZoom: true,
        touchZoom: true,
        dragging: true,
        boxZoom: true,
        keyboard: true,
    }).setView([-2.548926, 118.0148634], 5);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Indikator skala metrik
    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

    const markersGroup = L.featureGroup().addTo(map);
    const markerRegistry = {};

    pins.forEach(pin => {
        if (pin.latitude === null || pin.longitude === null) return;

        const colorMap = {
            'active': '#16a34a',
            'pending': '#eab308',
            'expired': '#dc2626',
            'uninitialized': '#64748b'
        };
        const color = colorMap[pin.license_status] || '#64748b';

        const customIcon = L.divIcon({
            className: 'custom-pin-marker',
            html: `
                <div style="background-color: ${color}; width: 30px; height: 30px; border-radius: 9999px; border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s ease;">
                    <div style="width: 10px; height: 10px; border-radius: 9999px; background-color: white;"></div>
                </div>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });

        const statusBadgeStyle = {
            'active': 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;',
            'pending': 'background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;',
            'expired': 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
            'uninitialized': 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;'
        }[pin.license_status] || 'background: #f1f5f9; color: #475569;';

        const popupContent = `
            <div style="font-family: Inter, sans-serif; min-width: 230px; padding: 4px 2px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; ${statusBadgeStyle}">
                        ${pin.license_status_label}
                    </span>
                    <span style="font-size: 11px; color: #64748b; font-family: monospace;">NPSN: ${pin.npsn}</span>
                </div>
                <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                    ${pin.name}
                </h4>
                <p style="margin: 0 0 10px 0; font-size: 11px; color: #64748b;">
                    ${pin.city}, ${pin.province}
                </p>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #475569; margin-bottom: 10px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; padding: 6px 0;">
                    <span>Siswa: <strong>${pin.students_count}</strong></span>
                    <span>Trainer Kit: <strong>${pin.devices_count} Unit</strong></span>
                </div>
                <a href="${pin.workspace_url}" style="display: block; text-align: center; background: #0c3c26; color: white; text-decoration: none; font-size: 11px; font-weight: 600; padding: 7px 12px; border-radius: 8px; transition: background 0.15s ease;">
                    Buka School Workspace Hub &rarr;
                </a>
            </div>
        `;

        const marker = L.marker([pin.latitude, pin.longitude], { icon: customIcon });
        marker.bindPopup(popupContent);
        marker.on('click', () => {
            map.flyTo([pin.latitude, pin.longitude], Math.max(map.getZoom(), 11), { duration: 0.8 });
        });
        marker.addTo(markersGroup);
        markerRegistry[pin.id] = { marker, pin };
    });

    // Invalidate size untuk stabilitas kalkulasi viewport peta
    setTimeout(() => {
        map.invalidateSize();
        if (markersGroup.getLayers().length > 0) {
            map.fitBounds(markersGroup.getBounds().pad(0.18), { maxZoom: 12 });
        }
    }, 250);

    // Dynamic zoom badge updater
    const updateZoomBadge = () => {
        const badge = document.getElementById('map-zoom-level-badge');
        if (badge) {
            badge.textContent = 'Zoom ' + map.getZoom() + 'x';
        }
    };
    map.on('zoomend', updateZoomBadge);
    updateZoomBadge();

    // Event listeners tombol navigasi GIS
    const btnZoomIn = document.getElementById('btn-map-zoom-in');
    if (btnZoomIn) {
        btnZoomIn.addEventListener('click', () => {
            map.zoomIn();
        });
    }

    const btnZoomOut = document.getElementById('btn-map-zoom-out');
    if (btnZoomOut) {
        btnZoomOut.addEventListener('click', () => {
            map.zoomOut();
        });
    }

    const btnFitBounds = document.getElementById('btn-map-fit-bounds');
    if (btnFitBounds) {
        btnFitBounds.addEventListener('click', () => {
            if (markersGroup.getLayers().length > 0) {
                map.flyToBounds(markersGroup.getBounds().pad(0.18), { maxZoom: 12, duration: 1 });
            }
        });
    }

    const btnResetIndonesia = document.getElementById('btn-map-reset-indonesia');
    if (btnResetIndonesia) {
        btnResetIndonesia.addEventListener('click', () => {
            map.flyTo([-2.548926, 118.0148634], 5, { duration: 1 });
        });
    }

    // Fungsi global fokus peta dari direktori tabel
    window.focusSchoolOnDashboardMap = function(schoolId) {
        const item = markerRegistry[schoolId];
        if (!item) return;

        const mapContainer = document.getElementById('macro-distribution-map');
        if (mapContainer) {
            mapContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        setTimeout(() => {
            map.flyTo([item.pin.latitude, item.pin.longitude], 13, { duration: 1 });
            setTimeout(() => {
                item.marker.openPopup();
            }, 900);
        }, 300);
    };

    if (typeof window.lucide !== 'undefined' && window.lucide.createIcons) {
        window.lucide.createIcons();
    }
});
</script>
@endpush
