@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    HARDWARE FLEET
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Stasiun Meja Praktikum IoT SMK</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Manajemen Armada Trainer Kit IoT
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Pantau kesehatan jaringan modul ESP32 RS485, alokasi IP & MAC address, level daya baterai, serta konfigurasi offset kalibrasi sensor per meja praktik.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a
                href="{{ url('/raw-logs') }}"
                class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-mono font-bold flex items-center gap-2 shadow-sm transition-colors"
            >
                <i data-lucide="terminal" class="w-4 h-4 text-emerald-400"></i>
                <span>Raw RS485 Debugger</span>
            </a>
        </div>
    </div>

    @if(session('new_api_key'))
    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-2">
        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-800">
            <i data-lucide="key" class="w-4 h-4 text-amber-700"></i>
            <span>Kunci API Perangkat Baru (Hanya Ditampilkan Sekali)</span>
        </div>
        <p class="text-xs text-amber-800">
            Segera simpan kunci API berikut pada firmware ESP32 perangkat Anda. Kunci rahasia ini tidak dapat dipulihkan atau dilihat kembali setelah Anda meninggalkan halaman ini.
        </p>
        <div class="p-2.5 rounded-lg bg-white border border-amber-300 font-mono text-xs font-bold text-slate-900 select-all break-all">
            {{ session('new_api_key') }}
        </div>
    </div>
    @endif

    <!-- Fleet Status Grid of Trainer Kits -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($kits as $kit)
            @php
                $isOnline = $kit->status === 'online';
                $isAnomaly = $kit->status === 'anomaly';
                $offsets = $kit->calibration_offsets;
                $relays = $kit->relays_state ?? [];
            @endphp
            <div class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-emerald-300 shadow-sm transition-all flex flex-col justify-between space-y-4">
                <!-- Header Card -->
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-extrabold text-slate-900">{{ $kit->code }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono {{ $isOnline ? 'bg-emerald-100 text-emerald-800' : ($isAnomaly ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600') }}">
                                {{ $kit->status ?? 'online' }}
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-600 mt-1">{{ $kit->bench_location ?? $kit->name }}</p>
                    </div>

                    <!-- Battery & Signal -->
                    <div class="text-right font-mono text-[11px] text-slate-500">
                        <div class="flex items-center justify-end gap-1">
                            <i data-lucide="battery-charging" class="w-3.5 h-3.5 {{ $kit->battery_percent !== null ? 'text-emerald-600' : 'text-slate-300' }}"></i>
                            <span class="font-bold text-slate-800">{{ $kit->battery_percent !== null ? $kit->battery_percent . '%' : '—' }}</span>
                        </div>
                        <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-0.5">
                            <i data-lucide="wifi" class="w-3 h-3 text-slate-400"></i>
                            <span>{{ $kit->rssi_signal !== null ? $kit->rssi_signal . ' dBm' : '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Specs Specs Details (No Fake Hardware Metrics) -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1.5 font-mono text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-slate-400">IP Gateway:</span>
                        <span class="text-slate-800 font-semibold">{{ $kit->ip_address ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">MAC Address:</span>
                        <span class="text-slate-600">{{ $kit->mac_address ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Firmware:</span>
                        <span class="text-emerald-700 font-semibold">{{ $kit->firmware_version ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Protokol:</span>
                        <span class="text-slate-800">{{ $kit->protocol ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1 border-t border-slate-200/60">
                        <span class="text-slate-400">Prefix Key:</span>
                        <span class="font-mono text-slate-700 font-semibold">{{ $kit->activeApiKey?->key_prefix ? $kit->activeApiKey->key_prefix . '...' : 'Tidak aktif' }}</span>
                    </div>
                    @if($kit->activeApiKey?->last_used_at)
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-400">Terakhir Digunakan:</span>
                        <span class="text-slate-600">{{ $kit->activeApiKey->last_used_at->diffForHumans() }}</span>
                    </div>
                    @endif
                </div>

                <!-- Actuator Relays Mini Status -->
                <div class="space-y-1.5">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Aktuator Terpasang</span>
                    <div class="grid grid-cols-4 gap-1.5 text-center text-[10px] font-semibold">
                        <div class="py-1 px-1 rounded {{ !empty($relays['irrigationPump']) ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-500' }}">
                            Pompa
                        </div>
                        <div class="py-1 px-1 rounded {{ !empty($relays['fertigationValve']) ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-500' }}">
                            Fertigasi
                        </div>
                        <div class="py-1 px-1 rounded {{ !empty($relays['mistingFan']) ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-500' }}">
                            Kipas
                        </div>
                        <div class="py-1 px-1 rounded {{ !empty($relays['growLight']) ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-500' }}">
                            Lampu
                        </div>
                    </div>
                </div>

                <!-- Calibration Offsets Summary -->
                <div class="pt-3 border-t border-slate-100 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Offset Sensor:</span>
                        <span class="font-mono text-[11px] text-slate-700">
                            @if(!empty($offsets))
                                pH: {{ $offsets['ph'] ?? ($offsets['phOffset'] ?? '0.0') }} | M: {{ $offsets['humidity'] ?? ($offsets['moistureOffset'] ?? '0.0') }}% | EC: {{ $offsets['ec'] ?? ($offsets['ecOffset'] ?? '0') }}
                            @else
                                —
                            @endif
                        </span>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center gap-2 pt-1">
                        <a
                            href="{{ url('/iot-monitoring?device_id=' . $kit->id) }}"
                            class="flex-1 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold text-center transition-colors"
                        >
                            Buka Stasiun
                        </a>
                        @if(auth()->user()?->role === 'superuser')
                        <form method="POST" action="{{ route('kits.api-key.rotate', $kit->id) }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Rotasi kunci API untuk {{ $kit->code }}? Kunci lama akan segera direvokasi.')"
                                class="px-2 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-semibold transition-colors flex items-center gap-1"
                                title="Rotasi Kunci API Hardware"
                            >
                                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                <span class="sr-only sm:not-sr-only text-[11px]">Rotasi Key</span>
                            </button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('kits.reboot', $kit->id) }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Kirim perintah reboot via Modbus ke {{ $kit->code }}?')"
                                class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                                title="Reboot Kit Hardware"
                            >
                                <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-200">
                <i data-lucide="cpu" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                <p class="text-sm font-bold text-slate-700">Belum Ada Meja Trainer Kit Terdaftar</p>
                <p class="text-xs text-slate-500 mt-1">Stasiun meja praktikum yang terhubung akan tampil di halaman ini.</p>
            </div>
        @endforelse
    </div>

    <!-- Calibration Info Modal / Guide -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-2">
        <div class="flex items-center gap-2 text-slate-800 font-bold text-sm">
            <i data-lucide="wrench" class="w-4 h-4 text-emerald-600"></i>
            <span>SOP Kalibrasi Sensor 7-in-1 Modbus RS485</span>
        </div>
        <p class="text-xs text-slate-600 leading-relaxed">
            Kalibrasi probe sensor tanah dilakukan berkala menggunakan larutan standar buffer pH 4.01, 7.00, 9.21 serta larutan kalibrasi konduktivitas listrik (EC) 1413 µS/cm sebelum modul praktikum semester baru dimulai.
        </p>
    </div>
</div>
@endsection
