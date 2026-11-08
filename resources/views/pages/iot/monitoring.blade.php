@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Hero Banner: Station Context, Kit Selector & Soil Health Index -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-2 max-w-2xl">
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Device Selector Dropdown -->
                <form method="GET" action="{{ url('/iot-monitoring') }}" class="inline-flex items-center">
                    <select
                        name="device_id"
                        onchange="this.form.submit()"
                        class="px-2.5 py-1 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-300 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
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

                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                    <span>{{ $activeDevice ? ($activeDevice->bench_location ?? $activeDevice->name) : 'Belum Ada Meja Terpilih' }}</span>
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-mono">Pembaruan: {{ $telemetry['recorded_at'] }}</span>
                @if($telemetry['provenance'])
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $telemetry['provenance'] === 'hardware' ? 'bg-emerald-100 text-emerald-800' : ($telemetry['provenance'] === 'simulation' ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' : 'bg-slate-200 text-slate-700') }}">
                        {{ $telemetry['provenance_label'] ?? $telemetry['provenance'] }}
                    </span>
                @endif
            </div>

            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Monitoring Telemetri Lahan & Trainer Kit Vokasi
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Stasiun ukur 7-in-1 aktif memantau dinamika hara makro (N-P-K), kesetimbangan asam-basa (pH), salinitas (EC), serta lengas perakaran secara otomatis.
            </p>
        </div>

        <!-- Health Score Gauge & Actions -->
        <div class="flex items-center gap-4 shrink-0 bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div class="text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Indeks Kesuburan</span>
                <div class="flex items-baseline justify-center gap-1 mt-0.5">
                    <span class="font-tabular font-extrabold text-3xl {{ $healthScore === null ? 'text-slate-300' : ($healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600')) }}">
                        {{ $healthScore === null ? '—' : $healthScore . '%' }}
                    </span>
                </div>
                <span class="text-[10px] font-semibold {{ $healthScore === null ? 'text-slate-500' : ($healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600')) }}">
                    {{ $healthScore === null ? 'Menunggu Telemetri' : ($healthScore >= 80 ? 'Optimal Horti' : ($healthScore >= 60 ? 'Perlu Kalibrasi' : 'Anomali Kritis')) }}
                </span>
            </div>

            <div class="h-10 w-px bg-slate-200"></div>

            <div class="flex flex-col gap-2">
                <button
                    type="button"
                    @click="$dispatch('open-ai-drawer')"
                    class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-sm transition-colors"
                >
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    <span>Analisis AI</span>
                </button>
                <a
                    href="{{ url('/telemetry/history') }}"
                    class="px-3.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Ekspor CSV</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Anomaly Alerts Banner jika ada -->
    <x-anomaly-alerts :alerts="$alerts" />

    <!-- Bento Grid Section: 7 Parameter Sensor Meters (3D Flip Card Glosarium) -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Parameter Sensor Tanah 7-in-1</h3>
                <p class="text-[11px] text-slate-500">Klik kartu untuk membalik & melihat Glosarium Agronomi SMK</p>
            </div>
            <span class="text-[11px] font-mono {{ ($telemetry['is_live'] ?? false) ? 'text-emerald-700' : 'text-slate-500' }} font-semibold flex items-center gap-1">
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
        @endphp

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

            <!-- Meter 4: Nitrogen (N) -->
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

            <!-- Meter 5: Fosfor (P) -->
            <x-sensor-card
                parameter="Fosfor Tersedia (P)"
                :value="$telemetry['phosphorus']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="100"
                :idealMin="25"
                :idealMax="60"
                :status="'optimal'"
                :statusLabel="$telemetry['phosphorus'] === null ? 'Menunggu Data' : 'Cukup'"
                sublabel="Energi & Perakaran"
                icon="sparkles"
                definition="Unsur hara makro primer untuk transfer energi molekuler (ATP/ADP), pembelahan sel, dan inisiasi perakaran."
                idealDesc="25 – 60 mg/kg tanah (Optimal memicu perkembangan rambut akar dan bunga)."
                lowRisk="P < 20: Sistem perakaran dangkal, daun berwarna keunguan (akumulasi antosianin)."
                highRisk="P > 80: Mengganggu penyerapan unsur mikro seng (Zn) dan besi (Fe)."
            />

            <!-- Meter 6: Kalium (K) -->
            <x-sensor-card
                parameter="Kalium Tersedia (K)"
                :value="$telemetry['potassium']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="300"
                :idealMin="100"
                :idealMax="200"
                :status="'optimal'"
                :statusLabel="$telemetry['potassium'] === null ? 'Menunggu Data' : 'Optimal'"
                sublabel="Kekebalan & Kualitas Buah"
                icon="shield"
                definition="Aktivator enzim, osmoregulasi pembukaan stomata, dan translokasi asimilasi hasil fotosintesis ke buah."
                idealDesc="100 – 200 mg/kg tanah (Meningkatkan daya tahan terhadap penyakit dan bobot buah)."
                lowRisk="K < 80: Tepi daun terbakar (nekrosis marginal), daya simpan hasil panen rendah."
                highRisk="K > 250: Menghambat absorbsi kation magnesium (Mg2+) dan kalsium (Ca2+)."
            />

            <!-- Meter 7: Suhu Tanah -->
            <x-sensor-card
                parameter="Suhu Tanah (Temp)"
                :value="$telemetry['temperature']"
                unit="°C"
                :scaleMin="10"
                :scaleMax="45"
                :idealMin="22"
                :idealMax="29"
                :status="'optimal'"
                :statusLabel="$telemetry['temperature'] === null ? 'Menunggu Data' : 'Ideal'"
                sublabel="Aktivitas Biologis Tanah"
                icon="thermometer"
                definition="Derajat panas zona perakaran (rhizosphere) yang mempengaruhi respirasi akar dan mikrobioma dekomposer."
                idealDesc="22 – 29°C (Laju penyerapan air dan respirasi akar paling seimbang)."
                lowRisk="Suhu < 18°C: Penyerapan hara fosfor dan air melambat drastis."
                highRisk="Suhu > 33°C: Respirasi perakaran berlebih, akar terdegradasi dan rentan busuk Phytophthora."
            />

            <!-- Card 8: Quick Station Hardware Specs (Trainer Kit Meja Praktik) -->
            <div class="p-4 rounded-xl bg-slate-900 text-white border border-slate-800 flex flex-col justify-between shadow-sm">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <i data-lucide="cpu" class="w-4 h-4 text-emerald-400"></i>
                        <span class="text-xs font-bold uppercase tracking-wider font-mono">Modbus Gateway</span>
                    </div>
                    <span class="w-2 h-2 rounded-full {{ ($activeDevice?->is_online ?? false) ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                </div>
                <div class="space-y-1.5 text-xs py-2 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Protokol:</span>
                        <span class="text-slate-200">{{ $activeDevice->protocol ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Baudrate:</span>
                        <span class="text-slate-200">{{ $activeDevice ? '9600 bps' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Firmware:</span>
                        <span class="text-emerald-400">{{ $activeDevice->firmware_version ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">IP Gateway:</span>
                        <span class="text-slate-200">{{ $activeDevice->ip_address ?? '—' }}</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                    <span>{{ $activeDevice->code ?? '—' }}</span>
                    <span class="{{ ($activeDevice?->is_online ?? false) ? 'text-emerald-400' : 'text-slate-400' }} font-semibold">{{ ucfirst($activeDevice->status ?? 'Offline') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actuators Relay Controls (4-Channel) -->
    <x-relay-controls :deviceId="$activeDevice?->id" :canControl="$activeDevice !== null" />

    <!-- Multi-Parameter Interactive Switcher ApexChart -->
    <x-telemetry-chart />

    <!-- Rekapitulasi Data Log Cepat (No jQuery) -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-xs font-bold text-slate-900">Riwayat Pembacaan Telemetri Terakhir</h4>
                <p class="text-[10px] text-slate-500">Tercatat di Basis Data PostgreSQL 17/18</p>
            </div>
            <a
                href="{{ url('/telemetry/history') }}"
                class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1"
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
