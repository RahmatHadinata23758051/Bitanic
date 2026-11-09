@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header / Konteks: Balanced Workstation Header -->
    <div class="space-y-3">
        <!-- Top Title -->
        <div class="space-y-0.5">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Monitoring IoT<span class="sr-only"> — Monitoring Telemetri Lahan & Trainer Kit Vokasi</span>
            </h1>
            <p class="text-xs text-slate-500 font-normal">
                Telemetri sensor tanah 7-in-1
            </p>
        </div>

        <!-- Workstation Selector & Connection Status Row (Natural context layout) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs">
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Stasiun Meja Praktik Selector -->
                <form method="GET" action="{{ url('/iot-monitoring') }}" class="inline-flex items-center gap-2">
                    <label for="device-selector" class="text-xs font-semibold text-slate-700 whitespace-nowrap">Stasiun:</label>
                    <select
                        id="device-selector"
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-800 border border-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-2xs min-w-[220px]"
                        @if($devices->isEmpty()) disabled aria-describedby="device-selector-empty" @endif
                    >
                        @forelse($devices as $dev)
                            <option value="{{ $dev->id }}" {{ ($activeDevice->id ?? null) == $dev->id ? 'selected' : '' }}>
                                {{ $dev->code }} — {{ $dev->bench_location ?? $dev->name }}
                            </option>
                        @empty
                            <option value="">Tidak ada Trainer Kit terdaftar</option>
                        @endforelse
                    </select>
                    @if($devices->isEmpty())
                        <span id="device-selector-empty" class="sr-only">Tidak ada Trainer Kit untuk dimonitor.</span>
                    @endif
                </form>

                <span class="text-slate-300 hidden sm:inline">|</span>

                <!-- Single Clear Device Status (No animated pulsing, fully distinguishable) -->
                <div class="inline-flex items-center gap-1.5 font-medium">
                    @if(! $activeDevice)
                        <span class="w-2 h-2 rounded-full bg-slate-300 shrink-0"></span>
                        <span class="text-slate-500">Tidak ada Trainer Kit terdaftar</span>
                        <span class="text-slate-300">·</span>
                        <span class="text-slate-500">Belum Ada Transmisi Data</span>
                    @elseif($activeDevice->is_online)
                        @if($telemetry['provenance'] === 'simulation' || $telemetry['provenance'] === 'fixture')
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-emerald-700">Online</span>
                            <span class="text-slate-300">·</span>
                            <span class="text-indigo-700">Data Simulasi / Fixture</span>
                            @if($telemetry['has_telemetry'])
                                <span class="text-slate-400 font-normal">({{ $telemetry['recorded_at'] }})</span>
                            @endif
                        @elseif($telemetry['is_live'])
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-emerald-700">Online</span>
                            <span class="text-slate-300">·</span>
                            <span class="text-emerald-700">Streaming Modbus Aktif</span>
                            <span class="text-slate-400 font-normal">({{ $telemetry['recorded_at'] }})</span>
                        @elseif($telemetry['has_telemetry'])
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-emerald-700">Online</span>
                            <span class="text-slate-300">·</span>
                            <span class="text-slate-600">Telemetri Terakhir (Stale)</span>
                            <span class="text-slate-400 font-normal">({{ $telemetry['recorded_at'] }})</span>
                        @else
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-emerald-700">Online</span>
                            <span class="text-slate-300">·</span>
                            <span class="text-slate-500">Belum Ada Pembacaan Sensor</span>
                        @endif
                    @else
                        <span class="w-2 h-2 rounded-full bg-slate-400 shrink-0"></span>
                        <span class="text-slate-600">Offline</span>
                        <span class="text-slate-300">·</span>
                        @if($telemetry['has_telemetry'])
                            <span class="text-amber-700">Telemetri Terakhir (Stale / Offline)</span>
                            <span class="text-slate-400 font-normal">({{ $telemetry['recorded_at'] }})</span>
                        @else
                            <span class="text-slate-500">Belum Ada Transmisi Data</span>
                        @endif
                    @endif
                </div>

                <!-- Info Perangkat Popover (Authentic Attributes, No Fabricated Defaults) -->
                <div x-data="{ open: false }" class="relative inline-block">
                    <button
                        type="button"
                        @click="open = !open"
                        class="text-slate-600 hover:text-slate-900 font-medium flex items-center gap-1 transition-colors"
                    >
                        <span>Info perangkat</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <div
                        x-show="open"
                        @click.outside="open = false"
                        x-cloak
                        class="absolute left-0 mt-2 w-72 p-3.5 bg-white border border-slate-200 rounded-xl shadow-lg z-30 font-mono text-[11px] space-y-1.5"
                    >
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="text-slate-400 font-sans">Protokol:</span>
                            <span class="text-slate-800 font-semibold">{{ $activeDevice->protocol ?? 'Belum dikonfigurasi' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="text-slate-400 font-sans">Baudrate:</span>
                            <span class="text-slate-800 font-semibold">{{ $activeDevice?->baudrate ? ($activeDevice->baudrate . ' bps') : 'Belum dikonfigurasi' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="text-slate-400 font-sans">Firmware:</span>
                            <span class="text-emerald-700 font-semibold">{{ $activeDevice->firmware_version ?? 'Belum dikonfigurasi' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-sans">IP Gateway:</span>
                            @if(Auth::check() && in_array(Auth::user()->role, ['superuser', 'admin', 'guru']))
                                <span class="text-slate-800 font-semibold">{{ $activeDevice->ip_address ?? 'Belum dikonfigurasi' }}</span>
                            @else
                                <span class="text-slate-400 italic font-sans text-[10px]">Akses terbatas</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($telemetry['provenance'])
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $telemetry['provenance'] === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($telemetry['provenance'] === 'simulation' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700') }}">
                        {{ $telemetry['provenance_label'] ?? $telemetry['provenance'] }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Section: Parameter Sensor (School-Scoped Thresholds) -->
    <div class="space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 tracking-tight">Parameter sensor</h2>
        </div>

        <!-- Group 1: Kondisi Media Tanam (4 Kolom) -->
        <div class="space-y-2">
            <h3 class="text-xs font-semibold text-slate-700">Kondisi media tanam</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- pH -->
                <x-sensor-card
                    parameter="{{ $thresholds['ph']['parameter'] }}"
                    :value="$telemetry['ph']"
                    unit="{{ $thresholds['ph']['unit'] }}"
                    :scaleMin="$thresholds['ph']['scale_min']"
                    :scaleMax="$thresholds['ph']['scale_max']"
                    :idealMin="$thresholds['ph']['ideal_min']"
                    :idealMax="$thresholds['ph']['ideal_max']"
                    :status="$evaluations['ph']['status']"
                    :statusLabel="$evaluations['ph']['label']"
                    icon="ph"
                    sublabel="{{ $thresholds['ph']['sublabel'] }}"
                    definition="{{ $thresholds['ph']['definition'] }}"
                    importance="{{ $thresholds['ph']['importance'] }}"
                    riskNote="{{ $thresholds['ph']['riskNote'] }}"
                />

                <!-- Lengas -->
                <x-sensor-card
                    parameter="{{ $thresholds['moisture']['parameter'] }}"
                    :value="$telemetry['humidity']"
                    unit="{{ $thresholds['moisture']['unit'] }}"
                    :scaleMin="$thresholds['moisture']['scale_min']"
                    :scaleMax="$thresholds['moisture']['scale_max']"
                    :idealMin="$thresholds['moisture']['ideal_min']"
                    :idealMax="$thresholds['moisture']['ideal_max']"
                    :status="$evaluations['moisture']['status']"
                    :statusLabel="$evaluations['moisture']['label']"
                    icon="droplets"
                    sublabel="{{ $thresholds['moisture']['sublabel'] }}"
                    definition="{{ $thresholds['moisture']['definition'] }}"
                    importance="{{ $thresholds['moisture']['importance'] }}"
                    riskNote="{{ $thresholds['moisture']['riskNote'] }}"
                />

                <!-- EC -->
                <x-sensor-card
                    parameter="{{ $thresholds['ec']['parameter'] }}"
                    :value="$telemetry['ec']"
                    unit="{{ $thresholds['ec']['unit'] }}"
                    :scaleMin="$thresholds['ec']['scale_min']"
                    :scaleMax="$thresholds['ec']['scale_max']"
                    :idealMin="$thresholds['ec']['ideal_min']"
                    :idealMax="$thresholds['ec']['ideal_max']"
                    :status="$evaluations['ec']['status']"
                    :statusLabel="$evaluations['ec']['label']"
                    icon="gauge"
                    sublabel="{{ $thresholds['ec']['sublabel'] }}"
                    definition="{{ $thresholds['ec']['definition'] }}"
                    importance="{{ $thresholds['ec']['importance'] }}"
                    riskNote="{{ $thresholds['ec']['riskNote'] }}"
                />

                <!-- Suhu Tanah -->
                <x-sensor-card
                    parameter="{{ $thresholds['temperature']['parameter'] }}"
                    :value="$telemetry['temperature']"
                    unit="{{ $thresholds['temperature']['unit'] }}"
                    :scaleMin="$thresholds['temperature']['scale_min']"
                    :scaleMax="$thresholds['temperature']['scale_max']"
                    :idealMin="$thresholds['temperature']['ideal_min']"
                    :idealMax="$thresholds['temperature']['ideal_max']"
                    :status="$evaluations['temperature']['status']"
                    :statusLabel="$evaluations['temperature']['label']"
                    icon="thermometer"
                    sublabel="{{ $thresholds['temperature']['sublabel'] }}"
                    definition="{{ $thresholds['temperature']['definition'] }}"
                    importance="{{ $thresholds['temperature']['importance'] }}"
                    riskNote="{{ $thresholds['temperature']['riskNote'] }}"
                />
            </div>
        </div>

        <!-- Group 2: Hara Makro N-P-K (4 Kolom: N, P, K, Indeks Kesuburan) -->
        <div class="space-y-2">
            <h3 class="text-xs font-semibold text-slate-700">Hara makro N-P-K</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Nitrogen -->
                <x-sensor-card
                    parameter="{{ $thresholds['nitrogen']['parameter'] }}"
                    :value="$telemetry['nitrogen']"
                    unit="{{ $thresholds['nitrogen']['unit'] }}"
                    :scaleMin="$thresholds['nitrogen']['scale_min']"
                    :scaleMax="$thresholds['nitrogen']['scale_max']"
                    :idealMin="$thresholds['nitrogen']['ideal_min']"
                    :idealMax="$thresholds['nitrogen']['ideal_max']"
                    :status="$evaluations['nitrogen']['status']"
                    :statusLabel="$evaluations['nitrogen']['label']"
                    letter="N"
                    sublabel="{{ $thresholds['nitrogen']['sublabel'] }}"
                    definition="{{ $thresholds['nitrogen']['definition'] }}"
                    importance="{{ $thresholds['nitrogen']['importance'] }}"
                    riskNote="{{ $thresholds['nitrogen']['riskNote'] }}"
                />

                <!-- Fosfor -->
                <x-sensor-card
                    parameter="{{ $thresholds['phosphorus']['parameter'] }}"
                    :value="$telemetry['phosphorus']"
                    unit="{{ $thresholds['phosphorus']['unit'] }}"
                    :scaleMin="$thresholds['phosphorus']['scale_min']"
                    :scaleMax="$thresholds['phosphorus']['scale_max']"
                    :idealMin="$thresholds['phosphorus']['ideal_min']"
                    :idealMax="$thresholds['phosphorus']['ideal_max']"
                    :status="$evaluations['phosphorus']['status']"
                    :statusLabel="$evaluations['phosphorus']['label']"
                    letter="P"
                    sublabel="{{ $thresholds['phosphorus']['sublabel'] }}"
                    definition="{{ $thresholds['phosphorus']['definition'] }}"
                    importance="{{ $thresholds['phosphorus']['importance'] }}"
                    riskNote="{{ $thresholds['phosphorus']['riskNote'] }}"
                />

                <!-- Kalium -->
                <x-sensor-card
                    parameter="{{ $thresholds['potassium']['parameter'] }}"
                    :value="$telemetry['potassium']"
                    unit="{{ $thresholds['potassium']['unit'] }}"
                    :scaleMin="$thresholds['potassium']['scale_min']"
                    :scaleMax="$thresholds['potassium']['scale_max']"
                    :idealMin="$thresholds['potassium']['ideal_min']"
                    :idealMax="$thresholds['potassium']['ideal_max']"
                    :status="$evaluations['potassium']['status']"
                    :statusLabel="$evaluations['potassium']['label']"
                    letter="K"
                    sublabel="{{ $thresholds['potassium']['sublabel'] }}"
                    definition="{{ $thresholds['potassium']['definition'] }}"
                    importance="{{ $thresholds['potassium']['importance'] }}"
                    riskNote="{{ $thresholds['potassium']['riskNote'] }}"
                />

                <!-- Indeks Kesuburan (4th Card) -->
                <div class="w-full h-[200px] p-4 rounded-2xl bg-[#f4f9f6] border border-emerald-100/70 shadow-2xs flex flex-col justify-between">
                    <div>
                        <h3 class="text-xs font-semibold text-slate-900 leading-tight">Indeks kesuburan</h3>
                        <span class="text-[10px] text-slate-400 font-normal block mt-0.5">Ringkasan seluruh parameter</span>
                    </div>

                    <div class="space-y-1 my-auto">
                        <div class="flex items-baseline gap-1">
                            <span class="font-tabular text-2xl font-bold text-slate-800 tracking-tight">
                                {{ $healthScore !== null ? $healthScore . '%' : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="w-2 h-2 rounded-full {{ $healthScore !== null ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            <span class="{{ $healthScore !== null ? 'text-emerald-700 font-medium' : 'text-slate-400' }}">
                                {{ $healthScore !== null ? ($healthScore >= 80 ? 'Optimal Horti' : 'Perlu Kalibrasi') : 'Menunggu Telemetri' }}
                            </span>
                        </div>
                    </div>

                    <div class="pt-1.5 border-t border-emerald-100/60 text-[11px] text-slate-500 leading-relaxed">
                        Terisi setelah ketujuh parameter terbaca.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Anomaly Alerts Banner jika ada -->
    <x-anomaly-alerts :alerts="$alerts" />

    <!-- Two-Column Section: Tren Telemetri Lahan (Kiri) & Aktuator Trainer Kit (Kanan) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <!-- Kiri: Chart Tren Telemetri Utama -->
        <div class="lg:col-span-8">
            <x-telemetry-chart :initialData="$chartData ?? []" :thresholds="$thresholds" />
        </div>

        <!-- Kanan: Aktuator Trainer Kit 4-Kanal -->
        <div class="lg:col-span-4">
            <x-relay-controls
                :deviceId="$activeDevice?->id"
                :canControl="$activeDevice !== null"
                :isOnline="$activeDevice?->is_online ?? false"
            />
        </div>
    </div>

    <!-- Section: Riwayat Pembacaan Telemetri Terakhir -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-semibold text-slate-900">Riwayat pembacaan terakhir</h3>
            <a
                href="{{ url('/telemetry/history') }}"
                class="text-xs font-medium text-emerald-700 hover:text-emerald-800 flex items-center gap-1 transition-colors"
            >
                <span>Lihat semua log</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600 text-xs font-medium bg-slate-50/70">
                        <th class="py-2.5 px-3">Waktu rekam</th>
                        <th class="py-2.5 px-3">pH</th>
                        <th class="py-2.5 px-3">Lengas (%)</th>
                        <th class="py-2.5 px-3">EC (µS/cm)</th>
                        <th class="py-2.5 px-3">N (mg/kg)</th>
                        <th class="py-2.5 px-3">P (mg/kg)</th>
                        <th class="py-2.5 px-3">K (mg/kg)</th>
                        <th class="py-2.5 px-3">Suhu (°C)</th>
                        <th class="py-2.5 px-3 text-right">Asal Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($recentReadings as $reading)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-2.5 px-3 text-slate-600 font-sans">
                                {{ $reading->recorded_at ? $reading->recorded_at->format('d/m H:i:s') : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular font-medium {{ $reading->ph !== null && $reading->ph < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $reading->ph !== null ? number_format((float)$reading->ph, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular {{ $reading->humidity !== null && $reading->humidity < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $reading->humidity !== null ? number_format((float)$reading->humidity, 1) . '%' : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular text-slate-800">
                                {{ $reading->ec !== null ? number_format((float)$reading->ec, 0) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular text-slate-800">
                                {{ $reading->nitrogen !== null ? number_format((float)$reading->nitrogen, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular text-slate-800">
                                {{ $reading->phosphorus !== null ? number_format((float)$reading->phosphorus, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular text-slate-800">
                                {{ $reading->potassium !== null ? number_format((float)$reading->potassium, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-tabular text-slate-800">
                                {{ $reading->temperature !== null ? number_format((float)$reading->temperature, 1) . '°C' : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium font-sans {{ $reading->provenance === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($reading->provenance === 'simulation' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700') }}">
                                    {{ $reading->provenance === 'hardware' ? 'Hardware' : ($reading->provenance === 'simulation' ? 'Simulasi' : 'Fixture') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 text-center text-slate-400 font-sans">
                                Belum ada data telemetri tercatat untuk trainer kit ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
