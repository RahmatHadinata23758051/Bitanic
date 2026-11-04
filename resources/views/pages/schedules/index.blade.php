@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showModal: false }">
    <!-- Header Banner & Action Bar -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">
                    Laboratorium IoT Agritech
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian ATPH 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Manajemen Jadwal Praktikum &amp; Alokasi Meja Trainer Kit
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                Pengaturan jadwal giliran sesi laboratorium kelas, pencegahan tabrakan alokasi meja trainer kit, dan penugasan modul ajar.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <button
                type="button"
                @click="showModal = true"
                class="px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center gap-2"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Jadwal Sesi</span>
            </button>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Alert Error / Validation -->
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Gagal Menyimpan Jadwal:</span>
            </div>
            <ul class="list-disc list-inside pl-6 space-y-0.5 text-[11px]">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Trainer Kit Workstations Status Grid (Meja Praktik 1-6) -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                Status Ketersediaan Meja Trainer Kit Hari Ini ({{ \Carbon\Carbon::parse($todayDate)->isoFormat('dddd, D MMMM Y') }})
            </h3>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($devices as $kit)
                @php
                    $isBookedToday = in_array($kit->id, $bookedDevicesToday);
                @endphp
                <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700">
                                {{ $kit->code }}
                            </span>
                            @if($isBookedToday)
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                    Terjadwal
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Siap Pakai
                                </span>
                            @endif
                        </div>
                        <h4 class="text-xs font-bold text-slate-900 line-clamp-1">{{ $kit->name }}</h4>
                        <p class="text-[11px] text-slate-500 line-clamp-1">{{ $kit->note ?? 'Stasiun laboratorium aktif' }}</p>
                    </div>

                    <div class="w-8 h-8 rounded-lg {{ $isBookedToday ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }} flex items-center justify-center shrink-0">
                        <i data-lucide="cpu" class="w-4 h-4"></i>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Practicum Schedules Table -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                Daftar Sesi Praktikum Laboratorium Terjadwal ({{ $schedules->count() }} Sesi)
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-mono text-[11px] uppercase">
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3">Waktu Sesi</th>
                        <th class="py-3 px-3">Judul Sesi / Kelas</th>
                        <th class="py-3 px-3">Alokasi Meja</th>
                        <th class="py-3 px-3">Agenda Pembelajaran</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($schedules as $sched)
                        @php
                            $isToday = $sched->activity_date && $sched->activity_date->isToday();
                            $isPast = $sched->activity_date && $sched->activity_date->isPast() && !$isToday;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors {{ $isToday ? 'bg-emerald-50/30' : '' }}">
                            <!-- Tanggal -->
                            <td class="py-3 px-3 whitespace-nowrap font-mono text-xs">
                                <span class="font-bold {{ $isToday ? 'text-emerald-800' : 'text-slate-800' }}">
                                    {{ $sched->activity_date ? $sched->activity_date->format('d M Y') : '-' }}
                                </span>
                                @if($isToday)
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-emerald-100 text-emerald-800">
                                        Hari Ini
                                    </span>
                                @endif
                            </td>

                            <!-- Waktu -->
                            <td class="py-3 px-3 whitespace-nowrap font-mono text-[11px] text-slate-600">
                                {{ substr($sched->start_time, 0, 5) }} - {{ substr($sched->end_time, 0, 5) }} WIB
                            </td>

                            <!-- Judul Kelas -->
                            <td class="py-3 px-3 font-medium text-slate-900 whitespace-nowrap">
                                {{ $sched->title }}
                            </td>

                            <!-- Alokasi Meja -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($sched->device)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $sched->device->code }} — {{ $sched->device->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Belum Dialokasikan</span>
                                @endif
                            </td>

                            <!-- Agenda -->
                            <td class="py-3 px-3 text-slate-600 max-w-xs truncate text-[11px]">
                                {{ $sched->agenda ?? '-' }}
                            </td>

                            <!-- Aksi -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <form method="POST" action="{{ route('schedules.destroy', $sched->id) }}" onsubmit="return confirm('Hapus jadwal sesi praktikum ini?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="p-1 rounded text-slate-400 hover:text-rose-600 transition-colors"
                                        title="Hapus Jadwal"
                                    >
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                <i data-lucide="calendar" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-xs font-semibold">Belum ada jadwal praktikum yang dibuat.</p>
                                <button
                                    type="button"
                                    @click="showModal = true"
                                    class="mt-2 text-xs font-bold text-emerald-700 hover:underline"
                                >
                                    + Buat Jadwal Praktikum Pertama
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Tambah Jadwal Praktikum (Alpine.js) -->
    <div
        x-cloak
        x-show="showModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <!-- Backdrop -->
            <div
                x-show="showModal"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="showModal = false"
                class="fixed inset-0 bg-slate-900/40 transition-opacity"
            ></div>

            <!-- Modal Panel -->
            <div
                x-show="showModal"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-slate-200 p-6 space-y-5"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900" id="modal-title">
                            Tambah Sesi Praktikum &amp; Alokasi Meja
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="showModal = false"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('schedules.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Judul Sesi / Kelas Praktikum <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            required
                            placeholder="Contoh: Praktikum Sensor pH — Kelas XII ATPH 1"
                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Alokasi Meja Trainer Kit <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="device_id"
                            required
                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                        >
                            <option value="">-- Pilih Stasiun Trainer Kit --</option>
                            @foreach($devices as $kit)
                                <option value="{{ $kit->id }}">
                                    {{ $kit->code }} — {{ $kit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Tanggal <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="activity_date"
                                required
                                value="{{ date('Y-m-d') }}"
                                class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Jam Mulai <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="start_time"
                                required
                                placeholder="08:00"
                                value="08:00"
                                class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Jam Selesai <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="end_time"
                                required
                                placeholder="10:30"
                                value="10:30"
                                class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Agenda Pembelajaran &amp; Instruksi Guru
                        </label>
                        <textarea
                            name="agenda"
                            rows="2"
                            placeholder="Materi kalibrasi elektroda kaca, pencatatan buffer, dan tugas LKS pertemuan 4..."
                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 outline-none"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors"
                        >
                            Simpan Jadwal Sesi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
