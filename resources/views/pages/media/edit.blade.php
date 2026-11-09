@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb & Navigation -->
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('media.index') }}" class="hover:text-slate-800 font-medium">Media Tanam</a>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
        <span class="text-slate-800 font-semibold">Edit Plot</span>
    </div>

    <!-- Header (Unboxed, Bitanic Standard) -->
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
            Edit Plot Media Tanam
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Perbarui informasi plot dan stasiun sensor yang terhubung untuk evaluasi agronomi terkini.
        </p>
    </div>

    <!-- Form Surface -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-2xs p-5 sm:p-6">
        <form action="{{ route('media.update', $media->id) }}" method="POST" class="space-y-5" x-data="{
            selectedPlants: {{ json_encode(old('plants', $media->plants ?? [])) }},
            customPlant: '',
            addCustomPlant() {
                const name = this.customPlant.trim();
                if (name && !this.selectedPlants.includes(name)) {
                    this.selectedPlants.push(name);
                    this.customPlant = '';
                }
            },
            removePlant(index) {
                this.selectedPlants.splice(index, 1);
            }
        }">
            @csrf
            @method('PUT')

            <!-- Plot Name -->
            <div class="space-y-1.5">
                <label for="name" class="block text-xs font-semibold text-slate-700">
                    Nama Plot / Media Tanam <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $media->name) }}"
                    required
                    placeholder="Contoh: Bedengan Hidroponik A1 Substrat Cocopeat"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] transition-colors @error('name') border-rose-300 bg-rose-50/50 @enderror"
                >
                @error('name')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Associated Workstation Device -->
            <div class="space-y-1.5">
                <label for="device_id" class="block text-xs font-semibold text-slate-700">
                    Stasiun Trainer Kit IoT (Opsional)
                </label>
                <select
                    name="device_id"
                    id="device_id"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] transition-colors @error('device_id') border-rose-300 bg-rose-50/50 @enderror"
                >
                    <option value="">-- Tanpa Workstation (Gunakan Estimasi Standar) --</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ (string) old('device_id', $media->device_id) === (string) $device->id ? 'selected' : '' }}>
                            {{ $device->code }} — {{ $device->name }} ({{ $device->bench_location ?? 'Lab Meja Praktik' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-500">
                    Jika stasiun dipilih, telemetri sensor Modbus terakhir dari workstation ini akan digunakan untuk evaluasi kondisi tanah.
                </p>
                @error('device_id')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Plants Selection -->
            <div class="space-y-3">
                <label class="block text-xs font-semibold text-slate-700">
                    Tanaman yang Dibudidayakan <span class="text-rose-500">*</span>
                </label>

                <!-- Preset plant buttons -->
                <div class="space-y-1.5">
                    <span class="text-[11px] text-slate-500">Pilihan Cepat Tanaman Vokasi:</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['Cabai Rawit', 'Tomat Cherry', 'Bawang Merah', 'Selada Keriting', 'Pakcoy Hidroponik', 'Melon Golden', 'Terong Ungu', 'Kangkung Darat'] as $preset)
                            <button
                                type="button"
                                @click="if(!selectedPlants.includes('{{ $preset }}')) selectedPlants.push('{{ $preset }}')"
                                :class="selectedPlants.includes('{{ $preset }}') ? 'bg-[#0c3c26] text-white border-[#0c3c26]' : 'bg-slate-50 text-slate-700 border-slate-200/80 hover:bg-slate-100'"
                                class="px-2.5 py-1 rounded-lg border text-[11px] font-medium transition-colors"
                            >
                                + {{ $preset }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Custom plant input -->
                <div class="flex gap-2">
                    <input
                        type="text"
                        x-model="customPlant"
                        @keydown.enter.prevent="addCustomPlant()"
                        placeholder="Ketik nama tanaman lalu tekan Tambah..."
                        class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] transition-colors"
                    >
                    <button
                        type="button"
                        @click="addCustomPlant()"
                        class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-medium transition-colors"
                    >
                        Tambah
                    </button>
                </div>

                <!-- Selected plants tags -->
                <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                    <span class="text-[11px] font-medium text-slate-500 block">Tanaman Terpilih:</span>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(plant, index) in selectedPlants" :key="index">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200/80 text-slate-800 text-xs font-medium">
                                <span x-text="plant"></span>
                                <input type="hidden" name="plants[]" :value="plant">
                                <button
                                    type="button"
                                    @click="removePlant(index)"
                                    class="text-slate-400 hover:text-slate-700 font-bold ml-1 text-xs"
                                >
                                    &times;
                                </button>
                            </span>
                        </template>
                        <template x-if="selectedPlants.length === 0">
                            <span class="text-xs text-slate-400 italic">Belum ada tanaman yang dipilih. Pilih dari daftar atau ketik tanaman baru.</span>
                        </template>
                    </div>
                </div>

                @error('plants')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
                @error('plants.*')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <a
                    href="{{ route('media.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 transition-colors shadow-2xs"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors flex items-center gap-2"
                >
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Perbarui Media Tanam</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
