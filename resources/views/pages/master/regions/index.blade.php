@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="schoolDistributionApp(@js($distribution['map_markers']))">
    <!-- Breadcrumb & Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <span>Data Master</span>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Wilayah & Persebaran Mitra</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Persebaran Sekolah Mitra BITANIC
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Sebaran institusi pengguna dan pelanggan LMS berdasarkan provinsi, kabupaten/kota, dan kecamatan.
            </p>
        </div>

        <!-- Navigation Link to National Reference -->
        <div class="flex items-center gap-2 shrink-0">
            <a
                href="{{ route('master.regions.index', ['view' => 'reference']) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 shadow-xs transition-colors"
                title="Buka basis data referensi resmi Kemendagri"
            >
                <i data-lucide="database" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Master Wilayah Indonesia (38 Provinsi Kemendagri, mis. JAWA BARAT)</span>
            </a>
        </div>
    </div>

    <!-- Navigation Tabs Between Primary & Reference -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a
            href="{{ route('master.regions.index') }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 border-[#0c3c26] text-[#0c3c26] transition-colors flex items-center gap-2"
        >
            <i data-lucide="map-pinned" class="w-4 h-4"></i>
            <span>Persebaran Sekolah Mitra</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 font-tabular">
                {{ $distribution['summary']['total_partner_schools'] }}
            </span>
        </a>
        <a
            href="{{ route('master.regions.index', ['view' => 'reference']) }}"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-2"
        >
            <i data-lucide="database" class="w-4 h-4"></i>
            <span>Referensi Administratif Nasional</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 text-slate-600 font-tabular">
                {{ $metadata->total_provinces ?? 38 }} Provinsi
            </span>
        </a>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Sekolah Mitra -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4.5 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Total Sekolah Mitra</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-[#0c3c26] flex items-center justify-center">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 font-tabular">{{ $distribution['summary']['total_partner_schools'] }}</span>
                <span class="text-xs text-slate-500 font-medium">Institusi Berlangganan</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>Memiliki hak akses entitas term</span>
            </p>
        </div>

        <!-- Provinsi Terjangkau -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4.5 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Provinsi Terjangkau</span>
                <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                    <i data-lucide="map" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 font-tabular">{{ $distribution['summary']['covered_provinces_count'] }}</span>
                <span class="text-xs text-slate-500 font-medium">dari 38 Provinsi</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                <i data-lucide="compass" class="w-3.5 h-3.5 text-blue-600"></i>
                <span>Sebaran geografis aktif</span>
            </p>
        </div>

        <!-- Kab / Kota Terjangkau -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4.5 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Kabupaten / Kota</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
                    <i data-lucide="building" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 font-tabular">{{ $distribution['summary']['covered_regencies_count'] }}</span>
                <span class="text-xs text-slate-500 font-medium">Daerah Tercover</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-600"></i>
                <span>Tingkat II administrasi</span>
            </p>
        </div>

        <!-- Sekolah Lisensi Aktif -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4.5 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Lisensi Aktif Hari Ini</span>
                <span class="w-8 h-8 rounded-xl bg-leaf-100 text-[#0c3c26] flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-emerald-700 font-tabular">{{ $distribution['summary']['active_license_count'] }}</span>
                <span class="text-xs text-slate-500 font-medium">dari {{ $distribution['summary']['total_partner_schools'] }} Mitra</span>
            </div>
            <div class="flex items-center gap-3 text-[11px] text-slate-400 mt-2">
                <span class="text-amber-600 font-medium font-tabular">{{ $distribution['summary']['pending_license_count'] }} Menunggu</span>
                <span>&bull;</span>
                <span class="text-rose-600 font-medium font-tabular">{{ $distribution['summary']['expired_license_count'] }} Kedaluwarsa</span>
            </div>
        </div>
    </div>

    <!-- Notification If Unmapped Schools Exist -->
    @if($distribution['summary']['unmapped_schools_count'] > 0)
        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-900 text-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <i data-lucide="map-pin-off" class="w-4 h-4 text-amber-600 shrink-0"></i>
                <div>
                    <span class="font-semibold">{{ $distribution['summary']['unmapped_schools_count'] }} sekolah mitra</span>
                    <span>belum memiliki koordinat geografis terverifikasi. Sekolah tetap terdata dalam direktori wilayah tetapi dikecualikan dari titik peta agar tidak menghasilkan data semu.</span>
                </div>
            </div>
            <a
                href="{{ route('master.schools.index') }}"
                class="shrink-0 px-3 py-1.5 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 font-semibold text-[11px] transition-colors inline-flex items-center gap-1.5"
            >
                <span>Lengkapi Koordinat</span>
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>
    @endif

    <!-- Toolbar Filters -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
            <a
                href="{{ route('master.regions.index', array_merge(request()->query(), ['status' => 'all'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors
                    {{ ($distribution['filters']['status'] ?? 'all') === 'all' ? 'bg-[#0c3c26] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Semua Mitra ({{ $distribution['summary']['total_partner_schools'] }})
            </a>
            <a
                href="{{ route('master.regions.index', array_merge(request()->query(), ['status' => 'active'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors flex items-center gap-1.5
                    {{ ($distribution['filters']['status'] ?? '') === 'active' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
            >
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Lisensi Aktif ({{ $distribution['summary']['active_license_count'] }})</span>
            </a>
            <a
                href="{{ route('master.regions.index', array_merge(request()->query(), ['status' => 'pending'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors flex items-center gap-1.5
                    {{ ($distribution['filters']['status'] ?? '') === 'pending' ? 'bg-amber-700 text-white shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}"
            >
                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                <span>Menunggu Efektif ({{ $distribution['summary']['pending_license_count'] }})</span>
            </a>
            <a
                href="{{ route('master.regions.index', array_merge(request()->query(), ['status' => 'expired'])) }}"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors flex items-center gap-1.5
                    {{ ($distribution['filters']['status'] ?? '') === 'expired' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-800 hover:bg-rose-100' }}"
            >
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                <span>Kedaluwarsa ({{ $distribution['summary']['expired_license_count'] }})</span>
            </a>
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('master.regions.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $distribution['filters']['status'] }}">
            @if(!empty($distribution['filters']['province_code']))
                <input type="hidden" name="province_code" value="{{ $distribution['filters']['province_code'] }}">
            @endif
            @if(!empty($distribution['filters']['regency_code']))
                <input type="hidden" name="regency_code" value="{{ $distribution['filters']['regency_code'] }}">
            @endif
            @if(!empty($distribution['filters']['district_code']))
                <input type="hidden" name="district_code" value="{{ $distribution['filters']['district_code'] }}">
            @endif
            <div class="relative w-full md:w-64">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $distribution['filters']['search'] }}"
                    placeholder="Cari sekolah atau wilayah..."
                    class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>
            @if(!empty($distribution['filters']['search']))
                <a
                    href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'province_code' => $distribution['filters']['province_code'], 'regency_code' => $distribution['filters']['regency_code'], 'district_code' => $distribution['filters']['district_code']])) }}"
                    class="px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                >
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Linked Geographic Scope Breadcrumbs & Clear Controls -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-3 px-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center flex-wrap gap-2 text-slate-700">
            <span class="text-slate-400 font-medium flex items-center gap-1.5">
                <i data-lucide="navigation" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Cakupan Wilayah:</span>
            </span>
            @foreach($distribution['breadcrumbs'] as $idx => $bc)
                @if($idx > 0)
                    <span class="text-slate-300">/</span>
                @endif
                @if($bc['active'] && $loop->last && ($distribution['selected_region']['has_selection'] ?? false))
                    <span class="font-bold text-[#0c3c26] bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-lg flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3 text-emerald-600"></i>
                        <span>{{ $bc['label'] }}</span>
                    </span>
                @else
                    <a
                        href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'search' => $distribution['filters']['search'], 'province_code' => $bc['province_code'], 'regency_code' => $bc['regency_code'], 'district_code' => $bc['district_code']])) }}"
                        class="text-slate-600 hover:text-[#0c3c26] font-medium hover:underline transition-colors"
                    >
                        {{ $bc['label'] }}
                    </a>
                @endif
            @endforeach
        </div>

        @if($distribution['selected_region']['has_selection'] ?? false)
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-[11px] text-slate-500 font-medium">Filter wilayah aktif</span>
                <a
                    href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'search' => $distribution['filters']['search']])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold transition-colors"
                    title="Tampilkan seluruh sekolah mitra di semua provinsi"
                >
                    <i data-lucide="x" class="w-3 h-3 text-slate-500"></i>
                    <span>Tampilkan Seluruh Wilayah</span>
                </a>
            </div>
        @else
            <div class="text-[11px] text-slate-400 font-medium">
                Menampilkan sebaran di seluruh provinsi terdaftar
            </div>
        @endif
    </div>

    <!-- MAIN VISUALIZATION: INTERACTIVE INDONESIA MAP -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-4 h-4 text-emerald-600"></i>
                    <span>Peta Interaktif Sebaran Sekolah Mitra</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Menampilkan <span class="font-semibold text-slate-800 font-tabular">{{ $distribution['summary']['mapped_schools_count'] }}</span> sekolah berkoordinat valid dari {{ $distribution['summary']['filtered_partner_schools'] }} mitra terpilih.
                </p>
            </div>

            <!-- Legend and Center Map Button -->
            <div class="flex items-center gap-3 text-xs">
                <div class="hidden sm:flex items-center gap-3 text-[11px] text-slate-600 bg-slate-50 border border-slate-200 px-3 py-1 rounded-xl">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span> Aktif</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Menunggu</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span> Kedaluwarsa</span>
                </div>
                <button
                    type="button"
                    @click="resetMapZoom()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                    title="Pusatkan kembali peta ke seluruh Indonesia"
                >
                    <i data-lucide="scan" class="w-3.5 h-3.5"></i>
                    <span>Pusatkan Indonesia</span>
                </button>
            </div>
        </div>

        <!-- Leaflet Map Container -->
        <div class="relative w-full h-[460px] bg-slate-100" id="school-distribution-map" x-ref="mapContainer">
            <!-- Non-map fallback while loading / if offline -->
            <div x-show="!mapLoaded" class="absolute inset-0 flex items-center justify-center bg-slate-50 text-slate-400">
                <div class="flex flex-col items-center">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-emerald-600 mb-2"></i>
                    <p class="text-xs font-medium text-slate-600">Memuat peta sebaran sekolah mitra...</p>
                </div>
            </div>
        </div>

        <!-- Map Footer Attribution & Info -->
        <div class="p-3 bg-slate-50/80 border-t border-slate-100 text-[11px] text-slate-500 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                Data Koordinat: Terverifikasi institusi &bull; Peta: &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" class="underline text-slate-600">OpenStreetMap</a> contributors
            </div>
            <div class="text-slate-400">
                Klik pin sekolah untuk melihat rincian lisensi dan navigasi stasiun.
            </div>
        </div>
    </div>

    <!-- GEOGRAPHIC EXPLORER & SCHOOL DIRECTORY -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Hierarchical Region Explorer (Left 4 cols) -->
        <div class="lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/60">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="folder-tree" class="w-4 h-4 text-emerald-600"></i>
                    <span>Eksplorasi Hirarki Wilayah</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Hanya menampilkan provinsi dan kota yang memiliki sekolah mitra terdaftar.
                </p>
            </div>

            <!-- Hierarchy Accordion -->
            <div class="divide-y divide-slate-100 text-xs max-h-[560px] overflow-y-auto">
                @forelse($distribution['hierarchy'] as $provIndex => $prov)
                    <div x-data="{ openProv: {{ ($distribution['filters']['province_code'] ?? '') === $prov['code'] || (empty($distribution['filters']['province_code']) && $provIndex === 0) ? 'true' : 'false' }} }" class="bg-white {{ ($distribution['filters']['province_code'] ?? '') === $prov['code'] ? 'bg-emerald-50/20' : '' }}">
                        <!-- Province Header -->
                        <div class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50/90 transition-colors">
                            <button
                                type="button"
                                @click="openProv = !openProv"
                                class="flex items-center gap-2.5 text-left grow focus:outline-hidden"
                            >
                                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-90 text-[#0c3c26]': openProv }"></i>
                                <span class="font-bold text-slate-900 {{ ($distribution['filters']['province_code'] ?? '') === $prov['code'] ? 'text-[#0c3c26]' : '' }}">{{ $prov['name'] }}</span>
                            </button>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 font-tabular border border-emerald-200/70">
                                    {{ $prov['schools_count'] }} Sekolah
                                </span>
                                <a
                                    href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'search' => $distribution['filters']['search'], 'province_code' => $prov['code']])) }}"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold transition-colors {{ ($distribution['filters']['province_code'] ?? '') === $prov['code'] && empty($distribution['filters']['regency_code']) ? 'bg-[#0c3c26] text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-emerald-100 hover:text-emerald-900' }}"
                                    title="Filter peta & direktori untuk provinsi ini"
                                >
                                    {{ ($distribution['filters']['province_code'] ?? '') === $prov['code'] && empty($distribution['filters']['regency_code']) ? 'Terpilih' : 'Filter' }}
                                </a>
                            </div>
                        </div>

                        <!-- Regencies List within Province -->
                        <div x-show="openProv" x-collapse class="bg-slate-50/50 border-t border-slate-100 pl-6 pr-3 py-2 space-y-2">
                            @foreach($prov['regencies'] as $regIndex => $reg)
                                <div x-data="{ openReg: {{ ($distribution['filters']['regency_code'] ?? '') === $reg['code'] || (empty($distribution['filters']['regency_code']) && $regIndex === 0) ? 'true' : 'false' }} }" class="bg-white rounded-xl border border-slate-200/70 overflow-hidden {{ ($distribution['filters']['regency_code'] ?? '') === $reg['code'] ? 'ring-1 ring-emerald-500' : '' }}">
                                    <!-- Regency Header -->
                                    <div class="w-full px-3 py-2 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                        <button
                                            type="button"
                                            @click="openReg = !openReg"
                                            class="flex items-center gap-2 text-left grow focus:outline-hidden"
                                        >
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-90 text-emerald-700': openReg }"></i>
                                            <span class="font-semibold text-slate-800 text-[11px] {{ ($distribution['filters']['regency_code'] ?? '') === $reg['code'] ? 'text-emerald-800' : '' }}">{{ $reg['name'] }}</span>
                                        </button>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 font-tabular">
                                                {{ $reg['schools_count'] }} Sekolah
                                            </span>
                                            <a
                                                href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'search' => $distribution['filters']['search'], 'province_code' => $prov['code'], 'regency_code' => $reg['code']])) }}"
                                                class="px-2 py-0.5 rounded text-[10px] font-semibold transition-colors {{ ($distribution['filters']['regency_code'] ?? '') === $reg['code'] && empty($distribution['filters']['district_code']) ? 'bg-[#0c3c26] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-emerald-100 hover:text-emerald-900' }}"
                                                title="Filter peta & direktori untuk kabupaten/kota ini"
                                            >
                                                {{ ($distribution['filters']['regency_code'] ?? '') === $reg['code'] && empty($distribution['filters']['district_code']) ? 'Terpilih' : 'Filter' }}
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Districts and Schools List -->
                                    <div x-show="openReg" x-collapse class="border-t border-slate-100 p-2.5 space-y-2 bg-slate-50/40">
                                        @foreach($reg['districts'] as $dist)
                                            <div class="border-l-2 border-emerald-600 pl-2.5 py-0.5 {{ ($distribution['filters']['district_code'] ?? '') === $dist['code'] ? 'bg-emerald-50/60 rounded-r-lg' : '' }}">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="font-bold text-slate-700 text-[11px] uppercase tracking-wider {{ ($distribution['filters']['district_code'] ?? '') === $dist['code'] ? 'text-[#0c3c26]' : '' }}">
                                                        KEC. {{ $dist['name'] }}
                                                    </span>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-[10px] font-medium text-slate-400 font-tabular">
                                                            {{ $dist['schools_count'] }} sekolah
                                                        </span>
                                                        <a
                                                            href="{{ route('master.regions.index', array_filter(['status' => $distribution['filters']['status'], 'search' => $distribution['filters']['search'], 'province_code' => $prov['code'], 'regency_code' => $reg['code'], 'district_code' => $dist['code']])) }}"
                                                            class="px-1.5 py-0.2 rounded text-[9px] font-semibold transition-colors {{ ($distribution['filters']['district_code'] ?? '') === $dist['code'] ? 'bg-[#0c3c26] text-white' : 'bg-slate-200/80 text-slate-700 hover:bg-emerald-100 hover:text-emerald-900' }}"
                                                            title="Filter peta & direktori untuk kecamatan ini"
                                                        >
                                                            {{ ($distribution['filters']['district_code'] ?? '') === $dist['code'] ? 'Terpilih' : 'Filter' }}
                                                        </a>
                                                    </div>
                                                </div>
                                                <ul class="space-y-1">
                                                    @foreach($dist['schools'] as $schoolItem)
                                                        <li class="flex items-center justify-between text-xs py-1 px-1.5 rounded hover:bg-white transition-colors group">
                                                            <div class="flex items-center gap-1.5 truncate">
                                                                <span class="w-1.5 h-1.5 rounded-full {{ $schoolItem['license_status'] === 'active' ? 'bg-emerald-500' : ($schoolItem['license_status'] === 'pending' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                                                                <span class="font-medium text-slate-800 truncate" title="{{ $schoolItem['name'] }}">
                                                                    {{ $schoolItem['name'] }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                @if($schoolItem['has_coordinates'])
                                                                    <button
                                                                        type="button"
                                                                        @click="focusSchoolOnMap({{ $schoolItem['id'] }})"
                                                                        class="p-1 rounded text-slate-400 hover:text-emerald-700 hover:bg-emerald-50"
                                                                        title="Fokuskan pada Peta"
                                                                    >
                                                                        <i data-lucide="crosshair" class="w-3 h-3"></i>
                                                                    </button>
                                                                @endif
                                                                <a
                                                                    href="{{ route('master.schools.show', $schoolItem['id']) }}"
                                                                    class="p-1 rounded text-slate-400 hover:text-[#0c3c26] hover:bg-slate-100"
                                                                    title="Buka Detail Sekolah"
                                                                >
                                                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                                                </a>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
                        <p class="text-xs font-medium text-slate-600">Tidak ada wilayah mitra yang cocok.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- School Directory Table (Right 7 cols) -->
        <div class="lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="building-2" class="w-4 h-4 text-emerald-600"></i>
                        <span>Direktori Sekolah Mitra Terdaftar</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Menampilkan {{ count($distribution['schools_list']) }} sekolah mitra terfilter.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Sekolah / NPSN</th>
                            <th class="py-3 px-4">Wilayah Administratif</th>
                            <th class="py-3 px-4">Status Lisensi</th>
                            <th class="py-3 px-4">Trainer Kit</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($distribution['schools_list'] as $s)
                            <tr class="hover:bg-slate-50/80 transition-colors" id="school-row-{{ $s['id'] }}">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $s['name'] }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono font-tabular mt-0.5">
                                        NPSN: {{ $s['npsn'] ?? '—' }} &bull; No: {{ $s['license_number'] }}
                                    </div>
                                    @if(($s['coordinate_status'] ?? '') === 'verified')
                                        <div class="text-[10px] text-emerald-700 font-mono font-tabular mt-0.5 flex items-center gap-1">
                                            <i data-lucide="map-pin-check" class="w-3 h-3 text-emerald-600"></i>
                                            <span>{{ number_format($s['latitude'], 4) }}, {{ number_format($s['longitude'], 4) }}</span>
                                            <span class="text-[9px] px-1 py-0.2 rounded bg-emerald-100 text-emerald-800 font-sans font-medium">Terverifikasi</span>
                                        </div>
                                    @elseif(($s['coordinate_status'] ?? '') === 'unverified')
                                        <div class="text-[10px] text-amber-700 font-mono font-tabular mt-0.5 flex items-center gap-1">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-amber-600"></i>
                                            <span>{{ number_format($s['latitude'], 4) }}, {{ number_format($s['longitude'], 4) }}</span>
                                            <span class="text-[9px] px-1 py-0.2 rounded bg-amber-100 text-amber-800 font-sans font-medium" title="Koordinat dicatat namun belum diverifikasi oleh Superuser (dikecualikan dari pin peta)">Menunggu Verifikasi</span>
                                        </div>
                                    @else
                                        <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-500 border border-slate-200">
                                            Lokasi belum dicatat
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    <div class="text-slate-800 font-semibold">{{ $s['regency_name'] }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $s['province_name'] }} &bull; Kec. {{ $s['district_name'] }}</div>
                                </td>

                                <td class="py-3 px-4">
                                    @if($s['license_status'] === 'active')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Aktif</span>
                                        </span>
                                        <div class="text-[10px] text-slate-400 font-tabular mt-0.5">Sisa {{ $s['days_remaining'] }} hari</div>
                                    @elseif($s['license_status'] === 'pending')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>Menunggu</span>
                                        </span>
                                        <div class="text-[10px] text-slate-400 font-tabular mt-0.5">Mulai {{ $s['start_date'] }}</div>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            <span>Kedaluwarsa</span>
                                        </span>
                                        <div class="text-[10px] text-slate-400 font-tabular mt-0.5">Habis {{ $s['expiration_date'] }}</div>
                                    @endif
                                </td>

                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono font-tabular font-medium text-[11px]">
                                        {{ $s['trainer_kits_count'] }} Unit
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($s['has_coordinates'])
                                            <button
                                                type="button"
                                                @click="focusSchoolOnMap({{ $s['id'] }})"
                                                class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors inline-flex items-center gap-1"
                                                title="Fokuskan ke Peta"
                                            >
                                                <i data-lucide="map-pin" class="w-3 h-3 text-emerald-600"></i>
                                                <span class="hidden sm:inline">Peta</span>
                                            </button>
                                        @endif
                                        <a
                                            href="{{ route('master.schools.show', $s['id']) }}"
                                            class="px-2.5 py-1.5 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white font-semibold text-xs transition-colors inline-flex items-center gap-1"
                                        >
                                            <span>Detail</span>
                                            <i data-lucide="chevron-right" class="w-3 h-3"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i data-lucide="school" class="w-8 h-8 text-slate-300 mb-2"></i>
                                        <p class="text-sm font-medium text-slate-700">Tidak ada sekolah mitra yang cocok</p>
                                        <p class="text-xs text-slate-400 mt-0.5">Ubah filter status lisensi atau kata kunci pencarian.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function schoolDistributionApp(markersData) {
    return {
        map: null,
        markersGroup: null,
        markerMap: {},
        mapLoaded: false,
        markers: markersData,

        init() {
            this.$nextTick(() => {
                this.initMap();
            });
        },

        initMap() {
            if (typeof L === 'undefined') {
                console.warn('Leaflet library is not loaded');
                return;
            }

            const container = this.$refs.mapContainer;
            if (!container) return;

            // Center of Indonesia: [-2.5, 118.0], zoom: 5
            this.map = L.map(container, {
                zoomControl: true,
                scrollWheelZoom: true,
            }).setView([-2.5489, 118.0149], 5);

            // Official OpenStreetMap Tile Layer (100% Free, No API Key Required)
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(this.map);

            this.markersGroup = L.featureGroup().addTo(this.map);

            // Populate markers
            this.plotMarkers();

            this.mapLoaded = true;

            // Fit bounds if markers exist
            if (this.markers && this.markers.length > 0) {
                setTimeout(() => {
                    this.map.invalidateSize();
                    try {
                        this.map.fitBounds(this.markersGroup.getBounds().pad(0.15));
                    } catch (e) {
                        // ignore single marker bounds issue
                    }
                }, 300);
            }
        },

        plotMarkers() {
            this.markersGroup.clearLayers();
            this.markerMap = {};

            this.markers.forEach(item => {
                if (!item.lat || !item.lng) return;

                const pinColor = item.status === 'active' ? '#15803d' : (item.status === 'pending' ? '#d97706' : '#e11d48');

                const customIcon = L.divIcon({
                    className: 'custom-school-marker',
                    html: `
                        <div style="background-color: ${pinColor}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.25); border: 2px solid #ffffff; cursor: pointer;">
                            <svg style="width: 14px; height: 14px; color: #ffffff;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 7.2c0 7.3-8 11.8-8 11.8z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                    `,
                    iconSize: [28, 28],
                    iconAnchor: [14, 28],
                    popupAnchor: [0, -28]
                });

                const marker = L.marker([item.lat, item.lng], { icon: customIcon });

                // Construct popup safely using DOM methods to prevent any persistent XSS
                const popup = document.createElement('div');
                popup.style.fontFamily = 'Inter, sans-serif';
                popup.style.minWidth = '220px';
                popup.style.padding = '4px';

                const titleEl = document.createElement('div');
                titleEl.style.fontSize = '13px';
                titleEl.style.fontWeight = '700';
                titleEl.style.color = '#0f172a';
                titleEl.style.marginBottom = '2px';
                titleEl.textContent = item.name || '';
                popup.appendChild(titleEl);

                const metaEl = document.createElement('div');
                metaEl.style.fontSize = '11px';
                metaEl.style.color = '#64748b';
                metaEl.style.marginBottom = '6px';
                metaEl.textContent = `NPSN: ${item.npsn || '—'} • ${item.regency || ''}, ${item.province || ''}`;
                popup.appendChild(metaEl);

                const badgeRow = document.createElement('div');
                badgeRow.style.display = 'flex';
                badgeRow.style.alignItems = 'center';
                badgeRow.style.justifyContent = 'space-between';
                badgeRow.style.marginBottom = '8px';
                badgeRow.style.fontSize = '11px';

                const statusBadge = document.createElement('span');
                statusBadge.style.fontWeight = '600';
                statusBadge.style.padding = '2px 8px';
                statusBadge.style.borderRadius = '9999px';
                if (item.status === 'active') {
                    statusBadge.style.background = '#dcfce7';
                    statusBadge.style.color = '#166534';
                } else if (item.status === 'pending') {
                    statusBadge.style.background = '#fef3c7';
                    statusBadge.style.color = '#92400e';
                } else {
                    statusBadge.style.background = '#ffe4e6';
                    statusBadge.style.color = '#9f1239';
                }
                statusBadge.textContent = item.status_label || '';
                badgeRow.appendChild(statusBadge);

                const kitCount = document.createElement('span');
                kitCount.style.color = '#475569';
                kitCount.style.fontWeight = '500';
                kitCount.textContent = `${item.kits_count || 0} Unit Kit`;
                badgeRow.appendChild(kitCount);
                popup.appendChild(badgeRow);

                const licenseRow = document.createElement('div');
                licenseRow.style.fontSize = '11px';
                licenseRow.style.color = '#64748b';
                licenseRow.style.marginBottom = '8px';
                licenseRow.textContent = 'Lisensi: ';
                const strongLic = document.createElement('strong');
                strongLic.textContent = item.license_number || '—';
                licenseRow.appendChild(strongLic);
                popup.appendChild(licenseRow);

                const linkBtn = document.createElement('a');
                let targetUrl = '#';
                if (typeof item.detail_url === 'string' && (item.detail_url.startsWith('/') || item.detail_url.startsWith(window.location.origin))) {
                    targetUrl = item.detail_url;
                }
                linkBtn.setAttribute('href', targetUrl);
                linkBtn.style.display = 'block';
                linkBtn.style.textAlign = 'center';
                linkBtn.style.backgroundColor = '#0c3c26';
                linkBtn.style.color = '#ffffff';
                linkBtn.style.padding = '6px 12px';
                linkBtn.style.borderRadius = '8px';
                linkBtn.style.fontSize = '11px';
                linkBtn.style.fontWeight = '600';
                linkBtn.style.textDecoration = 'none';
                linkBtn.textContent = 'Buka Detail Stasiun & Sekolah →';
                popup.appendChild(linkBtn);

                marker.bindPopup(popup);
                this.markersGroup.addLayer(marker);
                this.markerMap[item.id] = marker;
            });
        },

        focusSchoolOnMap(schoolId) {
            const marker = this.markerMap[schoolId];
            if (marker && this.map) {
                this.map.setView(marker.getLatLng(), 14, { animate: true });
                marker.openPopup();
                const container = this.$refs.mapContainer;
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        },

        resetMapZoom() {
            if (this.map && this.markersGroup) {
                try {
                    this.map.fitBounds(this.markersGroup.getBounds().pad(0.15));
                } catch (e) {
                    this.map.setView([-2.5489, 118.0149], 5);
                }
            }
        }
    };
}
</script>
@endsection
