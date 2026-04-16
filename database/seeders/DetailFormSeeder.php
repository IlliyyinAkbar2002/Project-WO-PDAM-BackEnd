<?php

namespace Database\Seeders;

use App\Models\DetailForm;
use App\Models\JenisWorkorder;
use Illuminate\Database\Seeder;

class DetailFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ambil semua jenis workorder
        $jenisWorkorders = JenisWorkorder::all();

        foreach ($jenisWorkorders as $jenisWorkorder) {
            $this->createDetailFormsForJenis($jenisWorkorder);
        }

        $this->command->info('DetailForm berhasil di-seed untuk semua jenis workorder!');
    }

    /**
     * Create detail forms for a specific jenis workorder
     */
    private function createDetailFormsForJenis(JenisWorkorder $jenisWorkorder): void
    {
        // Definisikan form fields untuk setiap jenis workorder
        $formDefinitions = $this->getFormDefinitions();

        $forms = $formDefinitions[$jenisWorkorder->nama] ?? $formDefinitions['default'];

        foreach ($forms as $order => $form) {
            $detailForm = DetailForm::create([
                'jenis_workorder_id' => $jenisWorkorder->id,
                'nama_field' => $form['nama_field'],
                'tipe_field' => $form['tipe_field'],
                'tipe_data' => $form['tipe_data'] ?? null,
                'unit_satuan' => $form['unit_satuan'] ?? null,
                'sifat' => $form['sifat'] ?? 'required',
                'min' => $form['min'] ?? null,
                'max' => $form['max'] ?? null,
                'parent' => $form['parent'] ?? 0,
                'keterangan' => $form['keterangan'] ?? null,
                'hint_text' => $form['hint_text'] ?? '',
                'order' => $order,
            ]);

        }
    }

    /**
     * Get form field definitions for each jenis workorder
     * 
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getFormDefinitions(): array
    {
        return [
            // ===== Form untuk Kalibrasi Meteran =====
            'Kalibrasi Meteran' => [
                [
                    'nama_field' => 'Hasil Kalibrasi',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih hasil kalibrasi',
                    'options' => ['Sesuai Standar', 'Tidak Sesuai', 'Perlu Penyesuaian'],
                ],
                [
                    'nama_field' => 'Akurasi Meteran',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => '%',
                    'sifat' => 'required',
                    'min' => 0,
                    'max' => 100,
                    'hint_text' => 'Masukkan persentase akurasi (0-100)',
                ],
                [
                    'nama_field' => 'Catatan Teknisi',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Catatan tambahan dari teknisi',
                ],
            ],

            // ===== Form untuk Perbaikan Pipa =====
            'Perbaikan Pipa' => [
                [
                    'nama_field' => 'Jenis Kerusakan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih jenis kerusakan pipa',
                    'options' => ['Bocor', 'Pecah', 'Korosi', 'Tersumbat', 'Retak'],
                ],
                [
                    'nama_field' => 'Diameter Pipa',
                    'tipe_field' => 'number',
                    'tipe_data' => 'integer',
                    'unit_satuan' => 'mm',
                    'sifat' => 'required',
                    'min' => 10,
                    'max' => 1000,
                    'hint_text' => 'Diameter pipa dalam mm',
                ],
                [
                    'nama_field' => 'Panjang Perbaikan',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'meter',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Panjang area yang diperbaiki',
                ],
                [
                    'nama_field' => 'Material Pengganti',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Material yang digunakan',
                    'options' => ['PVC', 'HDPE', 'Galvanis', 'Stainless Steel', 'Tembaga'],
                ],
                [
                    'nama_field' => 'Catatan Perbaikan',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Catatan tambahan',
                ],
            ],

            // ===== Form untuk Pemasangan Meteran =====
            'Pemasangan Meteran' => [
                [
                    'nama_field' => 'Nomor Seri Meteran',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Masukkan nomor seri meteran baru',
                ],
                [
                    'nama_field' => 'Merk Meteran',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih merk meteran',
                    'options' => ['Itron', 'Sensus', 'Elster', 'Zenner', 'Lainnya'],
                ],
                [
                    'nama_field' => 'Angka Awal Meteran',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'm³',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Stand awal meteran',
                ],
                [
                    'nama_field' => 'Lokasi Pemasangan',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Deskripsi lokasi pemasangan',
                ],
            ],

            // ===== Form untuk Inspeksi Jaringan =====
            'Inspeksi Jaringan' => [
                [
                    'nama_field' => 'Kondisi Jaringan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih kondisi jaringan',
                    'options' => ['Baik', 'Cukup Baik', 'Perlu Perbaikan', 'Kritis'],
                ],
                [
                    'nama_field' => 'Panjang Inspeksi',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'meter',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Panjang jaringan yang diinspeksi',
                ],
                [
                    'nama_field' => 'Temuan Masalah',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Deskripsi temuan masalah jika ada',
                ],
                [
                    'nama_field' => 'Rekomendasi',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Rekomendasi tindak lanjut',
                ],
            ],

            // ===== Form untuk Penggantian Meteran =====
            'Penggantian Meteran' => [
                [
                    'nama_field' => 'Nomor Seri Lama',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor seri meteran lama',
                ],
                [
                    'nama_field' => 'Angka Akhir Meteran Lama',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'm³',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Stand akhir meteran lama',
                ],
                [
                    'nama_field' => 'Nomor Seri Baru',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor seri meteran baru',
                ],
                [
                    'nama_field' => 'Angka Awal Meteran Baru',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'm³',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Stand awal meteran baru',
                ],
                [
                    'nama_field' => 'Alasan Penggantian',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih alasan penggantian',
                    'options' => ['Rusak', 'Tidak Akurat', 'Usia Pakai Habis', 'Upgrade', 'Permintaan Pelanggan'],
                ],
            ],

            // ===== Form untuk Pembersihan Saluran =====
            'Pembersihan Saluran' => [
                [
                    'nama_field' => 'Jenis Saluran',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih jenis saluran',
                    'options' => ['Pipa Distribusi', 'Pipa Transmisi', 'Pipa Service', 'Saluran Drainase'],
                ],
                [
                    'nama_field' => 'Panjang Pembersihan',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'meter',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Panjang saluran yang dibersihkan',
                ],
                [
                    'nama_field' => 'Metode Pembersihan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Metode yang digunakan',
                    'options' => ['Flushing', 'Jetting', 'Manual', 'Pigging'],
                ],
                [
                    'nama_field' => 'Hasil Pembersihan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Hasil pembersihan',
                    'options' => ['Bersih Total', 'Sebagian Bersih', 'Perlu Pembersihan Ulang'],
                ],
            ],

            // ===== Form untuk Pemeliharaan Pompa =====
            'Pemeliharaan Pompa' => [
                [
                    'nama_field' => 'Nomor Unit Pompa',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor identifikasi unit pompa',
                ],
                [
                    'nama_field' => 'Jenis Pemeliharaan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih jenis pemeliharaan',
                    'options' => ['Preventif', 'Korektif', 'Prediktif', 'Overhaul'],
                ],
                [
                    'nama_field' => 'Kondisi Pompa',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Kondisi pompa setelah pemeliharaan',
                    'options' => ['Normal', 'Perlu Monitoring', 'Perlu Perbaikan Lanjut', 'Tidak Layak Operasi'],
                ],
                [
                    'nama_field' => 'Debit Pompa',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'liter/detik',
                    'sifat' => 'optional',
                    'min' => 0,
                    'hint_text' => 'Debit pompa setelah pemeliharaan',
                ],
                [
                    'nama_field' => 'Catatan Pemeliharaan',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Catatan detail pemeliharaan',
                ],
            ],

            // ===== Form untuk Pengaduan Pelanggan =====
            'Pengaduan Pelanggan' => [
                [
                    'nama_field' => 'Nomor Pelanggan',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor ID pelanggan',
                ],
                [
                    'nama_field' => 'Jenis Pengaduan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Kategori pengaduan',
                    'options' => ['Air Tidak Mengalir', 'Air Keruh', 'Kebocoran', 'Tagihan', 'Tekanan Rendah', 'Lainnya'],
                ],
                [
                    'nama_field' => 'Status Penyelesaian',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Status penyelesaian pengaduan',
                    'options' => ['Terselesaikan', 'Perlu Tindak Lanjut', 'Dialihkan ke Unit Lain'],
                ],
                [
                    'nama_field' => 'Keterangan Penyelesaian',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Deskripsi penyelesaian',
                ],
            ],

            // ===== Form untuk Penanganan Kebocoran =====
            'Penanganan Kebocoran' => [
                [
                    'nama_field' => 'Lokasi Kebocoran',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Deskripsi lokasi kebocoran',
                ],
                [
                    'nama_field' => 'Tingkat Kebocoran',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Tingkat keparahan',
                    'options' => ['Ringan', 'Sedang', 'Berat', 'Kritis'],
                ],
                [
                    'nama_field' => 'Estimasi Volume Kehilangan',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'm³/hari',
                    'sifat' => 'optional',
                    'min' => 0,
                    'hint_text' => 'Estimasi kehilangan air per hari',
                ],
                [
                    'nama_field' => 'Metode Perbaikan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Metode yang digunakan',
                    'options' => ['Clamp', 'Penggantian Pipa', 'Pengelasan', 'Patching', 'Lainnya'],
                ],
                [
                    'nama_field' => 'Status Perbaikan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Status akhir perbaikan',
                    'options' => ['Selesai', 'Perlu Monitoring', 'Perlu Perbaikan Lanjut'],
                ],
            ],

            // ===== Form untuk Instalasi Baru =====
            'Instalasi Baru' => [
                [
                    'nama_field' => 'Nomor Pelanggan Baru',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor pelanggan yang didaftarkan',
                ],
                [
                    'nama_field' => 'Nomor Seri Meteran',
                    'tipe_field' => 'text',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Nomor seri meteran yang dipasang',
                ],
                [
                    'nama_field' => 'Diameter Pipa Service',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Diameter pipa service',
                    'options' => ['1/2 inch', '3/4 inch', '1 inch', '1 1/4 inch', '1 1/2 inch'],
                ],
                [
                    'nama_field' => 'Panjang Pipa Terpasang',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'meter',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Panjang pipa yang dipasang',
                ],
                [
                    'nama_field' => 'Angka Awal Meteran',
                    'tipe_field' => 'number',
                    'tipe_data' => 'decimal',
                    'unit_satuan' => 'm³',
                    'sifat' => 'required',
                    'min' => 0,
                    'hint_text' => 'Stand awal meteran',
                ],
            ],

            // ===== Default form untuk jenis workorder lainnya =====
            'default' => [
                [
                    'nama_field' => 'Hasil Pengerjaan',
                    'tipe_field' => 'select',
                    'tipe_data' => 'string',
                    'sifat' => 'required',
                    'hint_text' => 'Pilih status hasil pengerjaan',
                    'options' => ['Berhasil', 'Sebagian Berhasil', 'Gagal', 'Perlu Tindak Lanjut'],
                ],
                [
                    'nama_field' => 'Catatan',
                    'tipe_field' => 'textarea',
                    'tipe_data' => 'string',
                    'sifat' => 'optional',
                    'hint_text' => 'Catatan tambahan',
                ],
            ],
        ];
    }
}

