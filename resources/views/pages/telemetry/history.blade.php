@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Unboxed Header & Action Bar matching approved Monitoring IoT & Analytics -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Riwayat Telemetri
            </h1>
            <p class="text-xs text-slate-500">
                Arsip pengukuran sensor dan penelusuran deviasi stasiun meja praktik
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ route('telemetry.history') }}" class="flex items-center gap-2">
                    <label for="school_id" class="text-xs font-semibold text-slate-600">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                    >
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            <a
                href="{{ route('telemetry.history.export', request()->query()) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
                title="Unduh dataset telemetri terfilter ke format CSV"
            >
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Ekspor CSV</span>
            </a>
        </div>
    </div>

    <!-- Contextual Summary Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Total Rekaman Terfilter</div>
            <div class="text-xl font-bold text-slate-900 font-tabular mt-0.5">
                {{ number_format($totalLogsCount, 0, ',', '.') }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Baris data sesuai filter</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Stasiun Terdaftar</div>
            <div class="text-xl font-bold text-slate-900 font-tabular mt-0.5">
                {{ $totalDevicesCount }} <span class="text-xs font-normal text-slate-500">Kit</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Dalam lingkup otoritas</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Stasiun Online Saat Ini</div>
            <div class="text-xl font-bold font-tabular mt-0.5 {{ $onlineDevicesCount > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                {{ $onlineDevicesCount }} <span class="text-xs font-normal text-slate-500">Aktif</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Heartbeat ≤ 15 menit</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Periode Waktu</div>
            <div class="text-xs font-bold text-slate-800 mt-1 truncate" title="{{ $periodLabel }}">
                {{ $periodLabel }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Zona Waktu WIB (UTC+7)</div>
        </div>
    </div>

    <!-- Active Actionable Alert Hub (Only shown if fresh actionable alert exists) -->
    @if(!empty($isFreshAlert) && $activeAlerts->isNotEmpty())
        <div class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 shadow-2xs space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-200/60 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-600 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-amber-950">
                            Peringatan Deviasi Aktif · {{ $alertDevice?->code }} ({{ $alertDevice?->name }})
                        </h2>
                        <p class="text-xs text-amber-800">
                            Terdeteksi {{ $activeAlerts->count() }} parameter tanah di luar batas acuan ideal pada stasiun {{ $alertDevice?->code }} (pembacaan sensor terkini).
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-200/80 text-amber-900 border border-amber-300/80 shrink-0">
                    <span class="w-2 h-2 rounded-full bg-amber-600 animate-pulse"></span>
                    Peringatan Waktu-Nyata (WIB)
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($activeAlerts as $alert)
                    <div class="p-3.5 rounded-xl bg-white border {{ $alert['severity'] === 'danger' ? 'border-rose-200' : 'border-amber-200' }} shadow-2xs space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider {{ $alert['severity'] === 'danger' ? 'text-rose-600' : 'text-amber-700' }}">
                                    {{ $alert['parameter'] }}
                                </span>
                                <h3 class="text-xs font-bold text-slate-900 mt-0.5">{{ $alert['title'] }}</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase {{ $alert['severity'] === 'danger' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                {{ $alert['severity'] === 'danger' ? 'Kritis' : 'Peringatan' }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            <strong class="text-slate-700 font-medium">Implikasi:</strong> {{ $alert['impact'] }}
                        </p>

                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs text-slate-700 leading-relaxed space-y-0.5">
                            <span class="font-semibold text-emerald-800 flex items-center gap-1">
                                <i data-lucide="clipboard-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                <span>Panduan Pemeriksaan:</span>
                            </span>
                            <p class="text-[11px] text-slate-600">{{ $alert['action'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Validation Error Alert Banner (if inputs are invalid) -->
    @if(!empty($validationError))
        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3 text-xs text-amber-900">
            <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
            <div>
                <strong class="font-semibold">Kriteria filter tidak valid:</strong> {{ $validationError }}
                <p class="text-[11px] text-amber-700 mt-0.5">Silakan periksa kembali rentang tanggal atau parameter yang dimasukkan.</p>
            </div>
        </div>
    @endif

    <!-- Filter Toolbar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs">
        <form method="GET" action="{{ route('telemetry.history') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <!-- Stasiun Device Selector -->
            <div>
                <label for="filter_device_id" class="block text-xs font-semibold text-slate-700 mb-1">
                    Stasiun Trainer Kit
                </label>
                <select
                    id="filter_device_id"
                    name="device_id"
                    class="w-full text-xs p-2 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-colors cursor-pointer"
                >
                    <option value="">Semua Trainer Kit ({{ $devices->count() }})</option>
                    @foreach($devices as $dev)
                        <option value="{{ $dev->id }}" {{ (string) $selectedDevice === (string) $dev->id || (string) $selectedDevice === $dev->code ? 'selected' : '' }}>
                            {{ $dev->code }} — {{ $dev->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label for="filter_start_date" class="block text-xs font-semibold text-slate-700 mb-1">
                    Dari Tanggal (WIB)
                </label>
                <input
                    type="date"
                    id="filter_start_date"
                    name="start_date"
                    value="{{ $startDate }}"
                    class="w-full text-xs p-2 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-colors"
                />
            </div>

            <!-- End Date -->
            <div>
                <label for="filter_end_date" class="block text-xs font-semibold text-slate-700 mb-1">
                    Sampai Tanggal (WIB)
                </label>
                <input
                    type="date"
                    id="filter_end_date"
                    name="end_date"
                    value="{{ $endDate }}"
                    class="w-full text-xs p-2 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-colors"
                />
            </div>

            <!-- Condition Status Filter -->
            <div>
                <label for="filter_status" class="block text-xs font-semibold text-slate-700 mb-1">
                    Status Kondisi
                </label>
                <select
                    id="filter_status"
                    name="status"
                    class="w-full text-xs p-2 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-colors cursor-pointer"
                >
                    <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="anomaly" {{ $selectedStatus === 'anomaly' ? 'selected' : '' }}>Hanya Anomali / Deviasi</option>
                    <option value="normal" {{ $selectedStatus === 'normal' ? 'selected' : '' }}>Hanya Normal (Ideal)</option>
                </select>
            </div>

            <!-- Data Provenance Filter -->
            <div>
                <label for="filter_provenance" class="block text-xs font-semibold text-slate-700 mb-1">
                    Asal Data
                </label>
                <select
                    id="filter_provenance"
                    name="provenance"
                    class="w-full text-xs p-2 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-colors cursor-pointer"
                >
                    <option value="all" {{ $selectedProvenance === 'all' ? 'selected' : '' }}>Semua Asal Data</option>
                    <option value="hardware" {{ $selectedProvenance === 'hardware' ? 'selected' : '' }}>Hardware (Perangkat Fisik)</option>
                    <option value="simulation" {{ $selectedProvenance === 'simulation' ? 'selected' : '' }}>Simulasi (Engine IoT)</option>
                    <option value="fixture" {{ $selectedProvenance === 'fixture' ? 'selected' : '' }}>Fixture (Data Pengujian)</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors shadow-2xs flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Terapkan</span>
                </button>
                <a
                    href="{{ route('telemetry.history') }}"
                    class="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition-colors cursor-pointer text-center"
                    title="Reset Filter"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900">
                    Tabel Rekaman Telemetri Sensor 7-in-1
                </h3>
                <p class="text-xs text-slate-500">
                    Menampilkan {{ $readings->firstItem() ?? 0 }} – {{ $readings->lastItem() ?? 0 }} dari {{ number_format($readings->total(), 0, ',', '.') }} baris data
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/80 text-slate-600 font-semibold text-xs">
                        <th class="py-2.5 px-3 whitespace-nowrap">Waktu (WIB)</th>
                        <th class="py-2.5 px-3 whitespace-nowrap">Trainer Kit</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">pH</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">EC (<span class="normal-case">µS/cm</span>)</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Lengas (%)</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">Suhu (°C)</th>
                        <th class="py-2.5 px-3 text-right whitespace-nowrap">N-P-K (<span class="normal-case">mg/kg</span>)</th>
                        <th class="py-2.5 px-3 text-center whitespace-nowrap">Asal Data</th>
                        <th class="py-2.5 px-3 text-center whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($readings as $row)
                        @php
                            $classification = $alertEvaluator->classify($row, $selectedSchoolId);
                            $isAnomaly = $classification['code'] === 'anomaly';
                            $ph = $row->ph !== null ? (float) $row->ph : null;
                            $ec = $row->ec !== null ? (float) $row->ec : null;
                            $hum = $row->humidity !== null ? (float) $row->humidity : null;
                            $temp = $row->temperature !== null ? (float) $row->temperature : null;
                            $n = $row->nitrogen !== null ? (float) $row->nitrogen : null;
                            $p = $row->phosphorus !== null ? (float) $row->phosphorus : null;
                            $k = $row->potassium !== null ? (float) $row->potassium : null;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $isAnomaly ? 'bg-amber-50/25' : '' }}">
                            <!-- Waktu (WIB) -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="font-tabular font-semibold text-slate-800">
                                    {{ $row->recorded_at ? $row->recorded_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '—' }}
                                </span>
                            </td>

                            <!-- Perangkat -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="font-bold text-slate-900">{{ $row->device->name ?? 'Meja Lab' }}</div>
                                <div class="text-[11px] text-slate-500 font-normal">{{ $row->device->code ?? 'DEV-'.$row->device_id }}</div>
                            </td>

                            <!-- pH -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                @if($ph !== null)
                                    <span class="font-tabular font-bold {{ $ph < 6.0 || $ph > 7.2 ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/60' : 'text-slate-800' }}">
                                        {{ number_format($ph, 1, ',', '.') }}
                                    </span>
                                @else
                                    <span class="font-tabular text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- EC -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                @if($ec !== null)
                                    <span class="font-tabular font-bold {{ $ec < 1000 || $ec > 2200 ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/60' : 'text-slate-800' }}">
                                        {{ number_format($ec, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="font-tabular text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Kelembaban / Lengas -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                @if($hum !== null)
                                    <span class="font-tabular font-bold {{ $hum < 45 || $hum > 65 ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/60' : 'text-slate-800' }}">
                                        {{ number_format($hum, 1, ',', '.') }}%
                                    </span>
                                @else
                                    <span class="font-tabular text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Suhu -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                @if($temp !== null)
                                    <span class="font-tabular font-bold {{ $temp < 22 || $temp > 29 ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/60' : 'text-slate-800' }}">
                                        {{ number_format($temp, 1, ',', '.') }}°C
                                    </span>
                                @else
                                    <span class="font-tabular text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- NPK -->
                            <td class="py-3 px-3 text-right font-tabular text-xs text-slate-700 whitespace-nowrap">
                                <span class="text-slate-900 font-semibold">{{ $n !== null ? number_format($n, 0, ',', '.') : '—' }}</span>
                                <span class="text-slate-300">/</span>
                                <span class="text-slate-900 font-semibold">{{ $p !== null ? number_format($p, 0, ',', '.') : '—' }}</span>
                                <span class="text-slate-300">/</span>
                                <span class="text-slate-900 font-semibold">{{ $k !== null ? number_format($k, 0, ',', '.') : '—' }}</span>
                            </td>

                            <!-- Provenance Badge -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if($row->provenance === 'hardware')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Hardware
                                    </span>
                                @elseif($row->provenance === 'simulation')
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200">
                                        Simulasi
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        Fixture
                                    </span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-semibold border {{ $classification['badge_class'] }}" title="{{ implode('; ', $classification['deviations']) }}">
                                    {{ $classification['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <i data-lucide="database" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                @if($schoolTotalReadings === 0)
                                    <p class="text-xs font-semibold text-slate-700">Belum ada rekaman data telemetri.</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Data telemetri akan tercatat secara otomatis saat stasiun Trainer Kit mengirimkan pembacaan sensor.</p>
                                @else
                                    <p class="text-xs font-semibold text-slate-700">Tidak ada log telemetri yang sesuai kriteria filter.</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Coba ubah rentang tanggal, stasiun, atau filter status kondisi untuk menemukan rekaman data.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($readings->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $readings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
