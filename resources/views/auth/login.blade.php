<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — BITANIC LMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center px-4 py-10">
    <main class="w-full max-w-md">
        <div class="mb-8 text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">BITANIC LMS</p>
            <h1 class="mt-3 text-2xl font-bold tracking-tight">Portal Laboratorium Vokasi IoT</h1>
            <p class="mt-2 text-sm text-slate-600">Masuk menggunakan akun sekolah Anda.</p>
        </div>

        <section class="border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if (session('success'))
                <div role="status" class="mb-5 border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-5 border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-800">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        required
                        autofocus
                        class="block w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                        aria-describedby="email-error"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-800">Kata sandi</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="block w-full border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                    >
                </div>

                <label for="remember" class="flex items-center gap-2 text-sm text-slate-700">
                    <input id="remember" name="remember" type="checkbox" value="1" class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-700">
                    Ingat saya
                </label>

                <button type="submit" class="w-full bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                    Masuk
                </button>
            </form>
        </section>

        <p class="mt-6 text-center text-xs text-slate-500">Akses hanya untuk warga sekolah yang memiliki akun terdaftar.</p>
    </main>
</body>
</html>
