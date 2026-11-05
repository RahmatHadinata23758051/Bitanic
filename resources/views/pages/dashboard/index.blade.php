@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Lab Overview Header -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    SMK PERTANIAN 4.0
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Lab Presisi Agritech & Hidroponik</span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-mono">{{ now()->translatedFormat('l, d F Y') }}</span>
                @if($averageAttendance !== null)
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
    </div>

    <!-- 4 KPI Bento Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Kesiapan Trainer Kit -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Armada Trainer Kit</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-700">
                    <i data-lucide="cpu" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="my-3">
                <div class="flex items-baseline gap-2">
                    <span class="font-tabular text-3xl font-extrabold text-slate-900">{{ $onlineKitsCount }}</span>
                    <span class="text-xs font-semibold text-slate-500">/ {{ $totalKitsCount }} Meja Online</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Status Gateway</span>
                <span class="font-semibold {{ $totalKitsCount === 0 ? 'text-slate-500' : ($onlineKitsCount === $totalKitsCount ? 'text-emerald-700' : 'text-amber-600') }}">
                    {{ $totalKitsCount === 0 ? 'Belum Ada Meja Terdaftar' : ($onlineKitsCount === $totalKitsCount ? 'Semua Siap Pakai' : ($totalKitsCount - $onlineKitsCount) . ' Perlu Periksa') }}
                </span>
            </div>
        </div>

        <!-- Card 2: Sesi Praktikum -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sesi Praktikum</span>
                <span class="p-2 rounded-lg bg-indigo-50 text-indigo-700">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="my-3">
                @if($todaySchedule)
                    <p class="font-bold text-sm text-slate-900 line-clamp-1">{{ $todaySchedule->title }}</p>
                    @if(! $isScheduleToday)
                        <p class="text-xs font-semibold text-amber-700 mt-0.5">Jadwal berikutnya · {{ $todaySchedule->activity_date?->format('d/m/Y') }}</p>
                    @endif
                    <p class="text-xs text-slate-500 font-mono mt-0.5">
                        {{ substr($todaySchedule->start_time ?? '', 0, 5) }} – {{ substr($todaySchedule->end_time ?? '', 0, 5) }} WIB
                    </p>
                    @if($todaySchedule->lead_teacher)
                        <p class="text-xs text-slate-500 mt-1">Guru pengampu: {{ $todaySchedule->lead_teacher->name }}</p>
                    @endif
                @else
                    <p class="font-bold text-sm text-slate-500">Tidak ada sesi aktif</p>
                    <p class="text-xs text-slate-400 mt-0.5">Laboratorium bebas tugas</p>
                @endif
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Kelas</span>
                <span class="font-semibold text-indigo-700 font-mono">
                    {{ $todaySchedule?->classroom?->name ?? $todaySchedule?->class_grade ?? '—' }}
                </span>
            </div>
        </div>

        <!-- Card 3: LKS Status by Role -->
        @if($user?->isSiswa())
            <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">LKS Praktikum Saya</span>
                    <span class="p-2 rounded-lg bg-indigo-50 text-indigo-700">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="my-3">
                    <div class="flex items-baseline gap-2">
                        <span class="font-tabular text-3xl font-extrabold text-indigo-700">{{ $studentOwnReportsCount }}</span>
                        <span class="text-xs font-semibold text-slate-500">Laporan Saya</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ $studentReportsNeedingRevisionCount }} perlu revisi</p>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="{{ url('/reports') }}" class="text-emerald-700 hover:text-emerald-800 font-semibold flex items-center gap-1">
                        <span>Lihat LKS Saya</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        @else
            <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">LKS Menunggu Review</span>
                    <span class="p-2 rounded-lg bg-amber-50 text-amber-700">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="my-3">
                    <div class="flex items-baseline gap-2">
                        <span class="font-tabular text-3xl font-extrabold text-amber-600">{{ $pendingReportsCount }}</span>
                        <span class="text-xs font-semibold text-slate-500">Laporan Praktikan</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                    <a href="{{ url('/reports') }}" class="text-emerald-700 hover:text-emerald-800 font-semibold flex items-center gap-1">
                        <span>Periksa Laporan</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        @endif

        <!-- Card 4: Anomali Sensor Tanah -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Peringatan Anomali</span>
                <span class="p-2 rounded-lg {{ count($anomalies) > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="my-3">
                <div class="flex items-baseline gap-2">
                    <span class="font-tabular text-3xl font-extrabold {{ count($anomalies) > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ $affectedStationsCount }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500">Meja Terdampak</span>
                </div>
                <p class="text-xs text-slate-500 mt-1"><span class="font-semibold text-slate-700">{{ count($anomalies) }}</span> parameter melewati ambang pada data terbaru</p>
            </div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Tindakan Lapangan</span>
                <span class="font-semibold {{ count($anomalies) > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                    {{ count($anomalies) > 0 ? 'Perlu Intervensi' : 'Kondisi Optimal' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Section: Meja Praktik Fleet Status (Trainer Kits) -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Status Meja Praktikum IoT (Hardware Fleet)</h2>
                <p class="text-xs text-slate-500">Kondisi telemetri terkini pada masing-masing kit meja siswa</p>
            </div>
            <a href="{{ url('/trainer-kits') }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                <span>Kelola Armada Kit</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($devices as $kit)
                @php
                    $latest = $kit->latestReading;
                    $isOnline = $kit->status === 'online';
                @endphp
                <div class="p-4 rounded-xl border border-slate-200 hover:border-emerald-300 transition-colors bg-slate-50/50 flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between">
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
                        <div class="text-right font-mono text-[11px] text-slate-500">
                            <div>IP: {{ $kit->ip_address ?? '—' }}</div>
                            <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-0.5">
                                <i data-lucide="battery" class="w-3 h-3 {{ $kit->battery_percent !== null ? 'text-slate-500' : 'text-slate-300' }}"></i>
                                <span>{{ $kit->battery_percent !== null ? $kit->battery_percent . '%' : '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Telemetry Snapshot Mini (No Fake Fallback Metrics) -->
                    <div class="grid grid-cols-3 gap-2 py-2 px-3 bg-white rounded-lg border border-slate-200 text-center font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase block">pH</span>
                            <span class="text-xs font-bold {{ $latest && (float)$latest->ph < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $latest && $latest->ph !== null ? number_format((float)$latest->ph, 2) : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase block">Lengas</span>
                            <span class="text-xs font-bold {{ $latest && (float)$latest->humidity < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $latest && $latest->humidity !== null ? number_format((float)$latest->humidity, 1) . '%' : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase block">EC</span>
                            <span class="text-xs font-bold text-slate-800">
                                {{ $latest && $latest->ec !== null ? number_format((float)$latest->ec, 0) : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-slate-400 font-mono">
                                {{ $latest?->recorded_at ? $latest->recorded_at->diffForHumans() : 'Belum Ada Transmisi Data' }}
                            </span>
                            @if($latest?->provenance)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold font-mono uppercase {{ $latest->provenance === 'hardware' ? 'bg-emerald-50 text-emerald-700' : ($latest->provenance === 'simulation' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $latest->provenance === 'hardware' ? 'Hardware' : ($latest->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                </span>
                            @endif
                        </div>
                        <a
                            href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                            class="px-2.5 py-1 rounded bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold flex items-center gap-1 transition-colors"
                        >
                            <span>Stasiun Meja</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <p class="text-xs font-semibold text-slate-600">Belum Ada Meja Praktikum IoT Terdaftar</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Stasiun meja praktikum SMK akan tampil di sini setelah didaftarkan.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Two Columns: Jadwal Praktikum & Active Anomalies -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Col 1: Agenda Praktikum Terdekat -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Agenda Praktikum Terdekat</h3>
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
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 font-mono">
                                    {{ $sched->class_grade ?? 'XI ATPH' }}
                                </span>
                                <span class="text-xs font-bold text-slate-900">{{ $sched->title }}</span>
                            </div>
                            <p class="text-xs text-slate-600">{{ $sched->description ?? 'Praktikum sensor tanah presisi' }}</p>
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
        </div>

        <!-- Col 2: Peringatan Anomali & Rekomendasi Cepat -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Anomali Lahan & Tindakan Lapangan</h3>
                    <p class="text-xs text-slate-500">Deteksi otomatis batas ambang toleransi agronomi</p>
                </div>
                <span class="text-xs font-mono font-semibold {{ count($anomalies) > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                    {{ count($anomalies) }} Isu Aktif
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
                        <p class="text-[11px] text-slate-500">Tidak ada defisiensi unsur hara makro atau anomali lengas tanah.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
