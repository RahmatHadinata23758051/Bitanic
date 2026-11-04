@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'theory' }">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
        <a
            href="{{ route('modules.index') }}"
            class="text-xs font-semibold text-slate-500 hover:text-emerald-700 flex items-center gap-1.5 transition-colors"
        >
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Kembali ke Katalog 12 Modul</span>
        </a>

        <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-700">
                Pertemuan #{{ $module->meeting_number }}
            </span>
            <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
                {{ $module->badge }}
            </span>
        </div>
    </div>

    <!-- Main Module Header -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-3">
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
            {{ $module->title }}
        </h1>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-4xl">
            {{ $module->subtitle }}
        </p>

        <div class="pt-2 flex items-center gap-4 text-xs font-mono text-slate-500 flex-wrap">
            <span class="flex items-center gap-1.5">
                <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                <span>Durasi: {{ $module->duration_minutes }} Menit (3 JP)</span>
            </span>
            <span>•</span>
            <span class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Standar Industri Pertanian Presisi 4.0</span>
            </span>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex items-center border-b border-slate-200 gap-2 overflow-x-auto text-xs font-semibold">
        <button
            type="button"
            @click="activeTab = 'theory'"
            :class="activeTab === 'theory' ? 'border-emerald-600 text-emerald-800 border-b-2 font-bold' : 'text-slate-500 hover:text-slate-800'"
            class="py-3 px-4 flex items-center gap-2 transition-colors shrink-0"
        >
            <i data-lucide="book-open" class="w-4 h-4"></i>
            <span>1. Teori & Capaian</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'wiring'"
            :class="activeTab === 'wiring' ? 'border-emerald-600 text-emerald-800 border-b-2 font-bold' : 'text-slate-500 hover:text-slate-800'"
            class="py-3 px-4 flex items-center gap-2 transition-colors shrink-0"
        >
            <i data-lucide="cpu" class="w-4 h-4"></i>
            <span>2. Skema Wiring & Terminal</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'steps'"
            :class="activeTab === 'steps' ? 'border-emerald-600 text-emerald-800 border-b-2 font-bold' : 'text-slate-500 hover:text-slate-800'"
            class="py-3 px-4 flex items-center gap-2 transition-colors shrink-0"
        >
            <i data-lucide="list-ordered" class="w-4 h-4"></i>
            <span>3. Langkah Kerja (SOP)</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'lks'"
            :class="activeTab === 'lks' ? 'border-emerald-600 text-emerald-800 border-b-2 font-bold' : 'text-slate-500 hover:text-slate-800'"
            class="py-3 px-4 flex items-center gap-2 transition-colors shrink-0"
        >
            <i data-lucide="file-text" class="w-4 h-4"></i>
            <span>4. Tugas Lembar Kerja (LKS)</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'rubric'"
            :class="activeTab === 'rubric' ? 'border-emerald-600 text-emerald-800 border-b-2 font-bold' : 'text-slate-500 hover:text-slate-800'"
            class="py-3 px-4 flex items-center gap-2 transition-colors shrink-0"
        >
            <i data-lucide="award" class="w-4 h-4"></i>
            <span>5. Rubrik Penilaian Guru</span>
        </button>
    </div>

    <!-- Tab 1: Teori & Capaian -->
    <div x-show="activeTab === 'theory'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Capaian Pembelajaran -->
                <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Capaian Pembelajaran (CP)</h3>
                    <p class="text-sm text-slate-800 leading-relaxed font-medium">
                        {{ $module->capaian_pembelajaran }}
                    </p>
                </div>

                <!-- Landasan Teori -->
                <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Landasan Teori Agronomi</h3>
                    <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                        {{ $module->theory_summary }}
                    </p>
                </div>

                <!-- Tujuan Instruksional Khusus -->
                <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Tujuan Pembelajaran</h3>
                    <ul class="space-y-2 text-xs text-slate-700">
                        @foreach($module->tujuan_pembelajaran ?? [] as $tujuan)
                            <li class="flex items-start gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                                <span>{{ $tujuan }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <!-- Sidebar Alat & Bahan -->
            <div class="space-y-6">
                <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Alat & Bahan Praktikum</h3>
                    <ul class="space-y-2 text-xs text-slate-700">
                        @foreach($module->alat_bahan ?? [] as $alat)
                            <li class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 border border-slate-100">
                                <i data-lucide="wrench" class="w-3.5 h-3.5 text-slate-500"></i>
                                <span>{{ $alat }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Wiring & Register Modbus -->
    <div x-show="activeTab === 'wiring'" class="space-y-6">
        <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Skema Terminal & Pinout Hardware Kit</h3>
                <p class="text-xs text-slate-500">Koneksi kelistrikan dan proteksi arus trainer kit laboratorium</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-mono text-[11px] uppercase">
                            <th class="py-2.5 px-3">Pin / Jalur</th>
                            <th class="py-2.5 px-3">Port Fisik</th>
                            <th class="py-2.5 px-3">Komponen Terpasang</th>
                            <th class="py-2.5 px-3">Fungsi Teknis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                        @foreach($module->wiring_pinout ?? [] as $pin)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-bold text-emerald-800">{{ $pin['pin'] ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-slate-700">{{ $pin['port'] ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-slate-900 font-sans font-medium">{{ $pin['component'] ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-slate-500 font-sans">{{ $pin['functionDesc'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Langkah Kerja (SOP) -->
    <div x-show="activeTab === 'steps'" class="space-y-4">
        @foreach($module->steps ?? [] as $step)
            <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-2">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-mono font-bold text-xs flex items-center justify-center">
                        {{ $step['stepNumber'] ?? $loop->iteration }}
                    </span>
                    <h4 class="text-sm font-bold text-slate-900">{{ $step['title'] }}</h4>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed pl-10">
                    {{ $step['instruction'] }}
                </p>
            </div>
        @endforeach
    </div>

    <!-- Tab 4: Lembar Kerja Siswa (LKS) -->
    <div x-show="activeTab === 'lks'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Form Lembar Kerja Siswa (LKS)</h3>
                    <p class="text-xs text-slate-500">Jawab pertanyaan observasi berbasis data sensor telemetri aktual</p>
                </div>
                @if($currentReport)
                    <span class="px-2.5 py-1 rounded text-xs font-mono font-bold uppercase {{ $currentReport->status === 'graded' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        Status: {{ $currentReport->status }} (Nilai: {{ $currentReport->final_grade ?? 'Belum Dinilai' }})
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('modules.submit-lks', $module->id) }}" class="space-y-5">
                @csrf

                @foreach($module->lks_questions ?? [] as $q)
                    @php
                        $savedAns = $currentReport->lks_answers[$q['id']] ?? '';
                    @endphp
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-800">
                            {{ $loop->iteration }}. {{ $q['question'] }}
                        </label>
                        <textarea
                            name="lks_answers[{{ $q['id'] }}]"
                            rows="3"
                            required
                            class="w-full text-xs p-3 rounded-lg border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-all placeholder:text-slate-400"
                            placeholder="Tuliskan analisis pengamatan kelompokmu di sini..."
                        >{{ old('lks_answers.'.$q['id'], $savedAns) }}</textarea>
                    </div>
                @endforeach

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-800">Catatan Observasi Tambahan & Kendala Lapangan</label>
                    <textarea
                        name="observation_notes"
                        rows="2"
                        class="w-full text-xs p-3 rounded-lg border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition-all placeholder:text-slate-400"
                        placeholder="Kondisi cuaca, respons tanaman, atau deviasi sensor jika ada..."
                    >{{ old('observation_notes', $currentReport->observation_notes ?? '') }}</textarea>
                </div>

                <div class="pt-2 flex items-center justify-between">
                    <p class="text-[11px] text-slate-400">
                        * Snapshot telemetri 7-in-1 saat submit akan otomatis disertakan ke laporan.
                    </p>
                    <button
                        type="submit"
                        class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center gap-2"
                    >
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        <span>Kumpulkan Laporan Praktikum</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab 5: Rubrik Penilaian Guru -->
    <div x-show="activeTab === 'rubric'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h3 class="text-sm font-bold text-slate-900">Rubrik Asesmen Kompetensi Vokasi 4-Skala</h3>
                <p class="text-xs text-slate-500">Standar evaluasi ketercapaian capaian pembelajaran Kurikulum Merdeka SMK</p>
            </div>

            <!-- Rubric Criteria Reference -->
            <div class="space-y-4">
                @foreach($module->rubric ?? [] as $aspect)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-900">{{ $aspect['name'] }}</h4>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800">
                                Bobot: {{ $aspect['weight'] }}%
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-[11px]">
                            <div class="p-2.5 rounded bg-white border border-slate-200 space-y-1">
                                <span class="text-[10px] font-bold text-emerald-700 block uppercase">Sangat Kompeten (4)</span>
                                <p class="text-slate-600">{{ $aspect['criteria']['score4'] ?? '-' }}</p>
                            </div>
                            <div class="p-2.5 rounded bg-white border border-slate-200 space-y-1">
                                <span class="text-[10px] font-bold text-blue-700 block uppercase">Kompeten (3)</span>
                                <p class="text-slate-600">{{ $aspect['criteria']['score3'] ?? '-' }}</p>
                            </div>
                            <div class="p-2.5 rounded bg-white border border-slate-200 space-y-1">
                                <span class="text-[10px] font-bold text-amber-700 block uppercase">Cukup Kompeten (2)</span>
                                <p class="text-slate-600">{{ $aspect['criteria']['score2'] ?? '-' }}</p>
                            </div>
                            <div class="p-2.5 rounded bg-white border border-slate-200 space-y-1">
                                <span class="text-[10px] font-bold text-rose-700 block uppercase">Belum Kompeten (1)</span>
                                <p class="text-slate-600">{{ $aspect['criteria']['score1'] ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Guru Grading Table if reports exist -->
            @if($allReports->isNotEmpty())
                <div class="pt-6 border-t border-slate-200 space-y-4">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Laporan Siswa Masuk (Perlu Penilaian)</h4>

                    <div class="space-y-4">
                        @foreach($allReports as $rep)
                            <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-bold text-slate-900">{{ $rep->user->name ?? 'Siswa' }}</span>
                                        <span class="text-[11px] text-slate-500 font-mono ml-2">({{ $rep->user->email }})</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $rep->status === 'graded' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $rep->status }} • Nilai: {{ $rep->final_grade ?? '-' }}
                                    </span>
                                </div>

                                <form method="POST" action="{{ route('modules.grade-report', $rep->id) }}" class="space-y-3 pt-2">
                                    @csrf
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        @foreach($module->rubric ?? [] as $aspect)
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                                    {{ $aspect['name'] }}
                                                </label>
                                                <select
                                                    name="rubric_scores[{{ $aspect['id'] }}]"
                                                    class="w-full text-xs p-2 rounded-lg border border-slate-300 bg-white focus:border-emerald-600 outline-none"
                                                >
                                                    <option value="4" {{ ($rep->rubric_scores[$aspect['id']] ?? 3) == 4 ? 'selected' : '' }}>4 - Sangat Kompeten (100)</option>
                                                    <option value="3" {{ ($rep->rubric_scores[$aspect['id']] ?? 3) == 3 ? 'selected' : '' }}>3 - Kompeten (85)</option>
                                                    <option value="2" {{ ($rep->rubric_scores[$aspect['id']] ?? 3) == 2 ? 'selected' : '' }}>2 - Cukup (75)</option>
                                                    <option value="1" {{ ($rep->rubric_scores[$aspect['id']] ?? 3) == 1 ? 'selected' : '' }}>1 - Belum Kompeten (60)</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div>
                                        <input
                                            type="text"
                                            name="teacher_feedback"
                                            value="{{ $rep->teacher_feedback }}"
                                            placeholder="Catatan guru pembimbing..."
                                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 outline-none"
                                        />
                                    </div>

                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-sm"
                                        >
                                            Simpan Nilai Siswa
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
