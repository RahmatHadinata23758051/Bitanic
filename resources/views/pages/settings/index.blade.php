@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    SISTEM & AUDIT
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian Pembangunan Negeri 1 Sukaraja</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Pengaturan Lab & Log Aktivitas Sistem
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Kelola profil kejuruan sekolah, ambang batas ideal agronomi untuk alarm sensor, serta telusuri rekam jejak append-only audit trail dari setiap mutasi data.
            </p>
        </div>
    </div>

    <!-- 2 Column Layout: Settings Form & Sensor Thresholds -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Col 1: Profil Sekolah & Kurikulum -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Profil Laboratorium & Kurikulum</h3>
                <p class="text-xs text-slate-500">Konfigurasi entitas akademik sekolah dan penanggung jawab lab</p>
            </div>

            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-700">Nama Sekolah:</label>
                    <input
                        type="text"
                        name="school_name"
                        value="{{ old('school_name', $setting->school_name ?? '') }}"
                        required
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Tahun Akademik:</label>
                        <input
                            type="text"
                            name="academic_year"
                            value="{{ old('academic_year', $setting->academic_year ?? '2026/2027') }}"
                            required
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Semester:</label>
                        <input
                            type="text"
                            name="semester"
                            value="{{ old('semester', $setting->semester ?? 'Ganjil') }}"
                            required
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-700">Program Keahlian:</label>
                    <input
                        type="text"
                        name="course_name"
                        value="{{ old('course_name', $setting->course_name ?? 'Agribisnis Tanaman Pangan & Hortikultura') }}"
                        required
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-700">Kepala Lab / Guru Pembina:</label>
                    <input
                        type="text"
                        name="lead_teacher"
                        value="{{ old('lead_teacher', $setting->lead_teacher ?? 'Ir. Ahmad Suryanto, S.P., M.Pd.') }}"
                        required
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>

                <div class="pt-2">
                    <h4 class="text-xs font-bold text-slate-900 mb-2">Ambang Batas Agronomi Standar</h4>
                    @php $th = $setting->sensor_thresholds ?? []; @endphp
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="text-slate-600 block mb-1">pH Min – Max:</label>
                            <div class="flex items-center gap-1">
                                <input type="number" step="0.1" name="thresholds[minPH]" value="{{ $th['minPH'] ?? 5.5 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                                <span>-</span>
                                <input type="number" step="0.1" name="thresholds[maxPH]" value="{{ $th['maxPH'] ?? 7.2 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="text-slate-600 block mb-1">Lengas (%) Min – Max:</label>
                            <div class="flex items-center gap-1">
                                <input type="number" step="1" name="thresholds[minMoisture]" value="{{ $th['minMoisture'] ?? 30 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                                <span>-</span>
                                <input type="number" step="1" name="thresholds[maxMoisture]" value="{{ $th['maxMoisture'] ?? 75 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="text-slate-600 block mb-1">EC (µS/cm) Min – Max:</label>
                            <div class="flex items-center gap-1">
                                <input type="number" step="50" name="thresholds[minEC]" value="{{ $th['minEC'] ?? 800 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                                <span>-</span>
                                <input type="number" step="50" name="thresholds[maxEC]" value="{{ $th['maxEC'] ?? 2200 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="text-slate-600 block mb-1">Nitrogen (mg/kg) Min – Max:</label>
                            <div class="flex items-center gap-1">
                                <input type="number" step="5" name="thresholds[minNitrogen]" value="{{ $th['minNitrogen'] ?? 40 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                                <span>-</span>
                                <input type="number" step="5" name="thresholds[maxNitrogen]" value="{{ $th['maxNitrogen'] ?? 120 }}" class="w-full px-2 py-1.5 rounded border border-slate-300 text-xs font-mono">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex justify-end">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-sm"
                    >
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>

        <!-- Col 2: Append-Only Audit Trail -->
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Rekam Jejak Aktivitas (Audit Trail)</h3>
                    <p class="text-xs text-slate-500">Log perubahan konfigurasi dan kontrol relai meja praktikum</p>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600">
                    Append-Only
                </span>
            </div>

            <div class="space-y-3">
                @forelse($auditLogs as $log)
                    <div class="p-3 rounded-lg border border-slate-200/80 bg-slate-50/60 space-y-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-slate-900">{{ $log->actor_name ?? 'Sistem' }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-slate-200 text-slate-700">
                                    {{ $log->actor_role ?? 'user' }}
                                </span>
                            </div>
                            <span class="text-[11px] font-mono text-slate-400">
                                {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-700 font-medium">{{ $log->details }}</p>
                        <div class="flex items-center justify-between text-[10px] font-mono text-slate-400 pt-1">
                            <span>Aksi: {{ $log->action }}</span>
                            <span>IP: {{ $log->ip_address ?? '127.0.0.1' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-400">
                        Belum ada aktivitas mutasi yang tercatat dalam audit trail.
                    </div>
                @endforelse
            </div>

            @if($auditLogs->hasPages())
                <div class="pt-2">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
