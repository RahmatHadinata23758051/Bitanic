@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    ANALITIK DATA
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Korelasi Bivariat & Tren Agronomi</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Analitik Agronomi & Dinamika Lahan
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Evaluasi hubungan bivariat antar-parameter tanah (pH vs EC, Suhu vs Lengas) serta telusuri profil ketersediaan hara N-P-K untuk optimalisasi fertigasi presisi.
            </p>
        </div>

        <!-- Device Selector Form -->
        <form method="GET" action="{{ url('/analytics') }}" class="flex items-center gap-2 shrink-0">
            <label for="device_id" class="text-xs font-semibold text-slate-600">Stasiun:</label>
            <select
                id="device_id"
                name="device_id"
                onchange="this.form.submit()"
                class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm cursor-pointer"
            >
                @foreach($devices as $dev)
                    <option value="{{ $dev->id }}" {{ ($selectedDevice->id ?? null) === $dev->id ? 'selected' : '' }}>
                        {{ $dev->code }} — {{ $dev->bench_location ?? $dev->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <!-- Correlation Cards Bento Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($correlations as $corr)
            <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ $corr['label'] }}</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $corr['value'] !== null ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-500' }}">
                        Pearson r
                    </span>
                </div>
                <div class="flex items-baseline gap-3">
                    <span class="font-tabular text-3xl font-extrabold {{ $corr['value'] !== null ? 'text-slate-900' : 'text-slate-400' }}">
                        {{ $corr['value'] !== null ? ($corr['value'] > 0 ? '+' : '') . number_format($corr['value'], 3) : 'Data Belum Cukup' }}
                    </span>
                    @if($corr['value'] !== null)
                        <span class="text-xs font-semibold {{ abs($corr['value']) >= 0.5 ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ abs($corr['value']) >= 0.7 ? 'Korelasi Kuat' : (abs($corr['value']) >= 0.4 ? 'Korelasi Sedang' : 'Korelasi Lemah') }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    @if(str_contains($corr['label'], 'pH'))
                        Menilai keterikatan antara derajat kemasaman tanah dengan konsentrasi ion pupuk terlarut (salinitas EC) dalam larutan rizosfer.
                    @else
                        Mengamati pengaruh fluktuasi temperatur substrat terhadap laju evapotranspirasi dan retensi air pori tanah.
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <!-- Multi-Parameter Interactive Chart Component -->
    <x-telemetry-chart />

    <!-- Agronomic Reference Thresholds Matrix -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900">Matriks Ambang Batas Agronomi Standar (SMK ATPH)</h3>
            <p class="text-xs text-slate-500">Rentang ideal dan analisis risiko fisiologis tanaman budidaya</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50">
                        <th class="py-2.5 px-3">Parameter</th>
                        <th class="py-2.5 px-3">Satuan</th>
                        <th class="py-2.5 px-3">Rentang Ideal</th>
                        <th class="py-2.5 px-3">Risiko di Bawah Ambang</th>
                        <th class="py-2.5 px-3">Risiko di Atas Ambang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    <tr>
                        <td class="py-3 px-3 font-bold text-slate-800">pH Tanah</td>
                        <td class="py-3 px-3 font-mono text-slate-600">pH</td>
                        <td class="py-3 px-3 font-mono font-semibold text-emerald-700">6.0 – 7.2</td>
                        <td class="py-3 px-3 text-slate-600">Fiksasi fosfor kuat, kelarutan ion Al/Fe toksik</td>
                        <td class="py-3 px-3 text-slate-600">Defisiensi mikronutrien (Fe, Mn, Zn, Cu)</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 font-bold text-slate-800">Kadar Lengas Tanah (VWC)</td>
                        <td class="py-3 px-3 font-mono text-slate-600">%</td>
                        <td class="py-3 px-3 font-mono font-semibold text-emerald-700">45.0 – 65.0</td>
                        <td class="py-3 px-3 text-slate-600">Titik layu permanen, stomata menutup</td>
                        <td class="py-3 px-3 text-slate-600">Hipoksia akar (anaerob), busuk perakaran</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 font-bold text-slate-800">Konduktivitas Listrik (EC)</td>
                        <td class="py-3 px-3 font-mono text-slate-600">µS/cm</td>
                        <td class="py-3 px-3 font-mono font-semibold text-emerald-700">1000 – 2200</td>
                        <td class="py-3 px-3 text-slate-600">Larutan tanah miskin hara hara makro</td>
                        <td class="py-3 px-3 text-slate-600">Tekanan osmotik tinggi, akar terbakar pupuk</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 font-bold text-slate-800">Nitrogen Tersedia (N)</td>
                        <td class="py-3 px-3 font-mono text-slate-600">mg/kg</td>
                        <td class="py-3 px-3 font-mono font-semibold text-emerald-700">40.0 – 120.0</td>
                        <td class="py-3 px-3 text-slate-600">Klorosis daun tua, pertumbuhan kerdil</td>
                        <td class="py-3 px-3 text-slate-600">Jaringan sukulen berlebih, rentan roboh & hama</td>
                    </tr>
                    <tr>
                        <td class="py-3 px-3 font-bold text-slate-800">Suhu Zona Akar</td>
                        <td class="py-3 px-3 font-mono text-slate-600">°C</td>
                        <td class="py-3 px-3 font-mono font-semibold text-emerald-700">22.0 – 29.0</td>
                        <td class="py-3 px-3 text-slate-600">Penyerapan air dan translokasi hara lambat</td>
                        <td class="py-3 px-3 text-slate-600">Laju respirasi akar terlalu cepat, kematian sel</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
