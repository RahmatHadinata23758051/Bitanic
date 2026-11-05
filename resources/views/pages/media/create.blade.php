@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb & Navigation -->
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('media.index') }}" class="hover:text-emerald-700 font-medium">Media Tanam</a>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
        <span class="text-slate-800 font-bold">Tambah Plot Baru</span>
    </div>

    <!-- Header Card -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-1">
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight font-sans">
            Tambah Plot Media Tanam Baru
        </h1>
        <p class="text-xs text-slate-500">
            Daftarkan plot substrat media tanam dan hubungkan dengan stasiun Trainer Kit untuk evaluasi telemetri agronomi secara otomatis.
        </p>
    </div>

    <!-- Form Card -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <form action="{{ route('media.store') }}" method="POST" class="space-y-6" x-data="{
            selectedPlants: {{ json_encode(old('plants', ['Cabai Rawit'])) }},
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

            <!-- Plot Name -->
            <div class="space-y-1.5">
                <label for="name" class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                    Nama Plot / Media Tanam <span class="text-rose-600">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    required
                    placeholder="Contoh: Bedengan Hidroponik A1 Substrat Cocopeat"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-colors @error('name') border-rose-300 bg-rose-50/50 @enderror"
                >
                @error('name')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Associated Workstation Device -->
            <div class="space-y-1.5">
                <label for="device_id" class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                    Stasiun Trainer Kit IoT (Opsional)
                </label>
                <select
                    name="device_id"
                    id="device_id"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-colors @error('device_id') border-rose-300 bg-rose-50/50 @enderror"
                >
                    <option value="">-- Tanpa Workstation (Gunakan Estimasi Standar) --</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ (string) old('device_id') === (string) $device->id ? 'selected' : '' }}>
                            {{ $device->code }} — {{ $device->name }} ({{ $device->bench_location ?? 'Lab Meja Praktik' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-500">
                    Jika stasiun dipilih, telemetri sensor Modbus terakhir dari meja praktik ini akan digunakan untuk mengevaluasi pH, EC, dan lengas tanah.
                </p>
                @error('device_id')
                    <p class="text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Plants Selection -->
            <div class="space-y-3">
                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                    Tanaman yang Dibudidayakan <span class="text-rose-600">*</span>
                </label>

                <!-- Preset plant buttons -->
                <div class="space-y-1.5">
                    <span class="text-[11px] text-slate-500">Pilihan Cepat Tanaman Vokasi:</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['Cabai Rawit', 'Tomat Cherry', 'Bawang Merah', 'Selada Keriting', 'Pakcoy Hidroponik', 'Melon Golden', 'Terong Ungu', 'Kangkung Darat'] as $preset)
                            <button
                                type="button"
                                @click="if(!selectedPlants.includes('{{ $preset }}')) selectedPlants.push('{{ $preset }}')"
                                :class="selectedPlants.includes('{{ $preset }}') ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                class="px-2.5 py-1 rounded-md border text-[11px] font-medium transition-colors"
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
                        placeholder="Ketik tanaman lain lalu tekan Tambah..."
                        class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-colors"
                    >
                    <button
                        type="button"
                        @click="addCustomPlant()"
                        class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold transition-colors"
                    >
                        Tambah
                    </button>
                </div>

                <!-- Selected plants tags -->
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                    <span class="text-[11px] font-semibold text-slate-500 block">Tanaman Terpilih:</span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(plant, index) in selectedPlants" :key="index">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 border border-emerald-300 text-emerald-900 text-xs font-medium">
                                <span x-text="plant"></span>
                                <input type="hidden" name="plants[]" :value="plant">
                                <button
                                    type="button"
                                    @click="removePlant(index)"
                                    class="text-emerald-700 hover:text-emerald-900 font-bold ml-1 text-xs"
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
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <a
                    href="{{ route('media.index') }}"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center gap-2"
                >
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Simpan Media Tanam</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
