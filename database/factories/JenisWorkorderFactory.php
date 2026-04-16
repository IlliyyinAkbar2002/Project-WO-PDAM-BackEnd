<?php

namespace Database\Factories;

use App\Models\DetailForm;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisWorkorderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $names = [
            "Perbaikan Pipa",
            "Pemasangan Meteran",
            "Inspeksi Jaringan",
            "Penggantian Meteran",
            "Pembersihan Saluran",
            "Pemeliharaan Pompa",
            "Pengaduan Pelanggan",
            "Penanganan Kebocoran",
            "Instalasi Baru",
            "Kalibrasi Meteran",
        ];
        $nama = array_shift($names);

        return [
            'nama' => $nama,
        ];
    }

    /**
     * Configure the model factory.
     * Auto-create minimal detail_form setelah JenisWorkorder dibuat
     * untuk memastikan data valid (tidak kosong).
     */
    public function configure()
    {
        return $this->afterCreating(function ($jenisWorkorder) {
            // Create default detail_form dengan dropdown "Hasil Pengerjaan"
            $detailForm = DetailForm::create([
                'jenis_workorder_id' => $jenisWorkorder->id,
                'nama_field' => 'Hasil Pengerjaan',
                'tipe_field' => 'dropdown',
                'tipe_data' => 'string',
                'sifat' => 'required',
                'hint_text' => 'Pilih status hasil pengerjaan',
                'order' => 0,
                'parent' => 0,
                'keterangan' => null,
                'unit_satuan' => null,
                'min' => null,
                'max' => null,
            ]);

            // Create detail_form untuk catatan (optional)
            DetailForm::create([
                'jenis_workorder_id' => $jenisWorkorder->id,
                'nama_field' => 'Catatan',
                'tipe_field' => 'textarea',
                'tipe_data' => 'string',
                'sifat' => 'optional',
                'hint_text' => 'Catatan tambahan (opsional)',
                'order' => 1,
                'parent' => 0,
                'keterangan' => null,
                'unit_satuan' => null,
                'min' => null,
                'max' => null,
            ]);
        });
    }
}
