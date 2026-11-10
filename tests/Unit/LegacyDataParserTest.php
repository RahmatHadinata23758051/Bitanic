<?php

namespace Tests\Unit;

use App\Services\LegacyDataParser;
use PHPUnit\Framework\TestCase;

class LegacyDataParserTest extends TestCase
{
    public function test_it_parses_simple_insert_statement(): void
    {
        $sql = 'INSERT INTO `users` VALUES (1,\'Super User\',\'admin@gmail.com\',NULL,\'$2y$12$hash\',\'superuser\');';
        $parser = new LegacyDataParser;
        $rows = $parser->parseInsertRows($sql, 'users');

        $this->assertCount(1, $rows);
        $this->assertEquals([1, 'Super User', 'admin@gmail.com', null, '$2y$12$hash', 'superuser'], $rows[0]);
    }

    public function test_it_parses_multiple_rows_with_escaped_quotes_and_json(): void
    {
        $sql = "INSERT INTO `test` VALUES (1,'Nama 1','[\"1\"]',100),(2,'Nama 2','[{\"a\": 2}]',NULL);";
        $parser = new LegacyDataParser;
        $rows = $parser->parseInsertRows($sql, 'test');

        $this->assertCount(2, $rows);
        $this->assertEquals(1, $rows[0][0]);
        $this->assertEquals('Nama 1', $rows[0][1]);
        $this->assertEquals('["1"]', $rows[0][2]);
        $this->assertEquals(100, $rows[0][3]);

        $this->assertEquals(2, $rows[1][0]);
        $this->assertEquals('Nama 2', $rows[1][1]);
        $this->assertEquals('[{"a": 2}]', $rows[1][2]);
        $this->assertNull($rows[1][3]);
    }

    public function test_it_extracts_users_from_actual_dump_file(): void
    {
        $dumpPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'dump_upiedge.sql';
        if (! file_exists($dumpPath)) {
            $this->markTestSkipped('dump_upiedge.sql not found at expected location: '.$dumpPath);
        }

        $sql = file_get_contents($dumpPath);
        $parser = new LegacyDataParser;
        $users = $parser->parseInsertRows($sql, 'users');

        $this->assertCount(4, $users);
        $this->assertEquals('Super User', $users[0][1]);
        $this->assertEquals('superuser@gmail.com', $users[0][2]);
        $this->assertEquals('superuser', $users[0][5]);

        $this->assertEquals('Muhammad Fahri', $users[1][1]);
        $this->assertEquals('mahasiswa', $users[1][5]);

        $this->assertEquals('Dosen 1', $users[3][1]);
        $this->assertEquals('dosen', $users[3][5]);
    }

    public function test_it_extracts_sensor_readings_from_actual_dump_file(): void
    {
        $dumpPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'dump_upiedge.sql';
        if (! file_exists($dumpPath)) {
            $this->markTestSkipped('dump_upiedge.sql not found at expected location: '.$dumpPath);
        }

        $sql = file_get_contents($dumpPath);
        $parser = new LegacyDataParser;
        $sensors = $parser->parseInsertRows($sql, 'filtered_fix_stations');

        $this->assertGreaterThan(500, count($sensors));
        $this->assertEquals('BTLP-UPI', $sensors[0][1]);
        $firstSample = json_decode($sensors[0][2], true);
        $this->assertIsArray($firstSample);
        $this->assertArrayHasKey('Nitrogen', $firstSample);
        $this->assertArrayHasKey('Temperature', $firstSample);
    }
}
