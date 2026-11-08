@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Lab Overview Header (heading / context / action) -->
    <header class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    SMK PERTANIAN 4.0
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Lab Presisi Agritech & Hidroponik</span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-mono">{{ now()->translatedFormat('l, d F Y') }}</span>
                @if($averageAttendance !== null && $user && ($user->isGuru() || $user->isSuperuser()))
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs font-semibold text-slate-600 flex items-center gap-1 font-mono">
                        <i data-lucide="users" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span>Kehadiran: {{ number_format($averageAttendance, 1) }}%</span>
                    </span>
                @endif
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Pusat Kendali Laboratorium Vokasi IoT
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Pantau kesiapan stasiun Trainer Kit praktikum, jadwal kelas aktif, pengumpulan lembar kerja siswa (LKS), serta anomali tanah secara terpadu.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            @if($isSuperuser)
                <label class="text-xs font-semibold text-slate-600" for="dashboard-school-filter">Sekolah</label>
                <select id="dashboard-school-filter" name="school_id" form="dashboard-school-form" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-md text-xs bg-white border border-slate-300 text-slate-700">
                    <option value="">Semua Sekolah</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchoolId == $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
                <form id="dashboard-school-form" method="GET" action="{{ url('/') }}" class="hidden"></form>
            @endif
            <a
                href="{{ url('/iot-monitoring') }}{{ $isSuperuser && $selectedSchoolId ? '?school_id=' . $selectedSchoolId : '' }}"
                class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-2 shadow-sm transition-colors"
            >
                <i data-lucide="gauge" class="w-4 h-4"></i>
                <span>Buka Workstation IoT</span>
            </a>
            <a
                href="{{ url('/schedules') }}"
                class="px-4 py-2 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold flex items-center gap-2 transition-colors"
            >
                <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                <span>Jadwal Lab</span>
            </a>
        </div>
    </header>

    <!-- Compact Operational Summary (no repetitive KPI cards) -->
    <section class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm" aria-labelledby="summary-heading">
        <h2 id="summary-heading" class="sr-only">Ringkasan Operasional</h2>
        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div class="lg:col-span-2 space-y-1">
                <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Armada Trainer Kit</dt>
                <dd class="flex items-baseline gap-2">
                    <span class="font-tabular text-2xl font-extrabold text-slate-900">{{ $onlineKitsCount }}</span>
                    <span class="text-xs font-semibold text-slate-500">/ {{ $totalKitsCount }} Meja Online</span>
                </dd>
                <dd class="text-xs font-medium {{ $totalKitsCount === 0 ? 'text-slate-500' : ($onlineKitsCount === $totalKitsCount ? 'text-emerald-700' : 'text-amber-600') }}">
                    {{ $totalKitsCount === 0 ? 'Belum Ada Meja Terdaftar' : ($onlineKitsCount === $totalKitsCount ? 'Semua Siap Pakai' : ($totalKitsCount - $onlineKitsCount) . ' Perlu Periksa') }}
                </dd>
            </div>

            <div class="space-y-1">
                <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Jadwal Terdekat</dt>
                <dd class="font-semibold text-sm text-slate-900 line-clamp-1">
                    {{ $todaySchedule?->title ?? 'Tidak ada jadwal berikutnya' }}
                </dd>
                <dd class="text-xs text-slate-500 font-mono">
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

            <div class="space-y-1">
                <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                    @if($user?->isSiswa())
                        LKS Praktikum Saya
                    @else
                        LKS Menunggu Review
                    @endif
                </dt>
                <dd class="flex items-baseline gap-2">
                    <span class="font-tabular text-2xl font-extrabold {{ $user?->isSiswa() ? 'text-indigo-700' : 'text-amber-600' }}">{{ $user?->isSiswa() ? $studentOwnReportsCount : $pendingReportsCount }}</span>
                    <span class="text-xs font-semibold text-slate-500">
                        {{ $user?->isSiswa() ? 'Laporan Saya' : 'Laporan Praktikan' }}
                    </span>
                </dd>
                @if($user?->isSiswa() && $studentReportsNeedingRevisionCount)
                    <dd class="text-xs text-rose-600">{{ $studentReportsNeedingRevisionCount }} perlu revisi</dd>
                @endif
            </div>

            <div class="space-y-1">
                <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Peringatan Anomali</dt>
                <dd class="flex items-baseline gap-2">
                    <span class="font-tabular text-2xl font-extrabold {{ count($anomalies) > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ $affectedStationsCount }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500">Meja Terdampak</span>
                </dd>
                <dd class="text-xs text-slate-500"><span class="font-semibold text-slate-700">{{ count($anomalies) }}</span> parameter melewati ambang pada data terbaru</dd>
            </div>
        </dl>
    </section>

    <!-- Upcoming Practicum Schedule -->
    <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="schedule-heading">
        <div class="flex items-center justify-between">
            <div>
                <h3 id="schedule-heading" class="text-sm font-bold text-slate-900">Agenda Praktikum Terdekat</h3>
                <p class="text-xs text-slate-500">Jadwal penggunaan meja laboratorium SMK</p>
            </div>
            <a href="{{ url('/schedules') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                <span>Kalender Lengkap</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="space-y-3">
            @forelse($upcomingSchedules as $sched)
                <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50 flex items-start justify-between gap-4">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 font-mono shrink-0">
                                {{ $sched->class_grade ?? 'XI ATPH' }}
                            </span>
                            <span class="text-xs font-bold text-slate-900 truncate">{{ $sched->title }}</span>
                        </div>
                        <p class="text-xs text-slate-600 truncate">{{ $sched->description ?? 'Praktikum sensor tanah presisi' }}</p>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500 font-mono">
                            <span><i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>{{ $sched->activity_date ? $sched->activity_date->format('d/m/Y') : '-' }}</span>
                            <span><i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>{{ substr($sched->start_time ?? '', 0, 5) }} - {{ substr($sched->end_time ?? '', 0, 5) }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 px-2.5 py-1 rounded-md text-xs font-semibold {{ $sched->status === 'in_progress' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                        {{ $sched->status === 'in_progress' ? 'Berjalan' : 'Terjadwal' }}
                    </span>
                </div>
            @empty
                <div class="py-6 text-center text-xs text-slate-400">
                    Belum ada jadwal praktikum yang terdaftar.
                </div>
            @endforelse
        </div>
    </section>

    <!-- Trainer Kit Fleet Availability (table for clarity over repeated cards) -->
    <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="fleet-heading">
        <div class="flex items-center justify-between">
            <div>
                <h3 id="fleet-heading" class="text-sm font-bold text-slate-900">Status Meja Praktikum IoT (Hardware Fleet)</h3>
                <p class="text-xs text-slate-500">Kondisi telemetri terkini pada masing-masing kit meja siswa</p>
            </div>
            <a href="{{ url('/trainer-kits') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                <span>Kelola Armada Kit</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" role="table">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="pb-2">Meja</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2 text-center">pH</th>
                        <th class="pb-2 text-center">Lengas</th>
                        <th class="pb-2 text-center">EC</th>
                        <th class="pb-2">Terakhir</th>
                        <th class="pb-2">Sumber</th>
                        <th class="pb-2 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($devices as $kit)
                        @php
                            $latest = $kit->latestReading;
                            $isOnline = $kit->status === 'online';
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
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-600 font-sans border border-slate-200">
                                                {{ $kit->school->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-1">{{ $kit->bench_location ?? $kit->name }}</p>
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
                            <td class="py-3 text-[11px] text-slate-400 font-mono">
                                {{ $latest?->recorded_at ? $latest->recorded_at->diffForHumans() : 'Belum Ada Transmisi Data' }}
                            </td>
                            <td class="py-3">
                                @if($latest?->provenance)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold font-mono uppercase {{ $latest->provenance === 'hardware' ? 'bg-emerald-50 text-emerald-700' : ($latest->provenance === 'simulation' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $latest->provenance === 'hardware' ? 'Hardware' : ($latest->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <a
                                    href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                                    class="px-2.5 py-1 rounded bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold flex items-center gap-1 transition-colors justify-end"
                                >
                                    <span>Stasiun Meja</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                <p class="text-xs font-semibold text-slate-600">Belum Ada Meja Praktikum IoT Terdaftar</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Stasiun meja praktikum SMK akan tampil di sini setelah didaftarkan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Role-Relevant Academic Tasks + Anomalies (two columns on desktop) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Academic Tasks -->
        <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="tasks-heading">
            <div class="flex items-center justify-between">
                <div>
                    <h3 id="tasks-heading" class="text-sm font-bold text-slate-900">
                        @if($user?->isSiswa())
                            Tugas LKS Saya
                        @else
                            Laporan Menunggu Review
                        @endif
                    </h3>
                    <p class="text-xs text-slate-500">
                        @if($user?->isSiswa())
                            Lembar kerja praktikum yang perlu dikumpulkan atau direvisi
                        @else
                            Laporan praktikan yang menunggu penilaian
                        @endif
                    </p>
                </div>
                <a href="{{ url('/reports') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                    <span>Lihat Semua</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            @if($user?->isSiswa())
                @if($studentOwnReportsCount > 0)
                    <ul class="space-y-2">
                        <!-- Would populate from actual report data -->
                        <li class="p-3 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
                            <div class="space-y-0.5 min-w-0">
                                <p class="text-xs font-bold text-slate-900 truncate">Modul {{ $studentOwnReportsCount }} Laporan</p>
                                <p class="text-[11px] text-slate-500">{{ $studentReportsNeedingRevisionCount }} perlu revisi, sisanya dikumpulkan</p>
                            </div>
                            <a href="{{ url('/reports') }}" class="shrink-0 px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Buka</a>
                        </li>
                    </ul>
                @else
                    <div class="py-6 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center">
                            <i data-lucide="file-check" class="w-5 h-5"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-800">Belum Ada LKS Praktikum</p>
                        <p class="text-[11px] text-slate-500">Mulai praktikum untuk menghasilkan laporan.</p>
                    </div>
                @endif
            @else
                @if($pendingReportsCount > 0)
                    <ul class="space-y-2">
                        <li class="p-3 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
                            <div class="space-y-0.5 min-w-0">
                                <p class="text-xs font-bold text-slate-900 truncate">{{ $pendingReportsCount }} Laporan Menunggu</p>
                                <p class="text-[11px] text-slate-500">Perlu dinilai atau diminta revisi</p>
                            </div>
                            <a href="{{ url('/reports') }}" class="shrink-0 px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Review</a>
                        </li>
                    </ul>
                @else
                    <div class="py-6 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center">
                            <i data-lucide="check" class="w-5 h-5"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-800">Semua Laporan Terkini Sudah Dinilai</p>
                        <p class="text-[11px] text-slate-500">Tidak ada LKS yang menunggu tindakan.</p>
                    </div>
                @endif
            @endif
        </section>

        <!-- Anomalies with truthful empty state -->
        <section class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4" aria-labelledby="anomalies-heading">
            <div class="flex items-center justify-between">
                <div>
                    <h3 id="anomalies-heading" class="text-sm font-bold text-slate-900">Anomali Lahan & Tindakan Lapangan</h3>
                    <p class="text-xs text-slate-500">Deteksi otomatis batas ambang toleransi agronomi</p>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ count($anomalies) > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                    {{ count($anomalies) > 0 ? 'Perlu Intervensi' : 'Pantauan Normal' }}
                </span>
            </div>

            <div class="space-y-3">
                @forelse($anomalies as $ano)
                    <div class="p-3.5 rounded-lg border {{ $ano['severity'] === 'critical' ? 'border-rose-200 bg-rose-50/60' : 'border-amber-200 bg-amber-50/60' }} space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-slate-900">{{ $ano['device'] }} — {{ $ano['bench'] }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $ano['severity'] === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $ano['parameter'] }}: {{ $ano['value'] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-700 font-medium">{{ $ano['message'] }}</p>
                        <div class="text-xs text-slate-600 flex items-start gap-1 pt-1 border-t border-slate-200/60">
                            <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5"></i>
                            <span><strong>Rekomendasi:</strong> {{ $ano['action'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center">
                            <i data-lucide="check" class="w-5 h-5"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-800">Semua Parameter Dalam Batas Ideal</p>
                        <p class="text-[11px] text-slate-500">Tidak ada defisiensi unsur hara makro atau anomali lengas tanah pada data terbaru.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection