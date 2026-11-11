<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan Masa Berlaku Lisensi LMS Bitanic</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #0c3c26;
            padding: 28px 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.025em;
        }
        .header p {
            color: #a7f3d0;
            font-size: 13px;
            margin: 6px 0 0;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .intro {
            font-size: 14px;
            color: #475569;
            margin-bottom: 24px;
        }
        .card {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .card-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dcfce7;
            font-size: 14px;
        }
        .card-row:last-child {
            border-bottom: none;
        }
        .card-label {
            color: #166534;
            font-weight: 600;
        }
        .card-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .alert-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #92400e;
        }
        .instructions {
            font-size: 14px;
            color: #334155;
            margin-bottom: 24px;
        }
        .contact-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            font-size: 13px;
            color: #475569;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BITANIC SMART AGRICULTURE LMS</h1>
            <p>Sistem Pengingat Otomatis Lisensi Laboratorium Vokasi IoT</p>
        </div>
        <div class="content">
            <div class="greeting">Yth. Administrator Sekolah {{ $schoolName }},</div>
            <div class="intro">
                Melalui surat elektronik resmi ini, kami menginformasikan bahwa masa berlaku lisensi Learning Management System (LMS) Bitanic untuk institusi Anda akan berakhir dalam kurun waktu <strong>{{ $daysRemaining }} hari kalender</strong>.
            </div>

            <div class="card">
                <div class="card-row">
                    <span class="card-label">Nama Sekolah:</span>
                    <span class="card-value">{{ $schoolName }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Nomor Lisensi:</span>
                    <span class="card-value">{{ $license->license_number }}</span>
                </div>
                @if($term)
                <div class="card-row">
                    <span class="card-label">Periode Lisensi:</span>
                    <span class="card-value">Tahun ke-{{ $term->term_number }}</span>
                </div>
                @endif
                <div class="card-row">
                    <span class="card-label">Batas Akhir Berlaku:</span>
                    <span class="card-value">{{ $expirationDate }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Sisa Waktu:</span>
                    <span class="card-value" style="color: #b45309;">{{ $daysRemaining }} Hari</span>
                </div>
            </div>

            <div class="alert-box">
                <strong>Perhatian:</strong> Apabila masa berlaku lisensi berakhir tanpa perpanjangan, akses kegiatan pembelajaran digital LMS bagi guru dan siswa akan dibatasi sementara waktu. Namun demikian, seluruh data akademik, lembar kerja siswa, dan log telemetri perangkat Trainer Kit Anda tetap aman tersimpan di sistem.
            </div>

            <div class="instructions">
                <p><strong>Langkah Perpanjangan Lisensi:</strong></p>
                <p>Untuk memastikan kelangsungan kegiatan belajar mengajar dan asesmen praktikum vokasi pertanian, silakan berkoordinasi dengan Tim Kemitraan Bitanic Pusat untuk verifikasi administrasi dan otorisasi perpanjangan lisensi tahunan.</p>
            </div>

            @php
                $supportEmail = config('license.support_contact.email');
                $supportPhone = config('license.support_contact.phone');
                $supportUrl = config('license.support_contact.helpdesk_url') ?: config('app.url');
                $hasContactDetails = ! empty($supportEmail) || ! empty($supportPhone);
            @endphp

            <div class="contact-box">
                <strong>Kontak Bantuan & Kemitraan:</strong><br>
                @if($hasContactDetails)
                    @if($supportEmail)
                        Email: <a href="mailto:{{ $supportEmail }}" style="color: #047857; text-decoration: underline;">{{ $supportEmail }}</a><br>
                    @endif
                    @if($supportPhone)
                        WhatsApp / Helpdesk: {{ $supportPhone }}<br>
                    @endif
                    @if($supportUrl)
                        Portal Resmi: <a href="{{ $supportUrl }}" style="color: #047857; text-decoration: underline;">{{ $supportUrl }}</a>
                    @endif
                @else
                    <p style="margin: 4px 0 0 0; color: #475569;">
                        Silakan hubungi administrator sistem atau tim kemitraan resmi institusi Anda untuk pengurusan administrasi perpanjangan lisensi.
                    </p>
                    @if($supportUrl)
                        <div style="margin-top: 6px;">
                            Portal Resmi: <a href="{{ $supportUrl }}" style="color: #047857; text-decoration: underline;">{{ $supportUrl }}</a>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} PT Bitanic Agroteknologi Vokasi. Seluruh hak cipta dilindungi undang-undang.<br>
            Pemberitahuan ini diterbitkan secara otomatis oleh platform Bitanic IoT LMS.
        </div>
    </div>
</body>
</html>

