@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Hero Banner: Station Context & Soil Health Index -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-2 max-w-2xl">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-1 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    {{ $activeDevice->code ?? 'BITANIC-TK-01' }}
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                    <span>{{ $activeDevice->bench_location ?? 'Meja Praktik 01 - Bedengan A' }}</span>
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-mono">Pembaruan: {{ $telemetry['recorded_at'] }}</span>
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
                    <span class="font-tabular font-extrabold text-3xl {{ $healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600') }}">
                        {{ $healthScore }}%
                    </span>
                </div>
                <span class="text-[10px] font-semibold {{ $healthScore >= 80 ? 'text-emerald-700' : ($healthScore >= 60 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $healthScore >= 80 ? 'Optimal Horti' : ($healthScore >= 60 ? 'Perlu Kalibrasi' : 'Anomali Kritis') }}
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
            <span class="text-[11px] font-mono text-emerald-700 font-semibold flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Streaming Modbus Aktif</span>
            </span>
        </div>

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
                :status="$telemetry['ph'] < 5.5 ? 'critical' : ($telemetry['ph'] <= 7.2 ? 'optimal' : 'warning')"
                :statusLabel="$telemetry['ph'] < 5.5 ? 'Masam Ekstrem' : ($telemetry['ph'] <= 7.2 ? 'Optimal' : 'Alkali/Basa')"
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
                :status="$telemetry['humidity'] < 25 ? 'critical' : ($telemetry['humidity'] <= 65 ? 'optimal' : 'warning')"
                :statusLabel="$telemetry['humidity'] < 25 ? 'Kering Kritis' : ($telemetry['humidity'] <= 65 ? 'Optimal' : 'Jenuh Air')"
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
                :scaleMax="3000"
                :idealMin="1200"
                :idealMax="2000"
                :status="$telemetry['ec'] > 2200 ? 'warning' : 'optimal'"
                :statusLabel="$telemetry['ec'] > 2200 ? 'Salinitas Tinggi' : 'Optimal'"
                sublabel="Konsentrasi Garam Pupuk"
                icon="zap"
                definition="Ukuran daya hantar listrik larutan tanah yang merefleksikan konsentrasi ion garam hara pupuk larut."
                idealDesc="1.200 – 2.000 µS/cm (Dosis fertigasi ideal untuk fase generatif/vegetatif prima)."
                lowRisk="EC < 800 µS/cm: Defisiensi larutan hara makro, tanaman kelaparan pupuk."
                highRisk="EC > 2.500 µS/cm: Tekanan osmotik tinggi, plasmolisis akar, ujung daun terbakar."
            />

            <!-- Meter 4: Nitrogen (N) -->
            <x-sensor-card
                parameter="Nitrogen Tersedia (N)"
                :value="$telemetry['nitrogen']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="200"
                :idealMin="40"
                :idealMax="80"
                :status="$telemetry['nitrogen'] < 30 ? 'warning' : 'optimal'"
                :statusLabel="$telemetry['nitrogen'] < 30 ? 'Rendah' : 'Cukup'"
                sublabel="Vegetatif & Klorofil"
                icon="sprout"
                definition="Unsur hara makro primer esensial penyusun asam amino, protein, dan molekul klorofil hijau daun."
                idealDesc="40 – 80 mg/kg (Mendukung pertumbuhan pucuk, batang vegetatif, dan luas permukaan daun)."
                lowRisk="N Rendah: Klorosis pada daun tua (kuning merata), pertumbuhan kerdil terhenti."
                highRisk="N Berlebih: Tanaman sukulen rentan rebah, mudah diserang jamur & hama pengisap."
            />

            <!-- Meter 5: Fosfor (P) -->
            <x-sensor-card
                parameter="Fosfor Tersedia (P)"
                :value="$telemetry['phosphorus']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="100"
                :idealMin="20"
                :idealMax="50"
                :status="'optimal'"
                :statusLabel="'Optimal'"
                sublabel="Perakaran & Energi ATP"
                icon="flask-conical"
                definition="Komponen kunci adenosin trifosfat (ATP), pemacu perakaran awal, serta pembungaan dan pembuahan."
                idealDesc="20 – 50 mg/kg (Merangsang pemanjangan bulu akar dan inisiasi primordia bunga)."
                lowRisk="P Rendah: Daun tua berubah keunguan/kebiruan, sistem perakaran dangkal dan rapuh."
                highRisk="P Berlebih: Mengganggu penyerapan seng (Zn) dan besi (Fe) pada tanah alkalin."
            />

            <!-- Meter 6: Kalium (K) -->
            <x-sensor-card
                parameter="Kalium Tersedia (K)"
                :value="$telemetry['potassium']"
                unit="mg/kg"
                :scaleMin="0"
                :scaleMax="250"
                :idealMin="100"
                :idealMax="180"
                :status="'optimal'"
                :statusLabel="'Optimal'"
                sublabel="Imunitas & Regulasi Air"
                icon="shield"
                definition="Kation pengatur turgor sel, pembukaan stomata, translokasi asimilat pati, dan ketahanan penyakit."
                idealDesc="100 – 180 mg/kg (Meningkatkan bobot buah, kemanisan brix, dan ketahanan kekeringan)."
                lowRisk="K Rendah: Tepi daun tua mengering seperti terbakar (marginal chlorosis), batang lemas."
                highRisk="K Berlebih: Menyebabkan defisiensi magnesium (Mg) dan kalsium (Ca)."
            />

            <!-- Meter 7: Suhu Tanah -->
            <x-sensor-card
                parameter="Suhu Tanah Perakaran"
                :value="$telemetry['temperature']"
                unit="°C"
                :scaleMin="10"
                :scaleMax="45"
                :idealMin="24"
                :idealMax="30"
                :status="'optimal'"
                :statusLabel="'Ideal'"
                sublabel="Respirasi & Mikroba"
                icon="thermometer"
                definition="Suhu substrat rizosfer yang mengontrol laju respirasi akar dan aktivitas mikroorganisme tanah."
                idealDesc="24.0 – 30.0 °C (Kondisi biologis tanah paling aktif untuk serapan air)."
                lowRisk="Suhu < 18°C: Penyerapan hara terhenti, laju metabolisme anjlok."
                highRisk="Suhu > 35°C: Denaturasi enzim sel akar, penguapan ekstrem, cekaman panas."
            />

            <!-- Card 8: Trainer Kit Info Slot -->
            <div class="p-5 rounded-xl bg-slate-900 text-white border border-slate-800 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <span class="text-xs font-bold text-emerald-400">HARDWARE SPESIFIKASI</span>
                    <i data-lucide="cpu" class="w-4 h-4 text-slate-400"></i>
                </div>
                <div class="space-y-1.5 text-xs py-2 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Protokol:</span>
                        <span class="text-slate-200">Modbus RS485</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Baudrate:</span>
                        <span class="text-slate-200">9600 bps</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Microcontroller:</span>
                        <span class="text-emerald-400">ESP32 240MHz</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Security Key:</span>
                        <span class="text-slate-200">SHA-256 Auth</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                    <span>Meja Praktikum #01</span>
                    <span class="text-emerald-400 font-semibold">Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actuators Relay Controls (4-Channel) -->
    <x-relay-controls :deviceId="$activeDevice->id ?? 1" />

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
                        <th class="py-2.5 px-3 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    @forelse($recentReadings as $reading)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-2.5 px-3 text-slate-600 font-sans">
                                {{ $reading->recorded_at ? $reading->recorded_at->format('d/m H:i:s') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 font-semibold {{ $reading->ph < 5.5 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ number_format((float)$reading->ph, 2) }}
                            </td>
                            <td class="py-2.5 px-3 {{ $reading->humidity < 25 ? 'text-rose-600' : 'text-slate-800' }}">
                                {{ number_format((float)$reading->humidity, 1) }}%
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ number_format((float)$reading->ec, 0) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ number_format((float)$reading->nitrogen, 1) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ number_format((float)$reading->phosphorus, 1) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ number_format((float)$reading->potassium, 1) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-800">
                                {{ number_format((float)$reading->temperature, 1) }}°C
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 font-sans">
                                    Tercatat
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 text-center text-slate-400 font-sans">
                                Belum ada riwayat telemetri di tabel PostgreSQL.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
