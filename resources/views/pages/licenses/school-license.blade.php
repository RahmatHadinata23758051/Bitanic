@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    Administrasi Sekolah
                </span>
                <span class="text-slate-400 text-xs">•</span>
                <span class="text-xs text-slate-500 font-medium">Informasi Lisensi Perangkat Lunak</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans mt-1">
                Lisensi Bitanic LMS
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Status operasional dan masa berlaku lisensi pembelajaran digital SMK Pertanian (ATPH 4.0).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ url('/settings') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium shadow-2xs transition-colors"
            >
                <i data-lucide="settings" class="w-3.5 h-3.5 text-slate-500"></i>
                <span>Pengaturan Sekolah</span>
            </a>
        </div>
    </div>

    @if($license)
        {{-- License Status Hero Card --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-2xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
                <div class="space-y-1">
                    <span class="text-xs font-medium text-slate-400">Nomor Registri Lisensi</span>
                    <p class="font-mono text-xl sm:text-2xl font-bold text-slate-900">{{ $license->license_number }}</p>
                    <p class="text-xs text-slate-500">
                        Pemberian Hak Akses: <strong class="text-slate-700">{{ $license->entitlement_type === 'first_year_included' ? 'Bundling Paket Trainer Kit' : ucfirst($license->entitlement_type) }}</strong>
                    </p>
                </div>

                <div class="sm:text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $license->status_badge_class }}">
                        {{ $license->status_label }}
                    </span>
                    @if($license->computed_status === 'active')
                        <p class="text-xs text-emerald-700 font-semibold font-tabular mt-1.5">
                            {{ $license->days_remaining }} hari masa aktif tersisa
                        </p>
                    @elseif($license->computed_status === 'expired')
                        <p class="text-xs text-rose-600 font-semibold mt-1.5">
                            Lisensi telah berakhir. Hubungi pengelola platform.
                        </p>
                    @else
                        <p class="text-xs text-amber-700 font-medium mt-1.5">
                            Menunggu jadwal tanggal efektif mulai.
                        </p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                    <span class="text-[11px] text-slate-400 font-medium block">Tanggal Efektif Mulai</span>
                    <span class="font-tabular font-bold text-sm text-slate-800 mt-1 block">
                        {{ $license->start_date ? $license->start_date->translatedFormat('d F Y') : '—' }}
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                    <span class="text-[11px] text-slate-400 font-medium block">Tanggal Berakhir Hak Akses</span>
                    <span class="font-tabular font-bold text-sm text-slate-800 mt-1 block">
                        {{ $license->expiration_date ? $license->expiration_date->translatedFormat('d F Y') : '—' }}
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                    <span class="text-[11px] text-slate-400 font-medium block">Entitas Terdaftar</span>
                    <span class="font-bold text-sm text-slate-800 mt-1 block truncate">
                        {{ $school->name }}
                    </span>
                    <span class="text-[10px] text-slate-400 font-mono">NPSN: {{ $school->npsn ?: '—' }}</span>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200/70 text-xs text-emerald-900 space-y-1">
                <div class="flex items-center gap-1.5 font-semibold text-emerald-950">
                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-700"></i>
                    <span>Ketentuan Hak Akses & Tata Kelola Lisensi Bitanic</span>
                </div>
                <p class="text-[11px] text-emerald-800/90 leading-relaxed">
                    Setiap sekolah berhak atas 1 (satu) lisensi LMS terpadu yang mencakup seluruh modul praktikum Kurikulum Merdeka ATPH 4.0 dan stasiun workstation laboratorium IoT. Otorisasi perpanjangan tahunan dikelola secara terpusat oleh Superuser Platform Makerindo.
                </p>
            </div>
        </div>

        {{-- Terms History (Read-Only) --}}
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
                    <span>Riwayat Periode & Perpanjangan Lisensi</span>
                </h3>
                <span class="text-xs text-slate-400">Data Resmi Produsen</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-700 uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-4">Term</th>
                            <th class="py-2.5 px-4">Deskripsi Hak Akses</th>
                            <th class="py-2.5 px-4">Periode Masa Berlaku</th>
                            <th class="py-2.5 px-4">Status Periode</th>
                            <th class="py-2.5 px-4">Catatan Otorisasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($license->terms as $term)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    Term #{{ $term->term_number }}
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-800 whitespace-nowrap">
                                    {{ $term->term_type_label }}
                                </td>
                                <td class="py-3 px-4 font-tabular whitespace-nowrap">
                                    {{ $term->start_date->translatedFormat('d M Y') }} s.d. {{ $term->expiration_date->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($term->computed_status === 'active')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Aktif Berjalan
                                        </span>
                                    @elseif($term->computed_status === 'pending')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            Menunggu Periode
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            Selesai
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-500 max-w-sm truncate">
                                    {{ $term->notes ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 px-4 text-center text-slate-400 italic">
                                    Belum ada penetapan term hak akses yang dicatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Empty State: License Not Initialized --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-8 shadow-2xs text-center max-w-lg mx-auto space-y-4 my-8">
            <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto">
                <i data-lucide="shield-alert" class="w-6 h-6"></i>
            </div>
            <div class="space-y-1">
                <h2 class="text-base font-bold text-slate-900">Lisensi LMS Belum Diinisialisasi</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Registri lisensi resmi untuk <strong>{{ $school->name }}</strong> belum diinisialisasi oleh Superuser Platform Bitanic.
                </p>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-left text-xs text-slate-600 space-y-1">
                <p class="font-semibold text-slate-700">Panduan Operasional:</p>
                <p class="text-[11px] text-slate-500">
                    Lisensi tahun pertama akan otomatis diberikan bersamaan dengan proses komisioning dan serah terima perangkat Trainer Kit. Silakan hubungi tim dukungan teknis platform untuk aktivasi.
                </p>
            </div>
        </div>
    @endif
</div>
@endsection

