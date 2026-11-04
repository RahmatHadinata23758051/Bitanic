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
        aiDrawerOpen: false
    }"
    @open-ai-drawer.window="aiDrawerOpen = true"
    @close-ai-drawer.window="aiDrawerOpen = false"
>
    <div class="min-h-full flex">
        <!-- Backdrop mobile -->
        <div
            x-cloak
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/40 z-30 lg:hidden"
        ></div>

        <!-- Sidebar Component -->
        @include('components.sidebar')

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
            <!-- Topbar Component -->
            @include('components.topbar', [
                'activeDevice' => $activeDevice ?? null,
                'devices' => $devices ?? [],
                'criticalAlertsCount' => $criticalAlertsCount ?? 0,
            ])

            <!-- Global Flash Messages -->
            @if(session('success'))
                <div class="mx-4 sm:mx-6 mt-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-between text-xs text-emerald-800">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-4 sm:mx-6 mt-4 p-4 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-between text-xs text-rose-800">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 shrink-0"></i>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-800">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            @endif

            <!-- Main Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
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
