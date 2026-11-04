<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkUserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_users_preview_and_execution_via_csv(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $device = Device::create(['code' => 'TK-01', 'name' => 'Meja 1', 'is_active' => true]);

        $csvContent = "name,email,role,identifier_number,class_grade,group_name,assigned_kit\n" .
                      "Budi Siswa Baru,budi.baru@smk.id,siswa,NISN123456,XI ATPH 1,Kelompok 1,TK-01\n" .
                      "Siti Guru Baru,siti.baru@smk.id,guru,NIP987654321,Guru Agronomi,,\n" .
                      "Invalid Role User,invalid@smk.id,hacker,NISN999999,X ATPH 2,,";

        $previewResponse = $this->actingAs($teacher)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('users_sample.csv', $csvContent),
        ]);

        $previewResponse->assertOk()->assertJsonStructure([
            'total_rows',
            'valid_count',
            'invalid_count',
            'rows' => ['*' => ['row_index', 'name', 'email', 'role', 'is_valid', 'errors']],
        ]);

        $data = $previewResponse->json();
        $this->assertEquals(3, $data['total_rows']);
        $this->assertEquals(2, $data['valid_count']);
        $this->assertEquals(1, $data['invalid_count']);

        $executeResponse = $this->actingAs($teacher)->postJson(route('users.import.execute'), [
            'rows' => array_values(array_filter($data['rows'], fn ($row) => $row['is_valid'])),
        ]);

        $executeResponse->assertOk()->assertJsonPath('success', true)->assertJsonPath('imported_count', 2);
        $this->assertDatabaseHas('users', ['email' => 'budi.baru@smk.id', 'role' => 'siswa']);
        $this->assertDatabaseHas('users', ['email' => 'siti.baru@smk.id', 'role' => 'guru']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@smk.id']);

        $budi = User::where('email', 'budi.baru@smk.id')->firstOrFail();
        $this->assertEquals('NISN123456', $budi->profile->identifier_number);
        $this->assertEquals('TK-01', $budi->profile->assigned_kit_id);
        $this->assertSame(config('auth.default_import_password'), $data['default_password']);
    }

    public function test_preview_rejects_duplicate_existing_and_invalid_assignment_references(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $existing = User::factory()->create(['email' => 'existing@smk.id']);
        UserProfile::create(['user_id' => $existing->id, 'type' => 'siswa', 'identifier_number' => 'NIS-EXISTING']);
        $device = Device::create(['code' => 'TK-EXISTING', 'name' => 'Meja Existing', 'is_active' => true]);

        $csv = "nama,email,role,nisn,kelas,kelompok,kit\n" .
            "Siswa Baru,existing@smk.id,siswa,NIS-NEW,XI ATPH 1,Kelompok A,TK-EXISTING\n" .
            "Duplikat Email,existing@smk.id,siswa,NIS-OTHER,XI ATPH 1,Kelompok A,TK-EXISTING\n" .
            "Kit Tidak Valid,kit@smk.id,siswa,NIS-KIT,XI ATPH 1,Kelompok A,TK-MISSING\n" .
            "Kelompok Tanpa Kelas,group@smk.id,siswa,NIS-GROUP,,Kelompok A,TK-EXISTING";

        $response = $this->actingAs($teacher)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('invalid_assignments.csv', $csv),
        ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('valid_count'));
        $this->assertSame(4, $response->json('invalid_count'));
        $this->assertNotEmpty($response->json('rows.0.errors'));
        $this->assertNotEmpty($response->json('rows.1.errors'));
        $this->assertNotEmpty($response->json('rows.2.errors'));
        $this->assertNotEmpty($response->json('rows.3.errors'));
    }

    public function test_import_uses_the_configured_initial_password(): void
    {
        config(['auth.default_import_password' => 'Custom-Initial-Password']);
        $teacher = User::factory()->create(['role' => 'guru']);
        $response = $this->actingAs($teacher)->postJson(route('users.import.execute'), [
            'rows' => [[
                'name' => 'Password Test',
                'email' => 'password.test@smk.id',
                'role' => 'siswa',
            ]],
        ]);

        $response->assertOk();
        $imported = User::where('email', 'password.test@smk.id')->firstOrFail();
        $this->assertTrue(Hash::check('Custom-Initial-Password', $imported->password));
    }

    public function test_import_rejects_inactive_or_foreign_trainer_kit_before_writes(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $inactive = Device::create(['code' => 'TK-INACTIVE', 'name' => 'Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($teacher)->postJson(route('users.import.execute'), [
            'rows' => [[
                'name' => 'Invalid Kit User',
                'email' => 'invalid.kit@smk.id',
                'role' => 'siswa',
                'assigned_kit' => 'TK-INACTIVE',
            ]],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'invalid.kit@smk.id']);
    }

    public function test_bulk_import_xlsx_file_preview(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        Device::create(['code' => 'TK-99', 'name' => 'Meja 99', 'is_active' => true]);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

        $strings = [
            'name', 'email', 'role', 'identifier_number', 'class_grade', 'group_name', 'assigned_kit',
            'Dewa Siswa', 'dewa.xlsx@smk.id', 'siswa', 'NISN777', 'X ATPH 1', 'Kelompok A', 'TK-99',
        ];
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $string) {
            $sstXml .= '<si><t>' . htmlspecialchars($string) . '</t></si>';
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
  <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c></row>
  <row r="2"><c r="A2" t="s"><v>7</v></c><c r="B2" t="s"><v>8</v></c><c r="C2" t="s"><v>9</v></c><c r="D2" t="s"><v>10</v></c><c r="E2" t="s"><v>11</v></c><c r="F2" t="s"><v>12</v></c><c r="G2" t="s"><v>13</v></c></row>
</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        try {
            $uploadedFile = new UploadedFile($tempXlsx, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $previewResponse = $this->actingAs($teacher)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $previewResponse->assertOk()->assertJsonPath('total_rows', 1)->assertJsonPath('valid_count', 1)->assertJsonPath('rows.0.email', 'dewa.xlsx@smk.id');
        } finally {
            @unlink($tempXlsx);
        }
    }
}
