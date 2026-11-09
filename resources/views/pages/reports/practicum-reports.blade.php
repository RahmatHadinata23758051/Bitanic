@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Unboxed Header & Action Bar matching approved Monitoring IoT & Analytics -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Laporan LKS
            </h1>
            <p class="text-xs text-slate-500">
                Pengumpulan, pemeriksaan, dan penilaian hasil praktikum
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ url('/reports') }}" class="flex items-center gap-2">
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
                href="{{ url('/modules') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors"
                title="Lihat katalog 12 modul praktikum ATPH"
            >
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Katalog 12 Modul</span>
            </a>
        </div>
    </div>

    <!-- Contextual Summary Strip (4 Cards) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Total Pengumpulan</div>
            <div class="text-xl font-bold text-slate-900 font-tabular mt-0.5">
                {{ number_format($stats['total'], 0, ',', '.') }} <span class="text-xs font-normal text-slate-500">LKS</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Seluruh modul praktikum</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Menunggu Review</div>
            <div class="text-xl font-bold font-tabular mt-0.5 {{ $stats['submitted'] > 0 ? 'text-amber-700' : 'text-slate-700' }}">
                {{ number_format($stats['submitted'], 0, ',', '.') }} <span class="text-xs font-normal text-slate-500">LKS</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Perlu verifikasi &amp; nilai guru</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Selesai Dinilai</div>
            <div class="text-xl font-bold font-tabular mt-0.5 {{ $stats['graded'] > 0 ? 'text-emerald-700' : 'text-slate-700' }}">
                {{ number_format($stats['graded'], 0, ',', '.') }} <span class="text-xs font-normal text-slate-500">LKS</span>
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Telah diberi skor rubrik</div>
        </div>

        <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-2xs">
            <div class="text-[11px] font-medium text-slate-500">Rata-Rata Nilai</div>
            <div class="text-xl font-bold text-slate-900 font-tabular mt-0.5">
                {{ $stats['avg_grade'] !== null ? number_format($stats['avg_grade'], 1, ',', '.') : '—' }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Skala 0 – 100 (laporan dinilai)</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs">
        <form method="GET" action="{{ url('/reports') }}" class="flex flex-wrap items-center gap-3">
            @if($isSuperuser && $selectedSchoolId)
                <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
            @endif

            <div class="flex items-center gap-2">
                <label for="status" class="text-xs font-semibold text-slate-600">Status:</label>
                <select
                    id="status"
                    name="status"
                    onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Status</option>
                    <option value="submitted" {{ $currentStatus === 'submitted' ? 'selected' : '' }}>Menunggu Review</option>
                    <option value="graded" {{ $currentStatus === 'graded' ? 'selected' : '' }}>Selesai Dinilai</option>
                    <option value="revision_needed" {{ $currentStatus === 'revision_needed' ? 'selected' : '' }}>Perlu Revisi</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label for="module_id" class="text-xs font-semibold text-slate-600">Modul:</label>
                <select
                    id="module_id"
                    name="module_id"
                    onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 max-w-xs"
                >
                    <option value="">Semua Modul Praktikum</option>
                    @foreach($modules as $m)
                        <option value="{{ $m->id }}" {{ (string)$currentModuleId === (string)$m->id ? 'selected' : '' }}>
                            #{{ $m->meeting_number }} — {{ $m->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(isset($classrooms) && $classrooms->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label for="class" class="text-xs font-semibold text-slate-600">Kelas:</label>
                    <select
                        id="class"
                        name="class"
                        onchange="this.form.submit()"
                        class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">Semua Kelas</option>
                        @foreach($classrooms as $cls)
                            <option value="{{ $cls->name }}" {{ (string)$currentClass === (string)$cls->name ? 'selected' : '' }}>
                                {{ $cls->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($currentStatus || $currentModuleId || $currentClass)
                <a href="{{ url('/reports' . ($isSuperuser && $selectedSchoolId ? '?school_id='.$selectedSchoolId : '')) }}" class="text-xs font-semibold text-rose-600 hover:text-rose-700 transition-colors">
                    Reset Filter
                </a>
            @endif
        </form>
    </div>

    <!-- Reports Table -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Arsip Pengumpulan Lembar Kerja Siswa</h2>
                <p class="text-[11px] text-slate-500">Penilaian rubrik 4-skala dan bukti telemetri sensor praktikum</p>
            </div>
            <div class="text-[11px] text-slate-500 font-medium">
                Total data: <span class="font-semibold text-slate-800 font-tabular">{{ $reports->total() }}</span> laporan
            </div>
        </div>

        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-600 text-[11px] font-semibold">
                        <th class="py-2.5 px-3">Praktikan</th>
                        <th class="py-2.5 px-3">Modul &amp; Pertemuan</th>
                        <th class="py-2.5 px-3">Snapshot Sensor Telemetri</th>
                        <th class="py-2.5 px-3">Waktu Kumpul</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Nilai</th>
                        <th class="py-2.5 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($reports as $r)
                        @php
                            $snap = $r->telemetry_snapshot ?? [];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-900">{{ $r->user->name ?? 'Siswa Praktikan' }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    <span class="font-mono">{{ $r->user->profile->identifier_number ?? 'NISN —' }}</span>
                                    <span class="text-slate-300 mx-1">·</span>
                                    <span>{{ $r->user->profile->class_grade ?? 'Kelas ATPH' }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800 line-clamp-1">
                                    #{{ $r->module->meeting_number ?? '-' }} {{ $r->module->title ?? '-' }}
                                </div>
                                <span class="inline-block mt-0.5 text-[10px] font-medium text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                    {{ $r->module->badge ?? 'Praktikum' }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @if(!empty($snap))
                                    <div class="flex items-center gap-1.5 font-mono text-[11px] text-slate-700 flex-wrap">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-tabular">pH: {{ $snap['ph'] ?? '-' }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-tabular">Lengas: {{ $snap['moisture'] ?? '-' }}%</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-tabular">EC: {{ $snap['ec'] ?? '-' }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Tidak ada snapshot</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap font-tabular text-[11px] text-slate-600 font-medium">
                                {{ $r->created_at ? $r->created_at->format('d/m/Y H:i') : '-' }} WIB
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($r->status === 'graded')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Selesai Dinilai
                                    </span>
                                @elseif($r->status === 'submitted')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Menunggu Review
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Perlu Revisi
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-tabular font-bold text-sm whitespace-nowrap">
                                @if($r->final_grade !== null)
                                    <span class="text-slate-900">{{ number_format($r->final_grade, 0) }}</span>
                                    <span class="text-xs text-slate-400 font-normal">/100</span>
                                @else
                                    <span class="text-slate-400 font-normal text-xs">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                <a
                                    href="{{ url('/modules/' . ($r->module_id ?? 1)) }}"
                                    class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 font-semibold text-xs transition-colors inline-flex items-center gap-1.5"
                                >
                                    <span>Buka Modul &amp; Rubrik</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                    <i data-lucide="file-text" class="w-6 h-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-700">Belum ada laporan praktikum</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                    Belum ada laporan praktikum yang sesuai dengan filter yang dipilih.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div class="pt-4 border-t border-slate-200">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
