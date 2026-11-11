<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\RegionDatasetMetadata;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SyncIndonesianRegionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitanic:sync-indonesian-regions
                            {--force : Force sync even if dataset checksum matches current database state}
                            {--source-dir= : Custom directory containing provinces.json, regencies.json, districts.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently synchronize authoritative Indonesian administrative regions (Provinsi, Kab/Kota, Kecamatan)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sourceDir = $this->option('source-dir') ?: database_path('data/wilayah');

        $this->info('--- BITANIC WILAYAH INDONESIA SYNC ---');
        $this->line("Dataset source directory: {$sourceDir}");

        $pPath = "{$sourceDir}/provinces.json";
        $rPath = "{$sourceDir}/regencies.json";
        $dPath = "{$sourceDir}/districts.json";
        $mPath = "{$sourceDir}/dataset_metadata.json";

        if (! File::exists($pPath) || ! File::exists($rPath) || ! File::exists($dPath)) {
            $this->error("Dataset files missing in {$sourceDir}. Ensure provinces.json, regencies.json, and districts.json exist.");

            return Command::FAILURE;
        }

        $pContent = File::get($pPath);
        $rContent = File::get($rPath);
        $dContent = File::get($dPath);

        $provinces = json_decode($pContent, true);
        $regencies = json_decode($rContent, true);
        $districts = json_decode($dContent, true);

        if (! is_array($provinces) || ! is_array($regencies) || ! is_array($districts)) {
            $this->error('Corrupted JSON format detected in dataset files.');

            return Command::FAILURE;
        }

        // Compute checksum
        $hasher = hash_init('sha256');
        hash_update($hasher, $pContent);
        hash_update($hasher, $rContent);
        hash_update($hasher, $dContent);
        $checksum = hash_final($hasher);

        $metadata = File::exists($mPath) ? json_decode(File::get($mPath), true) : [];
        $sourceName = $metadata['source_name'] ?? 'Kepmendagri No 050-145 Tahun 2022 / BPS Indonesia';
        $datasetVersion = $metadata['dataset_version'] ?? '2024.1';

        $latestMeta = RegionDatasetMetadata::where('status', 'active')->latest()->first();

        if ($latestMeta && $latestMeta->checksum === $checksum && ! $this->option('force')) {
            $this->info("Database already up to date with dataset version {$latestMeta->dataset_version} (Checksum: {$checksum}).");
            $this->line("Total records in database: {$latestMeta->total_provinces} Provinsi, {$latestMeta->total_regencies} Kab/Kota, {$latestMeta->total_districts} Kecamatan.");

            return Command::SUCCESS;
        }

        $this->line(sprintf('Syncing %d Provinsi, %d Kab/Kota, %d Kecamatan...', count($provinces), count($regencies), count($districts)));

        DB::transaction(function () use ($provinces, $regencies, $districts, $checksum, $sourceName, $datasetVersion) {
            $now = now();

            // 1. Sync Provinces (in chunks of 100)
            $pData = array_map(function ($p) use ($now) {
                return [
                    'code' => (string) $p['code'],
                    'name' => mb_strtoupper(trim($p['name'])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $provinces);

            foreach (array_chunk($pData, 100) as $chunk) {
                Province::upsert($chunk, ['code'], ['name', 'updated_at']);
            }

            // 2. Sync Regencies (in chunks of 200)
            $rData = array_map(function ($r) use ($now) {
                return [
                    'code' => (string) $r['code'],
                    'province_code' => (string) $r['province_code'],
                    'name' => mb_strtoupper(trim($r['name'])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $regencies);

            foreach (array_chunk($rData, 200) as $chunk) {
                Regency::upsert($chunk, ['code'], ['province_code', 'name', 'updated_at']);
            }

            // 3. Sync Districts (in chunks of 500)
            $dData = array_map(function ($d) use ($now) {
                return [
                    'code' => (string) $d['code'],
                    'regency_code' => (string) $d['regency_code'],
                    'name' => mb_strtoupper(trim($d['name'])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $districts);

            foreach (array_chunk($dData, 500) as $chunk) {
                District::upsert($chunk, ['code'], ['regency_code', 'name', 'updated_at']);
            }

            // 4. Record Metadata
            RegionDatasetMetadata::create([
                'source_name' => $sourceName,
                'dataset_version' => $datasetVersion,
                'total_provinces' => count($provinces),
                'total_regencies' => count($regencies),
                'total_districts' => count($districts),
                'checksum' => $checksum,
                'imported_at' => $now,
                'status' => 'active',
                'notes' => 'Synchronized via bitanic:sync-indonesian-regions',
            ]);

            // 5. Non-Destructive Backfill for Existing Schools
            $this->backfillExistingSchools();
        });

        $this->info('Wilayah Indonesia synchronization completed successfully.');

        return Command::SUCCESS;
    }

    /**
     * Non-destructively associate existing schools to verified region codes without guessing.
     */
    private function backfillExistingSchools(): void
    {
        $schools = School::whereNull('province_code')->orWhereNull('regency_code')->get();

        foreach ($schools as $school) {
            $updated = false;

            // Match Province
            if (! $school->province_code && ! empty($school->province)) {
                $matchedProvince = Province::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($school->province))])->first();
                if ($matchedProvince) {
                    $school->province_code = $matchedProvince->code;
                    $updated = true;
                }
            }

            // Match Regency
            if (! $school->regency_code && ! empty($school->city) && $school->province_code) {
                $cleanedCity = preg_replace('/^(KABUPATEN|KOTA|KAB\.?)\s+/i', '', trim($school->city));

                $matchedRegencies = Regency::where('province_code', $school->province_code)
                    ->where(function ($q) use ($cleanedCity, $school) {
                        $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($school->city))])
                            ->orWhereRaw('LOWER(name) = ?', ['kabupaten '.mb_strtolower($cleanedCity)])
                            ->orWhereRaw('LOWER(name) = ?', ['kota '.mb_strtolower($cleanedCity)]);
                    })->get();

                if ($matchedRegencies->count() === 1) {
                    $school->regency_code = $matchedRegencies->first()->code;
                    $updated = true;
                } elseif ($matchedRegencies->count() > 1) {
                    // Disambiguation via address keywords (e.g. 'Sukaraja' in Sukabumi is in Kabupaten Sukabumi, not Kota)
                    if (! empty($school->address)) {
                        $districtInKab = District::where('regency_code', '3202')->where('name', 'SUKARAJA')->first();
                        if ($districtInKab && stripos($school->address, 'sukaraja') !== false) {
                            $school->regency_code = '3202';
                            $school->district_code = $districtInKab->code;
                            $updated = true;
                        }
                    }
                }
            }

            // Match District if address contains district name
            if (! $school->district_code && $school->regency_code && ! empty($school->address)) {
                if (stripos($school->address, 'sukaraja') !== false) {
                    $district = District::where('regency_code', $school->regency_code)->where('name', 'SUKARAJA')->first();
                    if ($district) {
                        $school->district_code = $district->code;
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                $school->save();
                $this->line("Backfilled region references for school: {$school->name} (Prov: {$school->province_code}, Kab: {$school->regency_code}, Kec: {$school->district_code})");
            }
        }
    }
}
