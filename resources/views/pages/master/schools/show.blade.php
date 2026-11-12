@extends('layouts.app')

@section('content')
<div x-data="{ activeTab: '{{ request('tab', 'profile') }}' }" class="max-w-7xl mx-auto space-y-6">

    <!-- Breadcrumb & Hub Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <a href="{{ route('master.schools.index') }}" class="hover:text-slate-800 transition-colors">Master Direktori Sekolah</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">{{ $school->name }}</span>
                <span>/</span>
                <span class="text-emerald-700 font-semibold">School Workspace Hub</span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
                    {{ $school->name }}
                </h1>

                <!-- School Status Badge -->
                @if($school->status === 'active')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Nonaktif
                    </span>
                @endif

                <!-- License Status Badge -->
                @php
                    $licStatus = $licenseResolution['status'] ?? 'uninitialized';
                    $licBadgeColor = match($licStatus) {
                        'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'expired' => 'bg-rose-50 text-rose-800 border-rose-200',
                        default => 'bg-slate-100 text-slate-600 border-slate-200',
                    };
                    $licStatusLabel = $licenseResolution['status_label'] ?? 'Belum Ada Lisensi';
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $licBadgeColor }}">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                    <span>Lisensi: {{ $licStatusLabel }}</span>
                </span>
            </div>

            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                NPSN: <span class="font-mono font-semibold text-slate-800">{{ $school->npsn ?: '—' }}</span>
                &bull; Wilayah: <span class="font-medium text-slate-700">{{ $school->city ?: '—' }}, {{ $school->province ?: '—' }}</span>
                &bull; Terdaftar sejak: <span class="font-tabular">{{ $school->created_at->format('d M Y') }}</span>
            </p>
        </div>

        <!-- Quick Hub Actions -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- Filtered Lab Dashboard -->
            <a
                href="{{ url('/?school_id=' . $school->id) }}"
                title="Buka lab dashboard khusus untuk sekolah ini"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#0a3120] shadow-2xs transition-colors"
            >
                <i data-lucide="gauge" class="w-3.5 h-3.5"></i>
                <span>Lab Dashboard Sekolah</span>
            </a>

            <!-- Edit School Profile -->
            <a
                href="{{ route('master.schools.edit', $school) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-2xs transition-colors"
            >
                <i data-lucide="edit" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Edit Profil</span>
            </a>

            <!-- Toggle Active Status -->
            <form action="{{ route('master.schools.toggle-status', $school) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl transition-colors {{ $school->status === 'active' ? 'bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100' : 'bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100' }}"
                    onclick="return confirm('Ubah status operasional sekolah ini?');"
                >
                    <i data-lucide="{{ $school->status === 'active' ? 'power-off' : 'power' }}" class="w-3.5 h-3.5"></i>
                    <span>{{ $school->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Key Associated Resource Metric Cards (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Siswa Terdaftar -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Siswa Praktikan</span>
                <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-bold text-slate-900 font-tabular">{{ $studentsCount }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Tersebar di {{ $classrooms->count() }} rombongan belajar</p>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100">
                <button @click="activeTab = 'classes'" class="text-[11px] font-semibold text-teal-700 hover:text-teal-800 inline-flex items-center gap-1">
                    <span>Lihat Daftar Kelas & Siswa</span>
                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </button>
            </div>
        </div>

        <!-- 2. Tenaga Pendidik & Admin -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Guru &amp; Admin Sekolah</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 font-tabular">{{ $teachersCount }}</span>
                    <span class="text-xs text-slate-500">Guru</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-sm font-semibold text-slate-700 font-tabular">{{ $adminsCount }}</span>
                    <span class="text-xs text-slate-500">Admin</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Pengampu lab & pengelola data warga sekolah</p>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100">
                <button @click="activeTab = 'teachers'" class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-800 inline-flex items-center gap-1">
                    <span>Kelola Pengajar & Admin</span>
                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </button>
            </div>
        </div>

        <!-- 3. Hardware Trainer Kit -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Workstation Trainer Kit</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                    <i data-lucide="cpu" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 font-tabular">{{ $devices->count() }}</span>
                    <span class="text-xs text-slate-500">Unit Meja</span>
                    <span class="text-xs font-medium text-emerald-700">({{ $devices->where('is_online', true)->count() }} Online)</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Sensor 7-in-1 RS485 & 4 relai aktuator</p>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100">
                <button @click="activeTab = 'devices'" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                    <span>Inspeksi Meja Lab</span>
                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </button>
            </div>
        </div>

        <!-- 4. Lisensi LMS Sekolah -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Kontrak Lisensi LMS</span>
                <div class="w-8 h-8 rounded-lg bg-[#0c3c26]/10 text-[#0c3c26] flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                @if($license)
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-bold font-tabular {{ $licStatus === 'active' ? 'text-emerald-700' : ($licStatus === 'pending' ? 'text-amber-700' : 'text-rose-700') }}">
                            {{ $licStatusLabel }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        @if($licenseResolution && $licenseResolution['days_remaining'] !== null)
                            Sisa masa aktif: <strong class="text-slate-700">{{ $licenseResolution['days_remaining'] }} hari</strong>
                        @else
                            Masa aktif tidak terbatas / termin aktif
                        @endif
                    </p>
                @else
                    <div class="text-sm font-bold text-slate-600">Belum Ada Lisensi</div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Sekolah belum memiliki hak akses LMS</p>
                @endif
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100">
                @if($license)
                    <a href="{{ route('master.licenses.show', $license) }}" class="text-[11px] font-semibold text-[#0c3c26] hover:underline inline-flex items-center gap-1">
                        <span>Detail Lisensi: {{ $license->license_number }}</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                @else
                    <a href="{{ route('master.licenses.index') }}" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                        <span>+ Inisialisasi Lisensi Sekarang</span>
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- School Workspace Hub Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="flex space-x-1 sm:space-x-3 overflow-x-auto pb-1" aria-label="Tabs Workspace Sekolah">
            <!-- Tab 1: Profil & Lisensi -->
            <button
                type="button"
                @click="activeTab = 'profile'"
                :class="activeTab === 'profile' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="building-2" class="w-4 h-4"></i>
                <span>Profil &amp; Lisensi</span>
            </button>

            <!-- Tab 2: Kelas & Siswa -->
            <button
                type="button"
                @click="activeTab = 'classes'"
                :class="activeTab === 'classes' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                <span>Kelas &amp; Siswa</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-600 font-tabular">{{ $studentsCount }}</span>
            </button>

            <!-- Tab 3: Guru & Admin Sekolah -->
            <button
                type="button"
                @click="activeTab = 'teachers'"
                :class="activeTab === 'teachers' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="users" class="w-4 h-4"></i>
                <span>Guru &amp; Admin</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-600 font-tabular">{{ $teachersCount + $adminsCount }}</span>
            </button>

            <!-- Tab 4: Trainer Kit Meja -->
            <button
                type="button"
                @click="activeTab = 'devices'"
                :class="activeTab === 'devices' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="cpu" class="w-4 h-4"></i>
                <span>Trainer Kit Meja</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-600 font-tabular">{{ $devices->count() }}</span>
            </button>

            <!-- Tab 5: Jadwal Praktikum -->
            <button
                type="button"
                @click="activeTab = 'schedules'"
                :class="activeTab === 'schedules' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="calendar" class="w-4 h-4"></i>
                <span>Jadwal Lab</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-600 font-tabular">{{ $school->schedules_count }}</span>
            </button>

            <!-- Tab 6: Laporan LKS & Rapor -->
            <button
                type="button"
                @click="activeTab = 'reports'"
                :class="activeTab === 'reports' ? 'border-[#0c3c26] text-[#0c3c26] font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 transition-colors"
            >
                <i data-lucide="file-check" class="w-4 h-4"></i>
                <span>Laporan LKS &amp; Rapor</span>
                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-slate-100 text-slate-600 font-tabular">{{ $reportsCount }}</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: PROFIL & LISENSI -->
    <div x-show="activeTab === 'profile'" class="space-y-6">
        <!-- Sub-panel A: Identitas & Wilayah Kemendagri -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-6">
            <div>
                <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-[#0c3c26]"></i>
                    <span>Spesifikasi Identitas &amp; Wilayah Tenant</span>
                </h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Nama Resmi Sekolah</dt>
                        <dd class="font-semibold text-slate-900 text-sm">{{ $school->name }}</dd>
                    </div>

                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">NPSN</dt>
                        <dd class="font-mono font-medium text-slate-900">
                            {{ $school->npsn ?: '— Belum diisi —' }}
                        </dd>
                    </div>

                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Provinsi (Kemendagri)</dt>
                        <dd class="font-medium text-slate-900 flex items-center gap-2">
                            @if($school->provinceRelation)
                                <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                    {{ $school->provinceRelation->code }}
                                </span>
                                <span>{{ $school->provinceRelation->name }}</span>
                            @else
                                <span>{{ $school->province ?: '—' }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Kabupaten / Kota (Kemendagri)</dt>
                        <dd class="font-medium text-slate-900 flex items-center gap-2">
                            @if($school->regencyRelation)
                                <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                    {{ $school->regencyRelation->code }}
                                </span>
                                <span>{{ $school->regencyRelation->name }}</span>
                            @else
                                <span>{{ $school->city ?: '—' }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Kecamatan (Kemendagri)</dt>
                        <dd class="font-medium text-slate-900 flex items-center gap-2">
                            @if($school->districtRelation)
                                <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                    {{ $school->districtRelation->code }}
                                </span>
                                <span>{{ $school->districtRelation->name }}</span>
                            @else
                                <span class="text-slate-400 italic">Belum dikaitkan</span>
                            @endif
                        </dd>
                    </div>

                    <div class="border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Nomor Telepon</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $school->phone ?: '—' }}
                        </dd>
                    </div>

                    <div class="sm:col-span-2 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Alamat Lengkap</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $school->address ?: '— Belum diisi —' }}
                        </dd>
                    </div>

                    <div class="sm:col-span-2 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500 mb-0.5">Koordinat Geografis (Peta GIS)</dt>
                        <dd class="font-medium text-slate-900">
                            @if($school->hasVerifiedCoordinates())
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs">
                                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                                            <span class="font-mono font-bold">{{ number_format($school->latitude, 6) }}, {{ number_format($school->longitude, 6) }}</span>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                            Terverifikasi
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            Diverifikasi {{ $school->coordinate_verified_at->translatedFormat('d M Y H:i') }}
                                        </span>
                                    </div>
                                    <form action="{{ route('master.schools.unverify-coordinates', $school) }}" method="POST" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition-colors"
                                            onclick="return confirm('Cabut verifikasi koordinat sekolah ini? Pin peta akan dinonaktifkan.');"
                                        >
                                            Cabut Verifikasi
                                        </button>
                                    </form>
                                </div>
                            @elseif($school->latitude !== null && $school->longitude !== null)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-xs">
                                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-amber-600"></i>
                                            <span class="font-mono font-bold">{{ number_format($school->latitude, 6) }}, {{ number_format($school->longitude, 6) }}</span>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                            Menunggu Verifikasi
                                        </span>
                                        <span class="text-xs text-amber-700">
                                            Dikecualikan dari pin peta sampai diverifikasi.
                                        </span>
                                    </div>
                                    <form action="{{ route('master.schools.verify-coordinates', $school) }}" method="POST" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-3 py-1 text-xs font-semibold rounded-lg bg-[#0c3c26] text-white hover:bg-[#082819] shadow-xs transition-colors"
                                        >
                                            Verifikasi Koordinat
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-xs">
                                            <i data-lucide="map-pin-off" class="w-3.5 h-3.5 text-slate-400"></i>
                                            <span>Lokasi belum dipetakan</span>
                                        </span>
                                        <span class="text-xs text-slate-400">Belum ada data koordinat lintang/bujur.</span>
                                    </div>
                                    <a href="{{ route('master.schools.edit', $school) }}" class="text-xs text-[#0c3c26] hover:underline font-semibold">
                                        + Input Koordinat
                                    </a>
                                </div>
                            @endif
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-slate-500 mb-0.5">Nama Laboratorium Smart Greenhouse</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $school->schoolSetting?->lab_name ?? 'Laboratorium Smart Greenhouse & ATPH' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Sub-panel B: Lisensi LMS Institusional & Termin Kontrak -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                        <span>Status Lisensi LMS &amp; Termin Hak Akses</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Kontrak lisensi institusional yang memvalidasi seluruh meja Trainer Kit di sekolah ini.</p>
                </div>
                <div>
                    @if($license)
                        <a href="{{ route('master.licenses.show', $license) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            <span>Buka Master Lisensi</span>
                        </a>
                    @else
                        <a href="{{ route('master.licenses.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold transition-colors">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>Inisialisasi Lisensi Baru</span>
                        </a>
                    @endif
                </div>
            </div>

            @if($license)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 block mb-1">Nomor Lisensi</span>
                        <span class="font-mono font-bold text-slate-900 text-sm">{{ $license->license_number }}</span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 block mb-1">Status Keabsahan</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold border {{ $licBadgeColor }}">
                            {{ $licStatusLabel }}
                        </span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 block mb-1">Sisa Masa Aktif</span>
                        <span class="font-bold text-slate-900 text-sm font-tabular">
                            {{ $licenseResolution['days_remaining'] !== null ? $licenseResolution['days_remaining'] . ' Hari' : '—' }}
                        </span>
                    </div>
                </div>

                <!-- Terms History Table -->
                <div class="mt-4">
                    <h4 class="text-xs font-bold text-slate-800 mb-2 uppercase tracking-wider">Riwayat Termin Kontrak Lisensi</h4>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Periode</th>
                                    <th class="py-2.5 px-3">Tanggal Mulai</th>
                                    <th class="py-2.5 px-3">Tanggal Kedaluwarsa</th>
                                    <th class="py-2.5 px-3">Status Termin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($licenseTerms as $term)
                                    @php
                                        $now = now('Asia/Jakarta');
                                        $termStatus = match(true) {
                                            $term->expiration_date->lt($now) => 'expired',
                                            $term->start_date->gt($now) => 'pending',
                                            default => 'active',
                                        };
                                        $termBadge = match($termStatus) {
                                            'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                                            default => 'bg-rose-50 text-rose-800 border-rose-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="py-2.5 px-3 font-semibold text-slate-900">Termin #{{ $loop->iteration }}</td>
                                        <td class="py-2.5 px-3 font-mono text-slate-700">{{ $term->start_date->format('d/m/Y') }}</td>
                                        <td class="py-2.5 px-3 font-mono text-slate-700">{{ $term->expiration_date->format('d/m/Y') }}</td>
                                        <td class="py-2.5 px-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $termBadge }}">
                                                {{ ucfirst($termStatus) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 px-3 text-center text-slate-400 italic">Belum ada termin kontrak terdaftar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="p-6 text-center rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <i data-lucide="shield-alert" class="w-8 h-8 text-amber-500 mx-auto"></i>
                    <p class="text-sm font-semibold text-slate-800">Sekolah Ini Belum Memiliki Lisensi LMS Terdaftar</p>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">Trainer Kit di sekolah ini memerlukan lisensi aktif agar dapat mengakses modul pembelajaran dan pengisian LKS siswa.</p>
                    <div class="pt-2">
                        <a href="{{ route('master.licenses.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0c3c26] text-white text-xs font-semibold shadow-xs">
                            <i data-lucide="key" class="w-3.5 h-3.5"></i>
                            <span>Buka Master Lisensi untuk Inisialisasi</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sub-panel C: Audit Trail Tenant -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-[#0c3c26]"></i>
                <span>Riwayat Audit Terakhir Tenant</span>
            </h3>

            @if($recentAudits->isNotEmpty())
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Waktu (WIB)</th>
                                <th class="py-2.5 px-3">Aksi</th>
                                <th class="py-2.5 px-3">Pelaksana</th>
                                <th class="py-2.5 px-3">Rincian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentAudits as $log)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 font-tabular whitespace-nowrap">
                                        {{ $log->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-slate-900">
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700">
                                            {{ $log->action }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-700">
                                        {{ $log->actor_name }} ({{ $log->actor_role }})
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600 truncate max-w-xs">
                                        {{ $log->details ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-slate-400 italic">Belum ada riwayat aktivitas yang tercatat untuk tenant ini.</p>
            @endif
        </div>
    </div>

    <!-- TAB 2: KELAS & SISWA -->
    <div x-show="activeTab === 'classes'" class="space-y-6">
        <!-- Sub-panel A: Ringkasan Rombongan Belajar / Kelas -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i>
                        <span>Daftar Kelas &amp; Rombongan Belajar</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Struktur kelas aktif yang terdaftar pada sekolah ini.</p>
                </div>
                <a href="{{ url('/classes') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    <span>Kelola Kelas Lengkap</span>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($classrooms as $cls)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 text-sm">{{ $cls->name }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                {{ $cls->department ?: 'ATPH' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Wali Kelas: <strong class="text-slate-700">{{ $cls->homeroomTeacher?->name ?? 'Belum ditentukan' }}</strong>
                        </p>
                        <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs text-slate-600">
                            <span>Siswa: <strong class="font-tabular text-slate-900">{{ $cls->profiles_count }}</strong></span>
                            <span>Kelompok: <strong class="font-tabular text-slate-900">{{ $cls->groups->count() }}</strong></span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 py-6 text-center text-xs text-slate-400 italic">
                        Belum ada kelas yang terdaftar pada sekolah ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Sub-panel B: Daftar Siswa Terdaftar -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="graduation-cap" class="w-4 h-4 text-emerald-600"></i>
                        <span>Daftar Siswa Praktikan Terdaftar</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Siswa yang memiliki hak akses praktikum pada workstation sekolah ini.</p>
                </div>
                <div class="text-xs text-slate-500">
                    Total: <strong class="font-tabular text-slate-900">{{ $studentsCount }}</strong> siswa
                </div>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Nama Siswa</th>
                            <th class="py-2.5 px-3">NISN / Identitas</th>
                            <th class="py-2.5 px-3">Kelas</th>
                            <th class="py-2.5 px-3">Kelompok Praktik</th>
                            <th class="py-2.5 px-3">Email Pengguna</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($students as $stu)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $stu->name }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $stu->profile?->identifier_number ?: '—' }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $stu->profile?->classroom?->name ?? ($stu->profile?->class_grade ?: '—') }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $stu->profile?->studentGroup?->name ?? ($stu->profile?->group_name ?: '—') }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-500">{{ $stu->email }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">Belum ada data siswa terdaftar di sekolah ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: GURU & ADMIN SEKOLAH -->
    <div x-show="activeTab === 'teachers'" class="space-y-6">
        <!-- Sub-panel A: Akun Perwakilan Admin Sekolah -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i>
                        <span>Akun Admin Sekolah (Otoritas Institusi)</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Penanggung jawab teknis sekolah untuk mengelola data guru, siswa, dan rombel.</p>
                </div>
                <a href="{{ url('/users-data') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0c3c26] text-white text-xs font-semibold hover:bg-[#0a3120] transition-colors shadow-2xs">
                    <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                    <span>Kelola Pengguna di Direktori</span>
                </a>
            </div>

            @if($schoolAdmins->isNotEmpty())
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Nama Admin</th>
                                <th class="py-2.5 px-3">Email Akun</th>
                                <th class="py-2.5 px-3">Role</th>
                                <th class="py-2.5 px-3">Tanggal Dibuat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($schoolAdmins as $adminUser)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $adminUser->name }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-600">{{ $adminUser->email }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            Admin Sekolah
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-tabular text-slate-500">{{ $adminUser->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5 rounded-xl border border-amber-200 bg-amber-50/60 flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                    <div class="space-y-1 text-xs">
                        <p class="font-bold text-amber-900">Belum Ada Akun Admin Sekolah Terdaftar</p>
                        <p class="text-amber-800">
                            Sekolah ini belum memiliki akun pengelola institusi (<code class="font-mono">admin_sekolah</code>). Superuser dapat membuat akun Admin Sekolah melalui menu Direktori Pengguna dengan mengaitkannya ke sekolah ini.
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sub-panel B: Guru Pembimbing Praktikum -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="award" class="w-4 h-4 text-emerald-600"></i>
                        <span>Daftar Guru Pembimbing Praktikum</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tenaga pendidik ATPH yang mengampu jadwal lab dan menilai LKS.</p>
                </div>
                <span class="text-xs text-slate-500">Total: <strong class="font-tabular text-slate-900">{{ $teachersCount }}</strong> guru</span>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Nama Guru</th>
                            <th class="py-2.5 px-3">NIP / Identitas</th>
                            <th class="py-2.5 px-3">Email</th>
                            <th class="py-2.5 px-3">Spesialisasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($teachers as $teacher)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $teacher->name }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $teacher->profile?->identifier_number ?: '—' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-500">{{ $teacher->email }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $teacher->profile?->specialization ?: 'Agronomi & IoT Pertanian' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400 italic">Belum ada guru terdaftar di sekolah ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: TRAINER KIT MEJA -->
    <div x-show="activeTab === 'devices'" class="space-y-6">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="cpu" class="w-4 h-4 text-emerald-600"></i>
                        <span>Armada Workstation Trainer Kit di Laboratorium</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Perangkat keras 7-in-1 Modbus RS485 yang terpasang pada meja praktikum sekolah ini.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/trainer-kits') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                        <i data-lucide="boxes" class="w-3.5 h-3.5"></i>
                        <span>Manajemen Fleet Global</span>
                    </a>
                </div>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Kode Kit</th>
                            <th class="py-2.5 px-3">Lokasi Meja</th>
                            <th class="py-2.5 px-3">Status Koneksi</th>
                            <th class="py-2.5 px-3">IP / Komunikasi</th>
                            <th class="py-2.5 px-3">Firmware</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($devices as $kit)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-900">{{ $kit->code }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $kit->bench_location ?: $kit->name }}</td>
                                <td class="py-2.5 px-3">
                                    @if($kit->is_online)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Online
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600">
                                    {{ $kit->ip_address ?: '192.168.10.x' }} ({{ $kit->connection_type }})
                                </td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $kit->firmware_version ?: 'v2.4.1' }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <a
                                        href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-[11px] transition-colors"
                                    >
                                        <i data-lucide="gauge" class="w-3 h-3"></i>
                                        <span>Monitoring</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 italic">Belum ada Trainer Kit yang dialokasikan ke sekolah ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 5: JADWAL PRAKTIKUM -->
    <div x-show="activeTab === 'schedules'" class="space-y-6">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                        <span>Agenda Jadwal Praktikum Laboratorium</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Jadwal penggunaan laboratorium oleh kelas-kelas di sekolah ini.</p>
                </div>
                <a href="{{ url('/schedules') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    <span>Kelola Jadwal Lengkap</span>
                </a>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Agenda / Praktikum</th>
                            <th class="py-2.5 px-3">Tanggal &amp; Waktu</th>
                            <th class="py-2.5 px-3">Kelas</th>
                            <th class="py-2.5 px-3">Meja Kit</th>
                            <th class="py-2.5 px-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($schedules as $sch)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $sch->title }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">
                                    {{ $sch->activity_date?->format('d/m/Y') }} ({{ substr($sch->start_time ?? '', 0, 5) }}–{{ substr($sch->end_time ?? '', 0, 5) }} WIB)
                                </td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $sch->classroom?->name ?? '—' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-700">{{ $sch->device?->code ?? '—' }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                        {{ ucfirst($sch->status ?? 'terjadwal') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">Belum ada agenda jadwal praktikum untuk sekolah ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 6: LAPORAN LKS & RAPOR -->
    <div x-show="activeTab === 'reports'" class="space-y-6">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
                        <span>Rekap Lembar Kerja Siswa (LKS) &amp; Nilai Rapor</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Hasil pengerjaan praktikum siswa dan evaluasi rubrik 4-skala.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/reports') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>Buka Hub LKS</span>
                    </a>
                    <a href="{{ url('/assessment') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 transition-colors">
                        <i data-lucide="award" class="w-3.5 h-3.5"></i>
                        <span>Rapor Kompetensi</span>
                    </a>
                </div>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Siswa</th>
                            <th class="py-2.5 px-3">Modul Pembelajaran</th>
                            <th class="py-2.5 px-3">Meja Kit</th>
                            <th class="py-2.5 px-3">Nilai Akhir</th>
                            <th class="py-2.5 px-3">Status Review</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($reports as $rep)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-semibold text-slate-900">{{ $rep->user?->name ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $rep->module?->title ?? 'Modul Praktikum' }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-600">{{ $rep->device?->code ?? '—' }}</td>
                                <td class="py-2.5 px-3 font-bold font-tabular text-slate-900">
                                    {{ $rep->final_grade !== null ? number_format($rep->final_grade, 1) : '—' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    @php
                                        $repBadge = match($rep->status) {
                                            'graded', 'approved' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'revision_requested' => 'bg-rose-50 text-rose-800 border-rose-200',
                                            default => 'bg-amber-50 text-amber-800 border-amber-200',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $repBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $rep->status ?? 'submitted')) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">Belum ada laporan LKS terkumpul dari siswa di sekolah ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
