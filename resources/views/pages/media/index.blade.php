@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Page Header (Unboxed, Bitanic Standard) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Media Tanam
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Pengelolaan plot, media, dan tanaman praktikum
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="{{ route('media.create') }}"
                class="px-4 py-2 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors flex items-center gap-2"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Media Tanam</span>
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Media Table Card -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/40 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <i data-lucide="sprout" class="w-4 h-4 text-slate-500"></i>
                <h2 class="text-xs font-semibold text-slate-800">Daftar Plot &amp; Substrat Media Tanam</h2>
            </div>
            <span class="text-xs font-medium px-2.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200/60 font-tabular">
                Total: {{ $medias->total() }} Plot
            </span>
        </div>

        @if($medias->isEmpty())
            <div class="py-12 px-6 text-center space-y-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-slate-800">Belum ada media tanam</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Tambahkan plot untuk mencatat media, tanaman, dan stasiun praktikum terkait.
                    </p>
                </div>
                <div class="pt-1">
                    <a
                        href="{{ route('media.create') }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold shadow-2xs transition-colors"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Tambah Media Tanam</span>
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-medium text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Nama Plot / Media</th>
                            <th class="px-5 py-3">Stasiun Trainer Kit</th>
                            <th class="px-5 py-3">Tanaman Terdaftar</th>
                            <th class="px-5 py-3">Evaluasi Agronomi</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($medias as $media)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="font-semibold text-slate-900">{{ $media->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-tabular mt-0.5">Dibuat: {{ $media->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($media->device)
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full {{ $media->device->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                            <span class="font-mono text-slate-800 font-medium">{{ $media->device->code }}</span>
                                            <span class="text-slate-500">({{ $media->device->name }})</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap gap-1 max-w-xs">
                                        @forelse($media->plants ?? [] as $plant)
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200/60">
                                                {{ $plant }}
                                            </span>
                                        @empty
                                            <span class="text-slate-400 text-[11px]">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if(!empty($media->plants_condition))
                                        <div class="space-y-1 max-w-md">
                                            @foreach(array_slice($media->plants_condition, 0, 2) as $condition)
                                                <div class="text-[11px] bg-slate-50/70 p-2 rounded-lg border border-slate-200/60">
                                                    <span class="font-medium text-slate-800">{{ $condition['tanaman'] ?? 'Tanaman' }}:</span>
                                                    <span class="text-slate-600">{{ Str::limit($condition['kondisi'] ?? '—', 90) }}</span>
                                                </div>
                                            @endforeach
                                            @if(count($media->plants_condition) > 2)
                                                <span class="text-[10px] text-slate-400 font-medium">+{{ count($media->plants_condition) - 2 }} tanaman lainnya</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-[11px]">Belum ada data evaluasi</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('media.edit', $media->id) }}"
                                            class="p-1.5 rounded-lg border border-slate-200/80 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shadow-2xs"
                                            title="Edit media tanam"
                                        >
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </a>

                                        <form
                                            action="{{ route('media.destroy', $media->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus plot media tanam ini?');"
                                            class="inline"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="p-1.5 rounded-lg border border-slate-200/80 bg-white text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors shadow-2xs"
                                                title="Hapus media tanam"
                                            >
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($medias->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $medias->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
