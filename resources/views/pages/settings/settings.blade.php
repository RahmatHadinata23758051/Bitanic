@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    activeTab: '{{ request()->has('page') ? 'audit' : 'config' }}'
}">
    <!-- Page Header (Unboxed, Bitanic Standard) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Pengaturan
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Konfigurasi sekolah, parameter agronomi, dan riwayat aktivitas
            </p>
        </div>

        <!-- School Scope Badge -->
        <div class="flex items-center gap-2.5 shrink-0">
            <span class="text-xs text-slate-600 font-medium bg-white px-3 py-1.5 rounded-xl border border-slate-200/80 shadow-2xs">
                {{ $setting->school_name }}
            </span>
        </div>
    </div>

    <!-- Flash Messages / Alerts -->
    @if (session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Perhatian: Ada kesalahan input konfigurasi</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Section Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200/80 pb-2">
        <button
            type="button"
            @click="activeTab = 'config'"
            :class="activeTab === 'config' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200/80'"
            class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2"
        >
            <i data-lucide="sliders" class="w-3.5 h-3.5"></i>
            <span>Konfigurasi Laboratorium</span>
        </button>
        <button
            type="button"
            @click="activeTab = 'audit'"
            :class="activeTab === 'audit' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200/80'"
            class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2"
        >
            <i data-lucide="history" class="w-3.5 h-3.5"></i>
            <span>Riwayat Aktivitas (Audit Trail)</span>
        </button>
    </div>

    <!-- Tab 1: Konfigurasi Laboratorium Form -->
    <div x-show="activeTab === 'config'" class="space-y-6">
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
            @csrf

            <!-- Section 1: Profil Sekolah & Kurikulum -->
            <div class="p-5 sm:p-6 rounded-xl bg-white border border-slate-200/80 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Profil Laboratorium &amp; Kurikulum</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Identitas sekolah, tahun ajaran, dan penanggung jawab teknis laboratorium.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Nama Sekolah *</label>
                        <input
                            type="text"
                            name="school_name"
                            value="{{ old('school_name', $setting->school_name ?? '') }}"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Tahun Akademik *</label>
                        <input
                            type="text"
                            name="academic_year"
                            value="{{ old('academic_year', $setting->academic_year ?? '2026/2027') }}"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Semester *</label>
                        <input
                            type="text"
                            name="semester"
                            value="{{ old('semester', $setting->semester ?? 'Ganjil') }}"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Program Keahlian *</label>
                        <input
                            type="text"
                            name="course_name"
                            value="{{ old('course_name', $setting->course_name ?? 'Agribisnis Tanaman Pangan & Hortikultura') }}"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Kepala Lab / Guru Pembina *</label>
                        <input
                            type="text"
                            name="lead_teacher"
                            value="{{ old('lead_teacher', $setting->lead_teacher ?? 'Ir. Ahmad Suryanto, S.P., M.Pd.') }}"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]"
                        >
                    </div>
                </div>
            </div>

            <!-- Section 2: Ambang Batas Agronomi Standar (7-in-1 Modbus RS485) -->
            <div class="p-5 sm:p-6 rounded-xl bg-white border border-slate-200/80 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Ambang Batas Agronomi Standar (7-in-1 Sensor)</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Rentang ideal telemetri tanah untuk deteksi deviasi dan peringatan praktikum.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200/60 self-start sm:self-auto">
                        Kurikulum ATPH 4.0
                    </span>
                </div>

                @php $th = $setting->sensor_thresholds ?? []; @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- pH -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Derajat Keasaman (pH)</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">pH</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="0.1" name="thresholds[minPH]" value="{{ $th['minPH'] ?? 5.5 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="0.1" name="thresholds[maxPH]" value="{{ $th['maxPH'] ?? 7.2 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 5.5 – 7.2</span>
                    </div>

                    <!-- Lengas / Moisture -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Lengas Tanah</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">%</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="1" name="thresholds[minMoisture]" value="{{ $th['minMoisture'] ?? 30 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="1" name="thresholds[maxMoisture]" value="{{ $th['maxMoisture'] ?? 75 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 30% – 75%</span>
                    </div>

                    <!-- EC -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Konduktivitas Listrik (EC)</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">µS/cm</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="50" name="thresholds[minEC]" value="{{ $th['minEC'] ?? 800 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="50" name="thresholds[maxEC]" value="{{ $th['maxEC'] ?? 2200 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 800 – 2200 µS/cm</span>
                    </div>

                    <!-- Suhu -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Suhu Tanah</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">°C</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="0.5" name="thresholds[minTemperature]" value="{{ $th['minTemperature'] ?? 22.0 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="0.5" name="thresholds[maxTemperature]" value="{{ $th['maxTemperature'] ?? 29.0 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 22.0 – 29.0 °C</span>
                    </div>

                    <!-- Nitrogen -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Nitrogen (N)</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">mg/kg</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="5" name="thresholds[minNitrogen]" value="{{ $th['minNitrogen'] ?? 40 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="5" name="thresholds[maxNitrogen]" value="{{ $th['maxNitrogen'] ?? 120 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 40 – 120 mg/kg</span>
                    </div>

                    <!-- Fosfor -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Fosfor (P)</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">mg/kg</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="5" name="thresholds[minPhosphorus]" value="{{ $th['minPhosphorus'] ?? 25 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="5" name="thresholds[maxPhosphorus]" value="{{ $th['maxPhosphorus'] ?? 60 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 25 – 60 mg/kg</span>
                    </div>

                    <!-- Kalium -->
                    <div class="p-3.5 rounded-xl bg-slate-50/50 border border-slate-200/80 space-y-2 md:col-span-2 lg:col-span-1">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800">Kalium (K)</span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200/60">mg/kg</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Min</label>
                                <input type="number" step="5" name="thresholds[minPotassium]" value="{{ $th['minPotassium'] ?? 100 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 block mb-1">Max</label>
                                <input type="number" step="5" name="thresholds[maxPotassium]" value="{{ $th['maxPotassium'] ?? 200 }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-tabular text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26]">
                            </div>
                        </div>
                        <span class="text-[10px] text-slate-400 block">Acuan baku: 100 – 200 mg/kg</span>
                    </div>
                </div>

                <!-- Scientific Disclaimer Note -->
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/60 text-slate-500 text-[11px] flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5"></i>
                    <span>
                        Ambang batas ini berfungsi sebagai acuan validasi telemetri 7-in-1 sensor Modbus RS485 dan penentu status deviasi/peringatan agronomi pada stasiun praktik.
                    </span>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="flex items-center justify-end">
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors flex items-center gap-2"
                >
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Simpan Pengaturan</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Tab 2: Riwayat Aktivitas (Audit Trail) -->
    <div x-show="activeTab === 'audit'" class="space-y-4">
        <div class="p-5 sm:p-6 rounded-xl bg-white border border-slate-200/80 shadow-2xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Rekam Jejak Aktivitas (Audit Trail)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Log perubahan konfigurasi dan kontrol relai meja praktikum.</p>
                </div>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200/60">
                    Append-Only
                </span>
            </div>

            <div class="space-y-2.5">
                @forelse($auditLogs as $log)
                    <div class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-slate-300 transition-colors space-y-1.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-xs text-slate-900">{{ $log->actor_name ?? 'Sistem' }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-700 border border-slate-200/60">
                                    {{ $log->actor_role ?? 'user' }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-200/70 text-slate-700">
                                    {{ $log->action }}
                                </span>
                            </div>
                            <span class="text-[11px] font-tabular text-slate-400">
                                {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—' }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-700 leading-relaxed">{{ $log->details }}</p>

                        <div class="flex items-center justify-between text-[10px] font-mono text-slate-400 pt-1 border-t border-slate-100/80 flex-wrap gap-2">
                            @if($log->correlation_id)
                                <span>Ref: {{ \Illuminate\Support\Str::limit($log->correlation_id, 8, '') }}</span>
                            @else
                                <span>Target: {{ $log->target_type ?? 'Sistem' }}</span>
                            @endif
                            <span>IP: {{ $log->ip_address ?? '127.0.0.1' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-slate-400">
                        Belum ada aktivitas mutasi yang tercatat dalam audit trail.
                    </div>
                @endforelse
            </div>

            @if($auditLogs->hasPages())
                <div class="pt-3 border-t border-slate-100">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
