@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Unboxed Header & Contextual Toolbar matching approved Bitanic baseline -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Modul Praktikum
            </h1>
            <p class="text-xs text-slate-500">
                Materi dan panduan kegiatan praktikum pertanian presisi • 12 Modul Praktikum Pertanian Presisi
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-white border border-slate-200/80 text-xs font-medium text-slate-700 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="tabular-nums">{{ $modules->count() }} Modul Praktikum</span>
                <span class="text-slate-300">•</span>
                <span class="tabular-nums">{{ round($modules->sum('duration_minutes') / 60) }} Jam Praktikum</span>
            </div>

            @if(Auth::check() && (Auth::user()->isGuru() || Auth::user()->isSuperuser()))
                <a
                    href="{{ url('/reports') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition-colors"
                >
                    <i data-lucide="file-check" class="w-4 h-4 text-slate-500"></i>
                    <span>Laporan LKS Siswa</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Modules Grid (12 Kartu) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        @foreach($modules as $module)
            @php
                $report = $userReports->get($module->id);
                $isCompleted = $report && $report->status === 'graded';
                $isSubmitted = $report && $report->status === 'submitted';
            @endphp
            <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs hover:border-emerald-300 hover:shadow-xs transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <!-- Top Card Header: Sequence & Subject Category -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-slate-500 tabular-nums">
                            Modul {{ str_pad($module->meeting_number, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                            {{ $module->badge }}
                        </span>
                    </div>

                    <!-- Title & Subtitle -->
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-slate-900 leading-snug line-clamp-2">
                            {{ $module->title }}
                        </h3>
                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                            {{ $module->subtitle }}
                        </p>
                    </div>

                    <!-- Concise Learning Target Summary -->
                    <div class="p-3 rounded-xl bg-slate-50/75 border border-slate-100 text-xs text-slate-600 line-clamp-2 leading-relaxed">
                        <span class="font-medium text-slate-800">Capaian:</span> {{ $module->capaian_pembelajaran }}
                    </div>
                </div>

                <!-- Footer Card: Duration, Status, Action -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 tabular-nums font-normal">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>{{ $module->duration_minutes }} menit</span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($isCompleted)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 tabular-nums">
                                <i data-lucide="check" class="w-3 h-3"></i>
                                <span>Nilai: {{ $report->final_grade }}</span>
                            </span>
                        @elseif($isSubmitted)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                Menunggu Nilai
                            </span>
                        @endif

                        <a
                            href="{{ route('modules.show', $module->id) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-2xs transition-colors shrink-0"
                        >
                            <span>Buka Modul</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
