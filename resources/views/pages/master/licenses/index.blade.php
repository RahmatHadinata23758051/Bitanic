@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showInitModal: false }">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-[#0c3c26]/10 text-[#0c3c26] border border-[#0c3c26]/20">
                    Platform Superuser
                </span>
                <span class="text-slate-400 text-xs">•</span>
                <span class="text-xs text-slate-500 font-medium">B2B Commercial Entitlement</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans mt-1">
                Master Lisensi LMS Sekolah
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Registri dan tata kelola hak akses lisensi perangkat lunak Bitanic LMS (Prinsip: 1 Sekolah = 1 Lisensi).
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($availableSchools->isNotEmpty())
                <button
                    type="button"
                    @click="showInitModal = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-[#0c3c26] text-white text-xs font-semibold shadow-xs hover:bg-[#0c3c26]/90 transition-colors"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Inisialisasi Lisensi Sekolah</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('status'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('status') }}</span>
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

    @if($errors->any())
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="flex items-center gap-2 font-semibold">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Gagal memproses permintaan lisensi:</span>
            </div>
            <ul class="list-disc list-inside text-rose-700 pl-2 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">Total Lisensi</span>
                <i data-lucide="shield-check" class="w-4 h-4 text-slate-400"></i>
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-2 font-tabular">{{ $totalLicenses }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Sekolah terdaftar</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-emerald-700">Lisensi Aktif</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <p class="text-2xl font-bold text-emerald-800 mt-2 font-tabular">{{ $activeLicenses }}</p>
            <p class="text-[11px] text-emerald-600 mt-0.5">Hak akses berlaku</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-amber-700">Menunggu Efektif</span>
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </div>
            <p class="text-2xl font-bold text-amber-800 mt-2 font-tabular">{{ $pendingLicenses }}</p>
            <p class="text-[11px] text-amber-600 mt-0.5">Pending aktivasi</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-rose-700">Kedaluwarsa</span>
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            </div>
            <p class="text-2xl font-bold text-rose-800 mt-2 font-tabular">{{ $expiredLicenses }}</p>
            <p class="text-[11px] text-rose-600 mt-0.5">Perlu perpanjangan</p>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-600">Tanpa Lisensi Aktif</span>
                <i data-lucide="alert-circle" class="w-4 h-4 text-amber-500"></i>
            </div>
            <p class="text-2xl font-bold text-slate-800 mt-2 font-tabular">{{ $unentitledSchoolsCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Sekolah belum terlindungi</p>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs space-y-3">
        <form method="GET" action="{{ route('master.licenses.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-6 relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nomor lisensi, NPSN, atau nama sekolah..."
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>

            <div class="sm:col-span-3">
                <select
                    name="status"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Status Lisensi</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Lisensi Aktif</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Menunggu Efektif / Pending</option>
                    <option value="expired" {{ $statusFilter === 'expired' ? 'selected' : '' }}>Kedaluwarsa</option>
                </select>
            </div>

            <div class="sm:col-span-3 flex items-center justify-between sm:justify-end gap-2">
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-600">
                    <input
                        type="checkbox"
                        name="unentitled_only"
                        value="1"
                        {{ $unentitledOnly ? 'checked' : '' }}
                        onchange="this.form.submit()"
                        class="rounded border-slate-300 text-[#0c3c26] focus:ring-[#0c3c26]"
                    >
                    <span>Tanpa Hak Aktif</span>
                </label>

                @if($search || $statusFilter || $unentitledOnly)
                    <a
                        href="{{ route('master.licenses.index') }}"
                        class="px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors"
                        title="Reset Filter"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Licenses Table --}}
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-700 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Nomor Lisensi</th>
                        <th class="py-3 px-4">Sekolah Penerima</th>
                        <th class="py-3 px-4">Status Hak Akses</th>
                        <th class="py-3 px-4">Periode Efektif</th>
                        <th class="py-3 px-4">Sisa Hari</th>
                        <th class="py-3 px-4">Riwayat Term</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($licenses as $item)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900 text-xs">{{ $item->license_number }}</span>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    {{ $item->entitlement_type === 'first_year_included' ? 'Bundling Kit (1 Thn)' : ucfirst($item->entitlement_type) }}
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-900">{{ $item->school->name ?? 'Sekolah Tidak Ditemukan' }}</div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    NPSN: {{ $item->school->npsn ?? '—' }}
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $item->status_badge_class }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($item->start_date && $item->expiration_date)
                                    <div class="font-tabular font-medium text-slate-800">
                                        {{ $item->start_date->translatedFormat('d M Y') }} s.d. {{ $item->expiration_date->translatedFormat('d M Y') }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">Konvensi [Mulai, Berakhir)</div>
                                @else
                                    <span class="text-slate-400 italic">Belum Ditetapkan</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($item->computed_status === 'active')
                                    <span class="font-tabular font-semibold text-emerald-700">
                                        {{ $item->days_remaining }} hari
                                    </span>
                                @elseif($item->computed_status === 'expired')
                                    <span class="font-tabular text-rose-600 font-medium">0 hari (Kedaluwarsa)</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 text-slate-700 font-tabular">
                                    <i data-lucide="layers" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>Term #{{ $item->terms->count() ?: 0 }}</span>
                                </span>
                            </td>

                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('master.licenses.show', $item) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors"
                                >
                                    <span>Kelola</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 px-4 text-center">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <i data-lucide="shield-alert" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-sm font-semibold text-slate-700">Tidak Ada Data Lisensi LMS</p>
                                    <p class="text-xs text-slate-400">
                                        Tidak ditemukan registri lisensi yang cocok dengan filter atau kriteria pencarian Anda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($licenses->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $licenses->links() }}
            </div>
        @endif
    </div>

    {{-- Modal: Inisialisasi Lisensi Sekolah --}}
    <div
        x-show="showInitModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        @keydown.escape.window="showInitModal = false"
    >
        <div
            @click.outside="showInitModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Inisialisasi Lisensi Sekolah</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftarkan entitas sekolah ke registri lisensi LMS dalam status pending.</p>
                </div>
                <button
                    type="button"
                    @click="showInitModal = false"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('master.licenses.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="school_id" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Sekolah Aktif <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="school_id"
                        name="school_id"
                        required
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    >
                        <option value="">-- Pilih Sekolah --</option>
                        @foreach($availableSchools as $sc)
                            <option value="{{ $sc->id }}">{{ $sc->name }} (NPSN: {{ $sc->npsn ?: '—' }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Hanya menampilkan sekolah berstatus aktif yang belum memiliki registri lisensi.</p>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Inisialisasi</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        placeholder="Contoh: Registri lisensi pengadaan Trainer Kit tahap 1."
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showInitModal = false"
                        class="px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] rounded-xl hover:bg-[#0c3c26]/90 shadow-xs transition-colors"
                    >
                        Simpan Registri
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

