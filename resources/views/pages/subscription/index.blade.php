{{-- @deprecated LEGACY-01 / BR-504 — Retired SaaS subscription pricing page.
     Web routes to this view are disabled in routes/web.php.
     Preserved intact for historic reference and potential re-activation.
     Do NOT delete. --}}
@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">
                    Midtrans Snap Gateway
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian ATPH 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Paket Langganan &amp; Kuota AI Gemini
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                Tingkatkan kuota rekomendasi AI agronomi dan aktifkan fitur evaluasi rubrik 4-skala terbobot untuk guru pembimbing dan laboratorium SMK.
            </p>
        </div>

        <!-- Active Subscription Status Badge -->
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 min-w-[220px] space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Status Akun Aktif</span>
            <div class="flex items-center justify-between">
                <span class="text-sm font-extrabold text-slate-900">
                    {{ $activeSub ? $activeSub->plan->name : 'Akun Starter' }}
                </span>
                <span class="w-2.5 h-2.5 rounded-full {{ $activeSub ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
            </div>
            <div class="text-[11px] font-mono text-emerald-700 font-semibold flex items-center justify-between pt-1 border-t border-slate-200/60">
                <span>Sisa Kuota AI:</span>
                <span>{{ $remainingQuota === null ? 'Unlimited' : $remainingQuota . ' Kuota' }}</span>
            </div>
        </div>
    </div>

    <!-- Alert Success / Notification -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Pricing Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($plans as $plan)
            @php
                $isPro = $plan->code === 'plan-guru-pro';
                $isCurrent = $activeSub && $activeSub->plan_id === $plan->id;
            @endphp
            <div class="p-6 rounded-2xl bg-white border {{ $isPro ? 'border-emerald-500 ring-2 ring-emerald-500/20 shadow-md' : 'border-slate-200 shadow-sm' }} flex flex-col justify-between space-y-6 relative">
                @if($isPro)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-600 text-white tracking-wider shadow-sm">
                            Rekomendasi Guru
                        </span>
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">{{ $plan->name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Durasi: {{ $plan->duration_days }} Hari Aktif
                        </p>
                    </div>

                    <div class="flex items-baseline gap-1">
                        <span class="text-xs font-bold text-slate-500">Rp</span>
                        <span class="text-3xl font-extrabold text-slate-900 font-tabular">
                            {{ number_format((float)$plan->price, 0, ',', '.') }}
                        </span>
                        <span class="text-xs text-slate-400 font-mono">/ {{ $plan->duration_days >= 180 ? 'semester' : ($plan->duration_days >= 30 ? 'bulan' : 'tahun') }}</span>
                    </div>

                    <!-- Features List -->
                    <ul class="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                            <span><strong>{{ $plan->features['ai_quota'] == 0 ? 'Unlimited' : $plan->features['ai_quota'] }} Kuota</strong> Rekomendasi AI Gemini</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                            <span>Akses 12 Modul Kurikulum Praktikum</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                            <span>Stream Live Telemetri 7-in-1 Modbus</span>
                        </li>
                        @if($isPro || $plan->code === 'plan-lab-enterprise')
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                <span>Asesmen Rubrik 4-Skala Terbobot Guru</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                <span>Ekspor CSV Riwayat Sensor Tanpa Batas</span>
                            </li>
                        @endif
                        @if($plan->code === 'plan-lab-enterprise')
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                <span>Alokasi 6 Meja Trainer Kit Praktik</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                <span>Prioritas Dukungan Teknis Lab IoT</span>
                            </li>
                        @endif
                    </ul>
                </div>

                <div>
                    @if($isCurrent)
                        <button
                            type="button"
                            disabled
                            class="w-full py-2.5 px-4 rounded-lg bg-slate-100 text-slate-500 font-bold text-xs cursor-default flex items-center justify-center gap-1.5"
                        >
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                            <span>Paket Sedang Aktif</span>
                        </button>
                    @else
                        <form method="POST" action="{{ route('subscription.checkout') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-lg {{ $isPro ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-900 hover:bg-slate-800 text-white' }} font-bold text-xs transition-colors shadow-sm flex items-center justify-center gap-1.5"
                            >
                                <span>{{ $plan->price <= 0 ? 'Pilih Paket Gratis' : 'Langganan Sekarang' }}</span>
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Riwayat Transaksi Pembayaran -->
    @if($payments->isNotEmpty())
        <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Riwayat Pembayaran Midtrans Snap
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-mono text-[11px] uppercase">
                            <th class="py-2.5 px-3">Order ID</th>
                            <th class="py-2.5 px-3">Waktu</th>
                            <th class="py-2.5 px-3">Paket</th>
                            <th class="py-2.5 px-3 text-right">Nominal</th>
                            <th class="py-2.5 px-3 text-center">Metode</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                        @foreach($payments as $pay)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-bold text-slate-800">{{ $pay->order_id }}</td>
                                <td class="py-2.5 px-3 text-slate-500">{{ $pay->created_at->format('d M Y H:i') }}</td>
                                <td class="py-2.5 px-3 text-slate-700 font-sans font-medium">{{ $pay->plan->name ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900">Rp {{ number_format((float)$pay->amount, 0, ',', '.') }}</td>
                                <td class="py-2.5 px-3 text-center text-slate-500 uppercase">{{ $pay->payment_type ?? 'Snap' }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($pay->status === 'settlement' || $pay->status === 'capture')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                            Berhasil
                                        </span>
                                    @elseif($pay->status === 'pending')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                            Menunggu
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                                            {{ $pay->status }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- Midtrans Snap JS Integration -->
@if(request()->query('snap_token'))
    @push('scripts')
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $clientKey }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const token = '{{ request()->query('snap_token') }}';
            if (token && window.snap) {
                window.snap.pay(token, {
                    onSuccess: function(result) {
                        window.location.href = "{{ route('subscription.index') }}";
                    },
                    onPending: function(result) {
                        window.location.href = "{{ route('subscription.index') }}";
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal atau dibatalkan.');
                    },
                    onClose: function() {
                        // User menutup popup Snap tanpa menyelesaikan
                    }
                });
            }
        });
    </script>
    @endpush
@endif
@endsection
