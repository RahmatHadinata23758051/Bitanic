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

    <!-- Fleet Status Grid of Trainer Kits -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($kits as $kit)
            @php
                $isOnline = $kit->status === 'online';
                $isAnomaly = $kit->status === 'anomaly';
                $offsets = $kit->calibration_offsets ?? ['phOffset' => 0.0, 'moistureOffset' => 0.0, 'ecOffset' => 0];
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
                            <i data-lucide="battery-charging" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span class="font-bold text-slate-800">{{ $kit->battery_percent ?? 90 }}%</span>
                        </div>
                        <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-0.5">
                            <i data-lucide="wifi" class="w-3 h-3 text-slate-400"></i>
                            <span>{{ $kit->rssi_signal ?? -55 }} dBm</span>
                        </div>
                    </div>
                </div>

                <!-- Specs Specs Details -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1.5 font-mono text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-slate-400">IP Gateway:</span>
                        <span class="text-slate-800 font-semibold">{{ $kit->ip_address ?? '192.168.10.100' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">MAC Address:</span>
                        <span class="text-slate-600">{{ $kit->mac_address ?? '24:0A:C4:00:00:00' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Firmware:</span>
                        <span class="text-emerald-700 font-semibold">{{ $kit->firmware_version ?? 'v2.4.1-PROD' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Protokol:</span>
                        <span class="text-slate-800">{{ $kit->protocol ?? 'Modbus RS485' }}</span>
                    </div>
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
                            pH: {{ $offsets['phOffset'] ?? '0.0' }} | M: {{ $offsets['moistureOffset'] ?? '0.0' }}% | EC: {{ $offsets['ecOffset'] ?? '0' }}
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
        @endforeach
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
