@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    RAPOR PRAKTIKUM
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Kurikulum Merdeka SMK Pertanian 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Penilaian Kompetensi & Rapor Siswa
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Rekapitulasi pencapaian kompetensi agronomi dan pengoperasian Trainer Kit IoT presisi bagi siswa kejuruan Agribisnis Tanaman Pangan & Hortikultura.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a
                href="{{ url('/reports') }}"
                class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-2 shadow-sm transition-colors"
            >
                <i data-lucide="file-check" class="w-4 h-4"></i>
                <span>Review LKS Siswa</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards Bento -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Praktikan</span>
            <div class="font-tabular text-2xl font-extrabold text-slate-900 mt-2">{{ $stats['total_students'] }} Siswa</div>
            <span class="text-[11px] text-slate-500 mt-1 block">Terdaftar aktif di laboratorium</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Sertifikasi Trainer Kit</span>
            <div class="font-tabular text-2xl font-extrabold text-emerald-700 mt-2">{{ $stats['certified'] }} Tersertifikasi</div>
            <span class="text-[11px] text-slate-500 mt-1 block">Kompeten uji operasional IoT</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Predikat A & B</span>
            <div class="font-tabular text-2xl font-extrabold text-indigo-700 mt-2">
                {{ $stats['pred_a'] + $stats['pred_b'] }} Siswa
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Predikat Sangat Baik & Baik</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rata-Rata Angkatan</span>
            <div class="font-tabular text-2xl font-extrabold text-slate-900 mt-2">
                {{ number_format($stats['avg_score'], 1) }}
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Skala penilaian 0 – 100</span>
        </div>
    </div>

    <!-- Student Gradebook Table -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Rekapitulasi Capaian Kompetensi Praktikum</h3>
                <p class="text-xs text-slate-500">Nilai rata-rata LKS modul 1 s/d 12 dan predikat akhir kompetensi</p>
            </div>

            <!-- Class Filter -->
            <form method="GET" action="{{ url('/assessment') }}" class="flex items-center gap-2">
                <label for="class" class="text-xs font-semibold text-slate-600">Kelas:</label>
                <select
                    id="class"
                    name="class"
                    onchange="this.form.submit()"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                >
                    <option value="">Semua Kelas</option>
                    <option value="XI ATPH 1" {{ $currentClass === 'XI ATPH 1' ? 'selected' : '' }}>XI ATPH 1</option>
                    <option value="XI ATPH 2" {{ $currentClass === 'XI ATPH 2' ? 'selected' : '' }}>XI ATPH 2</option>
                </select>
                @if($currentClass)
                    <a href="{{ url('/assessment') }}" class="text-xs text-rose-600 hover:text-rose-700 font-semibold">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50">
                        <th class="py-2.5 px-3">Siswa Praktikan</th>
                        <th class="py-2.5 px-3">Kelas & Kelompok</th>
                        <th class="py-2.5 px-3">Meja Trainer Kit</th>
                        <th class="py-2.5 px-3 text-center">Presensi (%)</th>
                        <th class="py-2.5 px-3 text-center">Rata-Rata Nilai</th>
                        <th class="py-2.5 px-3 text-center">Predikat</th>
                        <th class="py-2.5 px-3 text-right">Sertifikasi IoT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($students as $s)
                        @php
                            $profile = $s->profile;
                            $grade = $profile?->average_grade ?? 0;
                            $pred = $profile?->competency_predicative ?? ($grade >= 85 ? 'A' : ($grade >= 75 ? 'B' : ($grade >= 65 ? 'C' : 'D')));
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ $s->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500">{{ $profile?->identifier_number ?? 'NISN -' }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800">{{ $profile?->class_grade ?? 'XI ATPH 1' }}</div>
                                <span class="text-[11px] text-slate-500">{{ $profile?->group_name ?? 'Kelompok' }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold text-slate-700">
                                {{ $profile?->assigned_kit_id ? 'TK-0' . $profile->assigned_kit_id : 'Belum Ditugaskan' }}
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-semibold">
                                <span class="{{ ($profile?->attendance_percent ?? 95) >= 90 ? 'text-emerald-700' : 'text-amber-600' }}">
                                    {{ $profile?->attendance_percent ?? 95 }}%
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold text-sm text-indigo-700">
                                {{ number_format($grade, 1) }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold {{ $pred === 'A' ? 'bg-emerald-100 text-emerald-800' : ($pred === 'B' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $pred }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                @if(!empty($profile?->trainer_certified))
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        <i data-lucide="award" class="w-3.5 h-3.5"></i>
                                        <span>Certified</span>
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-400">Proses Belajar</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                Belum ada data siswa terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="pt-4 border-t border-slate-200">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
