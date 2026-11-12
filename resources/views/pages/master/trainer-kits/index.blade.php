@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ registerModalOpen: false }">
    <!-- Breadcrumb & Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <span>Data Master</span>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Master Serial &amp; Inventaris Trainer Kit</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Inventaris Trainer Kit
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Kelola serial fisik, kepemilikan sekolah, workstation, serah terima, dan garansi hardware.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 shrink-0">
            <button
                type="button"
                @click="registerModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-xs shadow-[#0c3c26]/20 transition-colors cursor-pointer"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>+ Daftarkan Unit</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
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

    <!-- Semantically Accurate KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        <!-- 1. Total Unit Fisik -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-medium">Total Unit Fisik</span>
                <i data-lucide="cpu" class="w-4 h-4 text-slate-400"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-slate-900 font-tabular">{{ number_format($totalCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Registri serial fisik pabrik</div>
        </div>

        <!-- 2. Stok Belum Terjual -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-slate-600 mb-2">
                <span class="text-xs font-medium">Stok Belum Terjual</span>
                <i data-lucide="package" class="w-4 h-4 text-slate-400"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-slate-700 font-tabular">{{ number_format($registeredStockCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Stok gudang belum terjual</div>
        </div>

        <!-- 3. Unit Milik Sekolah -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-blue-600 mb-2">
                <span class="text-xs font-medium">Unit Milik Sekolah</span>
                <i data-lucide="building-2" class="w-4 h-4 text-blue-500"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-blue-700 font-tabular">{{ number_format($schoolOwnedCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Kepemilikan komersial sekolah</div>
        </div>

        <!-- 4. Terhubung Workstation -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-indigo-600 mb-2">
                <span class="text-xs font-medium">Terhubung Workstation</span>
                <i data-lucide="layout-grid" class="w-4 h-4 text-indigo-500"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-indigo-700 font-tabular">{{ number_format($workstationLinkedCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Terikat stasiun IoT alat</div>
        </div>

        <!-- 5. Aktif Operasional -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between text-emerald-600 mb-2">
                <span class="text-xs font-medium">Aktif Operasional</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold text-emerald-700 font-tabular">{{ number_format($activatedCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Siap pembelajaran praktikum</div>
        </div>
    </div>

    <!-- Explanatory note on overlapping KPIs & Status Lifecycle Breakdown -->
    <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-600 flex flex-col md:flex-row md:items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-slate-400 shrink-0"></i>
            <span>
                <strong>Catatan Bisnis:</strong> Metrik unit milik sekolah dan workstation merupakan metrik operasional non-disjoint (mencakup unit terjual, terpasang, dan aktif).
            </span>
        </div>
        <div class="flex items-center gap-3 text-[11px] font-mono font-tabular shrink-0 text-slate-500">
            <span>Status Lifecycle:</span>
            <span>Pabrik: {{ $registeredStockCount }}</span>
            <span>&bull;</span>
            <span>Terjual: {{ $soldCount }}</span>
            <span>&bull;</span>
            <span>Ditugaskan: {{ $assignedCount }}</span>
            <span>&bull;</span>
            <span class="text-emerald-700 font-semibold">Aktif: {{ $activatedCount }}</span>
        </div>
    </div>

    <!-- Operational Filter Tabs -->
    <div class="flex items-center gap-1.5 border-b border-slate-200 overflow-x-auto pb-1">
        <a
            href="{{ route('master.trainer-kits.index', array_merge(request()->except('tab', 'page'), ['tab' => 'all'])) }}"
            class="px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                {{ ($tab ?? 'all') === 'all' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            Semua Unit ({{ $totalCount }})
        </a>
        <a
            href="{{ route('master.trainer-kits.index', array_merge(request()->except('tab', 'page'), ['tab' => 'factory_stock'])) }}"
            class="px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                {{ ($tab ?? '') === 'factory_stock' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            Stok Pabrik ({{ $registeredStockCount }})
        </a>
        <a
            href="{{ route('master.trainer-kits.index', array_merge(request()->except('tab', 'page'), ['tab' => 'school_owned'])) }}"
            class="px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                {{ ($tab ?? '') === 'school_owned' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            Milik Sekolah ({{ $schoolOwnedCount }})
        </a>
        <a
            href="{{ route('master.trainer-kits.index', array_merge(request()->except('tab', 'page'), ['tab' => 'maintenance'])) }}"
            class="px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                {{ ($tab ?? '') === 'maintenance' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            Dalam Perawatan ({{ $lifecycleDistribution['maintenance'] ?? 0 }})
        </a>
        <a
            href="{{ route('master.trainer-kits.index', array_merge(request()->except('tab', 'page'), ['tab' => 'decommissioned'])) }}"
            class="px-4 py-2 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                {{ ($tab ?? '') === 'decommissioned' ? 'border-[#0c3c26] text-[#0c3c26]' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            Dipensiunkan ({{ $lifecycleDistribution['decommissioned'] ?? 0 }})
        </a>
    </div>

    <!-- Filter and Search Toolbar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs">
        <form method="GET" action="{{ route('master.trainer-kits.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="relative sm:col-span-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari serial, model, sekolah, BAST..."
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>

            <div>
                <select
                    name="status"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Status Lifecycle</option>
                    <option value="registered" {{ $status === 'registered' ? 'selected' : '' }}>Stok Pabrik (Registered)</option>
                    <option value="sold" {{ $status === 'sold' ? 'selected' : '' }}>Terjual (Sold)</option>
                    <option value="assigned" {{ $status === 'assigned' ? 'selected' : '' }}>Terikat Workstation (Assigned)</option>
                    <option value="activated" {{ $status === 'activated' ? 'selected' : '' }}>Aktif Operasional (Activated)</option>
                    <option value="maintenance" {{ $status === 'maintenance' ? 'selected' : '' }}>Perawatan (Maintenance)</option>
                    <option value="decommissioned" {{ $status === 'decommissioned' ? 'selected' : '' }}>Dipensiunkan (Decommissioned)</option>
                </select>
            </div>

            <div>
                <select
                    name="warranty_status"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Status Garansi</option>
                    <option value="active" {{ $warrantyStatus === 'active' ? 'selected' : '' }}>Garansi Aktif</option>
                    <option value="expired" {{ $warrantyStatus === 'expired' ? 'selected' : '' }}>Garansi Kedaluwarsa</option>
                    <option value="not_recorded" {{ $warrantyStatus === 'not_recorded' ? 'selected' : '' }}>Garansi Belum Dicatat</option>
                </select>
            </div>

            <div>
                <select
                    name="school_id"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Sekolah Pemilik</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" {{ (string)$schoolId === (string)$school->id ? 'selected' : '' }}>
                            {{ $school->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <select
                    name="batch"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Batch</option>
                    @foreach($batches as $b)
                        <option value="{{ $b }}" {{ $batch === $b ? 'selected' : '' }}>
                            Batch {{ $b }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Serial / Model</th>
                        <th class="py-3 px-4">Sekolah Pemilik</th>
                        <th class="py-3 px-4">Workstation</th>
                        <th class="py-3 px-4">Status Operasional</th>
                        <th class="py-3 px-4">Serah Terima / BAST</th>
                        <th class="py-3 px-4">Status Garansi</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($inventories as $item)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <!-- Serial / Model -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <a href="{{ route('master.trainer-kits.show', $item) }}" class="font-mono font-bold text-slate-900 hover:text-[#0c3c26] text-xs">
                                    {{ $item->serial_number }}
                                </a>
                                <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                                    {{ $item->model_name }}
                                    @if($item->batch_number)
                                        &bull; <span class="font-mono text-slate-400">Batch {{ $item->batch_number }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Sekolah Pemilik -->
                            <td class="py-3 px-4">
                                @if($item->school)
                                    <div class="font-semibold text-slate-900">{{ $item->school->name }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        NPSN: {{ $item->school->npsn ?? '—' }}
                                        @if($item->latestSaleRecord)
                                            &bull; PO: {{ $item->latestSaleRecord->sale_reference }}
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-medium border border-slate-200">
                                        Stok Pabrik (Belum Terjual)
                                    </span>
                                @endif
                            </td>

                            <!-- Workstation -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($item->device)
                                    <div class="inline-flex items-center gap-1.5 font-medium text-slate-900">
                                        <i data-lucide="radio" class="w-3.5 h-3.5 text-indigo-600"></i>
                                        <span class="font-mono font-bold">{{ $item->device->code }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-[140px]">
                                        {{ $item->device->name }}
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px]">Belum terhubung</span>
                                @endif
                            </td>

                            <!-- Status Operasional (Lifecycle) -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @php
                                    $statusClasses = [
                                        'registered' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        'sold' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'assigned' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'activated' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'maintenance' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'decommissioned' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    ];
                                    $statusLabels = [
                                        'registered' => 'Stok Pabrik',
                                        'sold' => 'Terjual',
                                        'assigned' => 'Terikat Workstation',
                                        'activated' => 'Aktif Operasional',
                                        'maintenance' => 'Perawatan',
                                        'decommissioned' => 'Dipensiunkan',
                                    ];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusClasses[$item->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                    @if($item->status === 'activated')
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    @endif
                                    {{ $statusLabels[$item->status] ?? ucfirst($item->status) }}
                                </span>
                            </td>

                            <!-- Serah Terima / BAST -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($item->handover_date && $item->handover)
                                    <div class="font-mono font-medium text-slate-800 text-[11px]">
                                        {{ $item->handover->bast_number }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-tabular">
                                        {{ $item->handover_date->translatedFormat('d M Y') }}
                                    </div>
                                @elseif($item->handover_date)
                                    <div class="text-[11px] text-slate-700 font-tabular">
                                        {{ $item->handover_date->translatedFormat('d M Y') }}
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px]">Belum BAST</span>
                                @endif
                            </td>

                            <!-- Status Garansi Hardware -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $item->warranty_status_badge_class }}">
                                    {{ $item->warranty_status_label }}
                                </span>
                                @if($item->warranty_expiration_date)
                                    <div class="text-[10px] text-slate-400 font-tabular mt-0.5">
                                        Hingga {{ $item->warranty_expiration_date->translatedFormat('d M Y') }}
                                    </div>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('master.trainer-kits.show', $item) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white font-semibold text-xs transition-colors"
                                >
                                    <span>Kelola Unit</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="cpu" class="w-8 h-8 text-slate-300 mb-2"></i>
                                    <p class="text-sm font-medium text-slate-700">Tidak ada unit trainer kit yang cocok</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Coba ubah filter atau daftarkan nomor seri fisik baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventories->hasPages())
            <div class="p-3.5 border-t border-slate-100 bg-slate-50/50">
                {{ $inventories->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Registrasi Unit Fisik Baru -->
    <div
        x-show="registerModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="registerModalOpen = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="cpu" class="w-5 h-5 text-[#0c3c26]"></i>
                        <h3 class="text-sm font-bold text-slate-900">Daftarkan Nomor Seri Unit Baru</h3>
                    </div>
                    <button type="button" @click="registerModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('master.trainer-kits.store') }}" class="space-y-3.5">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Seri (Serial Number) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="serial_number"
                            required
                            placeholder="Contoh: BTK-2026-0007"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                        <p class="text-[11px] text-slate-400 mt-1">Nomor seri unik yang tertera pada casing fisik trainer kit.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Model</label>
                            <input
                                type="text"
                                name="model_name"
                                value="Bitanic Trainer Kit v1.0"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Revisi Hardware</label>
                            <input
                                type="text"
                                name="hardware_revision"
                                value="rev1.2"
                                placeholder="rev1.0 / rev1.2"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Batch Produksi</label>
                        <input
                            type="text"
                            name="batch_number"
                            placeholder="Contoh: BATCH-2026-Q1"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Catatan inspeksi QC pabrik atau perakitan..."
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="registerModalOpen = false"
                            class="px-3.5 py-2 text-xs font-medium text-slate-600 hover:text-slate-800 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] hover:bg-[#092d1c] rounded-xl shadow-xs transition-colors cursor-pointer"
                        >
                            Simpan ke Inventaris
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
