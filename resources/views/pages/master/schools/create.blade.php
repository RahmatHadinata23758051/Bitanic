@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <a href="{{ route('master.schools.index') }}" class="hover:text-slate-800 transition-colors">Master Sekolah</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Tambah Sekolah</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Pendaftaran Tenant Sekolah Baru
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Alur bertahap (3 langkah mudah): isi identitas, tentukan titik di peta, dan konfirmasi wilayah otomatis.
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

    <!-- Inlined constructor to ensure initSchoolFormWizard is globally defined before Alpine parses DOM -->
    <script>
    window.initSchoolFormWizard = function(config) {
        return {
            currentStep: config.initialStep || 1,
            schoolName: config.schoolName || '',
            selectedProvince: config.selectedProvince || '',
            selectedRegency: config.selectedRegency || '',
            selectedDistrict: config.selectedDistrict || '',
            address: config.address || '',
            latitude: config.latitude || '',
            longitude: config.longitude || '',
            regencies: config.regencies || [],
            districts: config.districts || [],
            searchQuery: '',
            step1Error: '',
            loadingRegencies: false,
            loadingDistricts: false,
            loadingGeocode: false,
            searchingLocation: false,
            detectedRegionText: '',
            map: null,
            marker: null,

            init() {
                if (this.selectedProvince && this.regencies.length === 0) {
                    this.fetchRegencies(this.selectedProvince, this.selectedRegency);
                }
            },

            setStep(step) {
                if (step === 2 || step === 3) {
                    if (!this.schoolName || this.schoolName.trim() === '') {
                        this.step1Error = 'Nama Sekolah wajib diisi terlebih dahulu sebelum melanjutkan.';
                        this.currentStep = 1;
                        return;
                    }
                }
                this.step1Error = '';
                this.currentStep = step;

                if (step === 2) {
                    this.$nextTick(() => {
                        this.initMapIfNeeded();
                    });
                }
            },

            goToStep2() {
                if (!this.schoolName || this.schoolName.trim() === '') {
                    this.step1Error = 'Nama Sekolah wajib diisi terlebih dahulu sebelum melanjutkan ke peta.';
                    return;
                }
                this.step1Error = '';
                this.setStep(2);
            },

            goToStep3() {
                this.setStep(3);
            },

            initMapIfNeeded() {
                if (this.map) {
                    setTimeout(() => {
                        this.map.invalidateSize();
                        if (this.latitude && this.longitude) {
                            this.map.setView([parseFloat(this.latitude), parseFloat(this.longitude)], 15);
                        }
                    }, 200);
                    return;
                }

                if (typeof window.L === 'undefined') {
                    setTimeout(() => this.initMapIfNeeded(), 150);
                    return;
                }

                const mapEl = document.getElementById('school-wizard-map-canvas');
                if (!mapEl) return;

                let defaultLat = -6.9175;
                let defaultLng = 107.6191;
                let defaultZoom = 8;

                const hasCoords = this.latitude && this.longitude && !isNaN(parseFloat(this.latitude)) && !isNaN(parseFloat(this.longitude));
                if (hasCoords) {
                    defaultLat = parseFloat(this.latitude);
                    defaultLng = parseFloat(this.longitude);
                    defaultZoom = 15;
                }

                this.map = L.map('school-wizard-map-canvas', {
                    zoomControl: true,
                    scrollWheelZoom: true,
                    smoothWheelZoom: true,
                    doubleClickZoom: true,
                    touchZoom: true,
                    dragging: true,
                }).setView([defaultLat, defaultLng], defaultZoom);

                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
                }).addTo(this.map);

                L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(this.map);

                if (hasCoords) {
                    this.placeMarker(defaultLat, defaultLng, false);
                }

                // Klik titik pada peta -> otomatis tempatkan pin & jalankan reverse geocode untuk mengisi Step 3
                this.map.on('click', (e) => {
                    const lat = Number(e.latlng.lat).toFixed(7);
                    const lng = Number(e.latlng.lng).toFixed(7);
                    this.latitude = lat;
                    this.longitude = lng;
                    this.placeMarker(lat, lng, true);
                    this.detectRegionAndFill(lat, lng);
                });

                setTimeout(() => {
                    this.map.invalidateSize();
                    if (hasCoords) {
                        this.map.setView([defaultLat, defaultLng], 15);
                    }
                }, 300);
            },

            placeMarker(lat, lng, panTo = true) {
                const customIcon = L.divIcon({
                    className: 'custom-wizard-picker-pin',
                    html: `
                        <div style="background-color: #dc2626; width: 34px; height: 34px; border-radius: 9999px 9999px 0 9999px; transform: rotate(-45deg); border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35); display: flex; align-items: center; justify-content: center; cursor: grab;">
                            <div style="width: 10px; height: 10px; border-radius: 9999px; background-color: white;"></div>
                        </div>
                    `,
                    iconSize: [34, 34],
                    iconAnchor: [17, 34],
                    popupAnchor: [0, -34]
                });

                if (this.marker) {
                    this.marker.setLatLng([lat, lng]);
                } else {
                    this.marker = L.marker([lat, lng], {
                        icon: customIcon,
                        draggable: true,
                    }).addTo(this.map);

                    this.marker.on('dragend', (e) => {
                        const pos = e.target.getLatLng();
                        this.latitude = Number(pos.lat).toFixed(7);
                        this.longitude = Number(pos.lng).toFixed(7);
                        this.placeMarker(this.latitude, this.longitude, false);
                        this.detectRegionAndFill(this.latitude, this.longitude);
                    });
                }

                this.marker.bindPopup(`
                    <div style="font-family: Inter, sans-serif; font-size: 11px; text-align: center; padding: 2px;">
                        <strong style="color: #0c3c26;">Titik Lokasi Sekolah</strong><br/>
                        <span style="font-family: monospace; color: #64748b;">${lat}, ${lng}</span>
                    </div>
                `).openPopup();

                if (panTo && this.map) {
                    this.map.flyTo([lat, lng], Math.max(this.map.getZoom(), 15), { duration: 0.8 });
                }
            },

            async searchMapLocation(query) {
                if (!query || query.trim().length < 2) return;
                this.searchingLocation = true;
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=id&limit=1`);
                    const json = await res.json();
                    if (json && json.length > 0) {
                        const place = json[0];
                        const lat = Number(place.lat).toFixed(7);
                        const lng = Number(place.lon).toFixed(7);
                        this.latitude = lat;
                        this.longitude = lng;
                        this.placeMarker(lat, lng, true);
                        this.detectRegionAndFill(lat, lng);
                    } else {
                        alert('Lokasi tidak ditemukan. Coba ketik nama kota/kecamatan/jalan yang lebih spesifik.');
                    }
                } catch (e) {
                    console.error('Map search error:', e);
                } finally {
                    this.searchingLocation = false;
                }
            },

            async detectRegionAndFill(lat, lng) {
                this.loadingGeocode = true;
                this.detectedRegionText = '';

                try {
                    const res = await fetch(`/api/regions/reverse-geocode?lat=${lat}&lng=${lng}`);
                    const json = await res.json();

                    if (json.success && json.data) {
                        const data = json.data;

                        // 1. Auto-fill Province
                        if (data.province_code) {
                            this.selectedProvince = data.province_code;
                            if (data.regencies && data.regencies.length > 0) {
                                this.regencies = data.regencies;
                            }
                        }

                        // 2. Auto-fill Regency
                        if (data.regency_code) {
                            this.selectedRegency = data.regency_code;
                            if (data.districts && data.districts.length > 0) {
                                this.districts = data.districts;
                            }
                        }

                        // 3. Auto-fill District
                        if (data.district_code) {
                            this.selectedDistrict = data.district_code;
                        }

                        // 4. Auto-fill Address if empty or update
                        if (data.address && (!this.address || this.address.trim() === '')) {
                            this.address = data.address;
                        }

                        // 5. Update Status Banner
                        const parts = [];
                        if (data.province_name) parts.push(data.province_name);
                        if (data.regency_name) parts.push(data.regency_name);
                        if (data.district_name) parts.push('KEC. ' + data.district_name);
                        this.detectedRegionText = parts.join(' → ');
                    }
                } catch (err) {
                    console.error('Reverse geocoding error:', err);
                } finally {
                    this.loadingGeocode = false;
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
                            this.fetchDistricts(preselectedReg, this.selectedDistrict);
                        } else {
                            this.selectedRegency = '';
                            this.districts = [];
                            this.selectedDistrict = '';
                        }
                    }
                } catch (e) {
                    console.error(e);
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
                    console.error(e);
                } finally {
                    this.loadingDistricts = false;
                }
            },

            resetMapLocation() {
                this.latitude = '';
                this.longitude = '';
                this.detectedRegionText = '';
                if (this.marker && this.map) {
                    this.map.removeLayer(this.marker);
                    this.marker = null;
                }
            }
        };
    };
    </script>

    <!-- Main Stepper Container with Alpine.js -->
    @php
        $initialStep = 1;
        if ($errors->has('province_code') || $errors->has('regency_code') || $errors->has('district_code') || $errors->has('address')) {
            $initialStep = 3;
        } elseif ($errors->has('name') || $errors->has('status') || $errors->has('npsn') || $errors->has('slug')) {
            $initialStep = 1;
        }
    @endphp

    <div
        x-data="initSchoolFormWizard({
            initialStep: {{ $initialStep }},
            schoolName: '{{ addslashes(old('name', '')) }}',
            selectedProvince: '{{ old('province_code', '') }}',
            selectedRegency: '{{ old('regency_code', '') }}',
            selectedDistrict: '{{ old('district_code', '') }}',
            address: '{{ addslashes(old('address', '')) }}',
            latitude: '{{ old('latitude', '') }}',
            longitude: '{{ old('longitude', '') }}',
            regencies: [],
            districts: []
        })"
        class="space-y-6"
    >
        <!-- Step Progress Bar -->
        <nav aria-label="Tahapan Formulir" class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <!-- Step 1 -->
                <button
                    type="button"
                    @click="setStep(1)"
                    class="flex items-center gap-3 text-left focus:outline-hidden group"
                >
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs transition-all"
                        :class="currentStep === 1
                            ? 'bg-[#0c3c26] text-white shadow-xs'
                            : (currentStep > 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500')"
                    >
                        <template x-if="currentStep > 1">
                            <i data-lucide="check" class="w-4.5 h-4.5"></i>
                        </template>
                        <template x-if="currentStep <= 1">
                            <span>1</span>
                        </template>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-xs font-bold" :class="currentStep === 1 ? 'text-slate-900' : 'text-slate-600'">Identitas Sekolah</div>
                        <div class="text-[10px] text-slate-400">Nama &amp; status operasional</div>
                    </div>
                </button>

                <!-- Divider 1 -->
                <div class="grow mx-3 sm:mx-6 h-0.5 transition-colors" :class="currentStep >= 2 ? 'bg-emerald-600' : 'bg-slate-200'"></div>

                <!-- Step 2 -->
                <button
                    type="button"
                    @click="setStep(2)"
                    class="flex items-center gap-3 text-left focus:outline-hidden group"
                >
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs transition-all"
                        :class="currentStep === 2
                            ? 'bg-[#0c3c26] text-white shadow-xs'
                            : (currentStep > 2 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500')"
                    >
                        <template x-if="currentStep > 2">
                            <i data-lucide="check" class="w-4.5 h-4.5"></i>
                        </template>
                        <template x-if="currentStep <= 2">
                            <span>2</span>
                        </template>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-xs font-bold" :class="currentStep === 2 ? 'text-slate-900' : 'text-slate-600'">Titik Lokasi Peta</div>
                        <div class="text-[10px] text-slate-400">Klik titik untuk auto-fill</div>
                    </div>
                </button>

                <!-- Divider 2 -->
                <div class="grow mx-3 sm:mx-6 h-0.5 transition-colors" :class="currentStep >= 3 ? 'bg-emerald-600' : 'bg-slate-200'"></div>

                <!-- Step 3 -->
                <button
                    type="button"
                    @click="setStep(3)"
                    class="flex items-center gap-3 text-left focus:outline-hidden group"
                >
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs transition-all"
                        :class="currentStep === 3
                            ? 'bg-[#0c3c26] text-white shadow-xs'
                            : 'bg-slate-100 text-slate-500'"
                    >
                        <span>3</span>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-xs font-bold" :class="currentStep === 3 ? 'text-slate-900' : 'text-slate-600'">Konfirmasi &amp; Simpan</div>
                        <div class="text-[10px] text-slate-400">Wilayah resmi &amp; alamat</div>
                    </div>
                </button>
            </div>
        </nav>

        <!-- Form Card -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs">
            <form action="{{ route('master.schools.store') }}" method="POST" class="space-y-6">
                @csrf

                {{-- Hidden Inputs to preserve coordinates across steps --}}
                <input type="hidden" name="latitude" :value="latitude">
                <input type="hidden" name="longitude" :value="longitude">

                {{-- ======================================================== --}}
                {{-- STEP 1: IDENTITAS SEKOLAH --}}
                {{-- ======================================================== --}}
                <div x-show="currentStep === 1" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="building-2" class="w-4 h-4 text-[#0c3c26]"></i>
                            <span>Langkah 1: Identitas Institusi Sekolah</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Masukkan informasi dasar sekolah vokasi mitra yang akan didaftarkan ke sistem Bitanic.
                        </p>
                    </div>

                    <div x-show="step1Error" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span x-text="step1Error"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nama Sekolah <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                x-model="schoolName"
                                required
                                placeholder="Contoh: SMK Negeri 1 Cibadak"
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
                                value="{{ old('npsn') }}"
                                placeholder="Contoh: 20202020"
                                class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
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
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Nonaktif (Arsip)</option>
                            </select>
                        </div>

                        <div>
                            <label for="phone" class="block text-xs font-semibold text-slate-700 mb-1">
                                Nomor Telepon Sekolah
                            </label>
                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="{{ old('phone') }}"
                                placeholder="Contoh: 0266-531234"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                        </div>

                        <div>
                            <label for="slug" class="block text-xs font-semibold text-slate-700 mb-1">
                                Slug Tenant URL <span class="text-slate-400 font-normal">(Opsional - otomatis dibuat)</span>
                            </label>
                            <input
                                type="text"
                                id="slug"
                                name="slug"
                                value="{{ old('slug') }}"
                                placeholder="Contoh: smkn-1-cibadak"
                                class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                        </div>
                    </div>

                    <!-- Step 1 Actions -->
                    <div class="border-t border-slate-100 pt-5 flex items-center justify-between">
                        <a
                            href="{{ route('master.schools.index') }}"
                            class="px-4 py-2 text-xs font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </a>
                        <button
                            type="button"
                            @click="goToStep2()"
                            class="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-all"
                        >
                            <span>Lanjut: Tentukan Lokasi di Peta</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                {{-- ======================================================== --}}
                {{-- STEP 2: TITIK LOKASI PETA & QUICK SEARCH --}}
                {{-- ======================================================== --}}
                <div x-show="currentStep === 2" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4">
                    <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-4 h-4 text-emerald-700"></i>
                                <span>Langkah 2: Tentukan Titik Lokasi pada Peta</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Cari daerah sekolah atau klik langsung pada peta. Wilayah administratif akan otomatis terdeteksi untuk langkah 3.
                            </p>
                        </div>

                        <button
                            type="button"
                            x-show="latitude && longitude"
                            @click="resetMapLocation()"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium transition-colors self-start sm:self-auto"
                            title="Reset pin peta"
                        >
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            <span>Reset Pin</span>
                        </button>
                    </div>

                    {{-- Quick Search on Map --}}
                    <div class="flex items-center gap-2">
                        <div class="relative grow">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input
                                type="text"
                                x-model="searchQuery"
                                @keydown.enter.prevent="searchMapLocation(searchQuery)"
                                placeholder="Ketik nama kota, kecamatan, atau jalan (contoh: Cibadak Sukabumi)..."
                                class="w-full text-xs pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                        </div>
                        <button
                            type="button"
                            @click="searchMapLocation(searchQuery)"
                            :disabled="searchingLocation || !searchQuery"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold transition-colors disabled:opacity-50 shrink-0 inline-flex items-center gap-1.5"
                        >
                            <i data-lucide="crosshair" class="w-3.5 h-3.5" :class="{ 'animate-spin': searchingLocation }"></i>
                            <span x-text="searchingLocation ? 'Mencari...' : 'Cari di Peta'"></span>
                        </button>
                    </div>

                    {{-- Geocode Loading Indicator --}}
                    <div x-show="loadingGeocode" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2.5 animate-pulse">
                        <i data-lucide="loader" class="w-4 h-4 animate-spin text-amber-600 shrink-0"></i>
                        <span>Mendeteksi wilayah administratif (Provinsi, Kab/Kota, Kecamatan) dari koordinat titik terpilih...</span>
                    </div>

                    {{-- Detected Region Banner with Quick Advance Button --}}
                    <div x-show="detectedRegionText && !loadingGeocode" class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                                <i data-lucide="check" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-[10px] text-emerald-700 font-semibold uppercase tracking-wider">Wilayah Terdeteksi Otomatis</div>
                                <div class="text-xs font-bold text-slate-900" x-text="detectedRegionText"></div>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="goToStep3()"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0c3c26] text-white hover:bg-[#092d1c] font-semibold text-xs shadow-2xs transition-colors shrink-0"
                        >
                            <span>Lanjut ke Konfirmasi Wilayah</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </button>
                    </div>

                    {{-- Map Canvas Container --}}
                    <div class="relative w-full rounded-2xl overflow-hidden border border-slate-200/90 shadow-2xs">
                        <div id="school-wizard-map-canvas" class="h-96 sm:h-[420px] w-full z-0 bg-slate-100"></div>
                        <div class="absolute bottom-3 left-3 z-10 px-3 py-1.5 rounded-lg bg-white/95 backdrop-blur-xs text-[11px] text-slate-700 border border-slate-200 shadow-sm pointer-events-none flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            <span class="font-medium text-slate-900">Petunjuk:</span> Klik di mana saja pada peta atau geser pin merah untuk menandai lokasi sekolah.
                        </div>
                    </div>

                    <!-- Step 2 Actions -->
                    <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
                        <button
                            type="button"
                            @click="setStep(1)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>Kembali ke Identitas</span>
                        </button>

                        <button
                            type="button"
                            @click="goToStep3()"
                            class="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-all"
                        >
                            <span>Lanjut: Konfirmasi Wilayah</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                {{-- ======================================================== --}}
                {{-- STEP 3: KONFIRMASI WILAYAH ADMINISTRATIF & ALAMAT --}}
                {{-- ======================================================== --}}
                <div x-show="currentStep === 3" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                    <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="globe-2" class="w-4 h-4 text-[#0c3c26]"></i>
                                <span>Langkah 3: Konfirmasi Wilayah Administratif &amp; Alamat</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Bidang di bawah telah terisi otomatis dari titik peta yang Anda klik. Anda dapat memeriksa dan menyempurnakan detail alamat.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="setStep(2)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold border border-emerald-200 transition-colors self-start sm:self-auto"
                        >
                            <i data-lucide="map" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Ubah Titik Peta</span>
                        </button>
                    </div>

                    {{-- Summary Card of Selected Location --}}
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="space-y-1">
                            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Sekolah Yang Didaftarkan</div>
                            <div class="font-bold text-slate-900 text-sm" x-text="schoolName || '—'"></div>
                            <div class="text-slate-500 flex items-center gap-2 text-[11px]">
                                <span class="inline-flex items-center gap-1 text-emerald-700 font-medium">
                                    <i data-lucide="map-pin" class="w-3 h-3"></i>
                                    <span x-text="latitude && longitude ? `${latitude}, ${longitude}` : 'Belum memilih koordinat peta'"></span>
                                </span>
                            </div>
                        </div>
                        <template x-if="latitude && longitude">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-semibold self-start sm:self-auto">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                <span>Koordinat Terhubung</span>
                            </span>
                        </template>
                    </div>

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
                                    <option value="{{ $prov->code }}">{{ $prov->code }} - {{ $prov->name }}</option>
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
                                Alamat Lengkap (Jalan, RT/RW, Dusun, Kelurahan/Desa)
                            </label>
                            <textarea
                                id="address"
                                name="address"
                                rows="3"
                                x-model="address"
                                placeholder="Contoh: Jl. Raya Sukabumi No. 12, RT 01/RW 04, Desa Nagrak"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            ></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">Detail alamat jalan yang akan dicetak pada surat/dokumen resmi sekolah.</p>
                        </div>
                    </div>

                    <!-- Step 3 Actions -->
                    <div class="border-t border-slate-100 pt-5 flex items-center justify-between">
                        <button
                            type="button"
                            @click="setStep(2)"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>Kembali ke Peta</span>
                        </button>

                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-bold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-all"
                        >
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span>Simpan &amp; Daftarkan Sekolah</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
