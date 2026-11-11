<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masa Berlaku Lisensi Berakhir — BITANIC LMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center px-4 py-12">
    <main class="w-full max-w-lg">
        {{-- Branding Header --}}
        <div class="mb-8 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200/80 mb-4 shadow-2xs">
                <svg class="w-7 h-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-800 font-sans">Bitanic IoT LMS • SMK Pertanian</p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 font-sans">Masa Berlaku Lisensi Berakhir</h1>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                Akses pembelajaran digital untuk institusi Anda sementara ditangguhkan hingga perpanjangan lisensi diselesaikan.
            </p>
        </div>

        {{-- Notice Container --}}
        <section class="border border-slate-200/80 bg-white rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
            {{-- Institutional & License Card --}}
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200/60">
                    <span class="text-xs font-medium text-slate-500">Institusi Sekolah</span>
                    <span class="text-xs font-bold text-slate-800 text-right">{{ $school->name ?? 'Sekolah Mitra' }}</span>
                </div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200/60">
                    <span class="text-xs font-medium text-slate-500">Nomor Lisensi LMS</span>
                    <span class="font-mono text-xs font-bold text-slate-900">{{ $license->license_number ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Status Lisensi</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                        Kedaluwarsa (Expired)
                    </span>
                </div>
            </div>

            {{-- Assurance Box --}}
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 text-xs text-emerald-900 space-y-1.5 leading-relaxed">
                <div class="flex items-center gap-1.5 font-semibold text-emerald-800">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    <span>Data Praktikum & Hasil Belajar Tersimpan Aman</span>
                </div>
                <p>
                    Seluruh lembar kerja siswa (LKS), penilaian guru, rapor capaian kompetensi, dan histori telemetri perangkat Trainer Kit Anda tetap tersimpan utuh dan tidak dihapus.
                </p>
            </div>

            {{-- Action Steps --}}
            <div class="space-y-2 text-xs text-slate-600 leading-relaxed">
                <p class="font-semibold text-slate-800">Langkah untuk Mengaktifkan Kembali:</p>
                <ol class="list-decimal list-inside space-y-1 pl-1">
                    <li>Hubungi Koordinator / Kepala Laboratorium di sekolah Anda.</li>
                    <li>Pihak sekolah mengonfirmasi perpanjangan lisensi tahunan ke Tim Kemitraan Bitanic.</li>
                    <li>Setelah lisensi diotorisasi ulang oleh Pusat, seluruh akses guru dan siswa akan pulih seketika tanpa perlu konfigurasi ulang.</li>
                </ol>
            </div>

            {{-- Support & Helpdesk --}}
            <div class="pt-4 border-t border-slate-100 text-xs text-slate-500 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <span class="font-medium text-slate-700">Pusat Bantuan Kemitraan:</span><br>
                    <a href="mailto:{{ config('license.support_contact.email', 'admin@bitanic.id') }}" class="text-emerald-700 hover:underline">
                        {{ config('license.support_contact.email', 'admin@bitanic.id') }}
                    </a>
                    • {{ config('license.support_contact.phone', '+62 812-3456-7890') }}
                </div>
            </div>

            {{-- Actions --}}
            <div class="pt-2 flex flex-col-reverse sm:flex-row items-center justify-end gap-3">
                <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                    @csrf
                    <button
                        type="submit"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition-colors text-center cursor-pointer"
                    >
                        Keluar dari Akun
                    </button>
                </form>

                @if($user->isAdmin())
                    <a
                        href="{{ route('school.license.show') }}"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-2xs transition-colors text-center"
                    >
                        Periksa Status Lisensi Sekolah
                    </a>
                @endif
            </div>
        </section>

        <p class="mt-6 text-center text-[11px] text-slate-400">
            &copy; {{ date('Y') }} PT Bitanic Agroteknologi Vokasi. Seluruh Hak Cipta Dilindungi.
        </p>
    </main>
</body>
</html>

