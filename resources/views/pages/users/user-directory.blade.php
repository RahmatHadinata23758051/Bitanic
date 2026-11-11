@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    showCreateModal: false,
    showEditModal: false,
    showImportModal: false,
    newUserData: {
        role: 'siswa',
        classroom_id: '',
        student_group_id: ''
    },
    editUserData: {
        id: null,
        name: '',
        email: '',
        role: 'siswa',
        identifier_number: '',
        phone: '',
        department: '',
        specialization: '',
        classroom_id: '',
        student_group_id: '',
        assigned_kit_id: ''
    },
    importFile: null,
    importPreview: null,
    importLoading: false,
    importError: null,
    uploadProgress: false,

    openEdit(u, p) {
        this.editUserData = {
            id: u.id,
            name: u.name,
            email: u.email,
            role: u.role,
            identifier_number: p ? (p.identifier_number || '') : '',
            phone: p ? (p.phone || '') : '',
            department: p ? (p.department || '') : '',
            specialization: p ? (p.specialization || '') : '',
            classroom_id: p ? (p.classroom_id || '') : '',
            student_group_id: p ? (p.student_group_id || '') : '',
            assigned_kit_id: p ? (p.assigned_kit_id || '') : ''
        };
        this.showEditModal = true;
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
        @if($selectedSchoolId)
        formData.append('school_id', '{{ $selectedSchoolId }}');
        @endif

        try {
            const res = await fetch('{{ route('users.import.preview') }}', {
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
            const res = await fetch('{{ route('users.import.execute') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    rows: validRows,
                    @if($selectedSchoolId)
                    school_id: '{{ $selectedSchoolId }}'
                    @endif
                })
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
    <!-- Page Header (Unboxed, Bitanic Standard) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-sans">
                Direktori
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Data pengguna dan keanggotaan institusi sekolah
            </p>
        </div>

        <!-- School Scope / Selector & Actions -->
        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            @if(!empty($isSuperuser) && $schools->isNotEmpty())
                <form method="GET" action="{{ url('/users-data') }}" class="flex items-center gap-2">
                    @if($currentRole)<input type="hidden" name="role" value="{{ $currentRole }}">@endif
                    @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    <label for="school_id" class="text-xs text-slate-500 font-medium">Sekolah:</label>
                    <select id="school_id" name="school_id" onchange="this.form.submit()" class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] shadow-2xs">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif(!empty($schoolName))
                <span class="text-xs text-slate-500 font-medium bg-white px-3 py-1.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    {{ $schoolName }}
                </span>
            @endif

            @if($isAdmin)
                <button
                    type="button"
                    @click="showImportModal = true"
                    class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-2 transition-colors border border-slate-200/80 shadow-2xs"
                >
                    <i data-lucide="upload" class="w-4 h-4 text-slate-500"></i>
                    <span>Impor Akun</span>
                </button>
                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="px-4 py-2 rounded-xl bg-[#0c3c26] hover:bg-[#082819] text-white font-semibold text-xs flex items-center gap-2 transition-colors shadow-2xs"
                >
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Tambah Pengguna</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Perhatian: Ada kesalahan validasi</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Summary Metrics Strip (Restrained, Bitanic Standard) -->
    <div class="grid grid-cols-3 gap-3">
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Siswa Praktikan</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['students'] }}</span>
        </div>
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Guru Pembimbing</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['teachers'] }}</span>
        </div>
        <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-center">
            <span class="text-[11px] font-medium text-slate-500 block">Admin Lab &amp; Sistem</span>
            <span class="font-tabular font-bold text-lg text-slate-900 mt-0.5 block">{{ $stats['admins'] }}</span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Role Tabs -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <a
                href="{{ url('/users-data' . ($selectedSchoolId ? '?school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ empty($currentRole) ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Semua
            </a>
            <a
                href="{{ url('/users-data?role=siswa' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $currentRole === 'siswa' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Siswa
            </a>
            <a
                href="{{ url('/users-data?role=guru' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ $currentRole === 'guru' ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Guru
            </a>
            <a
                href="{{ url('/users-data?role=admin' . ($selectedSchoolId ? '&school_id=' . $selectedSchoolId : '')) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ in_array($currentRole, ['admin', 'admin_sekolah', 'superuser'], true) ? 'bg-[#0c3c26] text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Admin
            </a>
        </div>

        <!-- Search Input -->
        <form method="GET" action="{{ url('/users-data') }}" class="flex items-center gap-2">
            @if($currentRole)
                <input type="hidden" name="role" value="{{ $currentRole }}">
            @endif
            @if($isSuperuser && $selectedSchoolId)
                <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
            @endif
            <div class="relative">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nama, NISN, NIP..."
                    class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] w-56 sm:w-64"
                >
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5"></i>
            </div>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#0c3c26] hover:bg-[#082819] text-white text-xs font-semibold transition-colors shadow-2xs">
                Cari
            </button>
            @if($search)
                <a href="{{ url('/users-data' . ($currentRole ? '?role=' . $currentRole : '') . ($selectedSchoolId ? ($currentRole ? '&' : '?') . 'school_id=' . $selectedSchoolId : '')) }}" class="text-xs text-slate-500 hover:text-slate-800 font-medium">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-medium text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Nama Lengkap &amp; Akun</th>
                        <th class="py-3 px-4">Peran</th>
                        <th class="py-3 px-4">Nomor Identitas</th>
                        <th class="py-3 px-4">Kelas / Kelompok</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Meja Kit</th>
                        @if($isAdmin)
                            <th class="py-3 px-4 text-right">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-sans text-xs">
                    @forelse($users as $u)
                        @php
                            $p = $u->profile;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500 mt-0.5">{{ $u->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($u->role === 'guru')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-700 border border-slate-200/60">
                                        Guru
                                    </span>
                                @elseif($u->role === 'superuser')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-200 text-slate-800 border border-slate-300/60">
                                        Superuser
                                    </span>
                                @elseif($u->role === 'admin_sekolah' || $u->role === 'admin')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-200 text-slate-800 border border-slate-300/60">
                                        {{ $u->role === 'admin_sekolah' ? 'Admin Sekolah' : 'Admin' }}
                                    </span>
                                @elseif($u->role === 'siswa')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-700 border border-slate-200/60">
                                        Siswa
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-100 text-slate-600 border border-slate-200/60">
                                        {{ ucfirst($u->role) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-700">
                                {{ $p?->identifier_number ?? '—' }}
                            </td>
                            <td class="py-3 px-4">
                                @if($u->role === 'siswa')
                                    @if($p?->class_grade || $p?->group_name)
                                        <div class="font-medium text-slate-800">{{ $p->class_grade ?? '—' }}</div>
                                        @if($p?->group_name)
                                            <span class="text-[11px] text-slate-500">{{ $p->group_name }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                @else
                                    @if($p?->specialization || $p?->department)
                                        <div class="font-medium text-slate-800">{{ $p->specialization ?? '—' }}</div>
                                        @if($p?->department)
                                            <span class="text-[11px] text-slate-500">{{ $p->department }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-3 px-4 font-tabular">
                                @if($u->is_active ?? true)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60 font-tabular">
                                        Aktif
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200/60 font-tabular">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-medium">
                                @if($u->role === 'siswa' && $p?->assigned_kit_id)
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[11px] border border-slate-200/80">
                                        {{ str_starts_with($p->assigned_kit_id, 'TK-') ? $p->assigned_kit_id : 'TK-' . str_pad($p->assigned_kit_id, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-normal">—</span>
                                @endif
                            </td>
                            @if($isAdmin)
                                <td class="py-3 px-4 text-right">
                                    @if($isSuperuser || !in_array($u->role, ['superuser', 'admin', 'admin_sekolah']))
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="openEdit({{ json_encode($u) }}, {{ json_encode($p) }})"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                                                title="Edit Pengguna"
                                            >
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                            @if($u->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.toggle-status', $u->id) }}" onsubmit="return confirm('Ubah status aktif akun ini?')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        class="p-1.5 rounded-lg transition-colors {{ ($u->is_active ?? true) ? 'text-slate-400 hover:text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50' }}"
                                                        title="{{ ($u->is_active ?? true) ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}"
                                                    >
                                                        <i data-lucide="{{ ($u->is_active ?? true) ? 'user-x' : 'user-check' }}" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('users.destroy', $u->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini secara permanen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                                        title="Hapus Akun"
                                                    >
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-medium italic">Akun Sistem</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? '7' : '6' }}" class="py-10 text-center text-slate-400 text-xs">
                                Tidak ada data pengguna yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if($isAdmin)
        <!-- Modal: Tambah Pengguna Baru -->
        <div
            x-show="showCreateModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 space-y-4 shadow-xl border border-slate-200" @click.outside="showCreateModal = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Tambah Pengguna Baru</h3>
                        <p class="text-xs text-slate-500">Pendaftaran akun guru atau siswa dengan kata sandi acak aman</p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('users.store') }}" class="space-y-3.5">
                    @csrf
                    @if($isSuperuser)
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sekolah Target *</label>
                            <select name="school_id" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="">-- Pilih Sekolah --</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" {{ (string) $selectedSchoolId === (string) $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                            <input type="text" name="name" required placeholder="Nama lengkap" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Email Resmi *</label>
                            <input type="email" name="email" required placeholder="user@smk.belajar.id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Peran Pengguna *</label>
                            <select name="role" x-model="newUserData.role" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="siswa">Siswa Praktikan</option>
                                <option value="guru">Guru Pembimbing</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Induk <span class="font-normal text-slate-400 text-[10px]">(Opsional)</span></label>
                            <input type="text" name="identifier_number" placeholder="Nomor induk (opsional)" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Kontak / WhatsApp</label>
                            <input type="text" name="phone" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Konsentrasi Keahlian / Jurusan</label>
                            <input type="text" name="department" value="Agribisnis Tanaman Pangan &amp; Hortikultura" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                    </div>

                    <!-- Fields specifically for Guru -->
                    <div x-show="newUserData.role === 'guru'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Spesialisasi Agronomi</label>
                        <input type="text" name="specialization" placeholder="Contoh: Agroteknologi &amp; Pertanian Presisi IoT" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                    </div>

                    <!-- Fields specifically for Siswa -->
                    <div x-show="newUserData.role === 'siswa'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kelas</label>
                            <select name="classroom_id" x-model="newUserData.classroom_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="">-- Pilih Kelas --</option>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kelompok</label>
                            <select name="student_group_id" x-model="newUserData.student_group_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="">-- Pilih Kelompok --</option>
                                @foreach($groups as $grp)
                                    <option value="{{ $grp->id }}" x-show="!newUserData.classroom_id || '{{ $grp->classroom_id }}' === String(newUserData.classroom_id)">{{ $grp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Meja Trainer Kit</label>
                            <select name="assigned_kit_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                                <option value="">-- Bebas --</option>
                                @foreach($devices as $dev)
                                    <option value="{{ $dev->code }}">{{ $dev->code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-[11px] text-slate-600 flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                        <span>Kata sandi acak awal di-generate otomatis; pengguna wajib mengubah sandi pada sesi login pertama.</span>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="showCreateModal = false" class="px-3.5 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#0c3c26] hover:bg-[#082819] transition-colors shadow-2xs">Simpan Pengguna</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Edit Pengguna -->
        <div
            x-show="showEditModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 space-y-4 shadow-xl border border-slate-200" @click.outside="showEditModal = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Edit Data Pengguna</h3>
                        <p class="text-xs text-slate-500">Perbarui profil dan alokasi keanggotaan</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form :action="'{{ url('/users') }}/' + editUserData.id" method="POST" class="space-y-3.5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
                            <input type="text" name="name" x-model="editUserData.name" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Email Resmi *</label>
                            <input type="email" name="email" x-model="editUserData.email" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Peran Pengguna *</label>
                            <select name="role" x-model="editUserData.role" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="siswa">Siswa Praktikan</option>
                                <option value="guru">Guru Pembimbing</option>
                                @if($isSuperuser)
                                    <option value="admin_sekolah">Admin Sekolah</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Induk <span class="font-normal text-slate-400 text-[10px]">(Opsional)</span></label>
                            <input type="text" name="identifier_number" x-model="editUserData.identifier_number" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Kontak / WhatsApp</label>
                            <input type="text" name="phone" x-model="editUserData.phone" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Konsentrasi Keahlian / Jurusan</label>
                            <input type="text" name="department" x-model="editUserData.department" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                        </div>
                    </div>

                    <!-- Fields specifically for Guru -->
                    <div x-show="editUserData.role === 'guru'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Spesialisasi Agronomi</label>
                        <input type="text" name="specialization" x-model="editUserData.specialization" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                    </div>

                    <!-- Fields specifically for Siswa -->
                    <div x-show="editUserData.role === 'siswa'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kelas</label>
                            <select name="classroom_id" x-model="editUserData.classroom_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="">-- Pilih Kelas --</option>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kelompok</label>
                            <select name="student_group_id" x-model="editUserData.student_group_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none">
                                <option value="">-- Pilih Kelompok --</option>
                                @foreach($groups as $grp)
                                    <option value="{{ $grp->id }}" x-show="!editUserData.classroom_id || '{{ $grp->classroom_id }}' === String(editUserData.classroom_id)">{{ $grp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Meja Trainer Kit</label>
                            <select name="assigned_kit_id" x-model="editUserData.assigned_kit_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-[#0c3c26]/20 focus:border-[#0c3c26] focus:outline-none font-mono">
                                <option value="">-- Bebas --</option>
                                @foreach($devices as $dev)
                                    <option value="{{ $dev->code }}">{{ $dev->code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="showEditModal = false" class="px-3.5 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#0c3c26] hover:bg-[#082819] transition-colors shadow-2xs">Perbarui Pengguna</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Bulk Import CSV/XLSX -->
        <div
            x-show="showImportModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-3xl w-full p-5 sm:p-6 space-y-4 shadow-xl border border-slate-200 max-h-[90vh] flex flex-col" @click.outside="showImportModal = false">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Bulk Import Akun Pengguna</h3>
                        <p class="text-xs text-slate-500">Unggah berkas CSV atau XLSX dengan kolom: nama, email, role (siswa/guru), nisn/nip (opsional), kelas, kelompok, kit.</p>
                    </div>
                    <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto pr-1">
                    <!-- File Input -->
                    <div class="p-4 rounded-xl border-2 border-dashed border-slate-200 hover:border-slate-400 bg-slate-50/40 text-center transition-colors">
                        <input type="file" accept=".csv, .xlsx" @change="handleFileSelect" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-2">Maksimal 10MB (.csv atau .xlsx). Pratinjau validasi data akan langsung ditampilkan.</p>
                    </div>

                    <!-- Loading State -->
                    <div x-show="importLoading" class="p-8 text-center space-y-2">
                        <div class="w-6 h-6 border-2 border-[#0c3c26] border-t-transparent rounded-full animate-spin mx-auto"></div>
                        <p class="text-xs text-slate-500">Menganalisis dan memvalidasi baris data...</p>
                    </div>

                    <!-- Error Alert -->
                    <div x-show="importError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                        <span x-text="importError"></span>
                    </div>

                    <!-- Preview Table -->
                    <div x-show="importPreview" class="space-y-3">
                        <div class="flex items-center justify-between text-xs flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-700">Total: <span class="font-tabular font-semibold" x-text="importPreview?.total_rows"></span></span>
                                <span class="text-slate-300">|</span>
                                <span class="text-emerald-700 font-medium">Valid: <span class="font-tabular font-semibold" x-text="importPreview?.valid_count"></span></span>
                                <span class="text-slate-300">|</span>
                                <span class="text-rose-700 font-medium">Tidak Valid: <span class="font-tabular font-semibold" x-text="importPreview?.invalid_count"></span></span>
                            </div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-slate-400"></i>
                                Proteksi sandi unik &amp; wajib ganti saat login pertama
                            </div>
                        </div>

                        <div class="border border-slate-200/80 rounded-xl overflow-hidden max-h-60 overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-medium sticky top-0">
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
                                        <tr :class="r.is_valid ? 'hover:bg-slate-50/60' : 'bg-rose-50/30'">
                                            <td class="p-2.5 font-tabular text-[11px] text-slate-400" x-text="r.row_index"></td>
                                            <td class="p-2.5 font-medium text-slate-800" x-text="r.name"></td>
                                            <td class="p-2.5 font-mono text-[11px] text-slate-600" x-text="r.email"></td>
                                            <td class="p-2.5">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-medium uppercase font-tabular" :class="r.role === 'siswa' ? 'bg-slate-100 text-slate-700' : 'bg-slate-200 text-slate-800'" x-text="r.role"></span>
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="r.is_valid" class="text-emerald-700 font-medium text-[11px]">Valid</span>
                                                <span x-show="!r.is_valid" class="text-rose-700 font-medium text-[11px]">Error</span>
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
                    <button type="button" @click="showImportModal = false" class="px-3.5 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100">Tutup</button>
                    <button
                        type="button"
                        x-show="importPreview && importPreview.valid_count > 0"
                        :disabled="uploadProgress"
                        @click="submitImport"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#0c3c26] hover:bg-[#082819] disabled:opacity-50 inline-flex items-center gap-2 transition-colors shadow-2xs"
                    >
                        <span x-show="!uploadProgress">Impor <span x-text="importPreview?.valid_count"></span> Akun Valid</span>
                        <span x-show="uploadProgress">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
