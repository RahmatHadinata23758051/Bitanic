@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <a href="{{ route('master.schools.index') }}" class="hover:text-slate-800 transition-colors">Master Sekolah</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">{{ $school->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    {{ $school->name }}
                </h1>
                @if($school->status === 'active')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Nonaktif (Arsip)
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Slug: <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">{{ $school->slug }}</code>
                &bull; Terdaftar sejak: <span class="font-tabular">{{ $school->created_at->format('d M Y') }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a
                href="{{ route('master.schools.edit', $school) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-xs transition-colors"
            >
                <i data-lucide="edit" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Edit Profil</span>
            </a>

            <form action="{{ route('master.schools.toggle-status', $school) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl transition-colors {{ $school->status === 'active' ? 'bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100' : 'bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100' }}"
                    onclick="return confirm('Ubah status operasional sekolah ini?');"
                >
                    <i data-lucide="{{ $school->status === 'active' ? 'power-off' : 'power' }}" class="w-3.5 h-3.5"></i>
                    <span>{{ $school->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                </button>
            </form>
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

    <!-- Key Associated Resource Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Total Pengguna Terdaftar</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 font-tabular">{{ $school->users_count }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Guru, siswa, dan admin sekolah</p>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Perangkat Trainer Kit</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                    <i data-lucide="cpu" class="w-4 h-4"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 font-tabular">{{ $school->devices_count }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Hardware workstation lab terdaftar</p>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500">Jadwal Praktikum</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 font-tabular">{{ $school->schedules_count }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Sesi jadwal tercatat</p>
        </div>
    </div>

    <!-- Detailed School Profile & Regional Associations -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-6">
        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-[#0c3c26]"></i>
                <span>Spesifikasi Identitas &amp; Wilayah Tenant</span>
            </h2>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Nama Resmi Sekolah</dt>
                    <dd class="font-semibold text-slate-900 text-sm">{{ $school->name }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">NPSN</dt>
                    <dd class="font-mono font-medium text-slate-900">
                        {{ $school->npsn ?: '— Belum diisi —' }}
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Provinsi (Kemendagri)</dt>
                    <dd class="font-medium text-slate-900 flex items-center gap-2">
                        @if($school->provinceRelation)
                            <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                {{ $school->provinceRelation->code }}
                            </span>
                            <span>{{ $school->provinceRelation->name }}</span>
                        @else
                            <span>{{ $school->province ?: '—' }}</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Kabupaten / Kota (Kemendagri)</dt>
                    <dd class="font-medium text-slate-900 flex items-center gap-2">
                        @if($school->regencyRelation)
                            <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                {{ $school->regencyRelation->code }}
                            </span>
                            <span>{{ $school->regencyRelation->name }}</span>
                        @else
                            <span>{{ $school->city ?: '—' }}</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Kecamatan (Kemendagri)</dt>
                    <dd class="font-medium text-slate-900 flex items-center gap-2">
                        @if($school->districtRelation)
                            <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 text-[11px]">
                                {{ $school->districtRelation->code }}
                            </span>
                            <span>{{ $school->districtRelation->name }}</span>
                        @else
                            <span class="text-slate-400 italic">Belum dikaitkan</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Nomor Telepon</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $school->phone ?: '—' }}
                    </dd>
                </div>

                <div class="sm:col-span-2 border-b border-slate-100 pb-3">
                    <dt class="text-slate-500 mb-0.5">Alamat Lengkap</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $school->address ?: '— Belum diisi —' }}
                    </dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-slate-500 mb-0.5">Nama Laboratorium Smart Greenhouse</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $school->schoolSetting?->lab_name ?? 'Laboratorium Smart Greenhouse & ATPH' }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Recent Audit Trail for this School -->
        <div class="border-t border-slate-100 pt-5">
            <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-[#0c3c26]"></i>
                <span>Riwayat Audit Terakhir Tenant</span>
            </h3>

            @if($recentAudits->isNotEmpty())
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Waktu (WIB)</th>
                                <th class="py-2.5 px-3">Aksi</th>
                                <th class="py-2.5 px-3">Pelaksana</th>
                                <th class="py-2.5 px-3">Rincian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentAudits as $log)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 font-tabular whitespace-nowrap">
                                        {{ $log->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-slate-900">
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700">
                                            {{ $log->action }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-700">
                                        {{ $log->actor_name }} ({{ $log->actor_role }})
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600 truncate max-w-xs">
                                        {{ $log->details ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-slate-400 italic">Belum ada riwayat aktivitas yang tercatat untuk tenant ini.</p>
            @endif
        </div>
    </div>
</div>
@endsection

