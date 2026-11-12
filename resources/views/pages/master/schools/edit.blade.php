@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <a href="{{ route('master.schools.index') }}" class="hover:text-slate-800 transition-colors">Master Sekolah</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Edit Profil Sekolah</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Edit Profil Sekolah: {{ $school->name }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Perbarui metadata institusi dan asosiasi kode wilayah administratif resmi.
            </p>
        </div>

        <a
            href="{{ route('master.schools.index') }}"
            class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors"
        >
            Kembali
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Terdapat kesalahan pada isian form:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Container with Alpine.js Cascading Selector -->
    <div
        x-data="{
            selectedProvince: '{{ old('province_code', $school->province_code) }}',
            selectedRegency: '{{ old('regency_code', $school->regency_code) }}',
            selectedDistrict: '{{ old('district_code', $school->district_code) }}',
            regencies: @js($regencies),
            districts: @js($districts),
            loadingRegencies: false,
            loadingDistricts: false,

            init() {
                if (this.selectedProvince && (!this.regencies || this.regencies.length === 0)) {
                    this.fetchRegencies(this.selectedProvince, this.selectedRegency);
                }
            },

            async fetchRegencies(provCode, preselectedReg = '') {
                if (!provCode) {
                    this.regencies = [];
                    this.districts = [];
                    this.selectedRegency = '';
                    this.selectedDistrict = '';
                    return;
                }
                this.loadingRegencies = true;
                try {
                    const res = await fetch(`/api/regions/regencies?province_code=${provCode}`);
                    const json = await res.json();
                    if (json.success) {
                        this.regencies = json.data;
                        if (preselectedReg) {
                            this.selectedRegency = preselectedReg;
                        } else {
                            this.selectedRegency = '';
                            this.districts = [];
                            this.selectedDistrict = '';
                        }
                    }
                } catch (e) {
                    console.error('Failed to load regencies', e);
                } finally {
                    this.loadingRegencies = false;
                }
            },

            async fetchDistricts(regCode, preselectedDist = '') {
                if (!regCode) {
                    this.districts = [];
                    this.selectedDistrict = '';
                    return;
                }
                this.loadingDistricts = true;
                try {
                    const res = await fetch(`/api/regions/districts?regency_code=${regCode}`);
                    const json = await res.json();
                    if (json.success) {
                        this.districts = json.data;
                        if (preselectedDist) {
                            this.selectedDistrict = preselectedDist;
                        } else {
                            this.selectedDistrict = '';
                        }
                    }
                } catch (e) {
                    console.error('Failed to load districts', e);
                } finally {
                    this.loadingDistricts = false;
                }
            }
        }"
        class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs"
    >
        <form action="{{ route('master.schools.update', $school) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Section 1: Profil Sekolah -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <i data-lucide="building-2" class="w-4 h-4 text-[#0c3c26]"></i>
                    <span>Informasi Institusi</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">
                            Nama Sekolah <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $school->name) }}"
                            required
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label for="npsn" class="block text-xs font-semibold text-slate-700 mb-1">
                            NPSN <span class="text-slate-400 font-normal">(Nomor Pokok Sekolah Nasional)</span>
                        </label>
                        <input
                            type="text"
                            id="npsn"
                            name="npsn"
                            value="{{ old('npsn', $school->npsn) }}"
                            placeholder="Contoh: 20202020"
                            class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label for="slug" class="block text-xs font-semibold text-slate-700 mb-1">
                            Slug Tenant URL <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="slug"
                            name="slug"
                            value="{{ old('slug', $school->slug) }}"
                            required
                            class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Telepon
                        </label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $school->phone) }}"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-700 mb-1">
                            Status Operasional <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="status"
                            name="status"
                            required
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                            <option value="active" {{ old('status', $school->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="inactive" {{ old('status', $school->status) === 'inactive' ? 'selected' : '' }}>Nonaktif (Arsip)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Wilayah Administratif Resmi (Cascading) -->
            <div class="border-t border-slate-100 pt-5">
                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-4 h-4 text-[#0c3c26]"></i>
                    <span>Wilayah Administratif Resmi (Kemendagri / BPS)</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="province_code" class="block text-xs font-semibold text-slate-700 mb-1">
                            Provinsi <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="province_code"
                            name="province_code"
                            x-model="selectedProvince"
                            @change="fetchRegencies(selectedProvince)"
                            required
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                            <option value="">Pilih Provinsi...</option>
                            @foreach($provinces as $prov)
                                <option value="{{ $prov->code }}" {{ $school->province_code === $prov->code ? 'selected' : '' }}>
                                    {{ $prov->code }} - {{ $prov->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="regency_code" class="block text-xs font-semibold text-slate-700 mb-1">
                            Kabupaten / Kota <span class="text-rose-500">*</span>
                            <span x-show="loadingRegencies" class="text-slate-400 italic text-[11px]">(Memuat...)</span>
                        </label>
                        <select
                            id="regency_code"
                            name="regency_code"
                            x-model="selectedRegency"
                            @change="fetchDistricts(selectedRegency)"
                            :disabled="!selectedProvince || loadingRegencies"
                            required
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26] disabled:opacity-50"
                        >
                            <option value="">Pilih Kab/Kota...</option>
                            <template x-for="r in regencies" :key="r.code">
                                <option :value="r.code" x-text="`${r.code} - ${r.name}`" :selected="r.code === selectedRegency"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label for="district_code" class="block text-xs font-semibold text-slate-700 mb-1">
                            Kecamatan <span class="text-slate-400 font-normal">(Opsional)</span>
                            <span x-show="loadingDistricts" class="text-slate-400 italic text-[11px]">(Memuat...)</span>
                        </label>
                        <select
                            id="district_code"
                            name="district_code"
                            x-model="selectedDistrict"
                            :disabled="!selectedRegency || loadingDistricts"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26] disabled:opacity-50"
                        >
                            <option value="">Pilih Kecamatan...</option>
                            <template x-for="d in districts" :key="d.code">
                                <option :value="d.code" x-text="`${d.code} - ${d.name}`" :selected="d.code === selectedDistrict"></option>
                            </template>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="address" class="block text-xs font-semibold text-slate-700 mb-1">
                            Alamat Lengkap
                        </label>
                        <textarea
                            id="address"
                            name="address"
                            rows="2"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >{{ old('address', $school->address) }}</textarea>
                    </div>

                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                        <div>
                            <label for="latitude" class="block text-xs font-semibold text-slate-700 mb-1">
                                Garis Lintang (Latitude) <span class="text-slate-400 font-normal">(Opsional GIS)</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                id="latitude"
                                name="latitude"
                                value="{{ old('latitude', $school->latitude) }}"
                                placeholder="-6.2088000"
                                class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Koordinat desimal lintang (contoh: -6.917464).</p>
                        </div>
                        <div>
                            <label for="longitude" class="block text-xs font-semibold text-slate-700 mb-1">
                                Garis Bujur (Longitude) <span class="text-slate-400 font-normal">(Opsional GIS)</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                id="longitude"
                                name="longitude"
                                value="{{ old('longitude', $school->longitude) }}"
                                placeholder="106.8456000"
                                class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Koordinat desimal bujur (contoh: 107.619123).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="border-t border-slate-100 pt-5 flex items-center justify-end gap-3">
                <a
                    href="{{ route('master.schools.index') }}"
                    class="px-4 py-2 text-xs font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-colors"
                >
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

