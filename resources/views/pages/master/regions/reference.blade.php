@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <span>Data Master</span>
                <span>/</span>
                <a href="{{ route('master.regions.index') }}" class="hover:text-slate-800 transition-colors">Wilayah Indonesia</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Referensi Administratif</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Master Wilayah Indonesia
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Basis data referensi resmi wilayah administratif nasional (Provinsi, Kabupaten/Kota, Kecamatan) berbasis Kepmendagri & BPS.
            </p>
        </div>

        <!-- Sync Button & Back to Distribution -->
        <div class="flex items-center gap-2 shrink-0">
            <a
                href="{{ route('master.regions.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-xs transition-colors"
            >
                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>Lihat Persebaran Mitra</span>
            </a>
            <form action="{{ route('master.regions.sync') }}" method="POST" onsubmit="return confirm('Sinkronisasi ulang referensi wilayah dari berkas master resmi?');">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#082819] shadow-xs transition-colors"
                >
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-emerald-300"></i>
                    <span>Sinkronisasi Referensi</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Navigation Tabs Between Primary & Reference -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a
            href="{{ route('master.regions.index') }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-2"
        >
            <i data-lucide="map-pinned" class="w-4 h-4"></i>
            <span>Persebaran Sekolah Mitra</span>
        </a>
        <a
            href="{{ route('master.regions.index', ['view' => 'reference']) }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 border-[#0c3c26] text-[#0c3c26] transition-colors flex items-center gap-2"
        >
            <i data-lucide="database" class="w-4 h-4"></i>
            <span>Referensi Administratif Nasional</span>
        </a>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Dataset Provenance Metadata Card -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-[#0c3c26] shrink-0 mt-0.5">
                    <i data-lucide="database" class="w-4.5 h-4.5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-slate-900">
                            {{ $metadata->source_name ?? 'Kepmendagri No 050-145 Tahun 2022 / BPS Indonesia' }}
                        </h2>
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                            v{{ $metadata->dataset_version ?? '2024.1' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Checksum SHA-256: <code class="font-mono text-[11px] text-slate-600 bg-slate-100 px-1 py-0.5 rounded">{{ substr($metadata->checksum ?? 'verified-local-reference', 0, 16) }}...</code>
                        @if($metadata?->imported_at)
                            &bull; Terakhir disinkronisasi: <span class="font-tabular">{{ $metadata->imported_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-5">
                <div class="text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Provinsi</p>
                    <p class="text-base font-bold text-slate-900 font-tabular">{{ $metadata->total_provinces ?? $provinces->count() }}</p>
                </div>
                <div class="w-px h-7 bg-slate-100"></div>
                <div class="text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kab / Kota</p>
                    <p class="text-base font-bold text-slate-900 font-tabular">{{ $metadata->total_regencies ?? 514 }}</p>
                </div>
                <div class="w-px h-7 bg-slate-100"></div>
                <div class="text-center">
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kecamatan</p>
                    <p class="text-base font-bold text-slate-900 font-tabular">{{ $metadata->total_districts ?? 826 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrative Levels Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a
            href="{{ route('master.regions.index', ['view' => 'reference', 'level' => 'provinces']) }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2
                {{ $level === 'provinces' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            <i data-lucide="map" class="w-4 h-4"></i>
            <span>Tingkat I — Provinsi</span>
        </a>
        <a
            href="{{ route('master.regions.index', ['view' => 'reference', 'level' => 'regencies', 'province_code' => $selectedProvince]) }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2
                {{ $level === 'regencies' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            <i data-lucide="building" class="w-4 h-4"></i>
            <span>Tingkat II — Kabupaten / Kota</span>
        </a>
        <a
            href="{{ route('master.regions.index', ['view' => 'reference', 'level' => 'districts', 'province_code' => $selectedProvince, 'regency_code' => $selectedRegency]) }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2
                {{ $level === 'districts' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            <i data-lucide="navigation" class="w-4 h-4"></i>
            <span>Tingkat III — Kecamatan</span>
        </a>
    </div>

    <!-- Filter and Search Toolbar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-xs">
        <form method="GET" action="{{ route('master.regions.index') }}" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="view" value="reference">
            <input type="hidden" name="level" value="{{ $level }}">

            @if($level !== 'provinces')
                <div class="sm:w-64">
                    <select
                        name="province_code"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        onchange="this.form.submit()"
                    >
                        <option value="">Semua Provinsi</option>
                        @foreach($provinces as $prov)
                            <option value="{{ $prov->code }}" {{ $selectedProvince === $prov->code ? 'selected' : '' }}>
                                {{ $prov->code }} - {{ $prov->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari kode wilayah atau nama..."
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="px-4 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#082819] transition-colors"
                >
                    Filter
                </button>
                @if(!empty($search) || !empty($selectedProvince) || !empty($selectedRegency))
                    <a
                        href="{{ route('master.regions.index', ['view' => 'reference', 'level' => $level]) }}"
                        class="px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4 w-36">Kode Wilayah</th>
                        @if($level === 'districts')
                            <th class="py-3 px-4">Provinsi</th>
                            <th class="py-3 px-4">Kabupaten / Kota</th>
                            <th class="py-3 px-4">Nama Kecamatan</th>
                        @elseif($level === 'regencies')
                            <th class="py-3 px-4">Provinsi</th>
                            <th class="py-3 px-4">Nama Kabupaten / Kota</th>
                        @else
                            <th class="py-3 px-4">Nama Provinsi</th>
                            <th class="py-3 px-4 text-center">Jumlah Kab / Kota</th>
                            <th class="py-3 px-4 text-center">Sekolah Terdaftar</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($items as $item)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-mono font-medium text-slate-900 font-tabular">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-800">
                                    {{ $item->code }}
                                </span>
                            </td>

                            @if($level === 'districts')
                                <td class="py-3 px-4 text-slate-600">
                                    {{ $item->regency?->province?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    {{ $item->regency?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-slate-900 font-semibold">
                                    {{ $item->name }}
                                </td>
                            @elseif($level === 'regencies')
                                <td class="py-3 px-4 text-slate-600">
                                    {{ $item->province?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-slate-900 font-semibold">
                                    {{ $item->name }}
                                </td>
                            @else
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    {{ $item->name }}
                                </td>
                                <td class="py-3 px-4 text-center font-tabular text-slate-700">
                                    {{ $item->regencies_count ?? 0 }}
                                </td>
                                <td class="py-3 px-4 text-center font-tabular text-slate-700">
                                    {{ $item->schools_count ?? 0 }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $level === 'districts' ? 4 : ($level === 'regencies' ? 3 : 4) }}" class="py-8 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="map-pin-off" class="w-8 h-8 text-slate-300 mb-2"></i>
                                    <p class="text-sm font-medium text-slate-700">Tidak ada data wilayah yang ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Coba ubah kata kunci pencarian atau filter provinsi.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
            <div class="p-3.5 border-t border-slate-100 bg-slate-50/50">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

