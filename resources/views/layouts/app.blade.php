<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Bitanic' }} — Precision Agriculture LMS</title>

    <!-- Google Fonts Preconnect & Stylesheets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Scripts & Styles via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body
    class="h-full font-sans text-slate-800 antialiased selection:bg-emerald-100 selection:text-emerald-900"
    x-data="{
        sidebarOpen: false,
        aiDrawerOpen: false,
        isCompact: false,
        init() {
            const mq = window.matchMedia('(max-width: 1023px)');
            const sync = (e) => {
                this.isCompact = e.matches;
                if (! e.matches) this.sidebarOpen = false;
            };
            sync(mq);
            mq.addEventListener('change', sync);
        }
    }"
    @keydown.escape.window="sidebarOpen = false"
    @open-ai-drawer.window="aiDrawerOpen = true"
    @close-ai-drawer.window="aiDrawerOpen = false"
>
    <div class="min-h-full flex">
        <!-- Sidebar Component -->
        @include('components.sidebar')

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Mobile Navigation Bar (compact viewports only) -->
            <header
                x-show="isCompact"
                x-cloak
                class="lg:hidden h-14 bg-white border-b border-slate-200 px-4 flex items-center justify-between sticky top-0 z-30"
                role="banner"
            >
                <button
                    type="button"
                    @click="sidebarOpen = ! sidebarOpen"
                    :aria-expanded="sidebarOpen ? 'true' : 'false'"
                    aria-controls="app-sidebar"
                    class="p-2 -ml-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                    aria-label="Buka navigasi"
                >
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-extrabold tracking-tight text-slate-900">BITANIC</span>
                    <span class="text-xs font-mono text-slate-500">Lab Presisi Agritech</span>
                </div>

                <span class="w-9" aria-hidden="true"></span>
            </header>

            <!-- Global Flash Messages -->
            @foreach (['success' => ['check-circle-2', 'emerald'], 'warning' => ['alert-triangle', 'amber'], 'error' => ['alert-octagon', 'rose']] as $key => [$icon, $tone])
                @if(session($key))
                    <div class="mx-4 sm:mx-6 mt-4 p-4 rounded-lg bg-{{ $tone }}-50 border border-{{ $tone }}-200 flex items-center justify-between text-xs text-{{ $tone }}-800" role="alert">
                        <div class="flex items-center gap-2">
                            <i data-lucide="{{ $icon }}" class="w-4 h-4 text-{{ $tone }}-600 shrink-0"></i>
                            <span class="font-medium">{{ session($key) }}</span>
                        </div>
                        <button type="button" @click="$el.parentElement.remove()" class="text-{{ $tone }}-600 hover:text-{{ $tone }}-800" aria-label="Tutup notifikasi">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                @endif
            @endforeach

            <!-- Main Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6" role="main">
                @yield('content')
                {{ $slot ?? '' }}
            </main>

            <!-- Clean Footer -->
            <footer class="h-12 border-t border-slate-200 bg-white px-6 flex items-center justify-between text-[11px] text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-700">BITANIC Platform</span>
                    <span>•</span>
                    <span>Laboratorium IoT SMK Pertanian Presisi</span>
                </div>
                <div>
                    <span>Versi 2.0 (LMS Edition)</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Slide-Over Drawer AI Gemini -->
    @include('components.slide-over-ai')

    @stack('scripts')
</body>
</html>