@extends('layouts.app')

@section('content')
<div
    x-data="{
        selectedPacket: null,
        selectedKit: null,
        selectedLevel: null,
        selectedTime: null,
        selectedPayload: null,
        showInjectModal: false,
        selectLog(item) {
            this.selectedKit = item.kit;
            this.selectedPacket = item.hex;
            this.selectedLevel = item.level;
            this.selectedTime = item.time;
            this.selectedPayload = item.payload;
        },
        clearSelection() {
            this.selectedPacket = null;
            this.selectedKit = null;
            this.selectedLevel = null;
            this.selectedTime = null;
            this.selectedPayload = null;
        },
        parseHex(hex) {
            if (!hex) return null;
            let clean = hex.replace(/\s+/g, '').toUpperCase();
            if (clean.length < 6) return null;
            let slave = clean.substring(0, 2);
            let fn = clean.substring(2, 4);
            let len = clean.substring(4, 6);
            let lenInt = parseInt(len, 16) || 0;
            let dataEnd = 6 + (lenInt * 2);
            let data = dataEnd <= clean.length ? clean.substring(6, dataEnd) : clean.substring(6, Math.max(6, clean.length - 4));
            let crc = clean.length > dataEnd ? clean.substring(dataEnd) : clean.slice(-4);
            return {
                slave: slave,
                fn: fn,
                len: len,
                data: data,
                crc: crc
            };
        }
    }"
    class="space-y-6"
>
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Debugger RS485
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Inspeksi frame komunikasi dan diagnostik sensor
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ url('/raw-logs') }}" class="flex items-center gap-2">
                    <label for="school_id" class="text-xs font-medium text-slate-600">Sekolah:</label>
                    <select
                        id="school_id"
                        name="school_id"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] shadow-2xs cursor-pointer"
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
                href="{{ route('kits.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-2xs transition-colors"
            >
                <i data-lucide="cpu" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Armada Trainer Kit</span>
            </a>

            @if(!empty($isSuperuser))
                <button
                    type="button"
                    @click="showInjectModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-2xs transition-colors"
                >
                    <i data-lucide="flask-conical" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Suntik Frame Simulasi</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-leaf-50 border border-leaf-200 text-leaf-900 flex items-start gap-3">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-leaf-700 shrink-0 mt-0.5"></i>
            <p class="text-xs text-leaf-800 leading-relaxed">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50/80 border border-rose-200 text-rose-900 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-700 shrink-0 mt-0.5"></i>
            <p class="text-xs text-rose-800 leading-relaxed">{{ session('error') }}</p>
        </div>
    @endif

    {{-- 4 Stats Counters (Strict Neutral Figures) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Total Paket</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['total']) }}</span>
                <span class="text-xs text-slate-400">frame</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Informasi</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['info']) }}</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#0c3c26]"></span>
                    normal
                </span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Peringatan</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['warnings']) }}</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    @if($stats['warnings'] > 0)
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        perhatian
                    @else
                        nihil
                    @endif
                </span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs">
            <span class="text-xs font-medium text-slate-500">Frame Error</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-tabular text-slate-900">{{ number_format($stats['errors']) }}</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    @if($stats['errors'] > 0)
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        kegagalan
                    @else
                        nihil
                    @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Frame Inspector Section --}}
    <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-bold text-slate-900 font-sans flex items-center gap-2">
                    <i data-lucide="binary" class="w-4 h-4 text-slate-600"></i>
                    <span>Inspeksi Frame Modbus RS485</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    <template x-if="selectedPacket">
                        <span>Menganalisis frame dari perangkat <strong class="text-slate-800 font-mono" x-text="selectedKit"></strong> pada pukul <span class="font-tabular" x-text="selectedTime"></span> WIB</span>
                    </template>
                    <template x-if="!selectedPacket">
                        <span>Contoh struktur frame — bukan data perangkat (Referensi Modbus RTU Function Code 03h)</span>
                    </template>
                </p>
            </div>

            <template x-if="selectedPacket">
                <button
                    type="button"
                    @click="clearSelection()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                    <span>Kembali ke Referensi</span>
                </button>
            </template>
        </div>

        {{-- Active Packet Inspection (Dynamic Alpine) --}}
        <template x-if="selectedPacket">
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500">Raw Hex Payload:</span>
                    <span class="font-mono text-xs font-medium px-2.5 py-1 rounded bg-slate-900 text-slate-100 select-all tracking-wider" x-text="selectedPacket"></span>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800">
                        <span class="text-[10px] text-slate-500 block">Slave ID</span>
                        <span class="font-mono font-bold text-slate-900" x-text="parseHex(selectedPacket)?.slave || '—'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800">
                        <span class="text-[10px] text-slate-500 block">Function Code</span>
                        <span class="font-mono font-bold text-slate-900" x-text="parseHex(selectedPacket)?.fn || '—'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800">
                        <span class="text-[10px] text-slate-500 block">Byte Count</span>
                        <span class="font-mono font-bold text-slate-900" x-text="parseHex(selectedPacket)?.len || '—'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 flex-1 min-w-[200px]">
                        <span class="text-[10px] text-slate-500 block">Register Data (Sensor Payload)</span>
                        <span class="font-mono font-bold tracking-wider text-slate-900" x-text="parseHex(selectedPacket)?.data || '—'"></span>
                    </div>
                    <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-slate-800">
                        <span class="text-[10px] text-slate-500 block">CRC16 (Low/High)</span>
                        <span class="font-mono font-bold text-slate-700" x-text="parseHex(selectedPacket)?.crc || '—'"></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-lg text-xs text-slate-600 border border-slate-200/60 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-slate-700">Catatan Integritas Diagnostik:</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Validasi checksum CRC16: Belum dievaluasi otomatis pada stream diagnostik mentah ini. Pembacaan di atas merepresentasikan transmisi serial byte asli dari gateway perangkat.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        {{-- Educational Reference Breakdown (Default State - High-contrast neutral text on dark chip) --}}
        <template x-if="!selectedPacket">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Slave ID</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">01</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Function</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">03</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Data Length</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">0E</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Lembap (M)</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">01 9A</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Suhu (T)</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">00 28</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">EC</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">05 DC</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">pH</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">00 1E</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">NPK</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-100 px-1.5 py-0.5 rounded">00 78 ...</span>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                        <span class="text-[10px] text-slate-400 block mb-0.5">CRC16</span>
                        <span class="font-mono font-bold bg-slate-900 text-slate-200 px-1.5 py-0.5 rounded">AB CD</span>
                    </div>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Struktur di atas menampilkan referensi respons probe tanah 7-in-1 Modbus RS485 RTU dengan 14 byte payload sensor. Klik tombol <strong>Inspeksi</strong> pada baris log di bawah untuk menelaah frame serial perangkat secara terperinci.
                </p>
            </div>
        </template>
    </div>

    {{-- Diagnostic Context & Filter Toolbar --}}
    <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Arsip Frame Diagnostik Serial</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    @if($logs->isNotEmpty() && $logs->first()->recorded_at)
                        Peristiwa diagnostik terakhir: <span class="font-medium text-slate-700 font-tabular">{{ $logs->first()->recorded_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i:s') }} WIB</span>
                    @else
                        Peristiwa diagnostik: <span class="text-slate-400">—</span>
                    @endif
                </p>
            </div>

            {{-- Filters Form --}}
            <form method="GET" action="{{ url('/raw-logs') }}" class="flex flex-wrap items-center gap-2">
                @if(!empty($selectedSchoolId))
                    <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                @endif

                <label for="kit_code" class="sr-only">Filter Trainer Kit</label>
                <select
                    id="kit_code"
                    name="kit_code"
                    onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-mono text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] shadow-2xs cursor-pointer"
                >
                    <option value="">Semua Perangkat</option>
                    @foreach($devices as $dev)
                        <option value="{{ $dev->code }}" {{ $currentKit === $dev->code ? 'selected' : '' }}>
                            {{ $dev->code }}
                        </option>
                    @endforeach
                </select>

                <label for="level" class="sr-only">Filter Tingkat Keparahan</label>
                <select
                    id="level"
                    name="level"
                    onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] shadow-2xs cursor-pointer"
                >
                    <option value="">Semua Tingkat</option>
                    <option value="INFO" {{ $currentLevel === 'INFO' ? 'selected' : '' }}>INFO</option>
                    <option value="WARN" {{ $currentLevel === 'WARN' ? 'selected' : '' }}>WARN</option>
                    <option value="ERROR" {{ $currentLevel === 'ERROR' ? 'selected' : '' }}>ERROR</option>
                </select>

                @if($currentKit || $currentLevel)
                    <a
                        href="{{ url('/raw-logs' . ($selectedSchoolId ? '?school_id='.$selectedSchoolId : '')) }}"
                        class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-medium text-slate-600 transition-colors"
                    >
                        Reset Filter
                    </a>
                @endif
            </form>
        </div>

        {{-- Log Records Table --}}
        <div class="overflow-x-auto rounded-lg border border-slate-200/80">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 text-[11px] font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-3.5">Waktu (WIB)</th>
                        <th class="py-2.5 px-3">Tingkat</th>
                        <th class="py-2.5 px-3">Perangkat</th>
                        <th class="py-2.5 px-3">Protokol</th>
                        <th class="py-2.5 px-3">Sumber Provenance</th>
                        <th class="py-2.5 px-3.5">Hex Stream Payload</th>
                        <th class="py-2.5 px-3.5">Data Sensor Terurai</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($logs as $log)
                        @php $payload = $log->parsed_payload ?? []; @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-2.5 px-3.5 font-mono text-slate-600 font-tabular whitespace-nowrap">
                                {{ $log->recorded_at ? $log->recorded_at->timezone('Asia/Jakarta')->format('H:i:s.u') : '—' }}
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                @if($log->level === 'ERROR')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-rose-50 text-rose-800 border border-rose-200/60">
                                        ERROR
                                    </span>
                                @elseif($log->level === 'WARN')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200/60">
                                        WARN
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        INFO
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 font-mono font-semibold text-slate-900 whitespace-nowrap">
                                {{ $log->kit_code ?? $log->device?->code ?? '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                {{ $log->protocol ?? 'Modbus RS485 RTU' }}
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                @if(!empty($payload['injected_manually']))
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Simulasi Injeksi
                                    </span>
                                @elseif(($payload['source'] ?? '') === 'sensor_gateway')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#0c3c26]"></span>
                                        Gateway Sensor
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Serial Ingress
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3.5 whitespace-nowrap">
                                <span class="font-mono text-xs font-medium tracking-wider bg-slate-900 text-slate-100 px-2.5 py-1 rounded select-all">
                                    {{ $log->packet_hex ?? '—' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3.5 text-[11px] max-w-xs truncate">
                                @if(!empty($payload))
                                    <span class="font-mono text-slate-600" title="{{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
                                        {{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic">Belum diparse</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="selectLog({
                                        kit: '{{ $log->kit_code ?? $log->device?->code }}',
                                        hex: '{{ $log->packet_hex }}',
                                        level: '{{ $log->level }}',
                                        time: '{{ $log->recorded_at ? $log->recorded_at->timezone('Asia/Jakarta')->format('H:i:s') : '—' }}',
                                        payload: {{ json_encode($payload) }}
                                    })"
                                    class="px-2.5 py-1 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-2xs transition-colors"
                                >
                                    Inspeksi
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400 bg-white">
                                <i data-lucide="terminal" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold text-slate-700">Belum Ada Catatan Frame Serial</p>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                    Tidak ada catatan komunikasi serial Modbus RS485 untuk filter stasiun atau sekolah yang dipilih.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="pt-2">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Injeksi Frame Simulasi (Superuser Only) --}}
    @if(!empty($isSuperuser))
        <div
            x-show="showInjectModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div
                @click.away="showInjectModal = false"
                class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-4 text-left"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 font-sans">
                            Suntik Frame Modbus RS485 (Simulasi)
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Pengujian diagnostik parser byte laboratorium tanpa transmisi fisik
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showInjectModal = false"
                        class="text-slate-400 hover:text-slate-600"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form method="POST" action="{{ url('/raw-logs/inject') }}" class="space-y-4">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label for="inject_kit_code" class="block text-xs font-medium text-slate-700">
                                Target Perangkat Trainer Kit
                            </label>
                            <select
                                id="inject_kit_code"
                                name="kit_code"
                                required
                                class="mt-1 w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                            >
                                <option value="">Pilih Trainer Kit</option>
                                @foreach($devices as $dev)
                                    <option value="{{ $dev->code }}">{{ $dev->code }} — {{ $dev->bench_location ?? $dev->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="inject_packet_hex" class="block text-xs font-medium text-slate-700">
                                Packet Hex Payload (Byte Hexadecimal)
                            </label>
                            <input
                                type="text"
                                id="inject_packet_hex"
                                name="packet_hex"
                                placeholder="01 03 0E 01 A0 00 30 06 00 00 20 00 80 00 15 00 30 AA BB"
                                value="01 03 0E 01 A0 00 30 06 00 00 20 00 80 00 15 00 30 AA BB"
                                required
                                class="mt-1 w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Spasi akan dibersihkan otomatis oleh sistem.</p>
                        </div>

                        <div>
                            <label for="inject_level" class="block text-xs font-medium text-slate-700">
                                Tingkat Keparahan (Level)
                            </label>
                            <select
                                id="inject_level"
                                name="level"
                                required
                                class="mt-1 w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                            >
                                <option value="INFO">INFO — Transmisi Telemetri Normal</option>
                                <option value="WARN">WARN — Peringatan Deviasi / Jangkauan</option>
                                <option value="ERROR">ERROR — Kerusakan Frame / Timeout</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-lg text-[11px] text-slate-600 leading-relaxed border border-slate-200">
                        <strong class="text-slate-800">Transparansi Provenance:</strong> Frame ini akan dicatat sebagai <em>Simulasi Injeksi</em> tanpa memicu transmisi fisik atau mengubah status operasional perangkat keras asli.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showInjectModal = false"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-3.5 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors"
                        >
                            Suntikkan Frame
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
