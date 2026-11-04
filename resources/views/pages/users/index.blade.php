@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    DIREKTORI AKADEMIK
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">{{ $schoolName }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Data Siswa & Guru Pembimbing
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Direktori terpadu data pengajar, instruktur lab, serta praktikan jurusan Agribisnis Tanaman Pangan & Hortikultura (ATPH).
            </p>
        </div>

        <!-- 3 Quick Metrics Pills -->
        <div class="flex items-center gap-3 shrink-0">
            <div class="px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Siswa</span>
                <span class="font-mono font-bold text-base text-slate-900">{{ $stats['students'] }}</span>
            </div>
            <div class="px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Guru</span>
                <span class="font-mono font-bold text-base text-indigo-700">{{ $stats['teachers'] }}</span>
            </div>
            <div class="px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Admin Lab</span>
                <span class="font-mono font-bold text-base text-amber-700">{{ $stats['admins'] }}</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Role Tabs -->
        <div class="flex items-center gap-2">
            <a
                href="{{ url('/users-data') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ empty($currentRole) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Semua
            </a>
            <a
                href="{{ url('/users-data?role=siswa') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $currentRole === 'siswa' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Siswa Praktikan
            </a>
            <a
                href="{{ url('/users-data?role=guru') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $currentRole === 'guru' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Guru Pembimbing
            </a>
            <a
                href="{{ url('/users-data?role=superuser') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $currentRole === 'superuser' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Admin Lab
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="{{ url('/users-data') }}" class="flex items-center gap-2">
            @if($currentRole)
                <input type="hidden" name="role" value="{{ $currentRole }}">
            @endif
            <div class="relative">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nama, NISN, NIP..."
                    class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 w-64"
                >
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5"></i>
            </div>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors">
                Cari
            </button>
            @if($search)
                <a href="{{ url('/users-data' . ($currentRole ? '?role=' . $currentRole : '')) }}" class="text-xs text-rose-600 hover:text-rose-700 font-semibold">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users Table -->
    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-mono bg-slate-50">
                        <th class="py-2.5 px-3">Nama Lengkap & Akun</th>
                        <th class="py-2.5 px-3">Peran (Role)</th>
                        <th class="py-2.5 px-3">Nomor Identitas</th>
                        <th class="py-2.5 px-3">Kelas / Spesialisasi</th>
                        <th class="py-2.5 px-3">Kontak</th>
                        <th class="py-2.5 px-3 text-right">Alokasi Meja Kit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($users as $u)
                        @php
                            $p = $u->profile;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ $u->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500">{{ $u->email }}</div>
                            </td>
                            <td class="py-3 px-3">
                                @if($u->role === 'guru')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800">
                                        Guru
                                    </span>
                                @elseif($u->role === 'superuser')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                        Admin
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                        Siswa
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-700">
                                {{ $p->identifier_number ?? '-' }}
                            </td>
                            <td class="py-3 px-3">
                                @if($u->role === 'siswa')
                                    <div class="font-semibold text-slate-800">{{ $p->class_grade ?? 'XI ATPH' }}</div>
                                    <span class="text-[11px] text-slate-500">{{ $p->group_name ?? 'Kelompok' }}</span>
                                @else
                                    <div class="font-semibold text-slate-800">{{ $p->specialization ?? 'Instruktur Agronomi' }}</div>
                                    <span class="text-[11px] text-slate-500">{{ $p->department ?? 'ATPH' }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-slate-600">
                                {{ $p->phone ?? '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-semibold">
                                @if($u->role === 'siswa' && $p?->assigned_kit_id)
                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        TK-0{{ $p->assigned_kit_id }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-normal">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                Tidak ada data pengguna yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="pt-4 border-t border-slate-200">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
