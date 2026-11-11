<?php

namespace App\Console\Commands;

use App\Actions\License\DispatchSchoolLicenseRemindersAction;
use Illuminate\Console\Command;

class CheckSchoolLicenseRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitanic:check-license-reminders 
                            {--dry-run : Run check without writing notifications or sending emails}
                            {--days=14 : Days threshold before expiration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check expiring school LMS licenses and dispatch H-14 reminder emails to tenant school administrators.';

    /**
     * Execute the console command.
     */
    public function handle(DispatchSchoolLicenseRemindersAction $action): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Memeriksa lisensi LMS sekolah yang akan kedaluwarsa dalam {$days} hari...".($dryRun ? ' [DRY RUN]' : ''));

        $summary = $action->execute($days, $dryRun);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Target Tanggal Kedaluwarsa', $summary['target_date']],
                ['Ambang Hari (H-)', $summary['target_days']],
                ['Lisensi Ditemukan', $summary['found_licenses']],
                ['Pengingat Diantrikan (Queued)', $summary['queued']],
                ['Dilewati (Sudah Ada)', $summary['skipped_existing']],
                ['Dilewati (Tanpa Admin Sekolah)', $summary['skipped_no_admins']],
                ['Dilewati (Status Bukan Aktif)', $summary['skipped_not_active']],
                ['Mode Simulasi (Dry Run)', $summary['dry_run'] ? 'Ya' : 'Tidak'],
            ]
        );

        if (! empty($summary['details'])) {
            $rows = array_map(function ($item) {
                return [
                    $item['license_number'] ?? '—',
                    $item['school'] ?? '—',
                    $item['recipient'] ?? '—',
                    $item['status'] ?? '—',
                    $item['reason'] ?? 'OK',
                ];
            }, $summary['details']);

            $this->newLine();
            $this->info('Rincian Entri:');
            $this->table(['No. Lisensi', 'Sekolah', 'Penerima', 'Status', 'Keterangan'], $rows);
        }

        $this->info('Pemeriksaan pengingat lisensi selesai dengan sukses.');

        return Command::SUCCESS;
    }
}
