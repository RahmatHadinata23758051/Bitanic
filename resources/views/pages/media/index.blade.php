@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header Banner & Action Bar -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 text-xs font-bold uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono">
                    Agronomi &amp; Nutrisi
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian ATPH 4.0</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Manajemen Media Tanam &amp; Plot Praktik
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 max-w-3xl leading-relaxed">
                Pemetaan jenis tanaman pada plot media tanam, pemantauan telemetri sensor tanah per workstation, dan evaluasi kesesuaian agronomi otomatis.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a
                href="{{ route('media.create') }}"
                class="px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center gap-2"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Media Tanam</span>
            </a>
        </div>
    </div>

    <!-- Media Table Card -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="sprout" class="w-4 h-4 text-emerald-600"></i>
                <h2 class="text-sm font-bold text-slate-900">Daftar Plot &amp; Substrat Media Tanam</h2>
            </div>
            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold">
                Total: {{ $medias->total() }} Plot
            </span>
        </div>

        @if($medias->isEmpty())
            <div class="p-12 text-center space-y-4">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                    <i data-lucide="sprout" class="w-6 h-6"></i>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-bold text-slate-800">Belum ada plot media tanam</p>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Hubungkan plot media tanam dengan stasiun Trainer Kit untuk mengevaluasi parameter pH, EC, dan lengas tanah secara otomatis.
                    </p>
                </div>
                <div>
                    <a
                        href="{{ route('media.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-colors"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Buat Plot Media Pertama</span>
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-6 py-3">Nama Plot / Media</th>
                            <th class="px-6 py-3">Stasiun Trainer Kit</th>
                            <th class="px-6 py-3">Tanaman Terdaftar</th>
                            <th class="px-6 py-3">Status Evaluasi Agronomi</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($medias as $media)
                            <tr class="hover:bg-slate-50/75 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900">{{ $media->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">Dibuat: {{ $media->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($media->device)
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full {{ $media->device->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                            <span class="font-mono font-semibold text-slate-800">{{ $media->device->code }}</span>
                                            <span class="text-slate-500 font-normal">({{ $media->device->name }})</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">Tidak terhubung workstation</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-xs">
                                        @forelse($media->plants ?? [] as $plant)
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-medium">
                                                {{ $plant }}
                                            </span>
                                        @empty
                                            <span class="text-slate-400 text-[11px]">-</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if(!empty($media->plants_condition))
                                        <div class="space-y-1 max-w-md">
                                            @foreach(array_slice($media->plants_condition, 0, 2) as $condition)
                                                <div class="text-[11px] bg-slate-50 p-2 rounded border border-slate-100">
                                                    <span class="font-bold text-slate-800">{{ $condition['tanaman'] ?? 'Tanaman' }}:</span>
                                                    <span class="text-slate-600">{{ Str::limit($condition['kondisi'] ?? '-', 90) }}</span>
                                                </div>
                                            @endforeach
                                            @if(count($media->plants_condition) > 2)
                                                <span class="text-[10px] text-slate-400 font-medium">+{{ count($media->plants_condition) - 2 }} tanaman lainnya</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">Belum ada data evaluasi</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            href="{{ route('media.edit', $media->id) }}"
                                            class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 transition-colors"
                                            title="Edit media tanam"
                                        >
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
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
                                                class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-rose-700 hover:border-rose-300 hover:bg-rose-50 transition-colors"
                                                title="Hapus media tanam"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
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
                <div class="p-4 border-t border-slate-200">
                    {{ $medias->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
