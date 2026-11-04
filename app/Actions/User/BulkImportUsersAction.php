<?php

namespace App\Actions\User;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\StudentGroup;
use App\Models\Device;
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
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid.';
            } elseif (isset($seenEmails[$email])) {
                $errors[] = 'Email duplikat di dalam file import.';
            } elseif (User::where('email', $email)->exists()) {
                $errors[] = 'Email sudah terdaftar di sistem.';
            }

            if (!in_array($role, ['siswa', 'guru'])) {
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
                $kitExists = \App\Models\Device::where(function ($q) use ($schoolId) {
                    if ($schoolId) {
                        $q->where('school_id', $schoolId)->orWhereNull('school_id');
                    }
                })->where(function ($q) use ($assignedKit) {
                    $q->where('code', $assignedKit);
                    if (is_numeric($assignedKit)) {
                        $q->orWhere('id', (int) $assignedKit);
                    }
                })->where('is_active', true)->exists();

                if (!$kitExists) {
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
        $schoolId = $schoolId ?? ($actor?->school_id ?? School::where('slug', 'smk-ppn-1-sukaraja')->value('id') ?? School::first()?->id);

        $this->validateExecutionRows($rows, $schoolId);
        $imported = [];
        $credentialDeliveries = [];

        DB::transaction(function () use ($rows, $schoolId, $actor, &$imported, &$credentialDeliveries) {
            foreach ($rows as $item) {
                $email = strtolower(trim($item['email']));
                $role = strtolower(trim($item['role']));
                $name = trim($item['name']);
                $identifier = !empty($item['identifier_number']) ? trim($item['identifier_number']) : null;

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
                $classGrade = !empty($item['class_grade']) ? trim($item['class_grade']) : null;
                if ($classGrade) {
                    $classroom = Classroom::firstOrCreate(
                        ['name' => $classGrade, 'school_id' => $schoolId],
                        ['department' => 'Agribisnis Tanaman Pangan & Hortikultura']
                    );
                    $classroomId = $classroom->id;
                }

                $groupId = null;
                $groupName = !empty($item['group_name']) ? trim($item['group_name']) : null;
                if ($groupName) {
                    $group = StudentGroup::firstOrCreate(
                        ['name' => $groupName, 'classroom_id' => $classroomId, 'school_id' => $schoolId]
                    );
                    $groupId = $group->id;
                }

                $assignedKit = !empty($item['assigned_kit']) ? trim($item['assigned_kit']) : null;

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
                    'details' => 'Berhasil mengimpor ' . count($imported) . ' akun pengguna baru dengan kata sandi awal unik dan proteksi wajib ganti sandi.',
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

            if ($name === '' || $email === '' || !in_array($role, ['siswa', 'guru'])) {
                throw ValidationException::withMessages([
                    "rows.{$index}" => "Baris #{$index} memiliki data wajib (nama, email, role) yang tidak lengkap atau tidak valid."
                ]);
            }

            if ($groupName !== '' && $classGrade === '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.group_name" => "Baris #{$index} memiliki kelompok kerja tanpa nama kelas."
                ]);
            }

            if (isset($emails[$email])) {
                throw ValidationException::withMessages([
                    "rows.{$index}.email" => "Email {$email} muncul berulang kali dalam berkas import."
                ]);
            }
            $emails[$email] = true;

            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    "rows.{$index}.email" => "Email {$email} sudah terdaftar dalam sistem."
                ]);
            }

            if ($identifier !== '') {
                if (isset($identifiers[$identifier])) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.identifier_number" => "Nomor induk {$identifier} muncul berulang kali."
                    ]);
                }
                $identifiers[$identifier] = true;

                if (UserProfile::where('identifier_number', $identifier)->exists()) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.identifier_number" => "Nomor induk {$identifier} sudah digunakan pengguna lain."
                    ]);
                }
            }

            if ($assignedKit !== '') {
                $kitExists = \App\Models\Device::where(function ($q) use ($schoolId) {
                    if ($schoolId) {
                        $q->where('school_id', $schoolId)->orWhereNull('school_id');
                    }
                })->where(function ($q) use ($assignedKit) {
                    $q->where('code', $assignedKit);
                    if (is_numeric($assignedKit)) {
                        $q->orWhere('id', (int) $assignedKit);
                    }
                })->where('is_active', true)->exists();

                if (!$kitExists) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.assigned_kit" => "Trainer Kit {$assignedKit} tidak ditemukan atau tidak aktif di sekolah ini."
                    ]);
                }
            }
        }
    }

    /**
     * Parse CSV file with BOM detection and delimiter flexibility.
     */
    private function parseCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        // Strip UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Detect delimiter: comma, semicolon, tab
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        return $rows;
    }

    /**
     * Lightweight native XLSX parser using ZipArchive and SimpleXML.
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $currentRow = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $val = (string) $cell->v;

                if ($type === 's' && isset($sharedStrings[(int) $val])) {
                    $currentRow[] = $sharedStrings[(int) $val];
                } else {
                    $currentRow[] = $val;
                }
            }
            $rows[] = $currentRow;
        }

        return $rows;
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
