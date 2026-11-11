@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <span>Data Master</span>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Sekolah</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Master Sekolah (Tenant Registry)
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Pengelolaan direktori institusi pendidikan vokasi dan tenant sekolah mitra Bitanic.
            </p>
        </div>

        <!-- Add School Button -->
        <div class="flex items-center gap-2 shrink-0">
            <a
                href="{{ route('master.schools.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-colors"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Sekolah</span>
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Filter and Search Toolbar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-xs">
        <form method="GET" action="{{ route('master.schools.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nama sekolah, NPSN, slug, alamat..."
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>

            <div class="sm:w-48">
                <select
                    name="status"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif (Arsip)</option>
                </select>
            </div>

            <div class="sm:w-56">
                <select
                    name="province_code"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Wilayah Provinsi</option>
                    @foreach($provinces as $prov)
                        <option value="{{ $prov->code }}" {{ $provinceCode === $prov->code ? 'selected' : '' }}>
                            {{ $prov->code }} - {{ $prov->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="px-4 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] transition-colors"
                >
                    Cari
                </button>
                @if(!empty($search) || !empty($status) || !empty($provinceCode))
                    <a
                        href="{{ route('master.schools.index') }}"
                        class="px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Schools Directory Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Nama Sekolah &amp; Tenant</th>
                        <th class="py-3 px-4">NPSN</th>
                        <th class="py-3 px-4">Wilayah Administratif</th>
                        <th class="py-3 px-4 text-center">Pengguna</th>
                        <th class="py-3 px-4 text-center">Perangkat</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($schools as $school)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">
                                    {{ $school->name }}
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                                    <span class="font-mono bg-slate-100 px-1.5 py-0.2 rounded text-slate-600">
                                        {{ $school->slug }}
                                    </span>
                                    <span>&bull;</span>
                                    <span class="truncate max-w-[200px]" title="{{ $school->address }}">
                                        {{ $school->address ?? 'Alamat belum diisi' }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-3.5 px-4 font-mono font-medium font-tabular">
                                @if($school->npsn)
                                    <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-800">
                                        {{ $school->npsn }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic">Belum diisi</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-slate-700">
                                <div class="font-medium text-slate-900">
                                    {{ $school->regencyRelation?->name ?? ($school->city ?: '—') }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $school->provinceRelation?->name ?? ($school->province ?: '—') }}
                                    @if($school->districtRelation)
                                        <span class="text-slate-400">&bull; Kec. {{ $school->districtRelation->name }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3.5 px-4 text-center font-tabular font-medium text-slate-800">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ $school->users_count }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 text-center font-tabular font-medium text-slate-800">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ $school->devices_count }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4">
                                @if($school->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('master.schools.show', $school) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
                                        title="Detail Sekolah"
                                    >
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>

                                    <a
                                        href="{{ route('master.schools.edit', $school) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
                                        title="Edit Data"
                                    >
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>

                                    <form action="{{ route('master.schools.toggle-status', $school) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-lg transition-colors {{ $school->status === 'active' ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }}"
                                            title="{{ $school->status === 'active' ? 'Nonaktifkan Sekolah' : 'Aktifkan Sekolah' }}"
                                            onclick="return confirm('Apakah Anda yakin ingin {{ $school->status === 'active' ? 'menonaktifkan' : 'mengaktifkan' }} sekolah {{ $school->name }}?');"
                                        >
                                            <i data-lucide="{{ $school->status === 'active' ? 'power-off' : 'power' }}" class="w-4 h-4"></i>
                                        </button>
                                    </form>

                                    @if($school->users_count === 0 && $school->devices_count === 0 && $school->schedules_count === 0 && $school->audit_logs_count === 0)
                                        <form action="{{ route('master.schools.destroy', $school) }}" method="POST" class="inline" onsubmit="return confirm('Hapus sekolah {{ $school->name }} secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors"
                                                title="Hapus Sekolah"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="building" class="w-9 h-9 text-slate-300 mb-2"></i>
                                    <p class="text-sm font-medium text-slate-700">Tidak ada sekolah yang ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Ubah kata kunci pencarian atau tambah sekolah baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($schools->hasPages())
            <div class="p-3.5 border-t border-slate-100 bg-slate-50/50">
                {{ $schools->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

