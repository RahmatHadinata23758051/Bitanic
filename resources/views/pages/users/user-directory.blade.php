@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Page Header (Unboxed, Bitanic Standard) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Direktori
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Data pengguna dan anggota sekolah
            </p>
        </div>

        <!-- School Scope / Selector -->
        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ url('/users-data') }}" class="flex items-center gap-2">
                    @if($currentRole)<input type="hidden" name="role" value="{{ $currentRole }}">@endif
                    @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    <label for="school_id" class="text-xs text-slate-500 font-medium">Sekolah:</label>
                    <select id="school_id" name="school_id" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] shadow-2xs">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="text-xs text-slate-500 font-medium bg-white px-3 py-1.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    {{ $schoolName }}
                </span>
            @endif
        </div>
    </div>

    <!-- Summary Metrics Strip (Restrained, Bitanic Standard) -->
    <div class="grid grid-cols-3 gap-3">
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Siswa Praktikan</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['students'] }}</span>
        </div>
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Guru Pembimbing</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['teachers'] }}</span>
        </div>
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Admin Lab & Sistem</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['admins'] }}</span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Role Tabs -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <a
                href="{{ url('/users-data' . ($selectedSchoolId ? '?school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ empty($currentRole) ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Semua
            </a>
            <a
                href="{{ url('/users-data?role=siswa' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $currentRole === 'siswa' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Siswa
            </a>
            <a
                href="{{ url('/users-data?role=guru' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $currentRole === 'guru' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Guru
            </a>
            <a
                href="{{ url('/users-data?role=admin' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ in_array($currentRole, ['admin', 'admin_sekolah', 'superuser'], true) ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Admin
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="{{ url('/users-data') }}" class="flex items-center gap-2">
            @if($currentRole)
                <input type="hidden" name="role" value="{{ $currentRole }}">
            @endif
            @if($isSuperuser && $selectedSchoolId)
                <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
            @endif
            <div class="relative">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nama, NISN, NIP..."
                    class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] w-56 sm:w-64"
                >
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5"></i>
            </div>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold transition-colors shadow-2xs">
                Cari
            </button>
            @if($search)
                <a href="{{ url('/users-data' . ($currentRole ? '?role=' . $currentRole : '') . ($selectedSchoolId ? ($currentRole ? '&' : '?') . 'school_id=' . $selectedSchoolId : '')) }}" class="text-xs text-slate-500 hover:text-slate-800 font-medium">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-medium text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Nama Lengkap &amp; Akun</th>
                        <th class="py-3 px-4">Peran</th>
                        <th class="py-3 px-4">Nomor Identitas</th>
                        <th class="py-3 px-4">Kelas / Spesialisasi</th>
                        <th class="py-3 px-4">Kontak</th>
                        <th class="py-3 px-4 text-right">Alokasi Meja Kit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($users as $u)
                        @php
                            $p = $u->profile;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500 mt-0.5">{{ $u->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($u->role === 'guru')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-700 border border-slate-200/60">
                                        Guru
                                    </span>
                                @elseif($u->role === 'superuser')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-200 text-slate-800 border border-slate-300/60">
                                        Superuser
                                    </span>
                                @elseif($u->role === 'admin_sekolah' || $u->role === 'admin')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-200 text-slate-800 border border-slate-300/60">
                                        {{ $u->role === 'admin_sekolah' ? 'Admin Sekolah' : 'Admin' }}
                                    </span>
                                @elseif($u->role === 'siswa')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-700 border border-slate-200/60">
                                        Siswa
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-600 border border-slate-200/60">
                                        {{ ucfirst($u->role) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-700">
                                {{ $p?->identifier_number ?? '—' }}
                            </td>
                            <td class="py-3 px-4">
                                @if($u->role === 'siswa')
                                    @if($p?->class_grade || $p?->group_name)
                                        <div class="font-medium text-slate-800">{{ $p->class_grade ?? '—' }}</div>
                                        @if($p?->group_name)
                                            <span class="text-[11px] text-slate-500">{{ $p->group_name }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                @else
                                    @if($p?->specialization || $p?->department)
                                        <div class="font-medium text-slate-800">{{ $p->specialization ?? '—' }}</div>
                                        @if($p?->department)
                                            <span class="text-[11px] text-slate-500">{{ $p->department }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                                {{ $p?->phone ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-medium">
                                @if($u->role === 'siswa' && $p?->assigned_kit_id)
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[11px] border border-slate-200/80">
                                        {{ str_starts_with($p->assigned_kit_id, 'TK-') ? $p->assigned_kit_id : 'TK-' . str_pad($p->assigned_kit_id, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-normal">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400 text-xs">
                                Tidak ada data pengguna yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
