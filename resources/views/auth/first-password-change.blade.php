<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pembaruan Kata Sandi Awal — BITANIC LMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center px-4 py-10">
    <main class="w-full max-w-md">
        <div class="mb-8 text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">BITANIC LMS</p>
            <h1 class="mt-3 text-2xl font-bold tracking-tight">Perbarui Kata Sandi Awal</h1>
            <p class="mt-2 text-sm text-slate-600">Demi keamanan akun vokasi Anda, silakan ubah kata sandi awal sementara Anda menjadi kata sandi pribadi baru.</p>
        </div>

        <section class="border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if (session('warning'))
                <div role="status" class="mb-5 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-5 border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.first_change.update') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-800">Kata Sandi Saat Ini (Awal)</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        required
                        autofocus
                        class="block w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-800">Kata Sandi Baru</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="block w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                        aria-describedby="password-rules"
                    >
                    <p id="password-rules" class="mt-1 text-xs text-slate-500">Minimal 12 karakter, mengandung huruf besar, huruf kecil, angka, dan simbol.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-800">Konfirmasi Kata Sandi Baru</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        class="block w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                    >
                </div>

                <button type="submit" class="w-full bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                    Simpan & Lanjutkan ke Dashboard
                </button>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-4 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-slate-600 hover:text-slate-900 underline">
                        Keluar dari Sesi
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
