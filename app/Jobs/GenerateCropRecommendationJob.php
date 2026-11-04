<?php

namespace App\Jobs;

use App\Models\TelemetryReading;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateCropRecommendationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout limit in seconds (Strict SLA).
     */
    public int $timeout = 15;

    /**
     * Number of attempts.
     */
    public int $tries = 2;

    public User $user;
    public TelemetryReading $reading;
    public string $ticketId;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, TelemetryReading $reading, string $ticketId)
    {
        $this->user = $user;
        $this->reading = $reading;
        $this->ticketId = $ticketId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Update ticket status to in-progress
        $cacheKey = 'crop_recommendation_' . $this->ticketId;
        Cache::put($cacheKey, [
            'status' => 'processing',
            'ticket_id' => $this->ticketId,
            'started_at' => now()->toIso8601String(),
        ], now()->addHour());

        $n = (float) $this->reading->nitrogen;
        $p = (float) $this->reading->phosphorus;
        $k = (float) $this->reading->potassium;
        $ec = (float) $this->reading->ec;
        $ph = (float) $this->reading->ph;
        $temp = (float) $this->reading->temperature;
        $humid = (float) $this->reading->humidity;

        $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));

        $recommendationData = null;

        if (!empty($apiKey)) {
            try {
                $recommendationData = $this->callGeminiApi($apiKey, $n, $p, $k, $ec, $ph, $temp, $humid);
            } catch (\Throwable $e) {
                Log::warning("Gemini AI API call failed, falling back to deterministic agronomy engine: {$e->getMessage()}");
            }
        }

        // Graceful deterministic fallback
        if (!$recommendationData) {
            $recommendationData = $this->generateDeterministicAgronomyRecommendation($n, $p, $k, $ec, $ph, $temp, $humid);
        }

        // Simpan hasil final ke cache
        Cache::put($cacheKey, [
            'status' => 'completed',
            'ticket_id' => $this->ticketId,
            'soil_classification' => $recommendationData['soil_classification'],
            'recommendations' => $recommendationData['recommendations'],
            'completed_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        // Potong kuota pengguna setelah sukses
        app(SubscriptionService::class)->consumeAi($this->user);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateCropRecommendationJob failed for ticket {$this->ticketId}: {$exception->getMessage()}");
        Cache::put('crop_recommendation_' . $this->ticketId, [
            'status' => 'failed',
            'ticket_id' => $this->ticketId,
            'error' => 'Gagal memproses rekomendasi AI dalam batas waktu 15 detik. Silakan coba kembali.',
            'failed_at' => now()->toIso8601String(),
        ], now()->addHour());
    }

    protected function callGeminiApi(string $apiKey, float $n, float $p, float $k, float $ec, float $ph, float $temp, float $humid): ?array
    {
        $prompt = "Kamu adalah sistem pakar agronomi tanah agritech Bitanic.
Berikut kondisi telemetri tanah riil:
- pH: {$ph}
- Kelembapan: {$humid}%
- Nitrogen (N): {$n} mg/kg
- Fosfor (P): {$p} mg/kg
- Kalium (K): {$k} mg/kg
- EC: {$ec} uS/cm
- Suhu Tanah: {$temp} °C

Berikan respon HANYA dalam JSON murni tanpa markdown/penjelasan luar:
{
  \"soil_classification\": {
    \"kategori\": \"nama kategori tanah\",
    \"deskripsi\": \"deskripsi agronomi singkat 1-2 kalimat\"
  },
  \"recommendations\": [
    {
      \"nama\": \"nama tanaman\",
      \"kategori\": \"sayuran | buah | pangan | rempah\",
      \"alasan\": \"alasan kecocokan agronomi 1 kalimat ringkas\"
    }
  ]
}
Berikan tepat 5 rekomendasi tanaman.";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

        $response = Http::timeout(10)->post($endpoint, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                $cleaned = trim(preg_replace('/^```json\s*|\s*```$/i', '', trim($text)));
                $decoded = json_decode($cleaned, true);
                if (isset($decoded['soil_classification']) && isset($decoded['recommendations'])) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    protected function generateDeterministicAgronomyRecommendation(float $n, float $p, float $k, float $ec, float $ph, float $temp, float $humid): array
    {
        $phStatus = $ph < 6.0 ? 'Asam Ringan' : ($ph > 7.5 ? 'Alkalin Moderat' : 'Netral Optimal');
        $fertility = ($n + $p + $k) > 150 ? 'Tinggi' : (($n + $p + $k) > 75 ? 'Sedang' : 'Rendah (Butuh Pupuk)');

        $category = "Tanah {$phStatus} (Kesuburan {$fertility})";
        $desc = "pH {$ph} dan kelembapan {$humid}% dengan suhu {$temp}°C sangat kondusif untuk budidaya hortikultura lahan terbuka maupun greenhouse.";

        $recs = [];
        if ($ph >= 6.0 && $ph <= 7.0) {
            $recs[] = ['nama' => 'Cabai Rawit (Capsicum frutescens)', 'kategori' => 'sayuran', 'alasan' => 'pH netral dan suhu hangat memicu pembungaan optimal.'];
            $recs[] = ['nama' => 'Tomat Ceri (Solanum lycopersicum)', 'kategori' => 'buah', 'alasan' => 'Daya serap hara optimal pada kelembapan stabil di atas 60%.'];
            $recs[] = ['nama' => 'Bayam Hijau (Amaranthus)', 'kategori' => 'sayuran', 'alasan' => 'Siklus panen cepat dan adaptif terhadap ketersediaan nitrogen tanah.'];
            $recs[] = ['nama' => 'Terung Ungu (Solanum melongena)', 'kategori' => 'sayuran', 'alasan' => 'Toleran terhadap suhu tanah hingga 30°C dengan drainase baik.'];
            $recs[] = ['nama' => 'Jahe Emprit (Zingiber officinale)', 'kategori' => 'rempah', 'alasan' => 'Rimpang berkembang sehat pada kelembapan tanah yang seimbang.'];
        } elseif ($ph < 6.0) {
            $recs[] = ['nama' => 'Nanas Madu (Ananas comosus)', 'kategori' => 'buah', 'alasan' => 'Sangat menyukai tanah masam dengan tingkat keasaman pH 4.5-5.5.'];
            $recs[] = ['nama' => 'Ubi Jalar (Ipomoea batatas)', 'kategori' => 'pangan', 'alasan' => 'Kuat terhadap pH rendah dan toleran kekurangan hara mineral.'];
            $recs[] = ['nama' => 'Kencur (Kaempferia galanga)', 'kategori' => 'rempah', 'alasan' => 'Adaptif pada media masam dengan aerasi memadai.'];
            $recs[] = ['nama' => 'Kangkung Darat (Ipomoea aquatica)', 'kategori' => 'sayuran', 'alasan' => 'Tetap tumbuh subur dan hijau walau tanah sedikit masam.'];
            $recs[] = ['nama' => 'Kacang Tanah (Arachis hypogaea)', 'kategori' => 'pangan', 'alasan' => 'Membantu fiksasi nitrogen alami memperbaiki kesuburan lahan.'];
        } else {
            $recs[] = ['nama' => 'Asparagus (Asparagus officinalis)', 'kategori' => 'sayuran', 'alasan' => 'Toleran terhadap pH alkalin tinggi di atas 7.0.'];
            $recs[] = ['nama' => 'Bit Merah (Beta vulgaris)', 'kategori' => 'sayuran', 'alasan' => 'Menghasilkan umbi manis berkualitas pada tanah agak berkapur.'];
            $recs[] = ['nama' => 'Kubis (Brassica oleracea)', 'kategori' => 'sayuran', 'alasan' => 'Mengurangi risiko penyakit akar gada pada pH netral cenderung basa.'];
            $recs[] = ['nama' => 'Bawang Putih (Allium sativum)', 'kategori' => 'rempah', 'alasan' => 'Memerlukan tanah gembur dengan ketersediaan kalsium cukup.'];
            $recs[] = ['nama' => 'Melon (Cucumis melo)', 'kategori' => 'buah', 'alasan' => 'Aroma dan rasa manis lebih maksimal pada pH stabil 6.8-7.5.'];
        }

        return [
            'soil_classification' => [
                'kategori' => $category,
                'deskripsi' => $desc,
            ],
            'recommendations' => $recs,
        ];
    }
}
