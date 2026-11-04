@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">
                    Kurikulum Vokasi Merdeka
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian ATPH 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                12 Modul Praktikum Pertanian Presisi (Precision Agriculture)
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                Panduan praktikum terstruktur berbasis fenomena (*inquiry learning*) menggunakan BITANIC Trainer Kit, dari instalasi panel K3 hingga analisis panen brix berbasis data.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Modul</span>
                <span class="text-2xl font-extrabold text-slate-900 font-mono">12</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Alokasi Waktu</span>
                <span class="text-2xl font-extrabold text-emerald-700 font-mono">36 JP</span>
            </div>
        </div>
    </div>

    <!-- Modules Grid (12 Kartu) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($modules as $module)
            @php
                $report = $userReports->get($module->id);
                $isCompleted = $report && $report->status === 'graded';
                $isSubmitted = $report && $report->status === 'submitted';
            @endphp
            <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm hover:border-emerald-300 hover:shadow-md transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <!-- Top Card Header -->
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700">
                            Pertemuan #{{ $module->meeting_number }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-emerald-50 text-emerald-700 border-emerald-200">
                            {{ $module->badge }}
                        </span>
                    </div>

                    <!-- Title & Subtitle -->
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-2">
                            {{ $module->title }}
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">
                            {{ $module->subtitle }}
                        </p>
                    </div>

                    <!-- Capaian Pembelajaran Excerpt -->
                    <div class="p-2.5 rounded-lg bg-slate-50 text-[11px] text-slate-600 line-clamp-2">
                        <span class="font-bold text-slate-800">Target:</span> {{ $module->capaian_pembelajaran }}
                    </div>
                </div>

                <!-- Footer Card -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 font-mono">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>{{ $module->duration_minutes }} Mnt</span>
                    </div>

                    @if($isCompleted)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1 font-mono">
                            <i data-lucide="check-circle" class="w-3 h-3"></i>
                            <span>Nilai: {{ $report->final_grade }}</span>
                        </span>
                    @elseif($isSubmitted)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 font-mono">
                            Menunggu Nilai
                        </span>
                    @endif

                    <a
                        href="{{ route('modules.show', $module->id) }}"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors flex items-center gap-1 shadow-sm"
                    >
                        <span>Buka Modul</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
