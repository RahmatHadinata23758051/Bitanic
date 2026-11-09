@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Unboxed Header & Action Bar matching approved Monitoring IoT & Analytics -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Rapor Kompetensi
            </h1>
            <p class="text-xs text-slate-500">
                Rekap hasil pembelajaran dan pencapaian kompetensi siswa
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ url('/assessment') }}" class="flex items-center gap-2">
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

            <a
                href="{{ url('/assessment/export' . ($currentClass ? '?class=' . urlencode($currentClass) : '') . ($selectedSchoolId ? ($currentClass ? '&' : '?') . 'school_id=' . $selectedSchoolId : '')) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition-colors"
                title="Unduh rekapitulasi nilai kompetensi siswa format CSV"
            >
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Ekspor CSV</span>
            </a>

            @if(!empty($user) && ($user->isGuru() || $user->isSuperuser()))
                <a
                    href="{{ url('/reports') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
                    title="Periksa dan nilai LKS praktikan"
                >
                    <i data-lucide="file-check" class="w-4 h-4"></i>
                    <span>Tinjau LKS Praktikan</span>
                </a>
            @endif
        </div>
    </div>

    <!-- 4 Stats Summary Cards Bento -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Total Praktikan</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ $stats['total_students'] }} <span class="text-xs font-normal text-slate-500">Siswa</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Terdaftar aktif di sistem</div>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Siswa Tersertifikasi</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ $stats['certified'] }} <span class="text-xs font-normal text-slate-500">Siswa</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ $stats['certified'] > 0 ? 'Uji operasional IoT' : 'Belum ada sertifikasi diterbitkan' }}
            </div>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Predikat A &amp; B</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                {{ $stats['pred_a'] + $stats['pred_b'] }} <span class="text-xs font-normal text-slate-500">Siswa</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Sangat Baik &amp; Baik</div>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
            <div class="text-xs font-medium text-slate-500">Rata-Rata Angkatan</div>
            <div class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">
                @if($stats['avg_score'] !== null)
                    {{ number_format($stats['avg_score'], 1) }}
                @else
                    —
                @endif
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Skala penilaian 0 – 100</div>
        </div>
    </div>

    <!-- Student Gradebook Table -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Rekapitulasi Capaian Kompetensi Siswa</h3>
                <p class="text-xs text-slate-500">Buku induk nilai asesmen kompetensi kejuruan dan status sertifikasi Trainer Kit</p>
            </div>

            <!-- Class Filter -->
            <form method="GET" action="{{ url('/assessment') }}" class="flex flex-wrap items-center gap-2">
                @if($isSuperuser && $selectedSchoolId)
                    <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                @endif
                <label for="class" class="text-xs font-semibold text-slate-600">Kelas:</label>
                <select
                    id="class"
                    name="class"
                    onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Kelas</option>
                    @foreach($availableClasses as $classOption)
                        <option value="{{ $classOption }}" {{ (string)$currentClass === (string)$classOption ? 'selected' : '' }}>
                            {{ $classOption }}
                        </option>
                    @endforeach
                </select>
                @if($currentClass)
                    <a href="{{ url('/assessment' . ($selectedSchoolId ? '?school_id='.$selectedSchoolId : '')) }}" class="text-xs text-rose-600 hover:text-rose-700 font-semibold inline-flex items-center gap-1 ml-1">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        <span>Reset Filter</span>
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold bg-slate-50/75">
                        <th class="py-3 px-3">Siswa Praktikan</th>
                        <th class="py-3 px-3">Kelas & Kelompok</th>
                        <th class="py-3 px-3">Meja Trainer Kit</th>
                        <th class="py-3 px-3 text-center">Presensi (%)</th>
                        <th class="py-3 px-3 text-center">Rata-Rata Nilai</th>
                        <th class="py-3 px-3 text-center">Predikat</th>
                        <th class="py-3 px-3 text-right">Sertifikasi IoT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($students as $s)
                        @php
                            $profile = $s->profile;
                            $grade = $profile?->average_grade !== null ? (float) $profile->average_grade : null;
                            $pred = $profile?->competency_predicative ?? ($grade !== null ? ($grade >= 85 ? 'A' : ($grade >= 75 ? 'B' : ($grade >= 65 ? 'C' : 'D'))) : null);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-3">
                                <div class="font-semibold text-slate-900">{{ $s->name }}</div>
                                <div class="tabular-nums text-[11px] text-slate-500">{{ $profile?->identifier_number ?? 'NISN —' }}</div>
                            </td>
                            <td class="py-3.5 px-3">
                                <div class="font-semibold text-slate-800">{{ $profile?->class_grade ?? ($profile?->classroom?->name ?? '—') }}</div>
                                <span class="text-[11px] text-slate-500">{{ $profile?->group_name ?? ($profile?->studentGroup?->name ?? 'Kelompok —') }}</span>
                            </td>
                            <td class="py-3.5 px-3">
                                @if($profile?->assigned_kit_id)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold font-mono bg-slate-100 text-slate-700">
                                        TK-0{{ $profile->assigned_kit_id }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Belum Ditugaskan</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-center tabular-nums font-medium">
                                @if($profile?->attendance_percent !== null)
                                    <span class="{{ (float)$profile->attendance_percent >= 90 ? 'text-emerald-700' : 'text-amber-600' }}">
                                        {{ number_format((float)$profile->attendance_percent, 1) }}%
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-center tabular-nums font-semibold text-sm">
                                @if($grade !== null)
                                    <span class="text-slate-900">{{ number_format($grade, 1) }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                @if($pred)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $pred === 'A' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($pred === 'B' ? 'bg-slate-100 text-slate-700 border border-slate-200' : ($pred === 'C' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200')) }}">
                                        {{ $pred }}
                                    </span>
                                @else
                                    <span class="text-slate-400 tabular-nums">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3 text-right">
                                @if(!empty($profile?->trainer_certified))
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                                        <i data-lucide="award" class="w-3.5 h-3.5"></i>
                                        <span>Tersertifikasi</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 bg-slate-50 px-2.5 py-1 rounded-full border border-slate-200">
                                        <span>Proses Belajar</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white">
                            <td colspan="7" class="py-12 px-4 text-center">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-50 border border-slate-200 text-slate-400 mb-3">
                                    <i data-lucide="award" class="w-6 h-6"></i>
                                </div>
                                <div class="text-sm font-semibold text-slate-800">Belum Ada Data Rapor Siswa</div>
                                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                                    Data penilaian kompetensi praktikum akan muncul setelah siswa mengumpulkan LKS dan dinilai oleh guru.
                                </p>
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
