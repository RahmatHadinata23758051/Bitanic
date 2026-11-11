@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ registerModalOpen: false }">
    <!-- Breadcrumb & Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <span>Data Master</span>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Trainer Kit</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Master Serial &amp; Inventaris Trainer Kit
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Pencatatan nomor seri fisik pabrik, riwayat penjualan, alokasi sekolah, dan aktivasi workstation IoT.
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
                <span>Daftarkan Serial Baru</span>
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

    <!-- Inventory Overview Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-medium">Total Unit</span>
                <i data-lucide="cpu" class="w-4 h-4 text-slate-400"></i>
            </div>
            <div class="text-xl font-bold text-slate-900 font-tabular">{{ number_format($totalCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Registri global</div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-medium">Stok Pabrik</span>
                <i data-lucide="package" class="w-4 h-4 text-slate-400"></i>
            </div>
            <div class="text-xl font-bold text-slate-700 font-tabular">{{ number_format($registeredStockCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Belum terjual</div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-blue-600 mb-2">
                <span class="text-xs font-medium">Terjual</span>
                <i data-lucide="shopping-cart" class="w-4 h-4 text-blue-500"></i>
            </div>
            <div class="text-xl font-bold text-blue-700 font-tabular">{{ number_format($soldCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Milik sekolah</div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-indigo-600 mb-2">
                <span class="text-xs font-medium">Workstation</span>
                <i data-lucide="layout-grid" class="w-4 h-4 text-indigo-500"></i>
            </div>
            <div class="text-xl font-bold text-indigo-700 font-tabular">{{ number_format($assignedCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Terikat ke alat</div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between text-emerald-600 mb-2">
                <span class="text-xs font-medium">Aktif Operasional</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
            </div>
            <div class="text-xl font-bold text-emerald-700 font-tabular">{{ number_format($activatedCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Siap pembelajaran</div>
        </div>
    </div>

    <!-- Filter and Search Toolbar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-xs">
        <form method="GET" action="{{ route('master.trainer-kits.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="relative sm:col-span-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nomor seri, model, batch, no PO..."
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                >
            </div>

            <div>
                <select
                    name="status"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Status</option>
                    <option value="registered" {{ $status === 'registered' ? 'selected' : '' }}>Stok Pabrik (Registered)</option>
                    <option value="sold" {{ $status === 'sold' ? 'selected' : '' }}>Terjual / Dimiliki (Sold)</option>
                    <option value="assigned" {{ $status === 'assigned' ? 'selected' : '' }}>Terikat Workstation (Assigned)</option>
                    <option value="activated" {{ $status === 'activated' ? 'selected' : '' }}>Aktif Operasional (Activated)</option>
                    <option value="maintenance" {{ $status === 'maintenance' ? 'selected' : '' }}>Perawatan (Maintenance)</option>
                    <option value="decommissioned" {{ $status === 'decommissioned' ? 'selected' : '' }}>Dipensiunkan (Decommissioned)</option>
                </select>
            </div>

            <div>
                <select
                    name="school_id"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-[#0c3c26]"
                    onchange="this.form.submit()"
                >
                    <option value="">Semua Sekolah</option>
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
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Nomor Seri</th>
                        <th class="py-3 px-4">Model & Rev</th>
                        <th class="py-3 px-4">Batch</th>
                        <th class="py-3 px-4">Kepemilikan Sekolah</th>
                        <th class="py-3 px-4">Workstation Terikat</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($inventories as $item)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3 px-4 font-mono font-semibold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('master.trainer-kits.show', $item) }}" class="text-[#0c3c26] hover:underline">
                                    {{ $item->serial_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-900">{{ $item->model_name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $item->hardware_revision ?? 'rev1.0' }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($item->batch_number)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-mono">
                                        {{ $item->batch_number }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($item->school)
                                    <div class="font-medium text-slate-900">{{ $item->school->name }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        Ref: {{ $item->latestSaleRecord?->sale_reference ?? 'Alokasi Awal' }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 text-[11px] font-medium border border-amber-200">
                                        Stok Pabrik (Belum Terjual)
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($item->device)
                                    <div class="inline-flex items-center gap-1.5 font-medium text-slate-900">
                                        <i data-lucide="radio" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        <span>{{ $item->device->name }}</span>
                                        <span class="text-slate-400 font-mono text-[11px]">({{ $item->device->code }})</span>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px]">Belum dikaitkan</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @php
                                    $statusClasses = [
                                        'registered' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        'sold' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'assigned' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'activated' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusClasses[$item->status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                    {{ $statusLabels[$item->status] ?? ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('master.trainer-kits.show', $item) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-medium transition-colors"
                                >
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                    <span>Detail</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-xs">Tidak ada data unit trainer kit yang cocok dengan filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventories->hasPages())
            <div class="p-3.5 border-t border-slate-200 bg-slate-50/50">
                {{ $inventories->links() }}
            </div>
        @endif
    </div>

    <!-- Registration Modal -->
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

