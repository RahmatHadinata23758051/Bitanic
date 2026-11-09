@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showModal: false }">
    <!-- Unboxed Header & Action Bar matching approved Monitoring IoT & Analytics -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Jadwal Praktikum
            </h1>
            <p class="text-xs text-slate-500">
                Pengaturan sesi pembelajaran dan penggunaan Trainer Kit
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ route('schedules.index') }}" class="flex items-center gap-2">
                    <label for="school_id" class="text-xs font-semibold text-slate-600">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                    >
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if($canManageSchedules)
                <button
                    type="button"
                    @click="showModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Jadwal</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Alert Error / Validation -->
    @if($errors->any())
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
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

    <!-- Contextual Summary Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Sesi Hari Ini</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ $schedules->filter(fn($s) => $s->activity_date && $s->activity_date->isToday())->count() }} <span class="text-xs font-normal text-slate-500">Sesi</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($todayDate)->locale('id')->isoFormat('dddd, D MMMM Y') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Meja Terjadwal Hari Ini</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ count($bookedDevicesToday) }} <span class="text-xs font-normal text-slate-500">/ {{ $devices->count() }} Meja</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $devices->count() - count($bookedDevicesToday) }} meja tanpa jadwal hari ini</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Total Sesi Terdaftar</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ $schedules->count() }} <span class="text-xs font-normal text-slate-500">Sesi Praktikum</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Semua jadwal aktif di laboratorium</div>
        </div>
    </div>

    <!-- Station Availability Section -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Ketersediaan Stasiun Trainer Kit</h2>
                <p class="text-xs text-slate-500">Status koneksi sensor dan alokasi sesi hari ini</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Sensor Online</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-slate-300"></span> Sensor Offline</span>
            </div>
        </div>

        @if($devices->isEmpty())
            <div class="py-6 text-center text-slate-400">
                <p class="text-xs font-medium">Belum ada stasiun Trainer Kit yang terdaftar di sekolah ini.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($devices as $kit)
                    @php
                        $st = $deviceStatuses[$kit->id] ?? ['is_online' => false, 'is_booked_today' => false];
                        $isOnline = $st['is_online'];
                        $isBookedToday = $st['is_booked_today'];
                    @endphp
                    <div class="p-3 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50/60 transition-colors flex flex-col justify-between gap-2">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-xs font-semibold text-slate-900 font-mono">{{ $kit->code }}</span>
                            <span class="inline-flex items-center gap-1 text-[11px] {{ $isOnline ? 'text-emerald-700' : 'text-slate-400' }}" title="{{ $isOnline ? 'Sensor online (heartbeat aktif)' : 'Sensor offline (tidak ada transmisi segar)' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isOnline ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                <span>{{ $isOnline ? 'Online' : 'Offline' }}</span>
                            </span>
                        </div>
                        <div class="text-xs text-slate-600 line-clamp-2 leading-snug" title="{{ $kit->bench_location ?: $kit->name }}">
                            {{ $kit->bench_location ?: $kit->name }}
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                            <span class="text-slate-400">Hari ini:</span>
                            @if($isBookedToday)
                                <span class="font-medium text-amber-700">Terjadwal</span>
                            @elseif($isOnline)
                                <span class="font-medium text-slate-700">Tersedia</span>
                            @else
                                <span class="text-slate-400 italic">Offline</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Practicum Schedules Table (Primary Content) -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Daftar Sesi Praktikum Laboratorium</h2>
                <p class="text-[11px] text-slate-500">Jadwal pemakaian stasiun kerja dan modul ajar praktikum ATPH</p>
            </div>
            <div class="text-[11px] text-slate-500 font-medium">
                Total: <span class="font-semibold text-slate-800 tabular-nums">{{ $schedules->count() }}</span> sesi
            </div>
        </div>

        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-600 text-[11px] font-semibold">
                        <th class="py-2.5 px-3">Tanggal</th>
                        <th class="py-2.5 px-3">Waktu (WIB)</th>
                        <th class="py-2.5 px-3">Modul Praktikum</th>
                        <th class="py-2.5 px-3">Kelas &amp; Kelompok</th>
                        <th class="py-2.5 px-3">Alokasi Meja</th>
                        <th class="py-2.5 px-3">Status Sesi</th>
                        @if($canManageSchedules)
                            <th class="py-2.5 px-3 text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($schedules as $sched)
                        @php
                            $isToday = $sched->activity_date && $sched->activity_date->isToday();
                            $dateStr = $sched->activity_date ? $sched->activity_date->format('Y-m-d') : null;
                            $now = now();
                            $sessionStatus = 'upcoming'; // upcoming, ongoing, completed
                            if ($dateStr) {
                                try {
                                    $sessionStart = \Carbon\Carbon::parse($dateStr . ' ' . $sched->start_time);
                                    $sessionEnd = \Carbon\Carbon::parse($dateStr . ' ' . $sched->end_time);
                                    if ($now->greaterThan($sessionEnd)) {
                                        $sessionStatus = 'completed';
                                    } elseif ($now->greaterThanOrEqualTo($sessionStart) && $now->lessThanOrEqualTo($sessionEnd)) {
                                        $sessionStatus = 'ongoing';
                                    } else {
                                        $sessionStatus = 'upcoming';
                                    }
                                } catch (\Throwable $e) {
                                    $sessionStatus = 'upcoming';
                                }
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors {{ $isToday ? 'bg-emerald-50/20' : '' }}">
                            <!-- Tanggal -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="font-semibold {{ $isToday ? 'text-emerald-800' : 'text-slate-800' }}">
                                    {{ $sched->activity_date ? $sched->activity_date->locale('id')->isoFormat('ddd, D MMM Y') : '—' }}
                                </span>
                                @if($isToday)
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-100 text-emerald-800">
                                        Hari Ini
                                    </span>
                                @endif
                            </td>

                            <!-- Waktu -->
                            <td class="py-3 px-3 whitespace-nowrap tabular-nums text-slate-700 font-medium">
                                {{ substr($sched->start_time, 0, 5) }} – {{ substr($sched->end_time, 0, 5) }} WIB
                            </td>

                            <!-- Modul Praktikum -->
                            <td class="py-3 px-3">
                                @if($sched->modulePracticum)
                                    <div class="font-medium text-slate-900 line-clamp-1">
                                        Modul {{ $sched->modulePracticum->meeting_number }}: {{ $sched->modulePracticum->title }}
                                    </div>
                                    <div class="text-[10px] text-slate-500">{{ $sched->title }}</div>
                                @else
                                    <div class="font-medium text-slate-900 line-clamp-1">{{ $sched->title }}</div>
                                    <div class="text-[10px] text-slate-400 italic">Praktikum Mandiri (Tanpa Modul)</div>
                                @endif
                            </td>

                            <!-- Kelas & Kelompok -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($sched->classroom)
                                    <div class="font-medium text-slate-900">{{ $sched->classroom->name }}</div>
                                    @if($sched->studentGroup)
                                        <div class="text-[10px] text-slate-500">{{ $sched->studentGroup->name }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Umum / Belum Ditentukan</span>
                                @endif
                            </td>

                            <!-- Alokasi Meja -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($sched->device)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                        <span class="font-mono font-bold">{{ $sched->device->code }}</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="truncate max-w-[120px]">{{ $sched->device->name }}</span>
                                    </span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Belum Dialokasikan</span>
                                @endif
                            </td>

                            <!-- Status Sesi -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($sessionStatus === 'ongoing')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Sedang Berlangsung
                                    </span>
                                @elseif($sessionStatus === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        Selesai
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>Mendatang
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            @if($canManageSchedules)
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <form method="POST" action="{{ route('schedules.destroy', $sched->id) }}" onsubmit="return confirm('Hapus jadwal sesi praktikum ini?');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                            title="Hapus Jadwal"
                                        >
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageSchedules ? 7 : 6 }}" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="calendar" class="w-6 h-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-700">Belum ada jadwal praktikum</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                    Belum ada sesi praktikum laboratorium yang dijadwalkan untuk sekolah ini.
                                </p>
                                @if($canManageSchedules)
                                    <button
                                        type="button"
                                        @click="showModal = true"
                                        class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
                                    >
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                        <span>Tambah Jadwal</span>
                                    </button>
                                @endif
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
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Judul Sesi / Kelas Praktikum <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            required
                            placeholder="Contoh: Praktikum Sensor pH — Kelas XII ATPH 1"
                            class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Kelas Praktikum
                            </label>
                            <select
                                name="classroom_id"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                            >
                                <option value="">-- Pilih Kelas (Opsional) --</option>
                                @foreach($classrooms ?? [] as $cls)
                                    <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Kelompok Praktikum
                            </label>
                            <select
                                name="student_group_id"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                            >
                                <option value="">-- Pilih Kelompok (Opsional) --</option>
                                @foreach($studentGroups ?? [] as $grp)
                                    <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Modul Praktikum
                            </label>
                            <select
                                name="module_practicum_id"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                            >
                                <option value="">-- Pilih Modul (Opsional) --</option>
                                @foreach($modules ?? [] as $mod)
                                    <option value="{{ $mod->id }}">Modul {{ $mod->meeting_number }}: {{ $mod->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Alokasi Meja Trainer Kit
                            </label>
                            <select
                                name="device_id"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                            >
                                <option value="">-- Pilih Stasiun Trainer Kit (Opsional) --</option>
                                @foreach($devices as $kit)
                                    <option value="{{ $kit->id }}">
                                        {{ $kit->code }} — {{ $kit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Tanggal <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="activity_date"
                                required
                                value="{{ date('Y-m-d') }}"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Jam Mulai <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="start_time"
                                required
                                placeholder="08:00"
                                value="08:00"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Jam Selesai <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="end_time"
                                required
                                placeholder="10:30"
                                value="10:30"
                                class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:border-emerald-600 outline-none font-mono"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Agenda Pembelajaran &amp; Instruksi Guru
                        </label>
                        <textarea
                            name="agenda"
                            rows="2"
                            placeholder="Materi kalibrasi elektroda kaca, pencatatan buffer, dan tugas LKS pertemuan 4..."
                            class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:border-emerald-600 outline-none"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
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
