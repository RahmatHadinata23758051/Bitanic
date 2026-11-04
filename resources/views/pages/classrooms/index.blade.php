@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    activeTab: 'classes',
    showClassModal: false,
    showGroupModal: false,
    showImportModal: false,
    selectedClassId: null,
    selectedClassName: '',
    importFile: null,
    importPreview: null,
    importLoading: false,
    importError: null,
    uploadProgress: false,

    openGroupModal(classId, className) {
        this.selectedClassId = classId;
        this.selectedClassName = className;
        this.showGroupModal = true;
    },

    async handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;
        this.importFile = file;
        this.importError = null;
        this.importLoading = true;
        this.importPreview = null;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        try {
            const res = await fetch('{{ Route::has('users.import.preview') ? route('users.import.preview') : url('/users/import/preview') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            const data = await res.json();
            if (!res.ok) {
                this.importError = data.message || Object.values(data.errors || {})[0]?.[0] || 'Gagal memproses berkas.';
            } else {
                this.importPreview = data;
            }
        } catch (err) {
            this.importError = 'Terjadi kesalahan jaringan saat mengunggah berkas.';
        } finally {
            this.importLoading = false;
        }
    },

    async submitImport() {
        if (!this.importPreview || !this.importPreview.rows) return;
        const validRows = this.importPreview.rows.filter(r => r.is_valid);
        if (validRows.length === 0) return;

        this.uploadProgress = true;
        try {
            const res = await fetch('{{ Route::has('users.import.execute') ? route('users.import.execute') : url('/users/import/execute') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ rows: validRows })
            });
            const data = await res.json();
            if (res.ok) {
                window.location.reload();
            } else {
                this.importError = data.message || 'Gagal melakukan eksekusi import.';
                this.uploadProgress = false;
            }
        } catch (err) {
            this.importError = 'Terjadi kesalahan saat memproses data.';
            this.uploadProgress = false;
        }
    }
}">

    <!-- Header Section -->
    <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-xs font-bold font-mono bg-emerald-50 text-emerald-800 border border-emerald-200">
                    AKADEMIK & LAB
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-semibold text-slate-600">SMK Pertanian Pembangunan Negeri 1 Sukaraja</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight font-sans">
                Manajemen Kelas, Kelompok & Alokasi Praktik
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-2xl">
                Pengelolaan kelas vokasi ATPH, pembagian kelompok bedengan kerja, dan alokasi meja Trainer Kit IoT.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <button
                type="button"
                @click="showImportModal = true"
                class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs flex items-center gap-2 transition-colors border border-slate-300"
            >
                <i data-lucide="upload" class="w-4 h-4 text-slate-500"></i>
                <span>Bulk Import Akun</span>
            </button>
            <button
                type="button"
                @click="showClassModal = true"
                class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-semibold text-xs flex items-center gap-2 transition-colors shadow-sm"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Kelas Baru</span>
            </button>
        </div>
    </div>

    <!-- Error Display -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>Perhatian: Ada kesalahan input</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Classroom & Groups Grid -->
    <div class="space-y-4">
        @forelse ($classrooms as $classroom)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Class Header -->
                <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono bg-slate-200 text-slate-800">
                                {{ $classroom->academic_year }}
                            </span>
                            <h2 class="text-base sm:text-lg font-bold text-slate-900">{{ $classroom->name }}</h2>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs text-slate-600">{{ $classroom->department }}</span>
                        </div>
                        <div class="text-xs text-slate-500 flex items-center gap-2">
                            <span>Wali Kelas:</span>
                            <span class="font-medium text-slate-700">{{ $classroom->homeroomTeacher->name ?? 'Belum Ditentukan' }}</span>
                            <span class="text-slate-300">|</span>
                            <span>{{ $classroom->groups->count() }} Kelompok Kerja</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="openGroupModal({{ $classroom->id }}, '{{ addslashes($classroom->name) }}')"
                            class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        >
                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                            <span>Tambah Kelompok</span>
                        </button>
                        <form method="POST" action="{{ route('classes.destroy', $classroom->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini?')">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                title="Hapus Kelas"
                            >
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Groups List inside Class -->
                <div class="p-5">
                    @if ($classroom->groups->isEmpty())
                        <div class="p-4 rounded-xl border border-dashed border-slate-200 text-center text-xs text-slate-500">
                            Belum ada kelompok kerja di kelas ini. Klik tombol <span class="font-semibold text-emerald-700">Tambah Kelompok</span> untuk membuat kelompok praktikum.
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach ($classroom->groups as $group)
                                <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-bold text-sm text-slate-800">{{ $group->name }}</div>
                                        <form method="POST" action="{{ route('groups.destroy', $group->id) }}" onsubmit="return confirm('Hapus kelompok ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-400 hover:text-rose-600 p-0.5">
                                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Trainer Kit Badge -->
                                    <div class="text-xs">
                                        @if ($group->device)
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-mono font-bold text-[11px] border border-emerald-200">
                                                <i data-lucide="cpu" class="w-3 h-3 text-emerald-600"></i>
                                                {{ $group->device->code }} ({{ $group->device->name }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-500 font-mono text-[11px]">
                                                Tanpa Default Kit
                                            </span>
                                        @endif
                                    </div>

                                    @if ($group->notes)
                                        <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">{{ $group->notes }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 rounded-2xl bg-white border border-slate-200 text-center space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center mx-auto text-emerald-700">
                    <i data-lucide="graduation-cap" class="w-6 h-6"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">Belum Ada Data Kelas</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    Mulai dengan membuat master kelas vokasi baru atau gunakan fitur bulk import akun siswa dan guru.
                </p>
                <button
                    type="button"
                    @click="showClassModal = true"
                    class="px-4 py-2 rounded-xl bg-emerald-700 text-white font-semibold text-xs hover:bg-emerald-800 inline-flex items-center gap-1.5"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Kelas Pertama</span>
                </button>
            </div>
        @endforelse

        <div class="mt-4">
            {{ $classrooms->links() }}
        </div>
    </div>

    <!-- Modal: Tambah Kelas -->
    <div
        x-show="showClassModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.outside="showClassModal = false">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 text-base">Tambah Kelas Baru</h3>
                <button type="button" @click="showClassModal = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('classes.store') }}" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Kelas *</label>
                    <input type="text" name="name" required placeholder="Contoh: XII ATPH 1" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jurusan / Konsentrasi Keahlian</label>
                    <input type="text" name="department" value="Agribisnis Tanaman Pangan & Hortikultura" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran *</label>
                    <input type="text" name="academic_year" value="2026/2027" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Wali Kelas / Guru Pendamping</label>
                    <select name="homeroom_teacher_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }} ({{ $teacher->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="showClassModal = false" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Tambah Kelompok -->
    <div
        x-show="showGroupModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200" @click.outside="showGroupModal = false">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Tambah Kelompok Kerja</h3>
                    <p class="text-xs text-slate-500">Kelas: <span class="font-bold text-slate-800" x-text="selectedClassName"></span></p>
                </div>
                <button type="button" @click="showGroupModal = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form :action="'{{ url('/classes') }}/' + selectedClassId + '/groups'" method="POST" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Kelompok *</label>
                    <input type="text" name="name" required placeholder="Contoh: Kelompok 1 (Bedengan A)" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Alokasi Default Trainer Kit Meja</label>
                    <select name="device_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">-- Tanpa Meja Default --</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->code }} - {{ $device->name }} ({{ $device->bench_location ?? 'Lab' }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Catatan / Komoditas Praktikum</label>
                    <textarea name="notes" rows="2" placeholder="Contoh: Budidaya tomat hidroponik substrat" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="showGroupModal = false" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800">Simpan Kelompok</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Bulk Import CSV/XLSX -->
    <div
        x-show="showImportModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div class="bg-white rounded-2xl max-w-3xl w-full p-6 space-y-4 shadow-xl border border-slate-200 max-h-[90vh] flex flex-col" @click.outside="showImportModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Bulk Import Akun Pengguna</h3>
                    <p class="text-xs text-slate-500">Unggah berkas CSV atau XLSX dengan kolom: nama, email, role (siswa/guru), nisn/nip, kelas, kelompok, kit.</p>
                </div>
                <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="space-y-4 flex-1 overflow-y-auto pr-1">
                <!-- File Input -->
                <div class="p-4 rounded-xl border-2 border-dashed border-slate-200 hover:border-emerald-500 text-center transition-colors">
                    <input type="file" accept=".csv, .xlsx" @change="handleFileSelect" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-2">Maksimal 10MB (.csv atau .xlsx). Pratinjau validasi data akan langsung ditampilkan.</p>
                </div>

                <!-- Loading State -->
                <div x-show="importLoading" class="p-8 text-center space-y-2">
                    <div class="w-6 h-6 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs text-slate-500">Menganalisis dan memvalidasi baris data...</p>
                </div>

                <!-- Error Alert -->
                <div x-show="importError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span x-text="importError"></span>
                </div>

                <!-- Preview Table -->
                <div x-show="importPreview" class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-700">Total Baris: <span class="font-mono" x-text="importPreview?.total_rows"></span></span>
                            <span class="text-slate-300">|</span>
                            <span class="text-emerald-700 font-bold">Valid: <span class="font-mono" x-text="importPreview?.valid_count"></span></span>
                            <span class="text-slate-300">|</span>
                            <span class="text-rose-700 font-bold">Tidak Valid: <span class="font-mono" x-text="importPreview?.invalid_count"></span></span>
                        </div>
                        <div class="text-[11px] text-slate-400">Password standar akun baru: <span class="font-mono font-bold text-slate-600">{{ config('auth.default_import_password') }}</span></div>
                    </div>

                    <div class="border border-slate-200 rounded-xl overflow-hidden max-h-60 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold sticky top-0">
                                <tr>
                                    <th class="p-2.5">#</th>
                                    <th class="p-2.5">Nama</th>
                                    <th class="p-2.5">Email</th>
                                    <th class="p-2.5">Role</th>
                                    <th class="p-2.5">Status</th>
                                    <th class="p-2.5">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="r in (importPreview?.rows || [])" :key="r.row_index">
                                    <tr :class="r.is_valid ? 'hover:bg-slate-50' : 'bg-rose-50/50'">
                                        <td class="p-2.5 font-mono text-[11px] text-slate-400" x-text="r.row_index"></td>
                                        <td class="p-2.5 font-medium text-slate-800" x-text="r.name"></td>
                                        <td class="p-2.5 font-mono text-[11px] text-slate-600" x-text="r.email"></td>
                                        <td class="p-2.5">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono" :class="r.role === 'siswa' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'" x-text="r.role"></span>
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="r.is_valid" class="text-emerald-700 font-bold text-[11px]">Valid</span>
                                            <span x-show="!r.is_valid" class="text-rose-700 font-bold text-[11px]">Error</span>
                                        </td>
                                        <td class="p-2.5 text-[11px] text-rose-600" x-text="r.errors?.join(', ') || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2 shrink-0">
                <button type="button" @click="showImportModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Tutup</button>
                <button
                    type="button"
                    x-show="importPreview && importPreview.valid_count > 0"
                    :disabled="uploadProgress"
                    @click="submitImport"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 inline-flex items-center gap-2"
                >
                    <span x-show="!uploadProgress">Impor <span x-text="importPreview?.valid_count"></span> Akun Valid</span>
                    <span x-show="uploadProgress">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
