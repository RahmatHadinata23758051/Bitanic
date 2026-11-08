@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header / Konteks -->
    <div class="space-y-3">
        <!-- Top Row: Page Title & Workstation Selector -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="space-y-0.5">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                    Monitoring telemetri lahan<span class="sr-only"> — Monitoring Telemetri Lahan & Trainer Kit Vokasi</span>
                </h1>
                <p class="text-xs text-slate-500 font-normal">
                    Stasiun ukur 7-in-1 memantau hara makro (N-P-K), pH, salinitas (EC), lengas, dan suhu perakaran secara otomatis.
                </p>
            </div>

            <!-- Device Selector -->
            <div class="shrink-0">
                <form method="GET" action="{{ url('/iot-monitoring') }}" class="space-y-1">
                    <label for="device-selector" class="block text-xs font-semibold text-slate-600">Stasiun meja praktik</label>
                    <select
                        id="device-selector"
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-800 border border-slate-200 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-2xs min-w-[240px]"
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
            </div>
        </div>

        <!-- Sub-row: Status, Timestamps, Disclosure & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1 border-t border-slate-100 text-xs">
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Online/Offline Indicator -->
                <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full {{ ($activeDevice?->is_online ?? false) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    <span>{{ ($activeDevice?->is_online ?? false) ? 'Online' : 'Offline' }}</span>
                </span>

                <!-- Last Update Timestamp -->
                <span class="text-slate-400 flex items-center gap-1 font-mono text-[11px]">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                    <span>Pembaruan terakhir: {{ $telemetry['has_telemetry'] ? $telemetry['recorded_at'] : 'belum ada transmisi data' }}</span>
                </span>

                <!-- Detail Gateway Modbus Popover -->
                <div x-data="{ open: false }" class="relative inline-block">
                    <button
                        type="button"
                        @click="open = !open"
                        class="text-slate-600 hover:text-slate-900 font-medium flex items-center gap-1 transition-colors"
                    >
                        <span>Detail gateway Modbus</span>
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
                            <span class="text-slate-800 font-semibold">{{ $activeDevice->protocol ?? 'Modbus RTU over RS485' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="text-slate-400 font-sans">Baudrate:</span>
                            <span class="text-slate-800 font-semibold">{{ $activeDevice ? '9600 bps, 8-N-1' : '—' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="text-slate-400 font-sans">Firmware:</span>
                            <span class="text-emerald-700 font-semibold">{{ $activeDevice->firmware_version ?? 'v2.4.1' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-sans">IP Gateway:</span>
                            <span class="text-slate-800 font-semibold">{{ $activeDevice->ip_address ?? '192.168.4.1' }}</span>
                        </div>
                    </div>
                </div>

                @if($telemetry['provenance'])
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $telemetry['provenance'] === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($telemetry['provenance'] === 'simulation' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700') }}">
                        {{ $telemetry['provenance_label'] ?? $telemetry['provenance'] }}
                    </span>
                @endif
            </div>

            <!-- Action Buttons: Analisis AI & Ekspor CSV -->
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="$dispatch('open-ai-drawer')"
                    class="px-3.5 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#08281a] text-white text-xs font-semibold flex items-center gap-1.5 shadow-2xs transition-colors"
                >
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    <span>Analisis AI</span>
                </button>
                <a
                    href="{{ url('/telemetry/history') }}"
                    class="px-3.5 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-medium flex items-center gap-1.5 shadow-2xs transition-colors"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Ekspor CSV</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Section: Parameter Sensor Tanah 7-in-1 -->
    <div class="space-y-4">
        <!-- Section Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 tracking-tight">Parameter sensor tanah 7-in-1</h2>
                <p class="text-xs text-slate-400">Klik kartu untuk membalik dan melihat glosarium agronomi.</p>
            </div>
            <span class="text-xs font-mono {{ ($telemetry['is_live'] ?? false) ? 'text-emerald-700' : 'text-slate-400' }} font-medium flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ ($telemetry['is_live'] ?? false) ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                <span>{{ ($telemetry['is_live'] ?? false) ? 'Streaming Modbus Aktif' : 'Belum Ada Transmisi Data' }}</span>
            </span>
        </div>

        @php
            $phVal = $telemetry['ph'];
            $phStatus = $phVal === null ? 'optimal' : ($phVal < 5.5 ? 'critical' : ($phVal <= 7.2 ? 'optimal' : 'warning'));
            $phLabel = $phVal === null ? 'Menunggu data' : ($phVal < 5.5 ? 'Masam Ekstrem' : ($phVal <= 7.2 ? 'Optimal' : 'Alkali/Basa'));

            $humVal = $telemetry['humidity'];
            $humStatus = $humVal === null ? 'optimal' : ($humVal < 25 ? 'critical' : ($humVal <= 65 ? 'optimal' : 'warning'));
            $humLabel = $humVal === null ? 'Menunggu data' : ($humVal < 25 ? 'Kering Kritis' : ($humVal <= 65 ? 'Optimal' : 'Jenuh Air'));

            $ecVal = $telemetry['ec'];
            $ecStatus = $ecVal === null ? 'optimal' : ($ecVal > 2200 ? 'warning' : 'optimal');
            $ecLabel = $ecVal === null ? 'Menunggu data' : ($ecVal > 2200 ? 'Salinitas Tinggi' : 'Optimal');

            $tempVal = $telemetry['temperature'];
            $tempStatus = $tempVal === null ? 'optimal' : ($tempVal < 18 ? 'warning' : ($tempVal <= 29 ? 'optimal' : 'warning'));
            $tempLabel = $tempVal === null ? 'Menunggu data' : ($tempVal < 18 ? 'Dingin' : ($tempVal <= 29 ? 'Ideal' : 'Panas'));

            $nVal = $telemetry['nitrogen'];
            $nStatus = $nVal === null ? 'optimal' : ($nVal < 30 ? 'warning' : 'optimal');
            $nLabel = $nVal === null ? 'Menunggu data' : ($nVal < 30 ? 'Rendah' : 'Cukup');

            $pVal = $telemetry['phosphorus'];
            $pStatus = $pVal === null ? 'optimal' : ($pVal < 20 ? 'warning' : 'optimal');
            $pLabel = $pVal === null ? 'Menunggu data' : ($pVal < 20 ? 'Rendah' : 'Cukup');

            $kVal = $telemetry['potassium'];
            $kStatus = $kVal === null ? 'optimal' : ($kVal < 80 ? 'warning' : 'optimal');
            $kLabel = $kVal === null ? 'Menunggu data' : ($kVal < 80 ? 'Rendah' : 'Optimal');
        @endphp

        <!-- Group 1: Kondisi Media Tanam (4 Kolom) -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-800">Kondisi media tanam</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- pH -->
                <x-sensor-card
                    parameter="Derajat Keasaman (pH)"
                    :value="$phVal"
                    unit="pH"
                    :scaleMin="0"
                    :scaleMax="14"
                    :idealMin="6.0"
                    :idealMax="7.2"
                    :status="$phStatus"
                    :statusLabel="$phLabel"
                    sublabel="Aktivitas ion H⁺"
                    icon="flask-conical"
                    definition="Derajat kemasaman tanah yang menentukan ketersediaan penyerapan ion hara bagi akar tanaman budidaya."
                    idealDesc="6.0 – 7.2 pH (Kondisi prima sebagian besar sayuran hortikultura & tanaman pangan)."
                    lowRisk="pH < 5.5: Fiksasi fosfor kuat, keracunan ion Al & Mn."
                    highRisk="pH > 7.5: Defisiensi Fe, Zn, Cu dan ion fosfor terikat kalsium."
                />

                <!-- Lengas -->
                <x-sensor-card
                    parameter="Kadar Lengas Tanah"
                    :value="$humVal"
                    unit="%"
                    :scaleMin="0"
                    :scaleMax="100"
                    :idealMin="45"
                    :idealMax="65"
                    :status="$humStatus"
                    :statusLabel="$humLabel"
                    sublabel="Volumetrik water (VWC)"
                    icon="droplets"
                    definition="Persentase volume air bebas dan kapiler dalam pori-pori tanah per satuan volume total tanah."
                    idealDesc="45 – 65% (Kapasitas Lapang ideal aerasi & ketersediaan air perakaran)."
                    lowRisk="Lengas < 25%: Titik layu permanen, stomata menutup."
                    highRisk="Lengas > 75%: Pori aerasi tergenang, hipoksia & busuk akar."
                />

                <!-- EC -->
                <x-sensor-card
                    parameter="Konduktivitas Listrik (EC)"
                    :value="$ecVal"
                    unit="µS/cm"
                    :scaleMin="0"
                    :scaleMax="4000"
                    :idealMin="1000"
                    :idealMax="2200"
                    :status="$ecStatus"
                    :statusLabel="$ecLabel"
                    sublabel="Indeks kepekatan pupuk"
                    icon="zap"
                    definition="Daya hantar listrik larutan tanah yang berbanding lurus dengan kepekatan total garam pupuk mineral."
                    idealDesc="1000 – 2200 µS/cm (Setara 1.0 – 2.2 mS/cm untuk hortikultura)."
                    lowRisk="EC < 800: Larutan miskin hara makro mineral."
                    highRisk="EC > 2400: Tekanan osmotik tinggi, akar terbakar pupuk."
                />

                <!-- Suhu Tanah -->
                <x-sensor-card
                    parameter="Suhu Tanah"
                    :value="$tempVal"
                    unit="°C"
                    :scaleMin="10"
                    :scaleMax="45"
                    :idealMin="22"
                    :idealMax="29"
                    :status="$tempStatus"
                    :statusLabel="$tempLabel"
                    sublabel="Aktivitas biologis tanah"
                    icon="thermometer"
                    definition="Derajat panas zona perakaran yang mempengaruhi respirasi akar dan mikrobioma dekomposer tanah."
                    idealDesc="22 – 29°C (Laju penyerapan air dan respirasi akar paling seimbang)."
                    lowRisk="Suhu < 18°C: Penyerapan fosfor dan translokasi melambat."
                    highRisk="Suhu > 33°C: Respirasi akar boros, rentan infeksi cendawan patogen."
                />
            </div>
        </div>

        <!-- Group 2: Hara Makro N-P-K (4 Kolom: N, P, K, Indeks Kesuburan) -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-800">Hara makro N-P-K</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Nitrogen -->
                <x-sensor-card
                    parameter="Nitrogen Tersedia (N)"
                    :value="$nVal"
                    unit="mg/kg"
                    :scaleMin="0"
                    :scaleMax="200"
                    :idealMin="40"
                    :idealMax="120"
                    :status="$nStatus"
                    :statusLabel="$nLabel"
                    sublabel="Pertumbuhan vegetatif"
                    letter="N"
                    definition="Unsur hara makro primer esensial penyusun protein dan klorofil daun untuk fotosintesis tanaman."
                    idealDesc="40 – 120 mg/kg tanah (Mendukung laju pembentukan biomassa hijau dan tajuk tanaman)."
                    lowRisk="N < 30: Klorosis daun tua seragam, pertumbuhan kerdil."
                    highRisk="N > 150: Sukulen berlebih, jaringan lunak rentan roboh dan hama."
                />

                <!-- Fosfor -->
                <x-sensor-card
                    parameter="Fosfor Tersedia (P)"
                    :value="$pVal"
                    unit="mg/kg"
                    :scaleMin="0"
                    :scaleMax="100"
                    :idealMin="25"
                    :idealMax="60"
                    :status="$pStatus"
                    :statusLabel="$pLabel"
                    sublabel="Energi dan perakaran"
                    letter="P"
                    definition="Unsur hara makro primer untuk transfer energi molekuler ATP, inisiasi perakaran, dan primordia bunga."
                    idealDesc="25 – 60 mg/kg tanah (Optimal memicu perkembangan rambut akar dan bunga)."
                    lowRisk="P < 20: Rambut akar dangkal, daun ungu antosianin."
                    highRisk="P > 80: Menghambat penyerapan mikronutrien seng & besi."
                />

                <!-- Kalium -->
                <x-sensor-card
                    parameter="Kalium Tersedia (K)"
                    :value="$kVal"
                    unit="mg/kg"
                    :scaleMin="0"
                    :scaleMax="300"
                    :idealMin="100"
                    :idealMax="200"
                    :status="$kStatus"
                    :statusLabel="$kLabel"
                    sublabel="Kekebalan dan kualitas buah"
                    letter="K"
                    definition="Aktivator enzim seluler, osmoregulasi stomata daun, dan translokasi asimilat gula ke organ buah."
                    idealDesc="100 – 200 mg/kg tanah (Meningkatkan daya tahan penyakit dan bobot panen)."
                    lowRisk="K < 80: Tepi daun nekrosis terbakar, daya simpan panen rendah."
                    highRisk="K > 250: Mengantagonis absorbsi ion magnesium dan kalsium."
                />

                <!-- Indeks Kesuburan (4th Card) -->
                <div class="w-full h-[195px] p-4 rounded-2xl bg-[#f4f9f6] border border-emerald-100/70 shadow-2xs flex flex-col justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 leading-tight">Indeks kesuburan</h3>
                        <span class="text-[10px] text-slate-400 font-medium block">Ringkasan seluruh parameter</span>
                    </div>

                    <div class="space-y-0.5 my-auto">
                        <div class="flex items-baseline gap-1">
                            <span class="font-tabular text-2xl font-extrabold text-slate-700 tracking-tight">
                                {{ $healthScore !== null ? $healthScore . '%' : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full {{ $healthScore !== null ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            <span>{{ $healthScore !== null ? ($healthScore >= 80 ? 'Optimal Horti' : 'Perlu Kalibrasi') : 'Menunggu Telemetri' }}</span>
                        </div>
                    </div>

                    <div class="pt-1 border-t border-emerald-100/60 text-[11px] text-slate-400 leading-relaxed">
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
            <x-telemetry-chart :initialData="$chartData ?? []" />
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
            <div>
                <h3 class="text-xs font-bold text-slate-900">Riwayat pembacaan telemetri terakhir</h3>
                <p class="text-[10px] text-slate-400">Lima pembacaan terbaru, tercatat di basis data PostgreSQL 17/18.</p>
            </div>
            <a
                href="{{ url('/telemetry/history') }}"
                class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 transition-colors"
            >
                <span>Lihat semua log</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50">
                        <th class="py-2.5 px-3">Waktu Rekam</th>
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
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    @forelse($recentReadings as $reading)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-2.5 px-3 text-slate-600 font-sans">
                                {{ $reading->recorded_at ? $reading->recorded_at->format('d/m H:i:s') : '—' }}
                            </td>
                            <td class="py-2.5 px-3 font-semibold {{ $reading->ph !== null && $reading->ph < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $reading->ph !== null ? number_format((float)$reading->ph, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 {{ $reading->humidity !== null && $reading->humidity < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ $reading->humidity !== null ? number_format((float)$reading->humidity, 1) . '%' : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ $reading->ec !== null ? number_format((float)$reading->ec, 0) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ $reading->nitrogen !== null ? number_format((float)$reading->nitrogen, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ $reading->phosphorus !== null ? number_format((float)$reading->phosphorus, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ $reading->potassium !== null ? number_format((float)$reading->potassium, 1) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ $reading->temperature !== null ? number_format((float)$reading->temperature, 1) . '°C' : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-sans {{ $reading->provenance === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($reading->provenance === 'simulation' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700') }}">
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
