@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    LAPORAN PRAKTIKUM
                </span>
                @if($isSuperuser && $selectedSchoolId)
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs font-semibold text-slate-600">{{ $schools->firstWhere('id', (int) $selectedSchoolId)?->name ?? 'Sekolah tidak aktif atau tidak ditemukan' }}</span>
                @endif
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">Verifikasi LKS & Snapshot Sensor Meja</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Hub Laporan Lembar Kerja Siswa (LKS)
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Guru pembimbing dapat memeriksa jawaban LKS, menganalisis kesesuaian snapshot data telemetri yang direkam praktikan, serta memasukkan skor rubrik 4-skala.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a
                href="{{ url('/modules') }}"
                class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-2 shadow-sm transition-colors"
            >
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Katalog 12 Modul</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards Bento -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Pengumpulan</span>
            <div class="font-tabular text-2xl font-extrabold text-slate-900 mt-2">{{ $stats['total'] }}</div>
            <span class="text-[11px] text-slate-500 mt-1 block">LKS dari seluruh modul</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Menunggu Review</span>
            <div class="font-tabular text-2xl font-extrabold text-amber-600 mt-2">{{ $stats['submitted'] }}</div>
            <span class="text-[11px] text-slate-500 mt-1 block">Perlu verifikasi guru</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Selesai Dinilai</span>
            <div class="font-tabular text-2xl font-extrabold text-emerald-700 mt-2">{{ $stats['graded'] }}</div>
            <span class="text-[11px] text-slate-500 mt-1 block">Telah diberi umpan balik</span>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rata-Rata Nilai</span>
            <div class="font-tabular text-2xl font-extrabold text-indigo-700 mt-2">
                {{ $stats['avg_grade'] > 0 ? number_format($stats['avg_grade'], 1) : '-' }}
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Skala 0 – 100</span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ url('/reports') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="status" class="text-xs font-semibold text-slate-600">Status:</label>
                <select
                    id="status"
                    name="status"
                    onchange="this.form.submit()"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
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
                    class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 max-w-xs"
                >
                    <option value="">Semua Modul Praktikum</option>
                    @foreach($modules as $m)
                        <option value="{{ $m->id }}" {{ (string)$currentModuleId === (string)$m->id ? 'selected' : '' }}>
                            #{{ $m->meeting_number }} — {{ $m->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if($currentStatus || $currentModuleId)
                <a href="{{ url('/reports') }}" class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                    Reset Filter
                </a>
            @endif
        </form>
    </div>

    <!-- Reports Table -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50">
                        <th class="py-2.5 px-3">Praktikan</th>
                        <th class="py-2.5 px-3">Modul & Pertemuan</th>
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
                                <div class="font-bold text-slate-900">{{ $r->user->name ?? 'Siswa Praktikan' }}</div>
                                <div class="font-mono text-[11px] text-slate-500">
                                    {{ $r->user->profile->identifier_number ?? 'NISN -' }} • {{ $r->user->profile->class_grade ?? 'XI ATPH' }}
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800">
                                    #{{ $r->module->meeting_number ?? '-' }} {{ $r->module->title ?? '-' }}
                                </div>
                                <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                    {{ $r->module->badge ?? 'Praktikum' }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @if(!empty($snap))
                                    <div class="flex items-center gap-2 font-mono text-[11px] text-slate-700">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100">pH: {{ $snap['ph'] ?? '-' }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100">Lengas: {{ $snap['moisture'] ?? '-' }}%</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100">EC: {{ $snap['ec'] ?? '-' }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Tidak ada snapshot</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-slate-500">
                                {{ $r->created_at ? $r->created_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                @if($r->status === 'graded')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                        Selesai Dinilai
                                    </span>
                                @elseif($r->status === 'submitted')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                        Menunggu Review
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800">
                                        Perlu Revisi
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-sm">
                                @if($r->final_grade !== null)
                                    <span class="text-indigo-700">{{ number_format($r->final_grade, 0) }}</span>
                                    <span class="text-xs text-slate-400 font-normal">/100</span>
                                @else
                                    <span class="text-slate-400 font-normal text-xs">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a
                                    href="{{ url('/modules/' . ($r->module_id ?? 1)) }}"
                                    class="px-2.5 py-1 rounded bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 font-semibold text-xs transition-colors inline-flex items-center gap-1"
                                >
                                    <span>Buka Modul & Rubrik</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                Belum ada laporan praktikum yang sesuai dengan filter yang dipilih.
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
