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
                Tambahkan profil institusi pendidikan vokasi dan asosiasi wilayah administratif resmi.
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
            selectedProvince: '{{ old('province_code', '') }}',
            selectedRegency: '{{ old('regency_code', '') }}',
            selectedDistrict: '{{ old('district_code', '') }}',
            regencies: [],
            districts: [],
            loadingRegencies: false,
            loadingDistricts: false,

            init() {
                if (this.selectedProvince) {
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
                            this.fetchDistricts(preselectedReg, this.selectedDistrict);
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
        <form action="{{ route('master.schools.store') }}" method="POST" class="space-y-6">
            @csrf

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
                            value="{{ old('name') }}"
                            required
                            placeholder="Contoh: SMK Negeri 1 Cibadak"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label for="npsn" class="block text-xs font-semibold text-slate-700 mb-1">
                            NPSN <span class="text-slate-400 font-normal">(Nomor Pokok Sekolah Nasional - Opsional)</span>
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
                        <label for="slug" class="block text-xs font-semibold text-slate-700 mb-1">
                            Slug Tenant URL <span class="text-slate-400 font-normal">(Opsional - dibuat otomatis jika kosong)</span>
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

                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Telepon
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
                                <option :value="r.code" x-text="`${r.code} - ${r.name}`"></option>
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
                                <option :value="d.code" x-text="`${d.code} - ${d.name}`"></option>
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
                            rows="2"
                            placeholder="Contoh: Jl. Raya Sukabumi No. 12, RT 01/RW 04, Desa Nagrak"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >{{ old('address') }}</textarea>
                    </div>

                    <div
                        x-data="schoolLocationPicker({
                            initialLat: '{{ old('latitude', '') }}',
                            initialLng: '{{ old('longitude', '') }}'
                        })"
                        class="sm:col-span-3 space-y-3 pt-2"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-700"></i>
                                    <span>Penentuan Titik Lokasi Peta (GIS Pinpoint Picker)</span>
                                </label>
                                <p class="text-[11px] text-slate-500">
                                    Klik titik lokasi sekolah pada peta atau seret pin merah untuk mengisi koordinat secara otomatis.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="getCurrentLocation()"
                                    :disabled="gettingLocation"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-semibold transition-colors disabled:opacity-50"
                                    title="Gunakan sensor GPS perangkat saat ini"
                                >
                                    <i data-lucide="crosshair" class="w-3.5 h-3.5" :class="{ 'animate-spin': gettingLocation }"></i>
                                    <span x-text="gettingLocation ? 'Mencari GPS...' : 'Lokasi Saat Ini'"></span>
                                </button>
                                <button
                                    type="button"
                                    x-show="latitude && longitude"
                                    @click="clearLocation()"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium transition-colors"
                                    title="Hapus pin dan kosongkan koordinat"
                                >
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                    <span>Reset Pin</span>
                                </button>
                            </div>
                        </div>

                        {{-- Leaflet Interactive Picker Map --}}
                        <div class="relative w-full rounded-xl overflow-hidden border border-slate-200 shadow-2xs">
                            <div id="school-location-picker-map" class="h-64 sm:h-72 w-full z-0 bg-slate-100"></div>
                            <div class="absolute bottom-2 left-2 z-10 px-2.5 py-1 rounded-md bg-white/95 backdrop-blur-xs text-[10px] text-slate-600 border border-slate-200/80 shadow-2xs pointer-events-none">
                                <span class="font-semibold text-emerald-800">Petunjuk:</span> Klik pada peta atau geser pin merah untuk menentukan koordinat presisi.
                            </div>
                        </div>

                        {{-- Manual Inputs (Two-Way Synced with Map) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="latitude" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Garis Lintang (Latitude) <span class="text-slate-400 font-normal">(Opsional GIS)</span>
                                </label>
                                <input
                                    type="number"
                                    step="any"
                                    id="latitude"
                                    name="latitude"
                                    x-model="latitude"
                                    @input="onManualCoordinateInput()"
                                    placeholder="-6.2088000"
                                    class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                                >
                                <p class="text-[11px] text-slate-400 mt-1">Koordinat desimal lintang (terisi otomatis dari klik peta).</p>
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
                                    x-model="longitude"
                                    @input="onManualCoordinateInput()"
                                    placeholder="106.8456000"
                                    class="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                                >
                                <p class="text-[11px] text-slate-400 mt-1">Koordinat desimal bujur (terisi otomatis dari klik peta).</p>
                            </div>
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
                    Simpan Sekolah
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function schoolLocationPicker(config) {
    return {
        latitude: config.initialLat || '',
        longitude: config.initialLng || '',
        map: null,
        marker: null,
        gettingLocation: false,

        init() {
            this.$nextTick(() => {
                this.initMap();
            });
        },

        initMap() {
            if (typeof window.L === 'undefined') {
                console.warn('Leaflet GIS library is not loaded');
                return;
            }

            const mapEl = document.getElementById('school-location-picker-map');
            if (!mapEl) return;

            let defaultLat = -6.9175;
            let defaultLng = 107.6191;
            let defaultZoom = 9;

            const hasInitial = this.latitude && this.longitude && !isNaN(parseFloat(this.latitude)) && !isNaN(parseFloat(this.longitude));
            if (hasInitial) {
                defaultLat = parseFloat(this.latitude);
                defaultLng = parseFloat(this.longitude);
                defaultZoom = 15;
            }

            this.map = L.map(mapEl, {
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

            if (hasInitial) {
                this.placeMarker(defaultLat, defaultLng, false);
            }

            this.map.on('click', (e) => {
                this.updateCoordinates(e.latlng.lat, e.latlng.lng, true);
            });

            setTimeout(() => {
                this.map.invalidateSize();
                if (hasInitial) {
                    this.map.setView([defaultLat, defaultLng], 15);
                }
            }, 300);
        },

        placeMarker(lat, lng, panTo = true) {
            const customIcon = L.divIcon({
                className: 'custom-school-picker-pin',
                html: `
                    <div style="background-color: #dc2626; width: 30px; height: 30px; border-radius: 9999px 9999px 0 9999px; transform: rotate(-45deg); border: 2.5px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; cursor: grab;">
                        <div style="width: 10px; height: 10px; border-radius: 9999px; background-color: white;"></div>
                    </div>
                `,
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
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
                    this.updateCoordinates(pos.lat, pos.lng, false);
                });
            }

            this.marker.bindPopup(`
                <div style="font-family: Inter, sans-serif; font-size: 11px; text-align: center; padding: 2px;">
                    <strong style="color: #0c3c26;">Titik Lokasi Sekolah</strong><br/>
                    <span style="font-family: monospace; color: #64748b;">${Number(lat).toFixed(7)}, ${Number(lng).toFixed(7)}</span>
                </div>
            `).openPopup();

            if (panTo && this.map) {
                this.map.flyTo([lat, lng], Math.max(this.map.getZoom(), 15), { duration: 0.8 });
            }
        },

        updateCoordinates(lat, lng, panTo = true) {
            this.latitude = Number(lat).toFixed(7);
            this.longitude = Number(lng).toFixed(7);
            this.placeMarker(lat, lng, panTo);
        },

        onManualCoordinateInput() {
            const lat = parseFloat(this.latitude);
            const lng = parseFloat(this.longitude);
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                this.placeMarker(lat, lng, true);
            }
        },

        getCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Browser Anda tidak mendukung fitur geolokasi GPS.');
                return;
            }

            this.gettingLocation = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.gettingLocation = false;
                    this.updateCoordinates(pos.coords.latitude, pos.coords.longitude, true);
                },
                (err) => {
                    this.gettingLocation = false;
                    alert('Gagal mengambil titik GPS: ' + (err.message || 'Izin lokasi ditolak.'));
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },

        clearLocation() {
            this.latitude = '';
            this.longitude = '';
            if (this.marker && this.map) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }
        }
    };
}
</script>
@endpush

