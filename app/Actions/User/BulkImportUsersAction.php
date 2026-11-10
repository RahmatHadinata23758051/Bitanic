<?php

namespace App\Actions\User;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class BulkImportUsersAction
{
    /**
     * Parse and validate file for preview.
     */
    public function preview(UploadedFile $file, ?int $schoolId = null): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if ($extension === 'xlsx') {
            $rows = $this->parseXlsx($file->getRealPath());
        } else {
            $rows = $this->parseCsv($file->getRealPath());
        }

        if (empty($rows)) {
            return [
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 0,
                'rows' => [],
            ];
        }

        $headers = array_shift($rows);
        $normalizedHeaders = $this->normalizeHeaders($headers);

        $parsedRows = [];
        $seenEmails = [];
        $seenIdentifiers = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $index => $row) {
            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue; // Skip completely empty rows
            }

            $mapped = $this->mapRow($normalizedHeaders, $row);
            $errors = [];

            // Validation rules
            $name = trim($mapped['name'] ?? '');
            $email = strtolower(trim($mapped['email'] ?? ''));
            $role = strtolower(trim($mapped['role'] ?? ''));
            $identifier = trim($mapped['identifier_number'] ?? '');
            $classGrade = trim($mapped['class_grade'] ?? '');
            $groupName = trim($mapped['group_name'] ?? '');
            $assignedKit = trim($mapped['assigned_kit'] ?? '');

            if ($name === '') {
                $errors[] = 'Nama wajib diisi.';
            }

            if ($email === '') {
                $errors[] = 'Email wajib diisi.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid.';
            } elseif (isset($seenEmails[$email])) {
                $errors[] = 'Email duplikat di dalam file import.';
            } elseif (User::where('email', $email)->exists()) {
                $errors[] = 'Email sudah terdaftar di sistem.';
            }

            if (! in_array($role, ['siswa', 'guru'])) {
                $errors[] = 'Role harus berupa "siswa" atau "guru".';
            }

            if ($identifier !== '') {
                if (isset($seenIdentifiers[$identifier])) {
                    $errors[] = 'Nomor Induk / NISN duplikat di dalam file import.';
                } elseif (UserProfile::where('identifier_number', $identifier)->exists()) {
                    $errors[] = 'Nomor Induk / NISN sudah digunakan pengguna lain.';
                }
            }

            if ($groupName !== '' && $classGrade === '') {
                $errors[] = 'Kelompok kerja memerlukan nama kelas yang ditentukan.';
            }

            if ($assignedKit !== '') {
                $kitExists = Device::where(function ($q) use ($schoolId) {
                    if ($schoolId) {
                        $q->where('school_id', $schoolId)->orWhereNull('school_id');
                    }
                })->where(function ($q) use ($assignedKit) {
                    $q->where('code', $assignedKit);
                    if (is_numeric($assignedKit)) {
                        $q->orWhere('id', (int) $assignedKit);
                    }
                })->where('is_active', true)->exists();

                if (! $kitExists) {
                    $errors[] = "Trainer Kit {$assignedKit} tidak ditemukan atau belum aktif.";
                }
            }

            $isValid = count($errors) === 0;

            if ($isValid) {
                $seenEmails[$email] = true;
                if ($identifier !== '') {
                    $seenIdentifiers[$identifier] = true;
                }
                $validCount++;
            } else {
                $invalidCount++;
            }

            $parsedRows[] = [
                'row_index' => $index + 1,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'identifier_number' => $identifier ?: null,
                'class_grade' => $classGrade ?: null,
                'group_name' => $groupName ?: null,
                'assigned_kit' => $assignedKit ?: null,
                'is_valid' => $isValid,
                'errors' => $errors,
            ];
        }

        return [
            'total_rows' => count($parsedRows),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'rows' => $parsedRows,
        ];
    }

    /**
     * Execute batch import for validated rows.
     */
    public function execute(array $rows, ?int $schoolId = null, ?User $actor = null): array
    {
        if (count($rows) > self::MAX_IMPORT_ROWS) {
            throw ValidationException::withMessages([
                'rows' => 'Berkas impor tidak boleh berisi lebih dari 500 baris data.',
            ]);
        }

        if ($actor && ! $actor->isSuperuser()) {
            $schoolId = $actor->school_id;
        }

        if (! $schoolId) {
            throw ValidationException::withMessages([
                'school_id' => 'Target sekolah wajib dipilih untuk eksekusi impor pengguna.',
            ]);
        }

        $school = School::where('id', $schoolId)->where('status', 'active')->first();
        if (! $school) {
            throw ValidationException::withMessages([
                'school_id' => 'Sekolah target tidak aktif atau tidak ditemukan.',
            ]);
        }

        $this->validateExecutionRows($rows, $schoolId);
        $imported = [];
        $credentialDeliveries = [];

        DB::transaction(function () use ($rows, $schoolId, $actor, &$imported, &$credentialDeliveries) {
            foreach ($rows as $item) {
                $email = strtolower(trim($item['email']));
                $role = strtolower(trim($item['role']));
                $name = trim($item['name']);
                $identifier = ! empty($item['identifier_number']) ? trim($item['identifier_number']) : null;

                // Ensure unique
                if (User::where('email', $email)->exists()) {
                    continue;
                }

                $initialPassword = $this->generateInitialPassword($identifier);

                $user = User::create([
                    'school_id' => $schoolId,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'password' => Hash::make($initialPassword),
                    'must_change_password' => true,
                ]);

                $classroomId = null;
                $classGrade = ! empty($item['class_grade']) ? trim($item['class_grade']) : null;
                if ($classGrade) {
                    $classroom = Classroom::firstOrCreate(
                        ['name' => $classGrade, 'school_id' => $schoolId],
                        ['department' => 'Agribisnis Tanaman Pangan & Hortikultura']
                    );
                    $classroomId = $classroom->id;
                }

                $groupId = null;
                $groupName = ! empty($item['group_name']) ? trim($item['group_name']) : null;
                if ($groupName) {
                    $group = StudentGroup::firstOrCreate(
                        ['name' => $groupName, 'classroom_id' => $classroomId, 'school_id' => $schoolId]
                    );
                    $groupId = $group->id;
                }

                $assignedKit = ! empty($item['assigned_kit']) ? trim($item['assigned_kit']) : null;

                UserProfile::create([
                    'user_id' => $user->id,
                    'type' => $role,
                    'identifier_number' => $identifier,
                    'class_grade' => $classGrade,
                    'classroom_id' => $classroomId,
                    'group_name' => $groupName,
                    'student_group_id' => $groupId,
                    'assigned_kit_id' => $assignedKit,
                ]);

                $imported[] = $user->id;
                try {
                    $token = app('auth.password.broker')->createToken($user);
                    $user->sendPasswordResetNotification($token);
                } catch (\Throwable $e) {
                    // Ignore mail transport errors in offline/demo environments
                }
            }

            if ($actor && count($imported) > 0) {
                AuditLog::create([
                    'user_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'actor_role' => $actor->role,
                    'action' => 'Bulk Import Pengguna',
                    'details' => 'Berhasil mengimpor '.count($imported).' akun pengguna baru dengan kata sandi awal unik dan proteksi wajib ganti sandi.',
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                ]);
            }
        });

        return [
            'success' => true,
            'imported_count' => count($imported),
        ];
    }

    /**
     * Generate unique, user-friendly initial password.
     */
    public function generateInitialPassword(?string $identifier): string
    {
        return Str::random(32);
    }

    /**
     * Validate rows prior to execution to prevent race conditions or invalid data.
     */
    private function validateExecutionRows(array $rows, int $schoolId): void
    {
        $emails = [];
        $identifiers = [];

        foreach ($rows as $index => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            $identifier = trim((string) ($row['identifier_number'] ?? ''));
            $classGrade = trim((string) ($row['class_grade'] ?? ''));
            $groupName = trim((string) ($row['group_name'] ?? ''));
            $assignedKit = trim((string) ($row['assigned_kit'] ?? ''));

            if ($name === '' || $email === '' || ! in_array($role, ['siswa', 'guru'])) {
                throw ValidationException::withMessages([
                    "rows.{$index}" => "Baris #{$index} memiliki data wajib (nama, email, role) yang tidak lengkap atau tidak valid.",
                ]);
            }

            if ($groupName !== '' && $classGrade === '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.group_name" => "Baris #{$index} memiliki kelompok kerja tanpa nama kelas.",
                ]);
            }

            if (isset($emails[$email])) {
                throw ValidationException::withMessages([
                    "rows.{$index}.email" => "Email {$email} muncul berulang kali dalam berkas import.",
                ]);
            }
            $emails[$email] = true;

            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    "rows.{$index}.email" => "Email {$email} sudah terdaftar dalam sistem.",
                ]);
            }

            if ($identifier !== '') {
                if (isset($identifiers[$identifier])) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.identifier_number" => "Nomor induk {$identifier} muncul berulang kali.",
                    ]);
                }
                $identifiers[$identifier] = true;

                if (UserProfile::where('identifier_number', $identifier)->exists()) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.identifier_number" => "Nomor induk {$identifier} sudah digunakan pengguna lain.",
                    ]);
                }
            }

            if ($assignedKit !== '') {
                $kitExists = Device::where(function ($q) use ($schoolId) {
                    if ($schoolId) {
                        $q->where('school_id', $schoolId)->orWhereNull('school_id');
                    }
                })->where(function ($q) use ($assignedKit) {
                    $q->where('code', $assignedKit);
                    if (is_numeric($assignedKit)) {
                        $q->orWhere('id', (int) $assignedKit);
                    }
                })->where('is_active', true)->exists();

                if (! $kitExists) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.assigned_kit" => "Trainer Kit {$assignedKit} tidak ditemukan atau tidak aktif di sekolah ini.",
                    ]);
                }
            }
        }
    }

    private const MAX_IMPORT_ROWS = 500;

    private const MAX_XLSX_ENTRY_BYTES = 20 * 1024 * 1024;

    private const MAX_XLSX_TOTAL_BYTES = 32 * 1024 * 1024;

    /**
     * Stream CSV records so RFC 4180 quoted cells can safely contain newlines.
     * Supports UTF-8 BOM and comma, semicolon, or tab delimiters.
     *
     * @return array<int, array<int, string|null>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibaca.']);
        }

        try {
            $prefix = fread($handle, 3);
            if ($prefix !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $dataStart = ftell($handle);

            $delimiter = ',';
            $maxColumns = 0;
            foreach ([',', ';', "\t"] as $candidate) {
                fseek($handle, $dataStart);
                $sample = fgetcsv($handle, 0, $candidate, '"', '');
                $columnCount = is_array($sample) ? count($sample) : 0;
                if ($columnCount > $maxColumns) {
                    $maxColumns = $columnCount;
                    $delimiter = $candidate;
                }
            }

            fseek($handle, $dataStart);
            $rows = [];
            $dataRowCount = 0;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                if ($rows !== []) {
                    $dataRowCount++;
                    if ($dataRowCount > self::MAX_IMPORT_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'Berkas impor tidak boleh berisi lebih dari 500 baris data.',
                        ]);
                    }
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse a bounded XLSX workbook without loading external XML resources.
     *
     * @return array<int, array<int, string>>
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Arsip XLSX rusak atau tidak dapat dibuka.']);
        }

        try {
            $this->assertXlsxArchiveSize($zip);
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($workbookXml === false || $relationshipsXml === false) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak memiliki struktur workbook yang valid.']);
            }

            $workbook = $this->loadSafeXml($workbookXml);
            $relationships = $this->loadSafeXml($relationshipsXml);
            $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $packageRelationshipNamespace = 'http://schemas.openxmlformats.org/package/2006/relationships';

            $relationshipMap = [];
            $relationships->registerXPathNamespace('package', $packageRelationshipNamespace);
            foreach ($relationships->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') ?: [] as $relationship) {
                $attributes = $relationship->attributes();
                $id = (string) ($attributes['Id'] ?? '');
                $target = (string) ($attributes['Target'] ?? '');
                $type = (string) ($attributes['Type'] ?? '');
                $targetMode = (string) ($attributes['TargetMode'] ?? '');
                if ($id !== '' && $target !== '' && $targetMode !== 'External') {
                    $relationshipMap[$id] = [
                        'target' => $this->resolveXlsxTarget($target),
                        'type' => $type,
                    ];
                }
            }

            $sheets = $workbook->xpath('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [];
            $firstSheet = $sheets[0] ?? null;
            $sheetAttributes = $firstSheet?->attributes($relationshipNamespace);
            $sheetRelationshipId = (string) ($sheetAttributes['id'] ?? '');
            $sheetRelationship = $relationshipMap[$sheetRelationshipId] ?? null;
            if (! $sheetRelationship || ! str_ends_with($sheetRelationship['type'], '/worksheet') || ! $sheetRelationship['target']) {
                throw ValidationException::withMessages(['file' => 'Target worksheet XLSX tidak ditemukan atau tidak valid.']);
            }

            $sharedStrings = [];
            foreach ($relationshipMap as $relationship) {
                if (str_ends_with($relationship['type'], '/sharedStrings') && $relationship['target']) {
                    $sharedStringsXml = $zip->getFromName($relationship['target']);
                    if ($sharedStringsXml === false) {
                        throw ValidationException::withMessages(['file' => 'Shared strings XLSX tidak dapat dibaca.']);
                    }
                    $sharedStrings = $this->parseSharedStrings($this->loadSafeXml($sharedStringsXml), $mainNamespace);
                    break;
                }
            }

            $sheetXml = $zip->getFromName($sheetRelationship['target']);
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'Worksheet XLSX tidak dapat dibaca.']);
            }

            $worksheet = $this->loadSafeXml($sheetXml);
            $worksheetRows = $worksheet->xpath('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
            $rows = [];
            $dataRowCount = 0;

            foreach ($worksheetRows as $worksheetRow) {
                $currentRow = [];
                $nextColumn = 0;
                foreach ($worksheetRow->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $attributes = $cell->attributes();
                    $reference = strtoupper((string) ($attributes['r'] ?? ''));
                    if ($reference !== '' && preg_match('/^([A-Z]+)\d+$/', $reference, $matches) === 1) {
                        $columnIndex = $this->xlsxColumnIndex($matches[1]);
                    } else {
                        $columnIndex = $nextColumn;
                    }
                    if ($columnIndex > 16383) {
                        throw ValidationException::withMessages(['file' => 'Worksheet XLSX memiliki referensi kolom di luar batas format.']);
                    }
                    $nextColumn = $columnIndex + 1;

                    $type = (string) ($attributes['t'] ?? '');
                    $currentRow[$columnIndex] = $this->parseXlsxCell($cell, $type, $sharedStrings, $mainNamespace);
                }

                if ($currentRow === []) {
                    continue;
                }

                $lastColumn = max(array_keys($currentRow));
                $normalizedRow = [];
                for ($column = 0; $column <= $lastColumn; $column++) {
                    $normalizedRow[] = $currentRow[$column] ?? '';
                }
                if (empty(array_filter($normalizedRow, fn ($value) => trim($value) !== ''))) {
                    continue;
                }

                if ($rows !== []) {
                    $dataRowCount++;
                    if ($dataRowCount > self::MAX_IMPORT_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'Berkas impor tidak boleh berisi lebih dari 500 baris data.',
                        ]);
                    }
                }
                $rows[] = $normalizedRow;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function assertXlsxArchiveSize(ZipArchive $zip): void
    {
        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false) {
                throw ValidationException::withMessages(['file' => 'Arsip XLSX memiliki entri yang tidak valid.']);
            }

            $entrySize = (int) ($stat['size'] ?? 0);
            if ($entrySize > self::MAX_XLSX_ENTRY_BYTES) {
                throw ValidationException::withMessages(['file' => 'Ukuran data hasil ekstraksi XLSX melampaui batas aman.']);
            }
            $totalSize += $entrySize;
            if ($totalSize > self::MAX_XLSX_TOTAL_BYTES) {
                throw ValidationException::withMessages(['file' => 'Ukuran total data XLSX melampaui batas aman.']);
            }
        }
    }

    private function loadSafeXml(string $contents): \SimpleXMLElement
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) === 1) {
            throw ValidationException::withMessages(['file' => 'Deklarasi DTD atau entity XML tidak diizinkan dalam berkas XLSX.']);
        }

        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'Struktur XML XLSX rusak atau tidak valid.']);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /** @return array<int, string> */
    private function parseSharedStrings(\SimpleXMLElement $xml, string $namespace): array
    {
        $xml->registerXPathNamespace('main', $namespace);
        $strings = [];
        foreach ($xml->xpath('/*[local-name()="sst"]/*[local-name()="si"]') ?: [] as $item) {
            $text = '';
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $textNode) {
                $text .= (string) $textNode;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /** @param array<int, string> $sharedStrings */
    private function parseXlsxCell(\SimpleXMLElement $cell, string $type, array $sharedStrings, string $namespace): string
    {
        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cell->xpath('.//*[local-name()="is"]//*[local-name()="t"]') ?: [] as $textNode) {
                $text .= (string) $textNode;
            }

            return $text;
        }

        $valueNodes = $cell->xpath('./*[local-name()="v"]');
        $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
        if ($type === 's') {
            if ($value === '' || ! ctype_digit($value) || ! array_key_exists((int) $value, $sharedStrings)) {
                throw ValidationException::withMessages(['file' => 'Worksheet XLSX merujuk indeks shared string yang tidak valid.']);
            }

            return $sharedStrings[(int) $value];
        }
        if ($type === 'b') {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        // Formula cells use their cached <v> result; never evaluate formulas from an uploaded workbook.
        return $value;
    }

    private function xlsxColumnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function resolveXlsxTarget(string $target): ?string
    {
        $target = rawurldecode($target);
        if ($target === '' || str_contains($target, '\\') || str_contains($target, '?') || str_contains($target, '#')) {
            return null;
        }

        $segments = str_starts_with($target, '/') ? [] : ['xl'];
        foreach (explode('/', trim($target, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);

                continue;
            }
            if (str_contains($segment, ':')) {
                return null;
            }
            $segments[] = $segment;
        }

        $resolved = implode('/', $segments);

        return str_starts_with($resolved, 'xl/') ? $resolved : null;
    }

    private function normalizeHeaders(array $rawHeaders): array
    {
        $normalized = [];
        foreach ($rawHeaders as $index => $header) {
            $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $header)));
            $normalized[$index] = match ($clean) {
                'name', 'nama', 'namalengkap', 'fullname' => 'name',
                'email', 'surel', 'mail' => 'email',
                'role', 'peran', 'jabatan' => 'role',
                'identifier_number', 'identifier', 'nisn', 'nip', 'nomorinduk', 'nis' => 'identifier_number',
                'class_grade', 'class', 'grade', 'kelas', 'tingkat' => 'class_grade',
                'group_name', 'group', 'kelompok' => 'group_name',
                'assigned_kit', 'assigned_kit_id', 'trainer_kit', 'kit', 'meja' => 'assigned_kit',
                default => $clean,
            };
        }

        return $normalized;
    }

    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $key) {
            $mapped[$key] = $row[$index] ?? null;
        }

        return $mapped;
    }
}
