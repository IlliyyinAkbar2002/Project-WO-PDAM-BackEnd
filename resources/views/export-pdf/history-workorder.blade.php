<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Penyelesaian Workorder</title>
    <style>
        @page {
            margin: 28px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #2c3e50;
        }
        .header {
            width: 100%;
            border-bottom: 3px solid #0F6CBD;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .logo {
            width: 85px;
        }
        .company {
            text-align: center;
        }
        .company h2 {
            margin: 0;
            color: #0F6CBD;
            font-size: 22px;
        }
        .company h3 {
            margin: 4px 0;
            color: #1F2937;
            font-size: 18px;
        }
        .company p {
            margin: 0;
            font-size: 11px;
        }
        .title {
            text-align: center;
            margin: 20px 0 10px;
        }
        .title h3 {
            margin: 0;
            color: #0F6CBD;
            font-size: 18px;
        }
        .info {
            margin-bottom: 15px;
            font-size: 11px;
        }
        .info table {
            width: 100%;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .history-table th {
            background: #0F6CBD;
            color: white;
            border: 1px solid #0F6CBD;
            padding: 8px;
            text-align: center;
        }
        .history-table td {
            border: 1px solid #C7D2E0;
            padding: 7px;
            vertical-align: top;
        }
        .history-table tbody tr:nth-child(even) {
            background: #F7FAFC;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 60px;
            width: 100%;
        }
        .signature-table {
            width: 100%;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .signature-space {
            height: 80px;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }
        .generated {
            margin-top: 15px;
            font-size: 10px;
            color: #666;
            text-align: right;
        }
        .section-title {
            margin-top: 18px;
            margin-bottom: 8px;
            background: #0F6CBD;
            color: white;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            border: 1px solid #d7e2f0;
            padding: 7px;
            vertical-align: top;
        }
        .label {
            width: 180px;
            background: #EDF5FD;
            font-weight: bold;
        }
        .timeline-table th {
            background: #0F6CBD;
            color: #fff;
            border: 1px solid #0F6CBD;
            padding: 6px;
        }
        .timeline-table td {
            border: 1px solid #d6e1ef;
            padding: 6px;
        }
        .photo-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .photo-grid td {
            width: 33%;
            text-align: center;
            padding: 8px;
        }
        .photo {
            width: 170px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #ccc;
        }
        .photo-grid td {
            width: 33.33%;
            text-align: center;
            padding: 6px;
            vertical-align: top;
        }
    </style>
</head>

<body>
    {{-- HEADER --}}
    <div class="header">
        <table>
            <tr>
                <td width="15%">
                    <img src="{{ public_path('asset/logo-pdam.png') }}" class="logo">
                </td>
                <td class="company">
                    <h2>PERUMDA AIR MINUM SURYA SEMBADA</h2>
                    <h3>KOTA SURABAYA</h3>
                    <p>Jl. Prof. Dr. Moestopo No.2 Surabaya</p>
                    <p>Telp. (031) 5025411 | https://www.pdam-sby.go.id</p>
                </td>
            </tr>
        </table>
    </div>

    {{-- JUDUL --}}
    <div class="title">
        <h3>LAPORAN PENYELESAIAN WORKORDER</h3>
    </div>

    {{-- INFORMASI LAPORAN --}}
    <table class="info-table">
        <tr>
            <td class="label">
                Nomor Laporan
            </td>
            <td>
                {{ $workorder->laporanWorkorder->nomor_laporan }}
            </td>
            <td class="label">
                Tanggal Terbit
            </td>
            <td>
                {{ optional($workorder->laporanWorkorder->tanggal_terbit)->format('d F Y') }}
            </td>
        </tr>
        <tr>
            <td class="label">
                Prioritas
            </td>
            <td>
                {{ $workorder->prioritas }}
            </td>
            <td class="label">
                Status
            </td>
            <td>
                {{ $workorder->status }}
            </td>
        </tr>
    </table>

    {{-- INFORMASI WORKORDER --}}
    <div class="section-title">
        INFORMASI WORKORDER
    </div>
    <table class="info-table">
        <tr>
            <td class="label">
                Kode Pengaduan
            </td>
            <td>
                {{ $workorder->kode_pengaduan }}
            </td>
            <td class="label">
                Tanggal Pengaduan
            </td>
            <td>
                {{ optional($workorder->pengaduan->tanggal_pengaduan)->format('d-m-Y') }}
            </td>
        </tr>
        <tr>
            <td class="label">
                Nama Workorder
            </td>
            <td>
                {{ $workorder->nama_workorder }}
            </td>
            <td class="label">
                Jenis Workorder
            </td>
            <td>
                {{ $workorder->jenisWorkorder->nama }}
            </td>
        </tr>
        <tr>
            <td class="label">
                Departemen
            </td>
            <td>
                {{ $workorder->departemen->nama }}
            </td>
            <td class="label">
                Lokasi
            </td>
            <td colspan="3">
                {{ $workorder->lokasi }}
            </td>
        </tr>
        <tr>
            <td class="label">
                Deskripsi
            </td>
            <td colspan="3">
                {{ $workorder->deskripsi ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- INFORMASI PENUGASAN --}}
    <div class="section-title">
        INFORMASI PENUGASAN
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Supervisor</td>
            <td>
                {{ optional($workorder->workorderAssignment->spv)->nama ?? '-' }}
            </td>
            <td class="label">PIC</td>
            <td>
                {{ optional(optional($workorder->workorderAssignment->picMember)->pegawai)->nama ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Tanggal Mulai
            </td>
            <td>
                {{ optional($workorder->workorderAssignment->tanggal_mulai)->format('d-m-Y H:i') }}
            </td>
            <td class="label">
                Tanggal Selesai
            </td>
            <td>
                {{ optional($workorder->workorderAssignment->tanggal_selesai)->format('d-m-Y H:i') ?? '-' }}
            </td>
            <td class="label">
                Estimasi Selesai
            </td>
            <td>
                {{ optional($workorder->workorderAssignment->estimasi_selesai)->format('d-m-Y H:i') }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Lokasi Assignment
            </td>
            <td>
                {{ optional($workorder->workorderAssignment->location)->nama ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- TIM PELAKSANA --}}
    <div class="section-title">
        TIM PELAKSANA
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th width="8%">No</th>
                <th>Nama</th>
                <th width="20%">NIP</th>
                <th width="15%">Peran</th>
                <th width="15%">PIC</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($workorder->workorderAssignment->members as $index => $member)
                <tr>
                    <td align="center">
                        {{ $index + 1 }}
                    </td>

                    <td>
                        {{ optional($member->pegawai)->nama }}
                    </td>

                    <td align="center">
                        {{ optional($member->pegawai)->nip }}
                    </td>

                    <td align="center">
                        {{ ucfirst($member->peran) }}
                    </td>

                    <td align="center">
                        {{ $member->is_pic ? 'Ya' : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- RINGKASAN PEKERJAAN --}}
    <div class="section-title">
        RINGKASAN PEKERJAAN
    </div>

    <table class="info-table">
        <tr>
            <td class="label">
                Ringkasan Penyelesaian
            </td>

            <td>
                {{ $workorder->laporanWorkorder->ringkasan_pekerjaan }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Catatan Supervisor
            </td>

            <td>
                {{ $workorder->laporanWorkorder->catatan_spv ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- HASIL INSPEKSI --}}
    <div class="section-title">
        HASIL INSPEKSI
    </div>

    @php
        $snapshot = $workorder->laporanWorkorder->hasil_akhir_snapshot;
    @endphp

    <table class="info-table">
        <tr>
            <td class="label">
                Jenis Pipa
            </td>
            <td>
                {{ $snapshot['jenis_pipa'] ?? '-' }}
            </td>

            <td class="label">
                Diameter
            </td>
            <td>
                {{ $snapshot['diameter_pipa'] ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Panjang
            </td>
            <td>
                {{ $snapshot['panjang_pipa'] ?? '-' }} Meter
            </td>

            <td class="label">
                Kerusakan
            </td>

            <td>
                {{ $snapshot['tingkat_kerusakan'] ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Hasil Inspeksi
            </td>

            <td colspan="3">
                {{ $snapshot['hasil_inspeksi'] ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Tindakan Perbaikan
            </td>

            <td colspan="3">
                {{ $snapshot['tindakan_perbaikan'] ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- TANDA TANGAN --}}
    <div class="footer">
        <table class="signature-table">
            <tr>
                <td>
                    Surabaya,
                    {{ optional($workorder->laporanWorkorder->tanggal_terbit)->format('d F Y') }}
                </td>
                <td></td>
            </tr>

            <tr>
                <td>
                    Mengetahui,
                    <br>
                    <strong>Manager Senior</strong>
                </td>

                <td>
                    Mengetahui,
                    <br>
                    <strong>Manager</strong>
                </td>
            </tr>

            <tr>
                <td class="signature-space">
                </td>

                <td class="signature-space">
                </td>
            </tr>

            <tr>
                <td>
                    <span class="signature-name">
                        {{ $managerSenior->nama ?? '-' }}
                    </span>
                </td>

                <td>
                    <span class="signature-name">
                        {{ $manager->nama ?? '-' }}
                    </span>
                </td>
            </tr>
        </table>
    </div>
