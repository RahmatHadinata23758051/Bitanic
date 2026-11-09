@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Trainer Kit
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pengelolaan dan diagnostik perangkat laboratorium IoT
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ route('kits.index') }}" class="flex items-center gap-2">
                    <label for="school_id" class="text-xs font-medium text-slate-600">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 shadow-2xs cursor-pointer"
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

            <a
                href="{{ url('/raw-logs') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-2xs transition-colors"
            >
                <i data-lucide="terminal" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Debugger RS485</span>
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('new_api_key'))
        <div class="p-4 rounded-xl bg-amber-50/80 border border-amber-200 text-amber-900 space-y-2">
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                <i data-lucide="key" class="w-4 h-4 text-amber-700 shrink-0"></i>
                <span>Kunci API Perangkat Baru Diterbitkan</span>
            </div>
            <p class="text-xs text-amber-700 leading-relaxed">
                Segera catat dan perbarui kunci API berikut pada firmware ESP32 perangkat. Nilai token rahasia ini hanya ditampilkan sekali dan tidak dapat dilihat kembali.
            </p>
            <div class="p-2.5 rounded-lg bg-white border border-amber-300 font-mono text-xs font-semibold text-slate-900 select-all break-all">
                {{ session('new_api_key') }}
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="p-4 rounded-xl bg-amber-50/80 border border-amber-200 text-amber-900 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-700 shrink-0 mt-0.5"></i>
            <p class="text-xs text-amber-800 leading-relaxed">{{ session('warning') }}</p>
        </div>
    @endif

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50/80 border border-emerald-200 text-emerald-900 flex items-start gap-3">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5"></i>
            <p class="text-xs text-emerald-800 leading-relaxed">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50/80 border border-rose-200 text-rose-900 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-700 shrink-0 mt-0.5"></i>
            <p class="text-xs text-rose-800 leading-relaxed">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Summary KPI Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Total Perangkat</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['total']) }}</span>
                <span class="text-xs text-slate-400">unit armada</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Online Saat Ini</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['online']) }}</span>
                <span class="inline-flex items-center gap-1 text-xs text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    heartbeat aktif
                </span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Memerlukan Perhatian</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['attention']) }}</span>
                <span class="inline-flex items-center gap-1 text-xs {{ $stats['attention'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                    @if($stats['attention'] > 0)
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        offline / anomali
                    @else
                        semua normal
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Fleet Device Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($kits as $kit)
            @php
                $isOnline = $kit->status === 'online';
                $isMaintenance = $kit->status === 'maintenance';
                $offsets = $kit->calibration_offsets ?? [];
                $relays = $kit->relays_state ?? [];
            @endphp
            <div
                x-data="{ showConfig: false, showCalib: false }"
                class="bg-white rounded-xl border border-slate-200/80 hover:border-slate-300 shadow-2xs transition-colors flex flex-col justify-between p-5 space-y-4"
            >
                {{-- Card Header: Identification & Availability --}}
                <div class="space-y-1.5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-slate-900">{{ $kit->code }}</span>
                                @if($isOnline)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Online
                                    </span>
                                @elseif($isMaintenance)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Maintenance
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Offline
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-600 mt-1 line-clamp-1">
                                {{ $kit->bench_location ?? $kit->name }}
                            </p>
                            @if(!empty($isSuperuser) && $kit->school)
                                <p class="text-[11px] text-slate-400 truncate mt-0.5">
                                    {{ $kit->school->name }}
                                </p>
                            @endif
                        </div>

                        {{-- Battery & RSSI (Honest Provenance) --}}
                        <div class="text-right text-[11px] text-slate-500 shrink-0 space-y-0.5">
                            <div class="flex items-center justify-end gap-1 font-tabular">
                                <i data-lucide="battery-charging" class="w-3.5 h-3.5 {{ $kit->battery_percent !== null ? 'text-emerald-600' : 'text-slate-300' }}"></i>
                                <span class="font-medium text-slate-700">{{ $kit->battery_percent !== null ? $kit->battery_percent . '%' : '—' }}</span>
                            </div>
                            <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400 font-tabular">
                                <i data-lucide="wifi" class="w-3 h-3 text-slate-400"></i>
                                <span>{{ $kit->rssi_signal !== null ? $kit->rssi_signal . ' dBm' : '—' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Heartbeat timestamp --}}
                    <div class="text-[11px] text-slate-400 flex items-center gap-1 pt-1">
                        <i data-lucide="radio" class="w-3 h-3 text-slate-400"></i>
                        <span>Komunikasi terakhir:</span>
                        <span class="font-medium text-slate-600">
                            @if($kit->last_seen_at)
                                {{ $kit->last_seen_at->timezone('Asia/Jakarta')->translatedFormat('d M, H:i') }} WIB
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Actuator Relays Status --}}
                <div class="space-y-1.5 pt-2 border-t border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500 block">Status Relai Aktuator</span>
                    <div class="grid grid-cols-4 gap-1.5 text-center text-[10px]">
                        <div class="py-1 px-1 rounded-md border {{ !empty($relays['irrigationPump']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200/60 font-medium' : 'bg-slate-50 text-slate-500 border-slate-200/40' }}">
                            Pompa
                        </div>
                        <div class="py-1 px-1 rounded-md border {{ !empty($relays['fertigationValve']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200/60 font-medium' : 'bg-slate-50 text-slate-500 border-slate-200/40' }}">
                            Fertigasi
                        </div>
                        <div class="py-1 px-1 rounded-md border {{ !empty($relays['mistingFan']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200/60 font-medium' : 'bg-slate-50 text-slate-500 border-slate-200/40' }}">
                            Kipas
                        </div>
                        <div class="py-1 px-1 rounded-md border {{ !empty($relays['growLight']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200/60 font-medium' : 'bg-slate-50 text-slate-500 border-slate-200/40' }}">
                            Lampu
                        </div>
                    </div>
                </div>

                {{-- Progressive Disclosure: Technical Specifications --}}
                <div class="border-t border-slate-100 pt-2 space-y-2">
                    <button
                        type="button"
                        @click="showConfig = !showConfig"
                        class="w-full flex items-center justify-between text-xs font-medium text-slate-600 hover:text-slate-900 transition-colors py-0.5"
                    >
                        <span>Parameter Teknis & Jaringan</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-150" :class="{ 'rotate-180': showConfig }"></i>
                    </button>

                    <div x-show="showConfig" x-cloak class="p-3 bg-slate-50/80 rounded-lg border border-slate-200/70 space-y-1.5 text-[11px]">
                        @if(!empty($canViewSensitiveMetadata))
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">IP Gateway:</span>
                                <span class="font-mono font-medium text-slate-800">{{ $kit->ip_address ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">MAC Address:</span>
                                <span class="font-mono text-slate-600">{{ $kit->mac_address ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Firmware:</span>
                                <span class="font-mono text-slate-700">{{ $kit->firmware_version ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Protokol Sensor:</span>
                                <span class="font-mono text-slate-700">{{ $kit->protocol ?? 'Modbus RS485 RTU' }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-1.5 border-t border-slate-200/60">
                                <span class="text-slate-500">Prefix Kunci API:</span>
                                <span class="font-mono font-medium text-slate-800">{{ $kit->activeApiKey?->key_prefix ? $kit->activeApiKey->key_prefix . '...' : 'Tidak aktif' }}</span>
                            </div>
                            @if($kit->activeApiKey?->last_used_at)
                                <div class="flex justify-between items-center text-[10px] text-slate-400">
                                    <span>Autentikasi Terakhir:</span>
                                    <span>{{ $kit->activeApiKey->last_used_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        @else
                            <div class="flex justify-between items-center text-xs text-slate-500">
                                <span>Konfigurasi Jaringan:</span>
                                <span class="italic text-slate-600">Dikelola Guru / Teknisi Lab</span>
                            </div>
                        @endif

                        <div class="flex justify-between items-center pt-1.5 border-t border-slate-200/60">
                            <span class="text-slate-500">Offset Kalibrasi:</span>
                            <span class="font-mono text-[10px] text-slate-700">
                                @if(!empty($offsets))
                                    pH: {{ $offsets['ph'] ?? ($offsets['phOffset'] ?? '0.0') }} | M: {{ $offsets['humidity'] ?? ($offsets['moistureOffset'] ?? '0.0') }}% | EC: {{ $offsets['ec'] ?? ($offsets['ecOffset'] ?? '0') }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Action Toolbar --}}
                <div class="pt-3 border-t border-slate-100 flex items-center gap-2">
                    <a
                        href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors"
                    >
                        <span>Buka Stasiun</span>
                        <i data-lucide="external-link" class="w-3 h-3"></i>
                    </a>

                    @if(!empty($canViewSensitiveMetadata))
                        <button
                            type="button"
                            @click="showCalib = true"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition-colors shadow-2xs"
                            title="Kalibrasi Offset Sensor"
                        >
                            <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                            <span class="sr-only">Kalibrasi Sensor</span>
                        </button>
                    @endif

                    @if(!empty($canRotateApiKey))
                        <form method="POST" action="{{ route('kits.api-key.rotate', $kit->id) }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Rotasi kunci API untuk {{ $kit->code }}? Kunci lama akan segera direvokasi.')"
                                class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-amber-50 text-slate-600 hover:text-amber-700 transition-colors shadow-2xs"
                                title="Rotasi Kunci API Hardware"
                            >
                                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                <span class="sr-only sm:not-sr-only text-[11px]">Rotasi Key</span>
                            </button>
                        </form>
                    @endif

                    @if(!empty($canReboot))
                        <form method="POST" action="{{ route('kits.reboot', $kit->id) }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Catat simulasi soft-reboot untuk {{ $kit->code }}? Tidak ada perintah fisik yang dikirim.')"
                                class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition-colors shadow-2xs"
                                title="Catat Simulasi Soft-Reboot (Transport Hardware Belum Tersedia)"
                            >
                                <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>
                                <span class="sr-only">Catat Simulasi Soft-Reboot (Transport Hardware Belum Tersedia)</span>
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Calibration Modal (Teacher / Superuser) --}}
                @if(!empty($canViewSensitiveMetadata))
                    <div
                        x-show="showCalib"
                        x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
                    >
                        <div
                            @click.away="showCalib = false"
                            class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4 text-left"
                        >
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 font-sans">
                                        Kalibrasi Sensor — {{ $kit->code }}
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Penyetelan offset probe 7-in-1 Modbus RS485
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="showCalib = false"
                                    class="text-slate-400 hover:text-slate-600"
                                >
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('kits.calibration', $kit->id) }}" class="space-y-4">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <label for="phOffset_{{ $kit->id }}" class="block text-xs font-medium text-slate-700">
                                            Offset Derajat Keasaman (pH)
                                        </label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="phOffset_{{ $kit->id }}"
                                                name="phOffset"
                                                step="0.01"
                                                min="-5"
                                                max="5"
                                                value="{{ $offsets['ph'] ?? ($offsets['phOffset'] ?? '0.0') }}"
                                                required
                                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600"
                                            >
                                            <span class="text-xs text-slate-400 font-mono">pH</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="moistureOffset_{{ $kit->id }}" class="block text-xs font-medium text-slate-700">
                                            Offset Kelembapan Tanah (Moisture)
                                        </label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="moistureOffset_{{ $kit->id }}"
                                                name="moistureOffset"
                                                step="0.1"
                                                min="-20"
                                                max="20"
                                                value="{{ $offsets['humidity'] ?? ($offsets['moistureOffset'] ?? '0.0') }}"
                                                required
                                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600"
                                            >
                                            <span class="text-xs text-slate-400 font-mono">%</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="ecOffset_{{ $kit->id }}" class="block text-xs font-medium text-slate-700">
                                            Offset Konduktivitas Listrik (EC)
                                        </label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="ecOffset_{{ $kit->id }}"
                                                name="ecOffset"
                                                step="1"
                                                min="-500"
                                                max="500"
                                                value="{{ $offsets['ec'] ?? ($offsets['ecOffset'] ?? '0') }}"
                                                required
                                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600"
                                            >
                                            <span class="text-xs text-slate-400 font-mono">µS/cm</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="reason_{{ $kit->id }}" class="block text-xs font-medium text-slate-700">
                                            Catatan / Alasan Kalibrasi
                                        </label>
                                        <input
                                            type="text"
                                            id="reason_{{ $kit->id }}"
                                            name="reason"
                                            placeholder="Misal: Kalibrasi berkala buffer pH 7.00 semester genap"
                                            class="mt-1 w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600"
                                        >
                                    </div>
                                </div>

                                <div class="p-3 bg-slate-50 rounded-lg text-[11px] text-slate-500 leading-relaxed border border-slate-200/60">
                                    Penyetelan offset dieksekusi secara atomik dan dicatat dalam audit log sekolah. Nilai offset diterapkan pada pembacaan sensor mendatang.
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        @click="showCalib = false"
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        class="px-3.5 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors"
                                    >
                                        Simpan Kalibrasi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-xl border border-dashed border-slate-200 p-8 space-y-2">
                <i data-lucide="cpu" class="w-8 h-8 mx-auto text-slate-300"></i>
                <p class="text-sm font-semibold text-slate-700">Belum Ada Meja Trainer Kit Terdaftar</p>
                <p class="text-xs text-slate-500 max-w-md mx-auto">
                    Stasiun meja praktikum laboratorium IoT yang terhubung ke sistem sekolah akan ditampilkan di sini.
                </p>
            </div>
        @endforelse
    </div>

    @if($kits->hasPages())
        <div class="pt-2">
            {{ $kits->links() }}
        </div>
    @endif

    {{-- SOP Kalibrasi Card --}}
    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-2xs space-y-2">
        <div class="flex items-center gap-2 text-slate-800 font-semibold text-sm">
            <i data-lucide="wrench" class="w-4 h-4 text-emerald-700"></i>
            <span>SOP Kalibrasi Sensor 7-in-1 Modbus RS485</span>
        </div>
        <p class="text-xs text-slate-600 leading-relaxed max-w-4xl">
            Kalibrasi probe sensor tanah dilakukan secara berkala menggunakan larutan standar buffer pH 4.01, 7.00, dan 9.21 serta larutan kalibrasi konduktivitas listrik (EC) 1413 µS/cm sebelum modul praktikum semester baru dimulai untuk menjaga akurasi pembacaan agronomi.
        </p>
    </div>
</div>
@endsection
