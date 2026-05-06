<?php

namespace App\Http\Controllers;

use App\Models\WoPeminjamanMaterial;
use App\Models\Material;
use App\Models\Workorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WoPeminjamanMaterialController extends Controller
{
    /**
     * Menampilkan riwayat peminjaman material untuk Work Order tertentu.
     */
    public function index($workorder_id)
    {
        $peminjaman = WoPeminjamanMaterial::with(['material:kode_material,nama,satuan', 'pengaju:id,name,pegawai_id', 'pengaju.pegawai:id,nama,nip'])
            ->where('workorder_id', $workorder_id)
            ->get();

        return response()->json([
            'message' => 'Data peminjaman material berhasil diambil.',
            'data'    => $peminjaman
        ]);
    }

    /**
     * Mengajukan peminjaman material untuk Work Order tertentu.
     */
    public function pinjam(Request $request, $workorder_id)
    {
        $request->validate([
            'material_kode' => 'required|exists:m_material,kode_material',
            'jumlah_pinjam' => 'required|integer|min:1',
            'catatan'       => 'nullable|string'
        ]);

        // Cek Workorder
        $workorder = Workorder::find($workorder_id);
        if (!$workorder) {
            return response()->json(['message' => 'Work order tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            // Lock row for update
            $material = Material::where('kode_material', $request->material_kode)->lockForUpdate()->first();

            // Cek ketersediaan
            $tersedia = $material->jumlah_stok - $material->terpakai;
            if ($request->jumlah_pinjam > $tersedia) {
                return response()->json([
                    'message' => 'Stok material tidak mencukupi.',
                    'tersedia' => $tersedia
                ], 400);
            }

            // Insert ke tabel peminjaman
            $peminjaman = WoPeminjamanMaterial::create([
                'workorder_id'  => $workorder_id,
                'material_kode' => $request->material_kode,
                'jumlah_pinjam' => $request->jumlah_pinjam,
                'catatan'       => $request->catatan,
                'diajukan_oleh' => Auth::id(),
                'diajukan_at'   => now(),
                'status'        => 'DIPINJAM'
            ]);

            // Tambahkan stok terpakai
            $material->terpakai += $request->jumlah_pinjam;
            $material->save();

            DB::commit();

            return response()->json([
                'message' => 'Peminjaman material berhasil diajukan.',
                'data'    => $peminjaman->load(['material:kode_material,nama,satuan'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses peminjaman material.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengembalikan material yang sudah dipinjam.
     */
    public function kembalikan(Request $request, $id)
    {
        $request->validate([
            'jumlah_kembali'  => 'required|integer|min:0',
            'kondisi_kembali' => 'nullable|string|max:64'
        ]);

        DB::beginTransaction();
        try {
            $peminjaman = WoPeminjamanMaterial::lockForUpdate()->find($id);

            if (!$peminjaman) {
                return response()->json(['message' => 'Data peminjaman tidak ditemukan.'], 404);
            }

            if ($peminjaman->status === 'DIKEMBALIKAN') {
                return response()->json(['message' => 'Material ini sudah dikembalikan sebelumnya.'], 400);
            }

            if ($request->jumlah_kembali > $peminjaman->jumlah_pinjam) {
                return response()->json(['message' => 'Jumlah kembali tidak boleh lebih dari jumlah yang dipinjam.'], 400);
            }

            // Update status peminjaman
            $peminjaman->jumlah_kembali  = $request->jumlah_kembali;
            $peminjaman->kondisi_kembali = $request->kondisi_kembali ?? 'Baik';
            $peminjaman->dikembalikan_at = now();
            $peminjaman->status          = 'DIKEMBALIKAN';
            $peminjaman->save();

            // Kembalikan stok terpakai (kurangi `terpakai`)
            // Jika yang dikembalikan lebih sedikit dari yang dipinjam, sisa barang tetap dianggap terpakai/hilang untuk WO tersebut.
            $material = Material::where('kode_material', $peminjaman->material_kode)->lockForUpdate()->first();
            if ($material) {
                $material->terpakai -= $request->jumlah_kembali;
                // Pastikan terpakai tidak minus (walau secara logika tidak mungkin terjadi jika kode berjalan benar)
                if ($material->terpakai < 0) {
                    $material->terpakai = 0;
                }
                $material->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Material berhasil dikembalikan.',
                'data'    => $peminjaman->load(['material:kode_material,nama,satuan'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses pengembalian material.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
