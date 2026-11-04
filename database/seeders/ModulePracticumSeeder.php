<?php

namespace Database\Seeders;

use App\Models\ModulePracticum;
use Illuminate\Database\Seeder;

class ModulePracticumSeeder extends Seeder
{
    /**
     * Seed 12 Vocational Practicum Modules for SMK Pertanian 4.0.
     */
    public function run(): void
    {
        $modules = [
            [
                'meeting_number' => 1,
                'title' => 'Pengenalan Trainer Kit BITANIC & Arsitektur Pertanian Presisi',
                'subtitle' => 'Orientasi Komponen Smart Agriculture, Power Distribution, & Safety K3 Lab Agronomi',
                'duration_minutes' => 180,
                'badge' => 'Hardware & K3',
                'capaian_pembelajaran' => 'Peserta didik mampu mengidentifikasi komponen fisik BITANIC Precision Agriculture Trainer Kit, memahami topologi sensor-aktuator, dan menerapkan prosedur K3 keselamatan kerja laboratorium pertanian modern.',
                'tujuan_pembelajaran' => [
                    'Menyebutkan 7 blok fungsi utama BITANIC Trainer Kit (MCU, Power Supply 12V/5V, Terminal RS485, Array Sensor Probe Tanah, Driver Relai Aktuator, Layar HMI OLED, Modul WiFi/LoRa).',
                    'Mengidentifikasi spesifikasi tegangan kerja probe multi-parameter NPK/pH/EC.',
                    'Melakukan check-list keselamatan kerja K3 kelistrikan di dekat media basah/air.',
                ],
                'alat_bahan' => [
                    '1 Unit BITANIC Precision Agriculture Trainer Kit',
                    'Kabel Jumper AWG24 & Obeng Terminal Presisi',
                    'Multimeter Digital Auto-Range',
                    'Sample Tanah Media Tanam Standar Polybag',
                ],
                'theory_summary' => 'Pertanian Presisi (Precision Agriculture) memadukan agronomi, instrumentasi elektronika, dan Internet of Things (IoT) untuk menerapkan prinsip 4T: Tepat Jenis, Tepat Dosis, Tepat Waktu, dan Tepat Lokasi.',
                'wiring_pinout' => [
                    ['pin' => 'VCC_EXT', 'port' => 'Terminal Block 1', 'component' => 'Catu Daya 12V DC', 'functionDesc' => 'Sumber daya utama probe terisolasi'],
                    ['pin' => 'RS485_A / B', 'port' => 'Terminal Block 2', 'component' => 'Modul MAX485', 'functionDesc' => 'Jalur diferensial transmisi Modbus RTU'],
                    ['pin' => 'RLY_1 s.d 4', 'port' => 'Relay Board 4-CH', 'component' => 'Relay 10A 250VAC', 'functionDesc' => 'Kontrol pompa, valve, kipas, lampu'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Inspeksi Visual & Pengenalan Panel', 'instruction' => 'Buka koper trainer kit, periksa kelengkapan probe 7-in-1, adapter daya switching 12V 3A, dan terminal input-output.'],
                    ['stepNumber' => 2, 'title' => 'Pengukuran Tegangan Sumber', 'instruction' => 'Gunakan multimeter pada mode DC Volt untuk memvalidasi output rail 12V dan rail regulator 5V/3.3V.'],
                    ['stepNumber' => 3, 'title' => 'Power-On & Observasi Display OLED', 'instruction' => 'Nyalakan sakelar power. Amati booting BITANIC OS dan status inisialisasi modul Modbus.'],
                    ['stepNumber' => 4, 'title' => 'Uji Coba Indikator Aktuator', 'instruction' => 'Tekan tombol tes manual pada relai pompa dan solenoid untuk memastikan optoisolator berfungsi normal.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Keterampilan Identifikasi Hardware & Wiring K3',
                        'weight' => 40,
                        'criteria' => [
                            'score4' => 'Mampu mengidentifikasi seluruh komponen, menjelaskan fungsi spesifik, dan wiring rapi sesuai K3.',
                            'score3' => 'Mampu mengidentifikasi sebagian besar komponen dengan sedikit bimbingan.',
                            'score2' => 'Mengenal sebagian komponen namun ragu dalam menentukan jalur terminal.',
                            'score1' => 'Belum memahami fungsi komponen kit dan membahayakan alat.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Pemahaman Arsitektur Pertanian Presisi',
                        'weight' => 35,
                        'criteria' => [
                            'score4' => 'Mampu menjelaskan korelasi sensorik tanah, controller, aktuator fertigasi, dan platform LMS analitik.',
                            'score3' => 'Mampu menjelaskan alur kerja sistem IoT dari probe ke dashboard.',
                            'score2' => 'Menjelaskan alur IoT secara terpisah-pisah.',
                            'score1' => 'Belum mampu membedakan sensor, aktuator, dan controller.',
                        ],
                    ],
                    [
                        'id' => 'r3',
                        'name' => 'Kedisiplinan & Dokumentasi Kerja',
                        'weight' => 25,
                        'criteria' => [
                            'score4' => 'Laporan kerja lengkap, presisi mencatat tegangan relai, tepat waktu, meja bersih.',
                            'score3' => 'Laporan terisi baik, data tercatat, meja cukup rapi.',
                            'score2' => 'Laporan kurang lengkap pada beberapa data pengamatan.',
                            'score1' => 'Laporan tidak selesai dan melalaikan kebersihan bench.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Sebutkan 3 perbedaan fundamental antara pertanian konvensional dengan sistem Pertanian Presisi berbasis IoT!'],
                    ['id' => 'q2', 'question' => 'Berapakah tegangan kerja nominal yang dibutuhkan oleh probe sensor multi-parameter BITANIC dan mengapa memerlukan terminal 12V khusus?'],
                ],
            ],
            [
                'meeting_number' => 2,
                'title' => 'Eksplorasi LMS, Dashboard, & Setup Lingkungan IoT',
                'subtitle' => 'Navigasi Platform BITANIC LMS, Konfigurasi Jaringan Lab, & Pairing Trainer Kit',
                'duration_minutes' => 180,
                'badge' => 'Software & Network',
                'capaian_pembelajaran' => 'Peserta didik mampu mengoperasikan antarmuka LMS BITANIC, mengonfigurasi konektivitas Wi-Fi trainer kit, serta melakukan pairing perangkat ke stasiun praktikum masing-masing.',
                'tujuan_pembelajaran' => [
                    'Login ke LMS BITANIC menggunakan akun siswa.',
                    'Mengonfigurasi kredensial WiFi lab pada trainer kit.',
                    'Memverifikasi stream telemetry live di dashboard siswa.',
                ],
                'alat_bahan' => ['Laptop/Tablet Siswa', '1 Unit BITANIC Trainer Kit', 'Router Wi-Fi Lab Agritech'],
                'theory_summary' => 'LMS BITANIC mengintegrasikan modul pedagogik dengan stream data telemetri waktu-nyata, memungkinkan siswa belajar berbasis fenomena nyata langsung dari tanaman uji.',
                'wiring_pinout' => [
                    ['pin' => 'USB_PROG', 'port' => 'Micro USB Type-C', 'component' => 'CH340 Serial UART', 'functionDesc' => 'Komunikasi data serial 115200 bps'],
                    ['pin' => 'WIFI_ANT', 'port' => 'Antena SMA 2.4GHz', 'component' => 'ESP32-WROOM', 'functionDesc' => 'Koneksi TCP/IP ke broker LMS'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Autentikasi Siswa', 'instruction' => 'Buka browser, login ke LMS BITANIC menggunakan NISN dan password terdaftar.'],
                    ['stepNumber' => 2, 'title' => 'Penyambungan Jaringan IoT Kit', 'instruction' => 'Hubungkan kit ke hotspot lab agritech.'],
                    ['stepNumber' => 3, 'title' => 'Verifikasi Device ID & Telemetry', 'instruction' => 'Pastikan indikator kit di dashboard berubah hijau "ONLINE".'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Kecakapan Konfigurasi Jaringan & Pairing Device',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Koneksi berhasil dalam 1x percobaan, IP address terverifikasi, sinyal kuat.',
                            'score3' => 'Koneksi berhasil dengan sedikit kendala minor yang dapat diselesaikan mandiri.',
                            'score2' => 'Membutuhkan bantuan teknisi lab untuk menyelesaikan proses.',
                            'score1' => 'Gagal menghubungkan kit ke jaringan lab.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Penguasaan Antarmuka LMS BITANIC',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Mampu menavigasi seluruh menu, mengidentifikasi status relai, dan mengunduh data.',
                            'score3' => 'Mampu menggunakan fitur utama dashboard monitoring dan melihat tugas modul.',
                            'score2' => 'Masih bingung membedakan menu laporan dengan menu monitoring.',
                            'score1' => 'Belum mampu mengoperasikan dashboard LMS.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Tuliskan MAC Address dan IP Address lokal yang diperoleh trainer kit kelompokmu!'],
                    ['id' => 'q2', 'question' => 'Jelaskan perbedaan antara dashboard guru pembimbing dengan dashboard siswa praktikan!'],
                ],
            ],
            [
                'meeting_number' => 3,
                'title' => 'Monitoring Lengas Tanah (Moisture VWC) & Irigasi Presisi',
                'subtitle' => 'Sensor TDR/FDR, Kurva Karakteristik Lengas, & Otomasi Drip Irrigation',
                'duration_minutes' => 180,
                'badge' => 'Fisika Tanah',
                'capaian_pembelajaran' => 'Peserta didik mampu mengukur kadar air volumetrik tanah (VWC) pada berbagai media tanam dan memprogram durasi irigasi tetes otomatis berdasarkan titik layu.',
                'tujuan_pembelajaran' => [
                    'Menjelaskan konsep Kapasitas Lapang, Titik Layu Permanen, dan Air Tersedia.',
                    'Membaca data sensor lengas tanah secara real-time.',
                    'Menguji otomasi pompa irigasi tetes berbasis threshold kelembaban.',
                ],
                'alat_bahan' => ['Probe Sensor Lengas', 'Media Tanah Kering, Basah, dan Kapasitas Lapang', 'Pompa Drip 12V'],
                'theory_summary' => 'Kadar lengas tanah menentukan ketersediaan air bagi perakaran tanaman. Irigasi presisi bertujuan menjaga lengas tanah pada rentang Kapasitas Lapang (45-65%).',
                'wiring_pinout' => [
                    ['pin' => 'RELAY_PUMP', 'port' => 'Relay CH-1', 'component' => 'Pompa Celup Mini 12V', 'functionDesc' => 'Injeksi air irigasi tetes ke polybag'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Uji Tanah Kering Udara', 'instruction' => 'Tancapkan probe ke polybag tanah kering, catat nilai VWC pada dashboard.'],
                    ['stepNumber' => 2, 'title' => 'Simulasi Irigasi & Perekaman Kurva', 'instruction' => 'Nyalakan pompa irigasi tetes, amati kenaikan nilai lengas tanah hingga kapasitas lapang.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Keterampilan Pengukuran & Kalibrasi Lengas',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Pemasangan probe tegak lurus sempurna tanpa rongga udara, data stabil dan akurat.',
                            'score3' => 'Pemasangan probe baik, deviasi pembacaan kecil.',
                            'score2' => 'Probe dipasang miring sehingga nilai terdistorsi.',
                            'score1' => 'Salah menancapkan probe dan merusak jarum baja sensor.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Analisis Kebutuhan Air Tanaman (ETc)',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Mampu menghitung durasi penyiraman optimal berdasarkan defisit lengas.',
                            'score3' => 'Mampu menentukan saat penyiraman yang tepat.',
                            'score2' => 'Hanya mengandalkan intuisi tanpa merujuk data sensor.',
                            'score1' => 'Tidak memahami konsep kapasitas lapang.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Berapakah nilai persentase lengas tanah yang terukur saat media mencapai Kapasitas Lapang?'],
                    ['id' => 'q2', 'question' => 'Jika sensor menunjukkan kelembaban 22% pada tanaman cabai, tindakan irigasi apa yang harus segera dilakukan?'],
                ],
            ],
            [
                'meeting_number' => 4,
                'title' => 'Kalibrasi & Pembacaan Sensor pH Tanah (Tingkat Keasaman)',
                'subtitle' => 'Standarisasi Buffer pH 4.01 & 6.86, Offset Kalibrasi, & Pengapuran Dolomit',
                'duration_minutes' => 180,
                'badge' => 'Kimia Tanah',
                'capaian_pembelajaran' => 'Peserta didik mampu melakukan kalibrasi sensor pH tanah BITANIC menggunakan larutan standar buffer, mengidentifikasi pH masam vs netral, dan menghitung dosis kapur dolomit.',
                'tujuan_pembelajaran' => [
                    'Memahami prinsip elektroda potensiometri pada pengukuran ion hidrogen [H+].',
                    'Melakukan kalibrasi buffer 4.01 dan 6.86.',
                    'Menghitung dosis amelioran dolomit pada tanah masam.',
                ],
                'alat_bahan' => ['Probe pH Sensor', 'Larutan Buffer pH 4.01 & 6.86', 'Aquades', 'Sampel Tanah Masam'],
                'theory_summary' => 'pH tanah adalah derajat kemasaman tanah yang menentukan kelarutan unsur hara. Pada pH < 5.5, ion Al dan Fe meracuni akar dan mengikat fosfor.',
                'wiring_pinout' => [
                    ['pin' => 'MODBUS_PH', 'port' => 'Register 0x0003', 'component' => 'Signal Conditioning pH', 'functionDesc' => 'Konversi tegangan elektroda ke nilai pH'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pembilasan Elektroda', 'instruction' => 'Bilas elektroda pH dengan aquades, keringkan perlahan dengan tisu bebas serat.'],
                    ['stepNumber' => 2, 'title' => 'Pengujian Tanah & Dosis Dolomit', 'instruction' => 'Tancapkan probe ke sampel tanah masam, catat pH, hitung kebutuhan kapur dolomit.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Prosedur Kalibrasi Standar Buffer',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Pembilasan sangat teliti, tidak menggosok probe kaca, offset tepat.',
                            'score3' => 'Kalibrasi berjalan sesuai SOP, deviasi < 0.1 pH.',
                            'score2' => 'Lupa membilas elektroda sebelum berpindah larutan.',
                            'score1' => 'Merusak elektroda kaca sensor.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Rekomendasi Agronomi & Perhitungan Remediasi',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Perhitungan dosis dolomit tepat sesuai luas tanam dan target kenaikan pH.',
                            'score3' => 'Perhitungan dosis mendekati tepat dengan rumus baku.',
                            'score2' => 'Kurang tepat dalam konversi satuan.',
                            'score1' => 'Tidak dapat menghitung kebutuhan kapur.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Jika sampel tanah terukur pH 4.8, mengapa tanaman rentan mengalami defisiensi Fosfor meskipun sudah dipupuk?'],
                ],
            ],
            [
                'meeting_number' => 5,
                'title' => 'Electrical Conductivity (EC) Tanah & Salinitas Larutan Nutrisi',
                'subtitle' => 'Pengukuran Ion Terlarut, Konsentrasi Larutan AB Mix, & Risiko Keracunan Salinitas',
                'duration_minutes' => 180,
                'badge' => 'Nutrisi & Salinitas',
                'capaian_pembelajaran' => 'Peserta didik mampu mengukur Electrical Conductivity (EC) tanah dan larutan nutrisi fertigasi menggunakan BITANIC Trainer Kit serta mengendalikan konsentrasi larutan agar tidak terjadi plasmolisis.',
                'tujuan_pembelajaran' => [
                    'Menjelaskan korelasi antara konduktivitas listrik (EC) dengan konsentrasi hara pupuk.',
                    'Mengukur EC larutan AB Mix pada berbagai tingkat kepekatan.',
                    'Mendeteksi salinitas tanah berlebih dan memicu sistem pembilasan otomatis.',
                ],
                'alat_bahan' => ['Sensor EC BITANIC Kit', 'Pekatan Nutrisi AB Mix', 'Gelas Ukur', 'Aquades'],
                'theory_summary' => 'EC mencerminkan konsentrasi kation dan anion hara terlarut. EC terlalu rendah mengindikasikan tanaman kekurangan hara, sedangkan EC terlalu tinggi menyebabkan plasmolisis perakaran.',
                'wiring_pinout' => [
                    ['pin' => 'MODBUS_EC', 'port' => 'Register 0x0002', 'component' => 'Elektroda Grafit/Platinum EC', 'functionDesc' => 'Pengukuran konduktivitas listrik'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pengukuran Air Baku', 'instruction' => 'Celupkan sensor ke dalam air baku, catat baseline EC.'],
                    ['stepNumber' => 2, 'title' => 'Pengujian Larutan AB Mix', 'instruction' => 'Buat larutan bertingkat, amati kenaikan nilai EC pada dashboard.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Presisi Pembuatan Larutan & Penanganan Alat EC',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Pengenceran larutan sangat presisi, pembacaan stabil tanpa gelembung.',
                            'score3' => 'Pengenceran baik, pembacaan konsisten.',
                            'score2' => 'Terdapat gelembung udara pada elektroda.',
                            'score1' => 'Salah dalam perhitungan takaran nutrisi.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Analisis Hubungan EC & Osmosis Tanaman',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Menjelaskan mekanisme tekanan osmotik akar dan bahaya plasmolisis secara ilmiah.',
                            'score3' => 'Mampu menjelaskan hubungan EC dengan ketersediaan hara.',
                            'score2' => 'Menjelaskan sebatas makin banyak pupuk makin tinggi EC.',
                            'score1' => 'Tidak mengerti satuan dan makna Electrical Conductivity.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Berapa rentang nilai EC yang ideal untuk tanaman tomat pada fase pembentukan buah?'],
                ],
            ],
            [
                'meeting_number' => 6,
                'title' => 'Estimasi Cepat Unsur Hara Makro Tanah N-P-K Digital',
                'subtitle' => 'Analisis Spektroskopi/Impedansi N-P-K, Defisiensi Hara, & Rekomendasi Pemupukan',
                'duration_minutes' => 180,
                'badge' => 'Unsur Hara Makro',
                'capaian_pembelajaran' => 'Peserta didik mampu mengukur ketersediaan Nitrogen (N), Fosfor (P), dan Kalium (K) menggunakan probe tanah 7-in-1 dan menyusun rekomendasi pemupukan berimbang.',
                'tujuan_pembelajaran' => [
                    'Mengidentifikasi peran fisiologis N, P, dan K pada tanaman.',
                    'Menganalisis hasil pembacaan mg/kg pada dashboard.',
                    'Menghitung dosis pupuk Urea, SP-36, dan KCl.',
                ],
                'alat_bahan' => ['Probe Sensor N-P-K 7-in-1', 'Sampel Tanah Subur dan Tanah Marjinal', 'Kalkulator Dosis Pupuk'],
                'theory_summary' => 'Unsur makro primer (N, P, K) diperlukan dalam jumlah terbesar. Rasio N-P-K yang seimbang memastikan fase vegetatif dan generatif berjalan optimal.',
                'wiring_pinout' => [
                    ['pin' => 'MODBUS_NPK', 'port' => 'Register 0x0004-0x0006', 'component' => 'Array Sensor N-P-K', 'functionDesc' => 'Pengukuran simultan Nitrogen, Fosfor, Kalium'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pengukuran Multi-Parameter', 'instruction' => 'Tancapkan probe 7-in-1 ke sampel tanah, catat nilai N, P, K dalam satuan mg/kg.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Analisis Status Hara Makro Tanah',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Mampu mengevaluasi kecukupan N-P-K dan menyusun rekomendasi pupuk tunggal secara presisi.',
                            'score3' => 'Mampu membaca status hara makro dengan baik.',
                            'score2' => 'Kesulitan membedakan gejala defisiensi N dan K.',
                            'score1' => 'Salah menginterpretasikan data N-P-K.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Perhitungan Dosis Pupuk Presisi',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Perhitungan dosis pupuk Urea, SP-36, KCl akurat sesuai kebutuhan tanaman uji.',
                            'score3' => 'Perhitungan dosis pupuk mendekati benar.',
                            'score2' => 'Kesalahan dalam konversi hara murni ke bobot pupuk komersial.',
                            'score1' => 'Tidak mampu menghitung dosis pupuk.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Jika nilai Kalium terukur 45 mg/kg (rendah), apa gejala visual yang akan muncul pada daun tanaman?'],
                ],
            ],
            [
                'meeting_number' => 7,
                'title' => 'Pengendalian Iklim Mikro Greenhouse (Suhu, RH, & VPD)',
                'subtitle' => 'Sensor Lingkungan SHT31, Vapor Pressure Deficit (VPD), & Misting Fan Control',
                'duration_minutes' => 180,
                'badge' => 'Iklim Mikro',
                'capaian_pembelajaran' => 'Peserta didik mampu memantau suhu dan kelembaban udara greenhouse, menghitung defisit tekanan uap (VPD), serta mengoperasikan sistem misting pendingin evaporatif.',
                'tujuan_pembelajaran' => [
                    'Menghitung nilai Vapor Pressure Deficit (VPD) dari suhu dan kelembaban relatif.',
                    'Mengontrol kipas misting untuk menurunkan suhu rizosfer.',
                ],
                'alat_bahan' => ['Sensor Suhu/Kelembaban SHT31', 'Kipas Misting 12V', 'Modul Greenhouse Mini'],
                'theory_summary' => 'VPD menentukan laju transpirasi tanaman. Nilai VPD optimal berkisar 0.8 - 1.2 kPa.',
                'wiring_pinout' => [
                    ['pin' => 'RELAY_FAN', 'port' => 'Relay CH-3', 'component' => 'Kipas Misting', 'functionDesc' => 'Pengatur kelembaban mikro udara'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pengukuran Baseline Iklim', 'instruction' => 'Catat suhu dan RH greenhouse mini pada siang hari.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Pemahaman Dinamika Iklim Mikro & VPD',
                        'weight' => 100,
                        'criteria' => [
                            'score4' => 'Mampu mengkorelasikan suhu, RH, VPD, dan laju transpirasi stomata.',
                            'score3' => 'Mampu menghitung nilai VPD dengan rumus baku.',
                            'score2' => 'Mengetahui suhu dan kelembaban tanpa analisis VPD.',
                            'score1' => 'Tidak memahami konsep iklim mikro.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Mengapa nilai VPD yang terlalu tinggi (> 1.6 kPa) dapat menyebabkan tanaman layu meskipun tanah cukup air?'],
                ],
            ],
            [
                'meeting_number' => 8,
                'title' => 'Pemrograman Logika Aktuator & Otomatisasi Terbuka/Tertutup',
                'subtitle' => 'State Machine Sederhana, Deadband Hysteresis, & Interlock Keselamatan',
                'duration_minutes' => 180,
                'badge' => 'Otomasi Terapan',
                'capaian_pembelajaran' => 'Peserta didik mampu menyusun logika kendali otomasi fertigasi dan irigasi menggunakan hysteresis deadband agar aktuator tidak sering ON-OFF mendadak.',
                'tujuan_pembelajaran' => ['Menerapkan logika hysteresis ambang batas.', 'Mencegah kerusakan relai akibat siklus switching cepat.'],
                'alat_bahan' => ['BITANIC Trainer Kit', 'Modul Relai 4-Ch', 'Simulasi Sensor'],
                'theory_summary' => 'Hysteresis deadband mencegah relai bergetar (chattering) saat nilai sensor berada di garis batas ambang.',
                'wiring_pinout' => [
                    ['pin' => 'GPIO_INTERLOCK', 'port' => 'Digital In', 'component' => 'Sensor Float Switch', 'functionDesc' => 'Proteksi pompa dry-run'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Setup Batas Hysteresis', 'instruction' => 'Atur batas ON di 35% dan batas OFF di 55% pada menu konfigurasi.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Implementasi Logika Otomasi Presisi',
                        'weight' => 100,
                        'criteria' => [
                            'score4' => 'Logika hysteresis terpasang rapi, interlock keselamatan berfungsi sempurna.',
                            'score3' => 'Logika bekerja dengan baik.',
                            'score2' => 'Masih terjadi chattering relai pada kondisi transisi.',
                            'score1' => 'Gagal membuat logika kontrol.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Apa dampak mekanik dan elektrik jika pompa irigasi tidak diberi jeda hysteresis?'],
                ],
            ],
            [
                'meeting_number' => 9,
                'title' => 'Deteksi Dini Anomali Lahan & Troubleshooting Lapangan',
                'subtitle' => 'Analisis Outlier Sensor, Wire Break Detection, & Tindakan Koreksi Agronomi',
                'duration_minutes' => 180,
                'badge' => 'Troubleshooting',
                'capaian_pembelajaran' => 'Peserta didik mampu mendiagnosis anomali data sensor (kabel putus, probe kotor, baterai drop) dan mengeksekusi tindakan agronomi korektif.',
                'tujuan_pembelajaran' => ['Membedakan kegagalan hardware vs anomali riil tanah.', 'Merespons Anomaly Alerts di LMS.'],
                'alat_bahan' => ['Trainer Kit dengan Simulasi Kerusakan', 'Multimeter', 'Kapur Amelioran'],
                'theory_summary' => 'Sistem alert cerdas memisahkan anomali biologis (contoh: tanah asam mendadak) dari anomali perangkat (contoh: sensor lepas).',
                'wiring_pinout' => [
                    ['pin' => 'RS485_FAULT', 'port' => 'Differential Pair', 'component' => 'Terminator 120 Ohm', 'functionDesc' => 'Peredam refleksi sinyal bus'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Diagnosis Gangguan', 'instruction' => 'Amati kode error pada dashboard, lakukan pengujian koneksi fisik kabel.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Kecepatan & Ketepatan Troubleshooting',
                        'weight' => 100,
                        'criteria' => [
                            'score4' => 'Mendiagnosis akar masalah dalam < 10 menit dan menyelesaikan tindakan agronomi.',
                            'score3' => 'Mendiagnosis masalah dengan benar dalam waktu wajar.',
                            'score2' => 'Membutuhkan arahan guru untuk menemukan kabel yang longgar.',
                            'score1' => 'Salah mendiagnosis kerusakan alat.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Bagaimana cara membedakan apakah nilai pH 0.00 disebabkan oleh tanah yang luar biasa asam atau kabel probe yang putus?'],
                ],
            ],
            [
                'meeting_number' => 10,
                'title' => 'Integrasi Kecerdasan Buatan (AI Gemini) untuk Rekomendasi Tanaman',
                'subtitle' => 'Prompt Engineering Agronomi, Analisis Sampel Komposit, & Formulasi Solusi',
                'duration_minutes' => 180,
                'badge' => 'AI Agronomi',
                'capaian_pembelajaran' => 'Peserta didik mampu memanfaatkan AI Gemini 2.0 Flash pada BITANIC LMS untuk menganalisis data tanah komposit dan menghasilkan rekomendasi komoditas serta perbaikan nutrisi.',
                'tujuan_pembelajaran' => [
                    'Mengirim request analisis kesuburan tanah via slide-over drawer.',
                    'Mengevaluasi output kecocokan tanaman yang dihasilkan AI.',
                ],
                'alat_bahan' => ['Dashboard BITANIC LMS', 'Akses AI Gemini Worker', 'Sampel Data 7-in-1'],
                'theory_summary' => 'AI bertindak sebagai konsultan agronomi digital yang mengombinasikan data 7 parameter sensor dengan literatur agronomi untuk rekomendasi adaptif.',
                'wiring_pinout' => [
                    ['pin' => 'API_GATEWAY', 'port' => 'HTTPS REST', 'component' => 'Gemini 2.0 Flash', 'functionDesc' => 'Analisis LLM asinkronus queue'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Eksekusi Analisis AI', 'instruction' => 'Klik tombol Analisis AI pada dashboard, amati proses background queue hingga hasil muncul.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Evaluasi Kritis Output Rekomendasi AI',
                        'weight' => 100,
                        'criteria' => [
                            'score4' => 'Mampu memvalidasi rekomendasi AI dengan kondisi lapangan nyata dan mengoreksi jika ada bias.',
                            'score3' => 'Memahami hasil rekomendasi AI dengan baik.',
                            'score2' => 'Menerima rekomendasi AI tanpa verifikasi agronomi.',
                            'score1' => 'Tidak mampu menginterpretasikan laporan AI.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Apakah rekomendasi tanaman dari AI selalu 100% cocok ditanam di greenhouse sekolahmu? Jelaskan faktor pembatas lain seperti iklim dan pasar!'],
                ],
            ],
            [
                'meeting_number' => 11,
                'title' => 'Audit Konsumsi Energi & Telemetri Berbasis Tenaga Surya',
                'subtitle' => 'Solar Panel MPPT, Battery State of Charge (SoC), & Efisiensi Energi Greenhouse',
                'duration_minutes' => 180,
                'badge' => 'Green Tech',
                'capaian_pembelajaran' => 'Peserta didik mampu memantau konsumsi daya perangkat IoT dan aktuator, menghitung kapasitas baterai Li-Ion/LiFePO4, dan mengoptimalkan sistem pembangkit surya.',
                'tujuan_pembelajaran' => ['Menghitung konsumsi daya harian (Watt-Hour).', 'Menentukan kapasitas solar panel minimal.'],
                'alat_bahan' => ['Panel Surya 20Wp', 'Solar Charge Controller', 'Baterai 12V 7Ah'],
                'theory_summary' => 'Pertanian presisi berkelanjutan menuntut efisiensi energi. Penggunaan mode deep sleep pada mikrokontroler dapat memperpanjang masa pakai baterai.',
                'wiring_pinout' => [
                    ['pin' => 'PV_IN', 'port' => 'Terminal Solar', 'component' => 'MPPT Controller', 'functionDesc' => 'Pengisian daya surya ke baterai kit'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pencatatan Penurunan Baterai', 'instruction' => 'Catat persentase baterai selama 1 jam operasi penuh pompa dan sensor.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Analisis Efisiensi Energi Terbarukan',
                        'weight' => 100,
                        'criteria' => [
                            'score4' => 'Perhitungan neraca energi surya presisi, rekomendasi efisiensi daya tepat sasaran.',
                            'score3' => 'Mampu menghitung kebutuhan daya dengan baik.',
                            'score2' => 'Kesulitan dalam konversi satuan Ampere-Hour ke Watt-Hour.',
                            'score1' => 'Tidak memahami konsep daya listrik.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Berapa lama baterai 7Ah dapat menyalakan pompa 12V 1.5A secara terus menerus sebelum habis total?'],
                ],
            ],
            [
                'meeting_number' => 12,
                'title' => 'Proyek Terpadu Panen Presisi Berbasis Data (Capstone Project)',
                'subtitle' => 'Korelasi Akumulasi Nutrisi dengan Kualitas Hasil Panen, Brix, & Presentasi Akhir',
                'duration_minutes' => 240,
                'badge' => 'Capstone Project',
                'capaian_pembelajaran' => 'Peserta didik mampu menyajikan laporan komprehensif yang mengorelasikan data telemetri historis (lengas, pH, EC, NPK) dengan bobot panen dan kadar kemanisan buah (Brix).',
                'tujuan_pembelajaran' => [
                    'Mengekspor seluruh log data telemetri selama 1 musim tanam.',
                    'Menghubungkan data hara dengan kualitas panen.',
                    'Mempresentasikan hasil di hadapan guru penguji.',
                ],
                'alat_bahan' => ['Refraktometer Brix', 'Timbangan Digital Presisi', 'Hasil Panen Tanaman Uji', 'Laporan Proyek'],
                'theory_summary' => 'Puncak dari pertanian presisi adalah korelasi data telemetri hara mikro-makro terhadap produktivitas dan kualitas organoleptik hasil panen.',
                'wiring_pinout' => [
                    ['pin' => 'DATA_EXPORT', 'port' => 'CSV / XLSX', 'component' => 'Database PostgreSQL', 'functionDesc' => 'Ekspor riwayat data analitik'],
                ],
                'steps' => [
                    ['stepNumber' => 1, 'title' => 'Pengukuran Kualitas Hasil Panen', 'instruction' => 'Ukur kadar gula buah dengan refraktometer Brix dan timbang bobot basah tanaman.'],
                    ['stepNumber' => 2, 'title' => 'Kompilasi Laporan Akhir', 'instruction' => 'Submit laporan akhir ke LMS untuk dievaluasi oleh guru pengampu.'],
                ],
                'rubric' => [
                    [
                        'id' => 'r1',
                        'name' => 'Kualitas Analisis Data Agronomi Presisi',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Analisis korelasi data sensor dengan hasil panen sangat mendalam dan ilmiah.',
                            'score3' => 'Analisis data tersaji runtut dan jelas.',
                            'score2' => 'Data disajikan tanpa ada korelasi bermakna dengan hasil panen.',
                            'score1' => 'Laporan tidak memuat data telemetri riil.',
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Presentasi & Penguasaan Kompetensi Vokasi',
                        'weight' => 50,
                        'criteria' => [
                            'score4' => 'Presentasi meyakinkan, menguasai seluruh aspek IoT dan agronomi, menjawab pertanyaan kritis dengan lugas.',
                            'score3' => 'Presentasi baik dan memahami materi.',
                            'score2' => 'Ragu-ragu saat menjawab pertanyaan guru penguji.',
                            'score1' => 'Tidak mampu mempresentasikan hasil proyek.',
                        ],
                    ],
                ],
                'lks_questions' => [
                    ['id' => 'q1', 'question' => 'Berdasarkan data selama praktikum, pada fase pertumbuhan manakah ketersediaan Kalium paling berpengaruh terhadap kemanisan buah?'],
                ],
            ],
        ];

        foreach ($modules as $mod) {
            ModulePracticum::updateOrCreate(
                ['meeting_number' => $mod['meeting_number']],
                $mod
            );
        }
    }
}
