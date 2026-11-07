<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — BITANIC LMS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
    </style>

    {{-- Inline critical styles for the login page to match the reference exactly --}}
    <style>
        :root {
            --u: min(calc(100vw / 1672), calc(100vh / 941));
            --green-900: #05381a;
            --green-800: #0a4d1c;
            --green-700: #0b6b14;
            --green-600: #0d7a16;
            --lime: #b6f52a;
            --lime-2: #d9ff3d;
            --text: #0e1a12;
            --muted: #4b5a50;
            --line: #dde3e0;
            --bg: #f4f7f6;
        }

        *,*::before,*::after { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: var(--bg); font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); -webkit-font-smoothing: antialiased; }
        body { overflow: hidden; }

        /* Kanvas desain — mengisi seluruh viewport; --u hanya menskalakan isi */
        .stage {
            position: fixed;
            inset: 0;
            overflow: hidden;
            background: radial-gradient(ellipse at 85% 20%, #ffffff 0%, #f3f6f5 60%, #eef2f1 100%);
        }

        /* ===== HERO (kiri) ===== */
        .hero {
            position: absolute; left: 0; top: 0;
            width: calc(var(--u) * 1672);
            height: 100%;
        }

        .hero-clip {
            position: absolute; inset: 0;
            clip-path: url(#heroClip);
            background: linear-gradient(180deg, #164d1f 0%, #0b3a17 100%);
            overflow: hidden;
        }

        .hero-photo {
            position: absolute;
            top: calc(var(--u) * -21);
            left: calc(var(--u) * 14);
            height: calc(var(--u) * 901);
            width: auto; max-width: none; display: block;
            filter: saturate(1.05) contrast(1.02);
        }

        .hero-overlay {
            position: absolute; inset: 0;
            background:
                linear-gradient(90deg,
                    rgba(4, 52, 22, .94) 0%,
                    rgba(6, 62, 26, .84) 22%,
                    rgba(8, 72, 30, .42) 38%,
                    rgba(8, 72, 30, 0) 56%),
                linear-gradient(180deg, rgba(3, 40, 16, .35) 0%, rgba(3, 40, 16, 0) 30%),
                linear-gradient(0deg, rgba(3, 40, 16, .45) 0%, rgba(3, 40, 16, 0) 28%);
        }

        .swoosh {
            position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none;
        }

        /* Teks hero */
        .hero-content {
            position: absolute;
            left: calc(var(--u) * 108);
            top: 0;
            width: calc(var(--u) * 520);
            height: 100%;
        }

        .hero-logo {
            position: absolute;
            left: calc(var(--u) * -6);
            top: calc(var(--u) * 158);
            width: calc(var(--u) * 290);
            height: auto;
            filter: drop-shadow(0 calc(var(--u) * 4) calc(var(--u) * 8) rgba(0, 0, 0, .25));
        }

        .badge {
            position: absolute; left: 0; top: calc(var(--u) * 291);
            height: calc(var(--u) * 44);
            padding: 0 calc(var(--u) * 20) 0 calc(var(--u) * 16);
            display: inline-flex; align-items: center; gap: calc(var(--u) * 10);
            border-radius: 999px;
            font-size: calc(var(--u) * 17); font-weight: 600; color: #fff;
            background: linear-gradient(90deg, rgba(18, 118, 34, .95), rgba(14, 96, 28, .9));
            border: 1px solid rgba(160, 235, 70, .35);
            box-shadow: 0 calc(var(--u) * 4) calc(var(--u) * 14) rgba(0, 0, 0, .18);
            white-space: nowrap;
        }
        .badge svg { width: calc(var(--u) * 20); height: calc(var(--u) * 20); color: var(--lime); }

        .hero-content h1 {
            position: absolute; left: 0; top: calc(var(--u) * 355);
            margin: 0; font-size: calc(var(--u) * 48); line-height: 1.09; font-weight: 800;
            letter-spacing: -.01em; color: #fff; white-space: nowrap;
            text-shadow: 0 calc(var(--u) * 2) calc(var(--u) * 12) rgba(0, 0, 0, .25);
        }
        .hero-content h1 .accent {
            background: linear-gradient(90deg, #a6f02a 0%, #d6ff3f 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            text-shadow: none;
        }

        .hero-content p {
            position: absolute; left: 0; top: calc(var(--u) * 538);
            width: calc(var(--u) * 420); margin: 0;
            font-size: calc(var(--u) * 18.5); line-height: 1.42; font-weight: 500;
            color: rgba(255, 255, 255, .95);
        }

        .bar {
            position: absolute; left: 0; top: calc(var(--u) * 661);
            width: calc(var(--u) * 76); height: calc(var(--u) * 5);
            border-radius: 99px;
            background: linear-gradient(90deg, #a6f02a, #d6ff3f);
        }

        /* ===== KARTU LOGIN (kanan) ===== */
        .login-card {
            position: absolute;
            left: calc(var(--u) * 1047);
            top: calc(var(--u) * 205);
            width: calc(var(--u) * 566);
            height: calc(var(--u) * 597);
            padding: 0 calc(var(--u) * 45);
            border-radius: calc(var(--u) * 64);
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfc 100%);
            box-shadow:
                0 calc(var(--u) * 24) calc(var(--u) * 60) rgba(20, 60, 40, .10),
                0 calc(var(--u) * 6) calc(var(--u) * 16) rgba(20, 60, 40, .06),
                inset 0 0 0 1px rgba(255, 255, 255, .9);
        }

        .card-logo {
            position: absolute;
            left: 50%; top: calc(var(--u) * 28);
            transform: translateX(-50%);
            width: calc(var(--u) * 210); height: auto;
        }

        .login-card h2 {
            position: absolute; left: 0; right: 0; top: calc(var(--u) * 146);
            margin: 0; font-size: calc(var(--u) * 36); font-weight: 800;
            letter-spacing: -.01em; color: #121a15; text-align: center;
            line-height: 1.15;
        }

        .login-card .sub {
            position: absolute; left: calc(var(--u) * 45); right: calc(var(--u) * 45);
            top: calc(var(--u) * 196);
            margin: 0; font-size: calc(var(--u) * 16.5); line-height: 1.42; font-weight: 500;
            color: var(--muted); text-align: center;
        }

        form { position: absolute; inset: 0; }

        .field {
            position: absolute; left: calc(var(--u) * 45);
            width: calc(var(--u) * 476); height: calc(var(--u) * 60);
            display: flex; align-items: center; gap: calc(var(--u) * 14);
            padding: 0 calc(var(--u) * 22);
            border-radius: 999px;
            background: #f8f9fa;
            border: calc(var(--u) * 1.6) solid var(--line);
            color: #2b3a31;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .field:nth-of-type(1) { top: calc(var(--u) * 262); }
        .field:nth-of-type(2) { top: calc(var(--u) * 340); }
        .field:focus-within {
            background: #fff; border-color: var(--green-600);
            box-shadow: 0 0 0 calc(var(--u) * 4) rgba(13, 122, 22, .14);
        }
        .field > svg { width: calc(var(--u) * 26); height: calc(var(--u) * 26); flex: none; }
        .field input {
            flex: 1; min-width: 0; border: 0; outline: 0; background: transparent;
            font: 500 calc(var(--u) * 18) "Plus Jakarta Sans", sans-serif; color: var(--text);
        }
        .field input::placeholder { color: #4d5b52; opacity: 1; }

        .eye {
            border: 0; background: transparent; padding: 0;
            width: calc(var(--u) * 30); height: calc(var(--u) * 30);
            display: grid; place-items: center; cursor: pointer;
            color: #27352c; border-radius: 50%;
        }
        .eye svg { width: calc(var(--u) * 26); height: calc(var(--u) * 26); }
        .eye .eye-on { display: none; }
        .eye[aria-pressed="true"] .eye-on { display: block; }
        .eye[aria-pressed="true"] .eye-off { display: none; }

        .row {
            position: absolute; left: calc(var(--u) * 49); top: calc(var(--u) * 420);
            width: calc(var(--u) * 472); height: calc(var(--u) * 36);
            display: flex; align-items: center; justify-content: space-between;
        }

        .check {
            display: inline-flex; align-items: center; gap: calc(var(--u) * 12);
            font-size: calc(var(--u) * 17); font-weight: 600; cursor: pointer; user-select: none;
        }
        .check input { position: absolute; opacity: 0; pointer-events: none; }
        .check .box {
            width: calc(var(--u) * 27); height: calc(var(--u) * 27);
            border-radius: calc(var(--u) * 7);
            display: grid; place-items: center;
            background: #fff; border: calc(var(--u) * 1.6) solid #c5cdc8;
            transition: background .15s, border-color .15s;
        }
        .check .box svg { width: 72%; height: 72%; opacity: 0; transform: scale(.6); transition: .15s; }
        .check input:checked + .box {
            background: linear-gradient(180deg, #0f8a1d, #0a6a14);
            border-color: #0a6a14;
        }
        .check input:checked + .box svg { opacity: 1; transform: scale(1); }
        .check input:focus-visible + .box { box-shadow: 0 0 0 calc(var(--u) * 4) rgba(13, 122, 22, .2); }

        .forgot {
            font-size: calc(var(--u) * 17); font-weight: 700;
            color: var(--green-700); text-decoration: none;
        }
        .forgot:hover { text-decoration: underline; }

        /* Aksi form: pesan + tombol ditumpuk supaya pesan tidak pernah menimpa tombol */
        .form-actions {
            position: absolute; left: calc(var(--u) * 45); top: calc(var(--u) * 476);
            width: calc(var(--u) * 478);
            display: flex; flex-direction: column; gap: calc(var(--u) * 8);
        }

        .form-message {
            margin: 0; padding: calc(var(--u) * 6) calc(var(--u) * 14);
            border: 1px solid; border-radius: calc(var(--u) * 12);
            font-size: calc(var(--u) * 14); line-height: 1.35; font-weight: 600;
            text-align: center;
        }
        .form-message.error { color: #a5261a; background: #fff4f2; border-color: #f3c3bd; }
        .form-message.success { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }

        .btn {
            width: 100%; height: calc(var(--u) * 57);
            border: 0; border-radius: 999px;
            display: flex; align-items: center; justify-content: center; gap: calc(var(--u) * 14);
            font: 700 calc(var(--u) * 21) "Plus Jakarta Sans", sans-serif; color: #fff;
            cursor: pointer;
            background: linear-gradient(90deg, #0b7a14 0%, #0a6212 55%, #0a5a12 100%);
            box-shadow:
                0 calc(var(--u) * 10) calc(var(--u) * 22) rgba(10, 98, 18, .28),
                inset 0 1px 0 rgba(255, 255, 255, .18);
            transition: transform .15s, box-shadow .2s, filter .2s;
        }
        .btn svg { width: calc(var(--u) * 26); height: calc(var(--u) * 26); }
        .btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn:disabled { cursor: progress; filter: saturate(.7) brightness(.95); }

        /* ===== MOBILE / LAYAR SEMPIT ===== */
        @media (max-width: 900px) {
            body { overflow: auto; display: block; }

            .stage {
                width: 100%; height: auto; min-height: 100vh;
                display: grid; place-items: center;
                padding: 24px 16px;
                background: linear-gradient(160deg, #0a4d1c 0%, #0d7a16 55%, #7fd62b 120%);
            }
            .hero { display: none; }

            .login-card {
                position: relative; left: auto; top: auto;
                width: min(100%, 440px); height: auto;
                padding: 28px 24px 32px; border-radius: 36px;
                display: flex; flex-direction: column; gap: 14px;
            }
            .card-logo, .login-card h2, .login-card .sub,
            form, .field, .row, .form-actions, .btn { position: static; transform: none; left: auto; top: auto; }

            .card-logo { width: 180px; margin: 0 auto 8px; }
            .login-card h2 { font-size: 28px; }
            .login-card .sub { font-size: 15px; white-space: normal; }
            form { display: flex; flex-direction: column; gap: 14px; }
            .field { width: 100%; height: 56px; padding: 0 18px; gap: 12px; border-width: 1.5px; }
            .field > svg, .eye svg { width: 24px; height: 24px; }
            .field input { font-size: 16px; }
            .row { width: 100%; height: auto; }
            .check, .forgot { font-size: 15px; }
            .check .box { width: 24px; height: 24px; border-width: 1.5px; border-radius: 7px; }
            .form-actions { width: 100%; gap: 12px; }
            .form-message { font-size: 14px; }
            .btn { width: 100%; height: 54px; font-size: 18px; gap: 12px; }
            .btn svg { width: 24px; height: 24px; }
        }
    </style>
</head>
<body>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <clipPath id="heroClip" clipPathUnits="objectBoundingBox">
            <path transform="scale(0.000598086 0.001062699)" d="M0 0 H1182 C1098 118 1006 196 985 376 C966 520 1002 640 904 760 C790 880 520 872 200 941 H0 Z" />
        </clipPath>
    </svg>

    <main class="stage">

        {{-- ============ PANEL KIRI (HERO) ============ --}}
        <section class="hero" aria-label="Tentang Bitanic">
            {{-- Foto + overlay, dipotong mengikuti kurva --}}
            <div class="hero-clip">
                <img
                    class="hero-photo"
                    src="{{ asset('bg-login.png') }}"
                    alt="Sensor IoT di dalam greenhouse"
                    width="1536"
                    height="1024"
                    fetchpriority="high"
                >
                <div class="hero-overlay"></div>
            </div>

            {{-- Ornamen gelombang & garis lime --}}
            <svg class="swoosh" viewBox="0 0 1672 941" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient id="limeLine" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#d9ff3d" />
                        <stop offset="0.55" stop-color="#8be02a" />
                        <stop offset="1" stop-color="#3fb52a" />
                    </linearGradient>
                    <linearGradient id="greenBlob" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#1c8a2c" />
                        <stop offset="1" stop-color="#0a4d1c" />
                    </linearGradient>
                    <linearGradient id="greenBlob2" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0" stop-color="#0d6a22" />
                        <stop offset="1" stop-color="#46c52d" />
                    </linearGradient>
                    <filter id="softGlow" x="-10%" y="-10%" width="120%" height="120%">
                        <feGaussianBlur stdDeviation="4" />
                    </filter>
                </defs>

                {{-- sudut kiri atas --}}
                <path d="M0 0 L335 0 C250 62 120 78 0 178 Z" fill="url(#greenBlob)" />
                <path d="M335 0 C250 62 120 78 0 178" fill="none" stroke="url(#limeLine)" stroke-width="5" stroke-linecap="round" />
                <path d="M300 0 C230 42 110 58 0 140" fill="none" stroke="#8be02a" stroke-opacity=".55" stroke-width="2" />

                {{-- sudut kiri bawah --}}
                <path d="M0 941 L0 858 C90 832 160 868 230 941 Z" fill="url(#greenBlob2)" />
                <path d="M0 858 C90 832 160 868 230 941" fill="none" stroke="url(#limeLine)" stroke-width="4" stroke-linecap="round" />

                {{-- garis lime pada tepi kurva utama --}}
                <path id="edge" d="M1182 0 C1098 118 1006 196 985 376 C966 520 1002 640 904 760 C790 880 520 872 200 941"
                      fill="none" stroke="#9bea2b" stroke-opacity=".6" stroke-width="14" filter="url(#softGlow)" />
                <path d="M1182 0 C1098 118 1006 196 985 376 C966 520 1002 640 904 760 C790 880 520 872 200 941"
                      fill="none" stroke="url(#limeLine)" stroke-width="7" stroke-linecap="round" />
            </svg>

            {{-- Konten teks --}}
            <div class="hero-content">
                {{-- Logo (white version for dark hero) --}}
                <img class="hero-logo" src="{{ asset('logo.png') }}" alt="Bitanic" width="2172" height="724">

                <span class="badge">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 4c-9 0-15 4-15 11 0 1.2.2 2.2.5 3C7 16 9.5 13 14 11c-3 2.5-5 5-6 8.5 1 .3 2 .5 3 .5 7 0 9-7 9-16z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                    </svg>
                    LMS & IoT Pertanian
                </span>

                <h1>
                    Pembelajaran<br />
                    Vokasi Pertanian<br />
                    <span class="accent">yang Lebih Nyata</span>
                </h1>

                <p>
                    Platform pembelajaran terintegrasi dengan Trainer Kit IoT untuk mendukung praktikum, monitoring sensor, dan penilaian kompetensi siswa SMK.
                </p>

                <span class="bar"></span>
            </div>

                    </section>

        {{-- ============ PANEL KANAN (LOGIN) ============ --}}
        <section class="login-card" aria-labelledby="login-title">
            <img class="card-logo" src="{{ asset('logo-dark.png') }}" alt="Bitanic" width="2172" height="724">

            <h2 id="login-title">Selamat datang kembali</h2>
            <p class="sub">Masuk dengan akun sekolah untuk melanjutkan pembelajaran.</p>

            <form method="POST" action="{{ route('login.store') }}" id="loginForm"
                  x-data="{ submitting: false }"
                  x-on:submit="submitting = true">
                @csrf

                {{-- Email field - email only per binding decision #10 --}}
                <label class="field" for="email">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.7" />
                        <path d="M3.8 7l8.2 6 8.2-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                    </svg>
                    <input
                        id="email" name="email" type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        placeholder="Email sekolah"
                        aria-label="Email sekolah"
                        required
                        autofocus
                        @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                    >
                    @error('email')
                        <p id="email-error" class="sr-only">{{ $message }}</p>
                    @enderror
                </label>

                {{-- Password field with reveal toggle --}}
                <label class="field" for="password" x-data="{ show: false }">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="5" y="10.5" width="14" height="10" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.7" />
                        <path d="M8.2 10.5V8a3.8 3.8 0 0 1 7.6 0v2.5" fill="none" stroke="currentColor" stroke-width="1.7" />
                        <circle cx="12" cy="15.5" r="1.2" fill="currentColor" />
                    </svg>
                    <input
                        id="password" name="password" type="password"
                        autocomplete="current-password"
                        placeholder="Kata sandi"
                        aria-label="Kata sandi"
                        required
                        x-bind:type="show ? 'text' : 'password'"
                        @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                    >
                    @error('password')
                        <p id="password-error" class="sr-only">{{ $message }}</p>
                    @enderror
                    <button
                        type="button"
                        class="eye"
                        x-cloak
                        x-on:click="show = !show"
                        x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        x-bind:aria-pressed="show"
                        aria-label="Tampilkan kata sandi"
                    >
                        <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 12s3.5-6.5 9-6.5S21 12 21 12s-3.5 6.5-9 6.5S3 12 3 12z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="2.8" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <path d="M4 4l16 16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                        </svg>
                        <svg class="eye-on" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 12s3.5-6.5 9-6.5S21 12 21 12s-3.5 6.5-9 6.5S3 12 3 12z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="2.8" fill="none" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                    </button>
                </label>

                {{-- Remember me row --}}
                <div class="row">
                    <label class="check" for="remember">
                        <input id="remember" name="remember" type="checkbox" value="1">
                        <span class="box">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 12.5l4.2 4.2L18.5 7.8" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        Ingat saya
                    </label>
                    {{-- Binding decision #8: no "Lupa password?" link — no recovery route exists --}}
                </div>

                {{-- Pesan + tombol submit ditumpuk agar pesan tidak menimpa tombol --}}
                <div class="form-actions">
                    @if (session('success'))
                        <p class="form-message success" role="status">{{ session('success') }}</p>
                    @endif

                    @if ($errors->any())
                        <p class="form-message error" role="alert">{{ $errors->first() }}</p>
                    @endif

                    <button type="submit" class="btn" x-bind:disabled="submitting">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 4h4.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M3.5 12H15M11.5 8l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span x-show="!submitting">Masuk</span>
                        <span x-cloak x-show="submitting">Memproses…</span>
                    </button>
                </div>
            </form>
        </section>

    </main>
</body>
</html>