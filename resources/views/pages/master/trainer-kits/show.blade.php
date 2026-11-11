@extends('layouts.app')

@section('content')
<div
    class="space-y-6"
    x-data="{
        editModalOpen: false,
        saleModalOpen: false,
        assignModalOpen: false,
        activateModalOpen: false
    }"
>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
                <a href="{{ route('master.trainer-kits.index') }}" class="hover:text-slate-800 transition-colors">Trainer Kit</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold font-mono">{{ $inventory->serial_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-mono">
                    {{ $inventory->serial_number }}
                </h1>
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
                        'sold' => 'Terjual / Dimiliki',
                        'assigned' => 'Terikat Workstation',
                        'activated' => 'Aktif Operasional',
                        'maintenance' => 'Perawatan',
                        'decommissioned' => 'Dipensiunkan',
                    ];
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $statusClasses[$inventory->status] ?? 'bg-slate-100 text-slate-700' }}">
                    {{ $statusLabels[$inventory->status] ?? ucfirst($inventory->status) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                {{ $inventory->model_name }} • {{ $inventory->hardware_revision ?? 'rev1.0' }}
                @if($inventory->batch_number)
                    • <span class="font-mono">Batch {{ $inventory->batch_number }}</span>
                @endif
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Edit Metadata -->
            <button
                type="button"
                @click="editModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 shadow-2xs transition-colors cursor-pointer"
            >
                <i data-lucide="edit-3" class="w-4 h-4 text-slate-500"></i>
                <span>Edit Spesifikasi</span>
            </button>

            <!-- Record Sale -->
            <button
                type="button"
                @click="saleModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 shadow-2xs transition-colors cursor-pointer"
            >
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                <span>Tetapkan Kepemilikan Sekolah</span>
            </button>

            <!-- Assign Workstation -->
            @if($inventory->school_id)
                <button
                    type="button"
                    @click="assignModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-2xs transition-colors cursor-pointer"
                >
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span>{{ $inventory->device_id ? 'Ubah Workstation' : 'Kaitkan Workstation' }}</span>
                </button>
            @endif

            <!-- Activate -->
            @if($inventory->school_id && $inventory->status !== 'activated')
                <button
                    type="button"
                    @click="activateModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-[#0c3c26] text-white hover:bg-[#092d1c] shadow-2xs transition-colors cursor-pointer"
                >
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    <span>Aktivasi Unit</span>
                </button>
            @endif
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

    <!-- Information Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Col 1 & 2: Hardware & Ownership Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Hardware Spec Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm">
                        <i data-lucide="cpu" class="w-4 h-4 text-[#0c3c26]"></i>
                        <span>Spesifikasi Hardware & Registri Pabrik</span>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono">ID: #{{ $inventory->id }}</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[11px]">Nomor Seri Fisik</span>
                        <span class="font-mono font-bold text-slate-900 text-sm">{{ $inventory->serial_number }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Model Trainer Kit</span>
                        <span class="font-semibold text-slate-800">{{ $inventory->model_name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Revisi Hardware</span>
                        <span class="font-mono text-slate-800">{{ $inventory->hardware_revision ?? 'rev1.0' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Batch Produksi</span>
                        <span class="font-mono text-slate-800">{{ $inventory->batch_number ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Waktu Pendaftaran</span>
                        <span class="font-tabular text-slate-800">{{ $inventory->registered_at?->translatedFormat('d M Y, H:i') ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Pendaftar (Operator QC)</span>
                        <span class="text-slate-800">{{ $inventory->registeredBy?->name ?? 'Sistem' }}</span>
                    </div>
                </div>

                @if($inventory->notes)
                    <div class="pt-3 border-t border-slate-100 text-xs">
                        <span class="text-slate-400 block text-[11px] mb-1">Catatan Tambahan:</span>
                        <p class="text-slate-700 bg-slate-50 p-3 rounded-xl whitespace-pre-line">{{ $inventory->notes }}</p>
                    </div>
                @endif
            </div>

            <!-- Ownership & School Binding Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm">
                        <i data-lucide="building-2" class="w-4 h-4 text-blue-600"></i>
                        <span>Kepemilikan Sekolah & Dokumen Penjualan</span>
                    </div>
                </div>

                @if($inventory->school)
                    <div class="space-y-4 text-xs">
                        <div class="flex items-start justify-between p-3.5 rounded-xl bg-blue-50/50 border border-blue-100">
                            <div>
                                <span class="text-blue-600 text-[11px] font-semibold uppercase tracking-wider block">Institusi Pemilik Saat Ini</span>
                                <h3 class="text-sm font-bold text-slate-900 mt-0.5">{{ $inventory->school->name }}</h3>
                                <p class="text-slate-500 text-[11px] mt-0.5">
                                    NPSN: {{ $inventory->school->npsn ?? '—' }} • {{ $inventory->school->city }}, {{ $inventory->school->province }}
                                </p>
                            </div>
                            <a
                                href="{{ route('master.schools.show', $inventory->school) }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
                            >
                                <span>Profil Sekolah</span>
                                <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </a>
                        </div>

                        <!-- Sale Records History -->
                        <div>
                            <span class="text-slate-500 font-semibold text-xs block mb-2">Riwayat Berkas & Transaksi Penjualan</span>
                            <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                                @forelse($inventory->saleRecords as $sale)
                                    <div class="p-3 text-xs flex items-center justify-between hover:bg-slate-50 transition-colors">
                                        <div>
                                            <div class="font-mono font-semibold text-slate-900">{{ $sale->sale_reference }}</div>
                                            <div class="text-[11px] text-slate-500">
                                                Sekolah: {{ $sale->school?->name }} • Dicatat oleh: {{ $sale->soldBy?->name ?? 'Admin' }}
                                            </div>
                                            @if($sale->notes)
                                                <div class="text-[11px] text-slate-400 italic mt-0.5">{{ $sale->notes }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right text-[11px] text-slate-500 font-tabular">
                                            {{ $sale->sold_at?->translatedFormat('d M Y') ?? '—' }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-slate-400 text-xs">Belum ada dokumen penjualan resmi.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-6 text-xs text-slate-500 space-y-2">
                        <i data-lucide="package-search" class="w-8 h-8 mx-auto text-slate-300"></i>
                        <p class="font-medium text-slate-700">Unit Berstatus Stok Pabrik / Gudang Pusat</p>
                        <p class="text-slate-400 text-[11px] max-w-sm mx-auto">
                            Unit ini belum dialokasikan ke sekolah manapun. Klik tombol "Tetapkan Kepemilikan Sekolah" di atas untuk mencatat penjualan atau transfer unit.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Linked Workstation Device Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm">
                        <i data-lucide="radio" class="w-4 h-4 text-indigo-600"></i>
                        <span>Workstation IoT Sekolah</span>
                    </div>
                </div>

                @if($inventory->device)
                    <div class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/30 space-y-3 text-xs">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-indigo-600 text-[11px] font-semibold uppercase tracking-wider block">Workstation Terikat</span>
                                <h4 class="text-sm font-bold text-slate-900 mt-0.5">{{ $inventory->device->name }}</h4>
                                <span class="text-slate-500 font-mono text-[11px]">Kode: {{ $inventory->device->code }} • Lokasi: {{ $inventory->device->bench_location ?? 'Meja Praktikum' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $inventory->device->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                    {{ $inventory->device->is_active ? 'Workstation Aktif' : 'Workstation Nonaktif' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2 border-t border-indigo-100/60 text-[11px]">
                            <div>
                                <span class="text-slate-400 block">Protokol</span>
                                <span class="font-mono text-slate-700">{{ $inventory->device->protocol ?? 'Modbus RS485' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Firmware</span>
                                <span class="font-mono text-slate-700">{{ $inventory->device->firmware_version ?? 'v1.0.0' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Telemetri Terakhir</span>
                                <span class="font-tabular text-slate-700">{{ $inventory->device->last_seen_at?->diffForHumans() ?? 'Belum ada transmisi' }}</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200/60 text-[11px] text-amber-800 flex items-center gap-2">
                            <i data-lucide="shield-alert" class="w-4 h-4 shrink-0 text-amber-600"></i>
                            <span>Aktivasi unit di platform merupakan registrasi administratif kesiapan perangkat. Kontrol downlink aktuator fisik tetap disimulasikan hingga konfirmasi ACK firmware diverifikasi.</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-6 text-xs text-slate-500 space-y-2">
                        <i data-lucide="layout-grid" class="w-8 h-8 mx-auto text-slate-300"></i>
                        <p class="font-medium text-slate-700">Belum Terkait ke Workstation IoT</p>
                        <p class="text-slate-400 text-[11px] max-w-sm mx-auto">
                            Unit nomor seri ini belum dipasangkan dengan perangkat workstation sekolah untuk pengiriman telemetri praktikum.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Col 3: Lifecycle Audit Trail Timeline -->
        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm">
                        <i data-lucide="history" class="w-4 h-4 text-slate-600"></i>
                        <span>Audit Siklus Hidup (Lifecycle)</span>
                    </div>
                    <span class="text-[11px] text-slate-400 font-tabular">{{ $inventory->lifecycleLogs->count() }} Peristiwa</span>
                </div>

                <div class="relative pl-4 space-y-4 before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    @forelse($inventory->lifecycleLogs as $log)
                        <div class="relative text-xs group">
                            <!-- Bullet -->
                            <div class="absolute -left-4 top-1 w-2.5 h-2.5 rounded-full border-2 border-white bg-slate-400 group-first:bg-[#0c3c26]"></div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-900 text-xs font-mono">{{ $log->action }}</span>
                                    <span class="text-[10px] text-slate-400 font-tabular">{{ $log->created_at?->translatedFormat('d M H:i') }}</span>
                                </div>

                                @if($log->from_status !== $log->to_status)
                                    <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-mono">
                                        <span>{{ $log->from_status ?? 'awal' }}</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3 text-slate-400"></i>
                                        <span class="font-semibold text-slate-800">{{ $log->to_status }}</span>
                                    </div>
                                @endif

                                @if($log->details)
                                    <p class="text-[11px] text-slate-600 leading-relaxed bg-slate-50 p-2 rounded-lg">
                                        {{ $log->details }}
                                    </p>
                                @endif

                                <div class="text-[10px] text-slate-400">
                                    Oleh: <span class="font-medium text-slate-600">{{ $log->user?->name ?? 'Sistem' }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-3">Belum ada riwayat tercatat.</p>
                    @endforelse
                </div>
            </div>

            <!-- Danger Zone: Delete registered stock -->
            @if($inventory->status === 'registered' && $inventory->saleRecords->isEmpty())
                <div class="p-4 rounded-2xl border border-rose-200 bg-rose-50/60 space-y-3 text-xs">
                    <div class="flex items-center gap-2 text-rose-800 font-bold">
                        <i data-lucide="alert-octagon" class="w-4 h-4"></i>
                        <span>Hapus Unit Pabrik</span>
                    </div>
                    <p class="text-rose-700 text-[11px] leading-relaxed">
                        Unit ini belum memiliki riwayat penjualan ataupun penugasan sekolah. Anda dapat menghapusnya secara aman dari registri inventaris.
                    </p>
                    <form method="POST" action="{{ route('master.trainer-kits.destroy', $inventory) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus nomor seri ini dari inventaris pabrik?');">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="w-full py-2 px-3 text-xs font-semibold text-rose-700 hover:text-white bg-white hover:bg-rose-600 border border-rose-300 rounded-xl transition-colors cursor-pointer"
                        >
                            Hapus dari Inventaris
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <!-- Edit Metadata Modal -->
    <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Edit Spesifikasi Unit</h3>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('master.trainer-kits.update', $inventory) }}" class="space-y-3.5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Model Name</label>
                            <input
                                type="text"
                                name="model_name"
                                value="{{ old('model_name', $inventory->model_name) }}"
                                required
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Hardware Revision</label>
                            <input
                                type="text"
                                name="hardware_revision"
                                value="{{ old('hardware_revision', $inventory->hardware_revision) }}"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Batch Number</label>
                            <input
                                type="text"
                                name="batch_number"
                                value="{{ old('batch_number', $inventory->batch_number) }}"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Status Operasional</label>
                            <select
                                name="status"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800"
                            >
                                <option value="registered" {{ $inventory->status === 'registered' ? 'selected' : '' }}>Stok Pabrik (Registered)</option>
                                <option value="sold" {{ $inventory->status === 'sold' ? 'selected' : '' }}>Terjual (Sold)</option>
                                <option value="assigned" {{ $inventory->status === 'assigned' ? 'selected' : '' }}>Terikat Workstation (Assigned)</option>
                                <option value="activated" {{ $inventory->status === 'activated' ? 'selected' : '' }}>Aktif Operasional (Activated)</option>
                                <option value="maintenance" {{ $inventory->status === 'maintenance' ? 'selected' : '' }}>Perawatan (Maintenance)</option>
                                <option value="decommissioned" {{ $inventory->status === 'decommissioned' ? 'selected' : '' }}>Dipensiunkan (Decommissioned)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                        <textarea
                            name="notes"
                            rows="3"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800"
                        >{{ old('notes', $inventory->notes) }}</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="editModalOpen = false" class="px-3.5 py-2 text-xs font-medium text-slate-600 rounded-xl border border-slate-200 hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] rounded-xl hover:bg-[#092d1c]">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sale & Ownership Modal -->
    <div x-show="saleModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="saleModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Tetapkan Kepemilikan Sekolah</h3>
                    <button type="button" @click="saleModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('master.trainer-kits.sale', $inventory) }}" class="space-y-3.5">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Pilih Institusi Sekolah Mitra <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="school_id"
                            required
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:bg-white"
                        >
                            <option value="">-- Pilih Sekolah --</option>
                            @foreach($schools as $sch)
                                <option value="{{ $sch->id }}" {{ $inventory->school_id === $sch->id ? 'selected' : '' }}>
                                    {{ $sch->name }} (NPSN: {{ $sch->npsn ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                No. Referensi Transaksi / PO <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="sale_reference"
                                required
                                placeholder="Contoh: PO-2026-0042 / BAST-SMK"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Transaksi</label>
                            <input
                                type="date"
                                name="sold_at"
                                value="{{ date('Y-m-d') }}"
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-mono"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Catatan pengiriman, kontak perwakilan sekolah, dsb..."
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="saleModalOpen = false" class="px-3.5 py-2 text-xs font-medium text-slate-600 rounded-xl border border-slate-200 hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700">Simpan Kepemilikan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Workstation Modal -->
    <div x-show="assignModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="assignModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Kaitkan ke Workstation Sekolah</h3>
                    <button type="button" @click="assignModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('master.trainer-kits.assign', $inventory) }}" class="space-y-3.5">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Pilih Workstation IoT ({{ $inventory->school?->name }})
                        </label>
                        <select
                            name="device_id"
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800"
                        >
                            <option value="">-- Lepas Pengaitan Workstation (Unassigned) --</option>
                            @foreach($availableDevices as $dev)
                                <option value="{{ $dev->id }}" {{ $inventory->device_id === $dev->id ? 'selected' : '' }}>
                                    {{ $dev->name }} ({{ $dev->code }}) — {{ $dev->bench_location ?? 'Meja Lab' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Hanya workstation yang terdaftar pada sekolah penerima yang dapat dipilih.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="assignModalOpen = false" class="px-3.5 py-2 text-xs font-medium text-slate-600 rounded-xl border border-slate-200 hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700">Simpan Pengaitan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Activation Confirmation Modal -->
    <div x-show="activateModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="activateModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
                        <h3 class="text-sm font-bold text-slate-900">Konfirmasi Aktivasi Unit</h3>
                    </div>
                    <button type="button" @click="activateModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>
                        Unit dengan nomor seri <strong class="text-slate-900 font-mono">{{ $inventory->serial_number }}</strong> akan diaktifkan untuk sekolah <strong class="text-slate-900">{{ $inventory->school?->name }}</strong>.
                    </p>
                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-emerald-800 text-[11px]">
                        Status unit akan berubah menjadi <strong>Aktif Operasional (Activated)</strong> dan siap digunakan untuk kegiatan belajar praktikum ATPH 4.0.
                    </div>
                </div>

                <form method="POST" action="{{ route('master.trainer-kits.activate', $inventory) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Aktivasi (Opsional)</label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Catatan serah terima atau komisioning..."
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-800"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="activateModalOpen = false" class="px-3.5 py-2 text-xs font-medium text-slate-600 rounded-xl border border-slate-200 hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-[#0c3c26] rounded-xl hover:bg-[#092d1c]">Konfirmasi Aktivasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

