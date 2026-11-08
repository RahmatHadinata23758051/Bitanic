@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Section A: Header / Konteks -->
    <header class="p-6 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-4">
        <!-- Top Row: Context & Secondary Actions -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ ($telemetry['is_live'] ?? false) ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($telemetry['has_telemetry'] ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-600 border border-slate-200') }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ ($telemetry['is_live'] ?? false) ? 'bg-emerald-500 animate-pulse' : ($telemetry['has_telemetry'] ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                        <span>{{ ($telemetry['is_live'] ?? false) ? 'Streaming Modbus Aktif' : ($telemetry['has_telemetry'] ? 'Telemetri Terakhir (Stale / Offline)' : 'Belum Ada Transmisi Data') }}</span>
                    </span>

                    @if($telemetry['provenance'])
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase font-mono {{ $telemetry['provenance'] === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($telemetry['provenance'] === 'simulation' ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-slate-200 text-slate-700') }}">
                            {{ $telemetry['provenance_label'] ?? $telemetry['provenance'] }}
                        </span>
                    @endif

                    <span class="text-xs text-slate-300">•</span>
                    <span class="text-xs text-slate-500 font-mono flex items-center gap-1">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>{{ $telemetry['recorded_at'] }}</span>
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-sans">
                    Monitoring IoT
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    {!! 'Monitoring Telemetri Lahan & Trainer Kit Vokasi' !!}
                </p>
            </div>

            <!-- Secondary Actions -->
            <div class="flex items-center gap-2.5 shrink-0">
                <a
                    href="{{ url('/telemetry/history') }}"
                    class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors shadow-2xs"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Ekspor CSV</span>
                </a>
                <button
                    type="button"
                    @click="$dispatch('open-ai-drawer')"
                    class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-colors"
                >
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    <span>Analisis AI</span>
                </button>
            </div>
        </div>

        <!-- Bottom Row: Station Selector & Bench Location -->
        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Device Selector Dropdown -->
                <form method="GET" action="{{ url('/iot-monitoring') }}" class="inline-flex items-center">
                    <label for="device-selector" class="sr-only">Pilih Trainer Kit</label>
                    <select
                        id="device-selector"
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold font-mono bg-emerald-50 text-emerald-900 border border-emerald-300 focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-2xs"
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

                <span class="text-xs text-slate-300">•</span>
                <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                    <span>{{ $activeDevice ? ($activeDevice->bench_location ?? $activeDevice->name) : 'Belum Ada Meja Terpilih' }}</span>
                </span>
            </div>

            <!-- Health Score Indicator -->
            <div class="inline-flex items-center gap-2 text-xs">
                <span class="text-slate-500 font-medium">Indeks Kesuburan:</span>
                <span class="font-tabular font-extrabold text-sm {{ $healthScore === null ? 'text-slate-400' : ($healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600')) }}">
                    {{ $healthScore === null ? '—' : $healthScore . '%' }}
                </span>
                <span class="text-[11px] font-semibold {{ $healthScore === null ? 'text-slate-400' : ($healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600')) }}">
                    ({{ $healthScore === null ? 'Menunggu Telemetri' : ($healthScore >= 80 ? 'Optimal Horti' : ($healthScore >= 60 ? 'Perlu Kalibrasi' : 'Anomali Kritis')) }})
                </span>
            </div>
        </div>
    </header>

    <!-- Section B: Summary Strip (Ringkas, Horizontal, Mudah Discan) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Status Koneksi -->
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Koneksi Hardware</span>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full {{ ($activeDevice?->is_online ?? false) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                <span class="text-xs font-bold text-slate-800">{{ ($activeDevice?->is_online ?? false) ? 'Online (Terhubung)' : 'Offline / Standby' }}</span>
            </div>
        </div>

        <!-- Update Terakhir -->
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Waktu Pembacaan</span>
            <span class="text-xs font-semibold text-slate-800 font-mono block truncate" title="{{ $telemetry['recorded_at'] }}">
                {{ $telemetry['recorded_at'] }}
            </span>
        </div>

        <!-- 7 Parameter Status -->
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kondisi Parameter</span>
            @if($telemetry['has_telemetry'])
                <div class="flex items-center gap-1 text-xs">
                    <span class="font-bold text-emerald-700">{{ $normalParamsCount }} Normal</span>
                    <span class="text-slate-300">/</span>
                    <span class="font-semibold {{ $warningParamsCount > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $warningParamsCount }} Perhatian</span>
                </div>
            @else
                <span class="text-xs font-medium text-slate-400">7 Menunggu Data</span>
            @endif
        </div>

        <!-- Anomali Aktif -->
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Anomali Terdeteksi</span>
            <div class="flex items-center gap-1 text-xs">
                @if(count($alerts) > 0)
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span class="font-bold text-rose-700">{{ count($alerts) }} Peringatan Aktif</span>
                @else
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="font-semibold text-emerald-700">Kondisi Stabil</span>
                @endif
            </div>
        </div>

        <!-- Aktuator Aktif -->
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-1 col-span-2 sm:col-span-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Status Aktuator</span>
            <span class="text-xs font-bold text-slate-800 font-mono">
                {{ $activeActuatorsCount }}/4 Kanal Menyala
            </span>
        </div>
    </div>

    <!-- Anomaly Alerts Banner jika ada -->
    <x-anomaly-alerts :alerts="$alerts" />

    <!-- Section C & D: Grid Parameter Sensor 7-in-1 (Desktop: 4 kolom lalu 3 kolom) -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 tracking-tight">Parameter Sensor Tanah 7-in-1</h2>
                <p class="text-xs text-slate-500">Klik kartu di mana saja untuk membalik &amp; melihat Glosarium Agronomi SMK</p>
            </div>
            <span class="text-xs font-mono {{ ($telemetry['is_live'] ?? false) ? 'text-emerald-700' : 'text-slate-500' }} font-semibold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full {{ ($telemetry['is_live'] ?? false) ? 'bg-emerald-500 animate-pulse' : ($telemetry['has_telemetry'] ? 'bg-amber-400' : 'bg-slate-400') }}"></span>
                <span>{{ ($telemetry['is_live'] ?? false) ? 'Streaming Modbus Aktif' : ($telemetry['has_telemetry'] ? 'Telemetri Terakhir (Stale / Offline)' : 'Belum Ada Transmisi Data') }}</span>
            </span>
        </div>

        @php
            $phVal = $telemetry['ph'];
            $phStatus = $phVal === null ? 'optimal' : ($phVal < 5.5 ? 'critical' : ($phVal <= 7.2 ? 'optimal' : 'warning'));
            $phLabel = $phVal === null ? 'Menunggu Data' : ($phVal < 5.5 ? 'Masam Ekstrem' : ($phVal <= 7.2 ? 'Optimal' : 'Alkali/Basa'));

            $humVal = $telemetry['humidity'];
            $humStatus = $humVal === null ? 'optimal' : ($humVal < 25 ? 'critical' : ($humVal <= 65 ? 'optimal' : 'warning'));
            $humLabel = $humVal === null ? 'Menunggu Data' : ($humVal < 25 ? 'Kering Kritis' : ($humVal <= 65 ? 'Optimal' : 'Jenuh Air'));

            $ecVal = $telemetry['ec'];
            $ecStatus = $ecVal === null ? 'optimal' : ($ecVal > 2200 ? 'warning' : 'optimal');
            $ecLabel = $ecVal === null ? 'Menunggu Data' : ($ecVal > 2200 ? 'Salinitas Tinggi' : 'Optimal');

            $nVal = $telemetry['nitrogen'];
            $nStatus = $nVal === null ? 'optimal' : ($nVal < 30 ? 'warning' : 'optimal');
            $nLabel = $nVal === null ? 'Menunggu Data' : ($nVal < 30 ? 'Rendah' : 'Cukup');

            $pVal = $telemetry['phosphorus'];
            $pStatus = $pVal === null ? 'optimal' : ($pVal < 20 ? 'warning' : 'optimal');
            $pLabel = $pVal === null ? 'Menunggu Data' : ($pVal < 20 ? 'Rendah' : 'Cukup');

            $kVal = $telemetry['potassium'];
            $kStatus = $kVal === null ? 'optimal' : ($kVal < 80 ? 'warning' : 'optimal');
            $kLabel = $kVal === null ? 'Menunggu Data' : ($kVal < 80 ? 'Rendah' : 'Optimal');

            $tempVal = $telemetry['temperature'];
            $tempStatus = $tempVal === null ? 'optimal' : ($tempVal < 18 ? 'warning' : ($tempVal <= 29 ? 'optimal' : 'warning'));
            $tempLabel = $tempVal === null ? 'Menunggu Data' : ($tempVal < 18 ? 'Dingin' : ($tempVal <= 29 ? 'Ideal' : 'Panas'));
        @endphp

        <!-- Baris 1: 4 Kolom (pH, Lengas, EC, Suhu) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Meter 1: pH Tanah -->
            <x-sensor-card
                parameter="Derajat Keasaman (pH)"
                :value="$telemetry['ph']"
                unit="pH"
                :scaleMin="0"
                :scaleMax="14"
                :idealMin="6.0"
                :idealMax="7.2"
                :status="$phStatus"
                :statusLabel="$phLabel"
                sublabel="Aktivitas Ion [H+]"
                icon="gauge"
                definition="Derajat kemasaman tanah yang menentukan ketersediaan larutan ion hara bagi akar tanaman budidaya."
                idealDesc="6.0 – 7.2 pH (Kondisi prima sebagian besar sayuran hortikultura & tanaman pangan)."
                lowRisk="pH < 5.5: Fiksasi fosfor kuat, kelarutan ion Al & Fe toksik, mikroba tanah terhambat."
                highRisk="pH > 7.5: Defisiensi mikronutrien (Fe, Mn, Zn, Cu) dan penyerapan fosfor terikat kalsium."
            />

            <!-- Meter 2: Kadar Lengas (VWC) -->
            <x-sensor-card
                parameter="Kadar Lengas Tanah"
                :value="$telemetry['humidity']"
                unit="%"
                :scaleMin="0"
                :scaleMax="100"
                :idealMin="45"
                :idealMax="65"
                :status="$humStatus"
                :statusLabel="$humLabel"
                sublabel="Volumetric Water (VWC)"
                icon="droplets"
                definition="Persentase volume air bebas dan kapiler dalam pori-pori tanah per satuan volume total tanah."
                idealDesc="45 – 65% (Kapasitas Lapang ideal aerasi & ketersediaan air perakaran)."
                lowRisk="Lengas < 25%: Titik layu permanen, penutupan stomata, respirasi terhenti."
                highRisk="Lengas > 75%: Pori aerasi tanah tergenang air, akar mengalami hipoksia (busuk akar)."
            />

            <!-- Meter 3: EC Nutrisi Salinitas -->
            <x-sensor-card
                parameter="Konduktivitas Listrik (EC)"
                :value="$telemetry['ec']"
                unit="µS/cm"
                :scaleMin="0"
                :scaleMax="4000"
                :idealMin="1000"
                :idealMax="2200"
                :status="$ecStatus"
                :statusLabel="$ecLabel"
                sublabel="Indeks Kepekatan Pupuk"
                icon="zap"
                definition="Daya hantar listrik larutan tanah yang berbanding lurus dengan kepekatan total garam mineral terlarut."
                idealDesc="1000 – 2200 µS/cm (Setara 1.0 – 2.2 mS/cm untuk tanaman hortikultura masa vegetatif-generatif)."
                lowRisk="EC < 800: Larutan tanah miskin hara, tanaman defisiensi mineral makro."
                highRisk="EC > 2400: Tekanan osmotik tinggi, air tertarik keluar dari sel akar (terbakar pupuk)."
            />

            <!-- Meter 4: Suhu Tanah -->
            <x-sensor-card
                parameter="Suhu Tanah (Temp)"
                :value="$telemetry['temperature']"
                unit="°C"
                :scaleMin="10"
                :scaleMax="45"
                :idealMin="22"
                :idealMax="29"
                :status="$tempStatus"
                :statusLabel="$tempLabel"
                sublabel="Aktivitas Biologis Tanah"
                icon="thermometer"
                definition="Derajat panas zona perakaran (rhizosphere) yang mempengaruhi respirasi akar dan mikrobioma dekomposer."
                idealDesc="22 – 29°C (Laju penyerapan air dan respirasi akar paling seimbang)."
                lowRisk="Suhu < 18°C: Penyerapan hara fosfor dan air melambat drastis."
                highRisk="Suhu > 33°C: Respirasi perakaran berlebih, akar terdegradasi dan rentan busuk Phytophthora."
            />
        </div>

        <!-- Baris 2: 3 Kolom (Nitrogen, Fosfor, Kalium) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Meter 5: Nitrogen (N) -->
            <x-sensor-card
                parameter="Nitrogen Tersedia (N)"
                :value="$telemetry['nitrogen']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="200"
                :idealMin="40"
                :idealMax="120"
                :status="$nStatus"
                :statusLabel="$nLabel"
                sublabel="Pertumbuhan Vegetatif"
                icon="sprout"
                definition="Unsur hara makro primer esensial penyusun asam amino, protein, dan klorofil untuk fotosintesis daun."
                idealDesc="40 – 120 mg/kg tanah (Mendukung laju pembentukan biomassa hijau dan tajuk tanaman)."
                lowRisk="N < 30: Klorosis (daun tua menguning seragam), batang kerdil dan kurus."
                highRisk="N > 150: Sukulen berlebih, jaringan daun lunak rentan rebah dan rentan serangan hama serangga."
            />

            <!-- Meter 6: Fosfor (P) -->
            <x-sensor-card
                parameter="Fosfor Tersedia (P)"
                :value="$telemetry['phosphorus']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="100"
                :idealMin="25"
                :idealMax="60"
                :status="$pStatus"
                :statusLabel="$pLabel"
                sublabel="Energi &amp; Perakaran"
                icon="sparkles"
                definition="Unsur hara makro primer untuk transfer energi molekuler (ATP/ADP), pembelahan sel, dan inisiasi perakaran."
                idealDesc="25 – 60 mg/kg tanah (Optimal memicu perkembangan rambut akar dan bunga)."
                lowRisk="P < 20: Sistem perakaran dangkal, daun berwarna keunguan (akumulasi antosianin)."
                highRisk="P > 80: Mengganggu penyerapan unsur mikro seng (Zn) dan besi (Fe)."
            />

            <!-- Meter 7: Kalium (K) -->
            <x-sensor-card
                parameter="Kalium Tersedia (K)"
                :value="$telemetry['potassium']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="300"
                :idealMin="100"
                :idealMax="200"
                :status="$kStatus"
                :statusLabel="$kLabel"
                sublabel="Kekebalan &amp; Kualitas Buah"
                icon="shield"
                definition="Aktivator enzim, osmoregulasi pembukaan stomata, dan translokasi asimilasi hasil fotosintesis ke buah."
                idealDesc="100 – 200 mg/kg tanah (Meningkatkan daya tahan terhadap penyakit dan bobot buah)."
                lowRisk="K < 80: Tepi daun terbakar (nekrosis marginal), daya simpan hasil panen rendah."
                highRisk="K > 250: Menghambat absorbsi kation magnesium (Mg2+) dan kalsium (Ca2+)."
            />
        </div>
    </div>

    <!-- Section E: Section Tren Telemetri (1 Area Chart Utama dengan Tabs & Time Range) -->
    <x-telemetry-chart :initialData="$chartData ?? []" />

    <!-- Section F: Aktuator Trainer Kit (Section Terpisah & Ringkas 4-Kanal) -->
    <div class="space-y-4">
        <x-relay-controls :deviceId="$activeDevice?->id" :canControl="$activeDevice !== null" />
    </div>

    <!-- Modbus Gateway Technical Specification (Subtle Technical Panel) -->
    <details class="group p-4 rounded-xl bg-slate-50 border border-slate-200/90 text-xs transition-colors">
        <summary class="font-bold text-slate-700 flex items-center justify-between cursor-pointer select-none">
            <div class="flex items-center gap-2">
                <i data-lucide="cpu" class="w-4 h-4 text-slate-500"></i>
                <span>Spesifikasi Teknis Gateway Modbus RS485 (Workstation Hardware)</span>
            </div>
            <span class="text-slate-400 group-open:rotate-180 transition-transform">
                <i data-lucide="chevron-down" class="w-4 h-4"></i>
            </span>
        </summary>
        <div class="pt-3 mt-3 border-t border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono text-[11px]">
            <div>
                <span class="text-slate-400 block text-[10px]">Protokol Fieldbus</span>
                <span class="text-slate-800 font-semibold">{{ $activeDevice->protocol ?? 'Modbus RTU over RS485' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px]">Baudrate Bus</span>
                <span class="text-slate-800 font-semibold">{{ $activeDevice ? '9600 bps, 8-N-1' : '—' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px]">Firmware Gateway</span>
                <span class="text-emerald-700 font-semibold">{{ $activeDevice->firmware_version ?? 'v1.4.2-atph' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px]">Alamat IP Gateway</span>
                <span class="text-slate-800 font-semibold">{{ $activeDevice->ip_address ?? '192.168.4.1' }}</span>
            </div>
        </div>
    </details>

    <!-- Section G: Riwayat Pembacaan Terakhir (Tabel Ringkas dengan Link Lihat Semua Log) -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xs font-bold text-slate-900">Riwayat Pembacaan Telemetri Terakhir</h3>
                <p class="text-[10px] text-slate-500">Tercatat di Basis Data PostgreSQL 17/18</p>
            </div>
            <a
                href="{{ url('/telemetry/history') }}"
                class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 transition-colors"
            >
                <span>Lihat Semua Log</span>
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
