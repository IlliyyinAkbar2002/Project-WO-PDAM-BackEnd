<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan History Workorder</title>

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
    </style>
</head>

<body>

    {{-- HEADER --}}
    <div class="header">
        <table>
            <tr>
                <td width="15%">
                    <img src="{{ public_path('asset/logo-pdam.svg') }}" class="logo">
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
        <h3>LAPORAN HISTORY WORKORDER</h3>
    </div>

    {{-- INFORMASI --}}
    <div class="info">
        <table>
            <tr>
                <td width="50%">
                    <strong>Total Workorder :</strong>
                    {{ $workorders->count() }}
                </td>

                <td width="50%" align="right">
                    <strong>Tanggal Cetak :</strong>
                    {{ now()->format('d-m-Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- TABEL --}}
    <table class="history-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Pengaduan</th>
                <th width="25%">Nama Workorder</th>
                <th width="20%">Lokasi</th>
                <th width="10%">Prioritas</th>
                <th width="10%">Status</th>
                <th width="15%">Tanggal</th>
            </tr>
        </thead>
        <tbody>

            @forelse($workorders as $index => $item)
                <tr>
                    <td class="text-center">
                        {{ $index + 1 }}
                    </td>
                    <td>
                        {{ $item->kode_pengaduan }}
                    </td>
                    <td>
                        {{ $item->nama_workorder }}
                    </td>
                    <td>
                        {{ $item->lokasi }}
                    </td>
                    <td class="text-center">
                        {{ $item->prioritas }}
                    </td>
                    <td class="text-center">
                        {{ $item->status }}
                    </td>
                    <td class="text-center">
                        {{ optional($item->updated_at)->format('d-m-Y') }}
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="7" class="text-center">
                        Tidak terdapat data history workorder.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        <table class="signature-table">
            <tr>
                <td>
                    Mengetahui,<br>
                    <strong>Manager Senior</strong>
                </td>
                <td>
                    Mengetahui,<br>
                    <strong>Manager</strong>
                </td>
            </tr>
            <tr>
                <td class="signature-space"></td>
                <td class="signature-space"></td>

            </tr>
            <tr>
                <td>
                    <span class="signature-name">
                        {{ $managerSenior->nama ?? '........................................' }}
                    </span>
                </td>
                <td>
                    <span class="signature-name">
                        {{ $manager->nama ?? '........................................' }}
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="generated">
        Dokumen ini dibuat secara otomatis oleh Sistem Workorder PDAM Surya Sembada.
    </div>

</body>
</html>