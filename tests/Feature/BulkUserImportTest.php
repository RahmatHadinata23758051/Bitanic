<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkUserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_users_preview_and_execution_via_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $device = Device::create(['code' => 'TK-01', 'name' => 'Meja 1', 'is_active' => true]);

        $csvContent = "name,email,role,identifier_number,class_grade,group_name,assigned_kit\n".
                      "Budi Siswa Baru,budi.baru@smk.id,siswa,NISN123456,XI ATPH 1,Kelompok 1,TK-01\n".
                      "Siti Guru Baru,siti.baru@smk.id,guru,NIP987654321,Guru Agronomi,,\n".
                      'Invalid Role User,invalid@smk.id,hacker,NISN999999,X ATPH 2,,';

        $previewResponse = $this->actingAs($admin)->postJson(route('users.import.preview'), [
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

        $executeResponse = $this->actingAs($admin)->postJson(route('users.import.execute'), [
            'rows' => array_values(array_filter($data['rows'], fn ($row) => $row['is_valid'])),
        ]);

        $executeResponse->assertOk()->assertJsonPath('success', true)->assertJsonPath('imported_count', 2);
        $this->assertDatabaseHas('users', ['email' => 'budi.baru@smk.id', 'role' => 'siswa']);
        $this->assertDatabaseHas('users', ['email' => 'siti.baru@smk.id', 'role' => 'guru']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@smk.id']);

        $budi = User::where('email', 'budi.baru@smk.id')->firstOrFail();
        $this->assertEquals('NISN123456', $budi->profile->identifier_number);
        $this->assertEquals('TK-01', $budi->profile->assigned_kit_id);
        $this->assertArrayNotHasKey('default_password', $data);
        $this->assertTrue($budi->must_change_password);
    }

    public function test_preview_rejects_duplicate_existing_and_invalid_assignment_references(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $existing = User::factory()->create(['email' => 'existing@smk.id']);
        UserProfile::create(['user_id' => $existing->id, 'type' => 'siswa', 'identifier_number' => 'NIS-EXISTING']);
        $device = Device::create(['code' => 'TK-EXISTING', 'name' => 'Meja Existing', 'is_active' => true]);

        $csv = "nama,email,role,nisn,kelas,kelompok,kit\n".
            "Siswa Baru,existing@smk.id,siswa,NIS-NEW,XI ATPH 1,Kelompok A,TK-EXISTING\n".
            "Duplikat Email,existing@smk.id,siswa,NIS-OTHER,XI ATPH 1,Kelompok A,TK-EXISTING\n".
            "Kit Tidak Valid,kit@smk.id,siswa,NIS-KIT,XI ATPH 1,Kelompok A,TK-MISSING\n".
            'Kelompok Tanpa Kelas,group@smk.id,siswa,NIS-GROUP,,Kelompok A,TK-EXISTING';

        $response = $this->actingAs($admin)->postJson(route('users.import.preview'), [
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

    public function test_import_generates_a_unique_initial_secret_and_sends_one_time_password_setup_link(): void
    {
        Notification::fake();
        config(['auth.default_import_password' => 'DoNotUseSharedPassword']);
        $admin = User::factory()->create(['role' => 'admin_sekolah']);

        $response = $this->actingAs($admin)->postJson(route('users.import.execute'), [
            'rows' => [
                [
                    'name' => 'Password Test A',
                    'email' => 'password.test.a@smk.id',
                    'role' => 'siswa',
                    'identifier_number' => 'NISN-UNIQUE-A',
                ],
                [
                    'name' => 'Password Test B',
                    'email' => 'password.test.b@smk.id',
                    'role' => 'siswa',
                    'identifier_number' => 'NISN-UNIQUE-B',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported_count', 2)
            ->assertJsonMissingPath('credentials')
            ->assertJsonMissingPath('default_password');

        $first = User::where('email', 'password.test.a@smk.id')->firstOrFail();
        $second = User::where('email', 'password.test.b@smk.id')->firstOrFail();
        $this->assertTrue($first->must_change_password);
        $this->assertTrue($second->must_change_password);
        $this->assertFalse(Hash::check('DoNotUseSharedPassword', $first->password));
        $this->assertFalse(Hash::check('DoNotUseSharedPassword', $second->password));
        Notification::assertSentTo($first, ResetPassword::class);
        Notification::assertSentTo($second, ResetPassword::class);
    }

    public function test_preview_and_classroom_import_page_never_expose_initial_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $preview = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('users.csv', 'name,email,role\\nSiswa,password.preview@smk.id,siswa'),
        ]);

        $preview->assertOk()->assertJsonMissingPath('default_password');

        $page = $this->get(route('classes.index'));
        $page->assertOk()
            ->assertDontSee('Bitanic@2026')
            ->assertDontSee('Password standar akun baru');
    }

    public function test_import_rejects_inactive_or_foreign_trainer_kit_before_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $inactive = Device::create(['code' => 'TK-INACTIVE', 'name' => 'Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($admin)->postJson(route('users.import.execute'), [
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
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        Device::create(['code' => 'TK-99', 'name' => 'Meja 99', 'is_active' => true]);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        $zip = new \ZipArchive;
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
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
        foreach ($strings as $string) {
            $sstXml .= '<si><t>'.htmlspecialchars($string).'</t></si>';
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
            $previewResponse = $this->actingAs($admin)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $previewResponse->assertOk()->assertJsonPath('total_rows', 1)->assertJsonPath('valid_count', 1)->assertJsonPath('rows.0.email', 'dewa.xlsx@smk.id');
        } finally {
            @unlink($tempXlsx);
        }
    }

    public function test_csv_with_quoted_multiline_cells_parses_without_splitting_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        Device::create(['code' => 'TK-MULTI', 'name' => 'Meja Multiline', 'is_active' => true]);

        // Group name and class grade contain multiline quoted text:
        $csvContent = "name,email,role,identifier_number,class_grade,group_name,assigned_kit\n".
            "\"Ahmad\nFauzi\",fauzi@smk.id,siswa,NISN-FAUZI,\"X ATPH 1\nUnggulan\",\"Kelompok A\nSesi Pagi\",TK-MULTI\n".
            "\"Siti\nNurhaliza\",siti.multi@smk.id,siswa,NISN-SITI,\"X ATPH 1\nUnggulan\",\"Kelompok B\nSesi Siang\",TK-MULTI\n";

        $response = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('multiline.csv', $csvContent),
        ]);

        $response->assertOk();
        $response->assertJsonPath('total_rows', 2);
        $response->assertJsonPath('valid_count', 2);
        $response->assertJsonPath('rows.0.email', 'fauzi@smk.id');
        $response->assertJsonPath('rows.1.email', 'siti.multi@smk.id');
    }

    public function test_csv_with_semicolon_and_tab_delimiters_and_bom_is_parsed_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        Device::create(['code' => 'TK-BOM', 'name' => 'Meja BOM', 'is_active' => true]);

        // UTF-8 BOM + semicolon delimited
        $csvSemicolon = "\xEF\xBB\xBFname;email;role;identifier_number;class_grade;group_name;assigned_kit\r\n".
            "Rian Siswa;rian.semi@smk.id;siswa;NISN-SEMI;X ATPH 1;Kelompok S;TK-BOM\r\n";

        $responseSemi = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('semicolon.csv', $csvSemicolon),
        ]);

        $responseSemi->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('valid_count', 1)
            ->assertJsonPath('rows.0.email', 'rian.semi@smk.id');

        // Tab-separated
        $csvTab = "name\temail\trole\tidentifier_number\tclass_grade\tgroup_name\tassigned_kit\n".
            "Doni Siswa\tdoni.tab@smk.id\tsiswa\tNISN-TAB\tX ATPH 1\tKelompok T\tTK-BOM\n";

        $responseTab = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('tab.csv', $csvTab),
        ]);

        $responseTab->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('valid_count', 1)
            ->assertJsonPath('rows.0.email', 'doni.tab@smk.id');
    }

    public function test_xlsx_with_sparse_cells_preserves_column_alignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        Device::create(['code' => 'TK-SPARSE', 'name' => 'Meja Sparse', 'is_active' => true]);

        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_sparse_').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $this->addXlsxPackageSkeleton($zip);

        $strings = [
            'name', 'email', 'role', 'identifier_number', 'class_grade', 'group_name', 'assigned_kit',
            'Budi Sparse', 'budi.sparse@smk.id', 'siswa', 'X ATPH 2', 'Kelompok C', 'TK-SPARSE',
        ];
        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
        foreach ($strings as $string) {
            $sstXml .= '<si><t>'.htmlspecialchars($string).'</t></si>';
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        // Notice: Row 2 has NO D2 (identifier_number is omitted in XLSX sparse row).
        // A2=name, B2=email, C2=role, E2=class_grade, F2=group_name, G2=assigned_kit
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
  <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c></row>
  <row r="2"><c r="A2" t="s"><v>7</v></c><c r="B2" t="s"><v>8</v></c><c r="C2" t="s"><v>9</v></c><c r="E2" t="s"><v>10</v></c><c r="F2" t="s"><v>11</v></c><c r="G2" t="s"><v>12</v></c></row>
</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        try {
            $uploadedFile = new UploadedFile($tempXlsx, 'sparse.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $previewResponse = $this->actingAs($admin)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $previewResponse->assertOk()
                ->assertJsonPath('total_rows', 1)
                ->assertJsonPath('valid_count', 1)
                ->assertJsonPath('rows.0.name', 'Budi Sparse')
                ->assertJsonPath('rows.0.email', 'budi.sparse@smk.id')
                ->assertJsonPath('rows.0.role', 'siswa')
                ->assertJsonPath('rows.0.identifier_number', null)
                ->assertJsonPath('rows.0.class_grade', 'X ATPH 2')
                ->assertJsonPath('rows.0.group_name', 'Kelompok C')
                ->assertJsonPath('rows.0.assigned_kit', 'TK-SPARSE');
        } finally {
            @unlink($tempXlsx);
        }
    }

    public function test_xlsx_resolves_sheet_target_from_workbook_and_handles_inlinestr(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        Device::create(['code' => 'TK-INLINE', 'name' => 'Meja Inline', 'is_active' => true]);

        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_inline_').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Workbook points rId99 to worksheets/custom_sheet.xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/custom_sheet.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Data Siswa" sheetId="2" r:id="rId99"/></sheets>
</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId99" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/custom_sheet.xml"/>
</Relationships>');

        // Uses inlineStr <is><t> instead of shared strings, and formula string <c t="str"><v>
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
  <row r="1">
    <c r="A1" t="inlineStr"><is><t>name</t></is></c>
    <c r="B1" t="inlineStr"><is><t>email</t></is></c>
    <c r="C1" t="inlineStr"><is><t>role</t></is></c>
    <c r="D1" t="inlineStr"><is><t>identifier_number</t></is></c>
    <c r="E1" t="inlineStr"><is><t>class_grade</t></is></c>
    <c r="F1" t="inlineStr"><is><t>group_name</t></is></c>
    <c r="G1" t="inlineStr"><is><t>assigned_kit</t></is></c>
  </row>
  <row r="2">
    <c r="A2" t="inlineStr"><is><t>Anisa Inline</t></is></c>
    <c r="B2" t="str"><v>anisa.inline@smk.id</v></c>
    <c r="C2" t="inlineStr"><is><t>siswa</t></is></c>
    <c r="D2" t="inlineStr"><is><t>NISN-INLINE</t></is></c>
    <c r="E2" t="inlineStr"><is><t>XI ATPH 2</t></is></c>
    <c r="F2" t="inlineStr"><is><t>Kelompok D</t></is></c>
    <c r="G2" t="inlineStr"><is><t>TK-INLINE</t></is></c>
  </row>
</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/custom_sheet.xml', $sheetXml);
        $zip->close();

        try {
            $uploadedFile = new UploadedFile($tempXlsx, 'inline.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $previewResponse = $this->actingAs($admin)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $previewResponse->assertOk()
                ->assertJsonPath('total_rows', 1)
                ->assertJsonPath('valid_count', 1)
                ->assertJsonPath('rows.0.name', 'Anisa Inline')
                ->assertJsonPath('rows.0.email', 'anisa.inline@smk.id')
                ->assertJsonPath('rows.0.role', 'siswa');
        } finally {
            @unlink($tempXlsx);
        }
    }

    public function test_import_preview_rejects_file_exceeding_500_data_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $csv = "name,email,role\n";
        for ($i = 1; $i <= 501; $i++) {
            $csv .= "User {$i},user{$i}@smk.id,siswa\n";
        }

        $response = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('too_large.csv', $csv),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
        $this->assertStringContainsString('500', $response->json('errors.file.0'));
    }

    public function test_xlsx_with_corrupt_archive_fails_gracefully_with_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $corruptFile = UploadedFile::fake()->createWithContent('corrupt.xlsx', 'PKThisIsCorruptedNotAZipArchive');

        $response = $this->actingAs($admin)->postJson(route('users.import.preview'), [
            'file' => $corruptFile,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_xlsx_with_malformed_xml_fails_gracefully_with_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_malformed_').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->addXlsxPackageSkeleton($zip);
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet></workbook>');
        $zip->close();

        try {
            $uploadedFile = new UploadedFile($tempXlsx, 'malformed.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->actingAs($admin)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $response->assertUnprocessable()->assertJsonValidationErrors('file');
        } finally {
            @unlink($tempXlsx);
        }
    }

    public function test_xlsx_rejects_dtd_and_external_entity_declarations(): void
    {
        $admin = User::factory()->create(['role' => 'admin_sekolah']);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_entity_').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->addXlsxPackageSkeleton($zip);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?>'."\n".'<!DOCTYPE workbook [<!ENTITY external SYSTEM "file:///nonexistent-import-test">]><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId1"/></sheets></workbook>');
        $zip->close();

        try {
            $uploadedFile = new UploadedFile($tempXlsx, 'entity.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->actingAs($admin)->postJson(route('users.import.preview'), ['file' => $uploadedFile]);
            $response->assertUnprocessable()->assertJsonValidationErrors('file');
        } finally {
            @unlink($tempXlsx);
        }
    }

    public function test_unassigned_school_actor_fails_closed_on_import_preview_and_execute(): void
    {
        $unassignedAdmin = User::factory()->create(['role' => 'admin_sekolah']);
        // Force null school_id after model boot event
        $unassignedAdmin->forceFill(['school_id' => null])->save();
        $unassignedAdmin->refresh();
        $csv = "name,email,role\nSiswa,siswa@smk.id,siswa\n";

        $this->actingAs($unassignedAdmin)
            ->postJson(route('users.import.preview'), [
                'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            ])
            ->assertForbidden();

        $this->actingAs($unassignedAdmin)
            ->postJson(route('users.import.execute'), [
                'rows' => [[
                    'name' => 'Siswa Baru',
                    'email' => 'siswa.baru@smk.id',
                    'role' => 'siswa',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_teacher_and_student_are_forbidden_from_import_preview_and_execute(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $csv = "name,email,role\nSiswa,siswa@smk.id,siswa\n";

        // Teacher forbidden
        $this->actingAs($teacher)
            ->postJson(route('users.import.preview'), [
                'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->postJson(route('users.import.execute'), [
                'rows' => [['name' => 'Siswa Baru', 'email' => 'siswa.baru@smk.id', 'role' => 'siswa']],
            ])
            ->assertForbidden();

        // Student forbidden
        $this->actingAs($student)
            ->postJson(route('users.import.preview'), [
                'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson(route('users.import.execute'), [
                'rows' => [['name' => 'Siswa Baru', 'email' => 'siswa.baru@smk.id', 'role' => 'siswa']],
            ])
            ->assertForbidden();
    }

    private function addXlsxPackageSkeleton(\ZipArchive $zip): void
    {
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
    }
}
