@extends('layouts.app')

@section('content')
<div
    class="space-y-6"
    x-data="{
        showActivateModal: false,
        showRenewModal: false,
        showCorrectModal: false,
    }"
>
    {{-- Breadcrumb & Navigation --}}
    <div class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('master.licenses.index') }}" class="hover:text-slate-800 transition-colors flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Kembali ke Master Lisensi</span>
        </a>
        <span>/</span>
        <span class="font-mono text-slate-700 font-semibold">{{ $license->license_number }}</span>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-mono">
                    {{ $license->license_number }}
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $license->status_badge_class }}">
                    {{ $license->status_label }}
                </span>
                <span class="text-xs text-slate-400 font-sans">•</span>
                <span class="text-xs text-slate-500 font-sans font-medium">
                    {{ $license->entitlement_type === 'first_year_included' ? 'Bundling Perangkat Keras' : ucfirst($license->entitlement_type) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-600 mt-1 flex items-center gap-2">
                <i data-lucide="building-2" class="w-4 h-4 text-slate-400"></i>
                <span class="font-semibold text-slate-800">{{ $license->school->name ?? 'Sekolah Tidak Terdaftar' }}</span>
                <span class="text-slate-400">(NPSN: {{ $license->school->npsn ?? '—' }})</span>
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if($license->terms->isEmpty())
                <button
                    type="button"
                    @click="showActivateModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0c3c26] text-white text-xs font-semibold shadow-xs hover:bg-[#0c3c26]/90 transition-colors"
                >
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    <span>Aktivasi Tahun Pertama</span>
                </button>
            @else
                <button
                    type="button"
                    @click="showRenewModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-700 text-white text-xs font-semibold shadow-xs hover:bg-emerald-800 transition-colors"
                >
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Perpanjang Tahunan</span>
                </button>

                <button
                    type="button"
                    @click="showCorrectModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-xs font-semibold shadow-2xs hover:bg-slate-50 transition-colors"
                >
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                    <span>Koreksi Tanggal</span>
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

    @if($errors->any())
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <div class="flex items-center gap-2 font-semibold">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Gagal memproses perubahan lisensi:</span>
            </div>
            <ul class="list-disc list-inside text-rose-700 pl-2 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Primary Info Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Status & Entitlement Overview --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="shield" class="w-4 h-4 text-[#0c3c26]"></i>
                <span>Informasi Hak Akses & Validitas Lisensi</span>
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <span class="text-[11px] font-medium text-slate-400 block">Status Operasional</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border mt-1 {{ $license->status_badge_class }}">
                        {{ $license->status_label }}
                    </span>
                </div>

                <div>
                    <span class="text-[11px] font-medium text-slate-400 block">Tanggal Efektif Mulai</span>
                    <span class="font-tabular text-xs font-semibold text-slate-800 mt-1 block">
                        {{ $license->start_date ? $license->start_date->translatedFormat('d M Y') : '—' }}
                    </span>
                </div>

                <div>
                    <span class="text-[11px] font-medium text-slate-400 block">Tanggal Kedaluwarsa</span>
                    <span class="font-tabular text-xs font-semibold text-slate-800 mt-1 block">
                        {{ $license->expiration_date ? $license->expiration_date->translatedFormat('d M Y') : '—' }}
                    </span>
                    <span class="text-[10px] text-slate-400">Pukul 00:00:00 WIB</span>
                </div>

                <div>
                    <span class="text-[11px] font-medium text-slate-400 block">Sisa Masa Aktif</span>
                    @if($license->computed_status === 'active')
                        <span class="font-tabular text-xs font-bold text-emerald-700 mt-1 block">
                            {{ $license->days_remaining }} hari tersisa
                        </span>
                    @elseif($license->computed_status === 'expired')
                        <span class="font-tabular text-xs font-bold text-rose-600 mt-1 block">
                            0 hari (Kedaluwarsa)
                        </span>
                    @else
                        <span class="text-xs text-slate-400 mt-1 block">—</span>
                    @endif
                </div>
            </div>

            <div class="p-3.5 rounded-lg bg-slate-50 border border-slate-200/80 text-xs text-slate-600 space-y-1">
                <div class="flex items-center gap-1.5 font-semibold text-slate-700">
                    <i data-lucide="info" class="w-3.5 h-3.5 text-slate-500"></i>
                    <span>Ketentuan Hak Akses Lisensi [start_date, expiration_date)</span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Lisensi berstatus <strong>Pending</strong> sebelum tanggal mulai, <strong>Aktif</strong> terhitung sejak tanggal mulai hingga pukul 23:59:59 sebelum tanggal kedaluwarsa, dan berstatus <strong>Kedaluwarsa</strong> tepat pada tanggal kedaluwarsa. Masa aktif dihitung penuh 1 tahun kalender dengan proteksi tahun kabisat (29 Feb &rarr; 28 Feb).
                </p>
            </div>
        </div>

        {{-- School Information Card --}}
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-2xs space-y-3">
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="graduation-cap" class="w-4 h-4 text-slate-500"></i>
                <span>Profil Entitas Sekolah</span>
            </h2>

            <div class="space-y-2 text-xs pt-2 border-t border-slate-100">
                <div>
                    <span class="text-[11px] text-slate-400 block">Nama Sekolah:</span>
                    <span class="font-semibold text-slate-800">{{ $license->school->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-[11px] text-slate-400 block">NPSN:</span>
                    <span class="font-mono text-slate-700">{{ $license->school->npsn ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-[11px] text-slate-400 block">Status Sekolah:</span>
                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold {{ $license->school->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                        {{ ucfirst($license->school->status ?? '—') }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] text-slate-400 block">Otorisasi Terakhir:</span>
                    <span class="text-slate-600">
                        {{ $license->lastAuthorizedBy->name ?? 'Sistem' }}
                        @if($license->last_authorized_at)
                            <span class="text-slate-400 font-tabular">({{ $license->last_authorized_at->translatedFormat('d M Y H:i') }} WIB)</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Terms History --}}
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-2xs overflow-hidden space-y-0">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-[#0c3c26]"></i>
                <h3 class="text-sm font-bold text-slate-900">Riwayat Penetapan Term Lisensi</h3>
            </div>
            <span class="text-xs text-slate-400 font-medium">Append-Only Term Ledger</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-700 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-4">Term</th>
                        <th class="py-2.5 px-4">Jenis Hak Akses</th>
                        <th class="py-2.5 px-4">Periode Efektif</th>
                        <th class="py-2.5 px-4">Status Term</th>
                        <th class="py-2.5 px-4">Otorisator</th>
                        <th class="py-2.5 px-4">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($license->terms as $term)
                        <tr class="hover:bg-slate-50/50">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                Term #{{ $term->term_number }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-medium text-slate-800">{{ $term->term_type_label }}</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-tabular">
                                {{ $term->start_date->translatedFormat('d M Y') }} s.d. {{ $term->expiration_date->translatedFormat('d M Y') }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($term->computed_status === 'active')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Aktif Berjalan
                                    </span>
                                @elseif($term->computed_status === 'pending')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Menunggu Periode
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Tuntas
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="text-slate-700">{{ $term->authorizer->name ?? 'Superuser' }}</span>
                                <div class="text-[10px] text-slate-400 font-tabular">{{ $term->created_at->translatedFormat('d M Y H:i') }}</div>
                            </td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">
                                {{ $term->notes ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 px-4 text-center text-slate-400 italic">
                                Belum ada riwayat term hak akses yang ditetapkan untuk lisensi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Automated Email Notifications & Reminders (LICENSE-05-03) --}}
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-2xs overflow-hidden space-y-0">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="mail" class="w-4 h-4 text-emerald-700"></i>
                <h3 class="text-sm font-bold text-slate-900">Riwayat Pengingat & Notifikasi Email Otomatis</h3>
            </div>
            <span class="text-xs text-slate-400 font-medium">H-14 Automated Dispatch</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-700 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-4">Tipe Pengingat</th>
                        <th class="py-2.5 px-4">Penerima (Admin Sekolah)</th>
                        <th class="py-2.5 px-4">Term Terkait</th>
                        <th class="py-2.5 px-4">Status Pengiriman</th>
                        <th class="py-2.5 px-4">Jadwal / Waktu Kirim</th>
                        <th class="py-2.5 px-4">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($license->notifications as $notif)
                        <tr class="hover:bg-slate-50/50">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-medium text-slate-900">
                                    {{ $notif->notification_type === 'reminder_h14' ? 'Pengingat H-14 Kedaluwarsa' : $notif->notification_type }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800">{{ $notif->recipient_name ?: 'Admin Sekolah' }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $notif->recipient_email }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-700">
                                {{ $notif->term ? 'Term #' . $notif->term->term_number : '—' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($notif->status === 'sent')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Terkirim
                                    </span>
                                @elseif($notif->status === 'queued')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Dalam Antrean
                                    </span>
                                @elseif($notif->status === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Gagal
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Dilewati
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-tabular">
                                @if($notif->sent_at)
                                    <span class="text-slate-700">{{ $notif->sent_at->translatedFormat('d M Y H:i') }}</span>
                                @else
                                    <span class="text-slate-400">Jadwal: {{ $notif->scheduled_for->translatedFormat('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-500 max-w-xs truncate">
                                {{ $notif->error_message ?: 'Terkirim tanpa kendala' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 px-4 text-center text-slate-400 italic">
                                Belum ada riwayat pengiriman notifikasi pengingat untuk lisensi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Activity & Audit Logs --}}
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-2xs overflow-hidden space-y-0">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
                <h3 class="text-sm font-bold text-slate-900">Jejak Audit & Aktivitas Lisensi</h3>
            </div>
            <span class="text-xs text-slate-400 font-medium">Immutable Activity Trail</span>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($license->logs as $log)
                <div class="p-4 hover:bg-slate-50/50 transition-colors flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $log->action }}
                            </span>
                            <span class="text-xs text-slate-600">oleh <strong>{{ $log->user->name ?? 'Superuser' }}</strong></span>
                        </div>
                        <p class="text-xs text-slate-700 leading-relaxed">{{ $log->details }}</p>
                        @if($log->reason)
                            <p class="text-[11px] text-slate-500 italic">
                                <strong>Alasan Audit:</strong> "{{ $log->reason }}"
                            </p>
                        @endif
                    </div>
                    <div class="text-[11px] text-slate-400 whitespace-nowrap font-tabular shrink-0 text-right">
                        {{ $log->created_at->translatedFormat('d M Y H:i:s') }} WIB
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 italic text-xs">
                    Belum ada log aktivitas yang tercatat.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal: Aktivasi Tahun Pertama --}}
    <div
        x-show="showActivateModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        @keydown.escape.window="showActivateModal = false"
    >
        <div
            @click.outside="showActivateModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Aktivasi Hak Akses Tahun Pertama</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tetapkan tanggal mulai efektif lisensi bundling 1 tahun kalender.</p>
                </div>
                <button
                    type="button"
                    @click="showActivateModal = false"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('master.licenses.activate', $license) }}" class="space-y-4">
                @csrf

                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Mulai Efektif <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="{{ date('Y-m-d') }}"
                        required
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    >
                    <p class="text-[11px] text-slate-400 mt-1">
                        Tanggal kedaluwarsa akan otomatis dihitung tepat +1 tahun kalender dengan proteksi tahun kabisat.
                    </p>
                </div>

                <div>
                    <label for="notes_act" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Otorisasi</label>
                    <textarea
                        id="notes_act"
                        name="notes"
                        rows="2"
                        placeholder="Contoh: Aktivasi hak akses LMS hasil komisioning paket pengadaan 2026."
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showActivateModal = false"
                        class="px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] rounded-xl hover:bg-[#0c3c26]/90 shadow-xs transition-colors"
                    >
                        Aktifkan Lisensi
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Perpanjangan Tahunan --}}
    <div
        x-show="showRenewModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        @keydown.escape.window="showRenewModal = false"
    >
        <div
            @click.outside="showRenewModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Perpanjangan Tahunan Lisensi LMS</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tambahkan term hak akses baru tanpa menimpa riwayat masa lalu.</p>
                </div>
                <button
                    type="button"
                    @click="showRenewModal = false"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('master.licenses.renew', $license) }}" class="space-y-4">
                @csrf

                <div>
                    <label for="renewal_start_date" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Mulai Term Baru (Opsional)
                    </label>
                    <input
                        type="date"
                        id="renewal_start_date"
                        name="renewal_start_date"
                        placeholder="Biarkan kosong untuk otomatis menyambung"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    >
                    <p class="text-[11px] text-slate-400 mt-1">
                        Jika dikosongkan, term perpanjangan otomatis dimulai tepat pada tanggal kedaluwarsa saat ini ({{ $license->expiration_date ? $license->expiration_date->format('Y-m-d') : 'hari ini' }}).
                    </p>
                </div>

                <div>
                    <label for="notes_ren" class="block text-xs font-semibold text-slate-700 mb-1">Catatan / Rujukan Administrasi</label>
                    <textarea
                        id="notes_ren"
                        name="notes"
                        rows="2"
                        placeholder="Contoh: Rujukan kontrak perpanjangan tahunan nomor KTR-2027-01."
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showRenewModal = false"
                        class="px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-emerald-700 rounded-xl hover:bg-emerald-800 shadow-xs transition-colors"
                    >
                        Otorisasi Perpanjangan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Koreksi Tanggal --}}
    <div
        x-show="showCorrectModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        @keydown.escape.window="showCorrectModal = false"
    >
        <div
            @click.outside="showCorrectModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4"
        >
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Koreksi Tanggal Lisensi LMS</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Penyesuaian tanggal efektif dengan pencatatan alasan audit wajib.</p>
                </div>
                <button
                    type="button"
                    @click="showCorrectModal = false"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('master.licenses.correct', $license) }}" class="space-y-4">
                @csrf

                <div>
                    <label for="corr_start_date" class="block text-xs font-semibold text-slate-700 mb-1">
                        Tanggal Mulai Efektif Baru <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        id="corr_start_date"
                        name="start_date"
                        value="{{ $license->start_date ? $license->start_date->format('Y-m-d') : date('Y-m-d') }}"
                        required
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    >
                </div>

                <div>
                    <label for="correction_reason" class="block text-xs font-semibold text-slate-700 mb-1">
                        Alasan Koreksi Audit (Wajib minimal 5 karakter) <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        id="correction_reason"
                        name="correction_reason"
                        rows="3"
                        required
                        placeholder="Contoh: Koreksi penyesuaian tanggal aktivasi sesuai surat perjanjian adendum nomor 02."
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showCorrectModal = false"
                        class="px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] rounded-xl hover:bg-[#0c3c26]/90 shadow-xs transition-colors"
                    >
                        Simpan Koreksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

