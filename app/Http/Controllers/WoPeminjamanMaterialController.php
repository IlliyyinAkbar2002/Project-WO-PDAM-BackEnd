<?php

namespace App\Http\Controllers;

use App\Models\WoPeminjamanMaterial;
use App\Models\Workorder;
use App\Services\PeminjamanMaterialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WoPeminjamanMaterialController extends Controller
{
    public function __construct(private PeminjamanMaterialService $service = new PeminjamanMaterialService()) {}

    /**
     * GET /v1/workorder/{id}/peminjaman-material
     * Riwayat peminjaman material untuk WO tertentu.
     */
    public function index()
    {
        $peminjaman = WoPeminjamanMaterial::with([
            'workorder:id,nama_workorder,status',
            'material:kode_material,nama,jumlah_stok,rusak',
            'pengaju:id,nama,nip',
            'verifier:id,nama,nip',
        ])
        ->whereHas('workorder', function ($query) {
            $query->where('status', 'Selesai');
        })
        ->orderBy('diajukan_at')
        ->get();
        return response()->json([
            'message' => 'Log penggunaan material berhasil diambil.',
            'data'    => $peminjaman,
        ]);
    }

    public function show($workorder_id)
    {
        $pegawaiId = $this->actorPegawaiId();

        // SPV WO (workorder.assigned_to) melihat seluruh peminjaman tim; staff biasa
        // hanya peminjaman miliknya (diajukan_oleh). Enforcement berbasis identitas
        // server (pegawai_id user login), BUKAN parameter klien.
        $isSpv = $pegawaiId !== null
            && (int) optional(Workorder::find($workorder_id))->assigned_to === $pegawaiId;

        $peminjaman = WoPeminjamanMaterial::with([
            'material:kode_material,nama,jumlah_stok,rusak',
            'pengaju:id,nama,nip',
            'verifier:id,nama,nip',
        ])
            ->where('workorder_id', $workorder_id)
            ->when(! $isSpv, fn ($q) => $q->where('diajukan_oleh', $pegawaiId))
            ->orderByDesc('diajukan_at')
            ->get();

        return response()->json([
            'message' => 'Data peminjaman material berhasil diambil.',
            'data'    => $peminjaman,
        ]);
    }
    

    /**
     * POST /v1/workorder/{id}/peminjaman-material
     * Staff ajukan peminjaman material (auto-approved).
     */
    public function pinjam(Request $request, $workorder_id)
    {
        $data = $request->validate([
            'material_kode' => 'required|exists:m_material,kode_material',
            'jumlah_pinjam' => 'required|integer|min:1',
            'catatan'       => 'nullable|string',
        ]);

        $workorder = Workorder::find($workorder_id);
        if (! $workorder) {
            return response()->json(['message' => 'Work order tidak ditemukan.'], 404);
        }

        $pegawaiId = $this->actorPegawaiId();
        if (! $pegawaiId) {
            return response()->json(['message' => 'Akun Anda tidak terhubung dengan data pegawai.'], 403);
        }

        try {
            $peminjaman = $this->service->pinjam($workorder, $pegawaiId, $data);

            return response()->json([
                'message' => 'Peminjaman material berhasil diproses dan stok dikurangi.',
                'data'    => $peminjaman,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logFailure('pinjam', $e, ['workorder_id' => $workorder_id, 'pegawai_id' => $pegawaiId]);

            return $this->serverError('Terjadi kesalahan saat memproses peminjaman material.', $e);
        }
    }

    /**
     * POST /v1/peminjaman-material/{id}/kembalikan
     * Staff ajukan pengembalian. Status → PENDING_KEMBALI, menunggu approval SPV.
     */
    public function kembalikan(Request $request, $id)
    {
        $data = $request->validate([
            'jumlah_kembali'  => 'required|integer|min:0',
            'jumlah_rusak'    => 'nullable|integer|min:0',
            'kondisi_kembali' => 'nullable|string',
        ]);

        $pinjaman = WoPeminjamanMaterial::find($id);
        if (! $pinjaman) {
            return response()->json(['message' => 'Data peminjaman tidak ditemukan.'], 404);
        }

        $pegawaiId = $this->actorPegawaiId();
        if (! $pegawaiId) {
            return response()->json(['message' => 'Akun Anda tidak terhubung dengan data pegawai.'], 403);
        }

        try {
            $updated = $this->service->submitReturn($pinjaman, $pegawaiId, $data);

            return response()->json([
                'message' => 'Pengajuan pengembalian material berhasil dikirim dan menunggu persetujuan supervisor.',
                'data'    => $updated,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logFailure('kembalikan', $e, ['peminjaman_id' => $id, 'pegawai_id' => $pegawaiId]);

            return $this->serverError('Terjadi kesalahan saat memproses pengembalian material.', $e);
        }
    }

    /**
     * POST /v1/peminjaman-material/{id}/verify
     * SPV approve/reject pengajuan pengembalian.
     */
    public function verify(Request $request, $id)
    {
        $data = $request->validate([
            'status'              => 'required|string|in:APPROVED,REJECTED',
            'catatan_verifikator' => 'nullable|string',
        ]);

        $pinjaman = WoPeminjamanMaterial::find($id);
        if (! $pinjaman) {
            return response()->json(['message' => 'Data peminjaman tidak ditemukan.'], 404);
        }

        $pegawaiId = $this->actorPegawaiId();
        if (! $pegawaiId) {
            return response()->json(['message' => 'Akun Anda tidak terhubung dengan data pegawai.'], 403);
        }

        try {
            $updated = $this->service->verify($pinjaman, $pegawaiId, $data);

            $label = $data['status'] === 'APPROVED' ? 'APPROVED' : 'REJECTED';
            return response()->json([
                'message' => "Status pengembalian berhasil diperbarui menjadi {$label}.",
                'data'    => $updated,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logFailure('verify', $e, ['peminjaman_id' => $id, 'pegawai_id' => $pegawaiId]);

            return $this->serverError('Terjadi kesalahan saat memverifikasi pengembalian material.', $e);
        }
    }

    /**
     * pegawai_id aktor yang sedang login (jembatan users.pegawai_id → m_pegawai).
     */
    private function actorPegawaiId(): ?int
    {
        $pegawaiId = optional(Auth::user())->pegawai_id;

        return $pegawaiId ? (int) $pegawaiId : null;
    }

    /**
     * Catat kegagalan 500 ke log dengan konteks agar bisa didiagnosa
     * (sebelumnya error ditelan tanpa jejak).
     */
    private function logFailure(string $action, \Throwable $e, array $context = []): void
    {
        Log::error("peminjaman-material.{$action} gagal", array_merge($context, [
            'sqlstate' => $e instanceof \Illuminate\Database\QueryException ? $e->getCode() : null,
            'message'  => $e->getMessage(),
        ]));
    }

    /**
     * Respons 500 seragam. Pesan mentah exception hanya dibocorkan saat APP_DEBUG.
     */
    private function serverError(string $message, \Throwable $e)
    {
        return response()->json(array_merge(
            ['message' => $message],
            config('app.debug') ? ['error' => $e->getMessage()] : []
        ), 500);
    }
}
