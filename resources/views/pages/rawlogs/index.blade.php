@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-400/10 text-emerald-300 border border-emerald-400/30">
                    RAW SERIAL STREAM
                </span>
                <span class="text-xs text-slate-500">•</span>
                <span class="text-xs font-semibold text-slate-400">Parser Diagnostik RS485/Modbus RTU</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight font-mono">
                Debugger Frame Hex Telemetri IoT
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-2xl">
                Audit aliran byte serial, dekode register sensor 7-in-1, dan diagnosis error checksum atau CRC Modbus dari gateway ESP32 di meja praktik.
            </p>
        </div>

        <!-- Connection Status & Honest Provenance Indicator -->
        <div class="flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300 shrink-0">
            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
            <span>Ingress Debugger Stream (Modbus RS485)</span>
        </div>
    </div>

    <!-- 4 Stats Counters -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 font-mono">
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Paket</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-2">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Informasi</span>
            <div class="text-2xl font-extrabold text-emerald-700 mt-2">{{ number_format($stats['info']) }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Peringatan</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-2">{{ number_format($stats['warnings']) }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Frame Error</span>
            <div class="text-2xl font-extrabold text-rose-600 mt-2">{{ number_format($stats['errors']) }}</div>
        </div>
    </div>

    <!-- Modbus Frame Explainer -->
    <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800">Struktur Paket Modbus RTU 7-in-1 (Hex Decoder)</h3>
            <span class="text-[10px] font-mono text-slate-400">Function Code 03h (Read Holding Registers)</span>
        </div>
        <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px]">
            <div class="px-3 py-2 bg-slate-900 rounded text-white"><span class="text-slate-400 block mb-1">[Slave ID]</span>01</div>
            <div class="px-3 py-2 bg-indigo-900 rounded text-indigo-100"><span class="text-indigo-300 block mb-1">[Function]</span>03</div>
            <div class="px-3 py-2 bg-indigo-900 rounded text-indigo-100"><span class="text-indigo-300 block mb-1">[Reg High]</span>00</div>
            <div class="px-3 py-2 bg-indigo-900 rounded text-indigo-100"><span class="text-indigo-300 block mb-1">[Reg Low]</span>00</div>
            <div class="px-3 py-2 bg-amber-900 rounded text-amber-100"><span class="text-amber-300 block mb-1">[Len High]</span>00</div>
            <div class="px-3 py-2 bg-amber-900 rounded text-amber-100"><span class="text-amber-300 block mb-1">[Len Low]</span>07</div>
            <div class="px-3 py-2 bg-emerald-900 rounded text-emerald-100"><span class="text-emerald-300 block mb-1">[Data Bytes]</span>0E</div>
            <div class="px-3 py-2 bg-slate-700 rounded text-slate-200"><span class="text-slate-400 block mb-1">[Sensor Data...]</span>01 9A ...</div>
            <div class="px-3 py-2 bg-rose-900 rounded text-rose-100"><span class="text-rose-300 block mb-1">[CRC16]</span>AB CD</div>
        </div>
        <p class="text-[11px] text-slate-500">Frame normal menampilkan 7 register nilai sensor dengan response length 14 byte data. CRC16 divalidasi untuk mendeteksi korupsi transmisi.</p>
    </div>

    <!-- Log Console & Table -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Log Debug Serial Port</h3>
                <p class="text-xs text-slate-500">Record diagnostik logger; tampilan ini tidak mengindikasikan koneksi COM atau aktuasi perangkat aktif.</p>
            </div>

            <!-- Filter Form -->
            <div class="flex flex-wrap items-center gap-2">
                @if(!empty($isSuperuser) && $schools->isNotEmpty())
                    <form method="GET" action="{{ url('/raw-logs') }}" class="flex items-center gap-2">
                        <label for="school_id" class="text-xs font-semibold text-slate-600">Sekolah:</label>
                        <select id="school_id" name="school_id" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">Semua Sekolah</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <form method="GET" action="{{ url('/raw-logs') }}" class="flex flex-wrap items-center gap-2">
                    @if(!empty($selectedSchoolId))
                        <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                    @endif
                    <label for="kit_code" class="sr-only">Filter kit</label>
                    <select id="kit_code" name="kit_code" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Semua Kit</option>
                        @foreach($devices as $dev)
                            <option value="{{ $dev->code }}" {{ $currentKit === $dev->code ? 'selected' : '' }}>{{ $dev->code }}</option>
                        @endforeach
                    </select>
                    <label for="level" class="sr-only">Filter level</label>
                    <select id="level" name="level" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">Semua Level</option>
                        <option value="INFO" {{ $currentLevel === 'INFO' ? 'selected' : '' }}>INFO</option>
                        <option value="WARN" {{ $currentLevel === 'WARN' ? 'selected' : '' }}>WARN</option>
                        <option value="ERROR" {{ $currentLevel === 'ERROR' ? 'selected' : '' }}>ERROR</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-800 bg-slate-950">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-900 text-slate-400 text-[10px] uppercase tracking-wider sticky top-0">
                    <tr>
                        <th class="py-2.5 px-4">Timestamp</th>
                        <th class="py-2.5 px-4">Level</th>
                        <th class="py-2.5 px-4">Kit ID</th>
                        <th class="py-2.5 px-4">Protocol</th>
                        <th class="py-2.5 px-4">Sumber Provenance</th>
                        <th class="py-2.5 px-4">Hex Stream Payload</th>
                        <th class="py-2.5 px-4 text-right">Parsed Sensor Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($logs as $log)
                        @php $payload = $log->parsed_payload ?? []; @endphp
                        <tr class="hover:bg-slate-900/50">
                            <td class="py-2.5 px-4 text-slate-500">{{ $log->recorded_at?->format('H:i:s.u') ?? '-' }}</td>
                            <td class="py-2.5 px-4">
                                <span class="font-bold {{ $log->level === 'ERROR' ? 'text-rose-400' : ($log->level === 'WARN' ? 'text-amber-400' : 'text-emerald-400') }}">
                                    {{ $log->level ?? 'INFO' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 font-bold text-indigo-300">{{ $log->kit_code ?? $log->device?->code ?? '-' }}</td>
                            <td class="py-2.5 px-4 text-slate-500">{{ $log->protocol ?? 'Modbus RTU' }}</td>
                            <td class="py-2.5 px-4">
                                @if(!empty($payload['injected_manually']))
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-amber-950/80 text-amber-300 border border-amber-800 font-bold">
                                        Simulasi Injeksi
                                    </span>
                                @elseif(($payload['source'] ?? '') === 'sensor_gateway')
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-950/80 text-emerald-300 border border-emerald-800 font-bold">
                                        Gateway Sensor
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-slate-800 text-slate-400 border border-slate-700 font-bold">
                                        Serial Ingress
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-amber-300 tracking-widest">{{ $log->packet_hex ?? '-' }}</td>
                            <td class="py-2.5 px-4 text-right text-[10px]">
                                @if(!empty($payload))
                                    {{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                @else
                                    <span class="text-slate-600 italic">Belum diparse</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 italic text-xs">
                                Belum ada catatan frame serial untuk sekolah atau stasiun meja ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="pt-4 border-t border-slate-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
