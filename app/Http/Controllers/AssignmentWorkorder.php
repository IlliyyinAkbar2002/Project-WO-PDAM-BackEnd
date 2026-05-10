<?php

namespace App\Http\Controllers;

use App\Models\Workorder;
use App\Services\AssignmentService;
use Illuminate\Http\Request;

class AssignmentWorkorder extends Controller
{
    private AssignmentService $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function assignStaff(Request $request, $id)
    {
        $validated = $request->validate([
            'form_kategori'          => 'required|array',
            'petugas'                => 'required|array|min:1',
            'petugas.*.user_id'      => 'required|exists:users,id',
            'petugas.*.peran'        => 'nullable|in:koordinator,anggota',
            'latitude'               => 'nullable|numeric|between:-90,90',
            'longitude'              => 'nullable|numeric|between:-180,180',
            'accuracy'               => 'nullable|numeric|min:0',
            'deskripsi'              => 'nullable|string',
            'tanggal_mulai'          => 'nullable|date',
            'tanggal_selesai'        => 'nullable|date',
            'estimasi_selesai'       => 'nullable|date',
        ]);

        $workorder = Workorder::with('jenisWorkorder', 'workorderAssignment.members')
            ->findOrFail($id);

        try {
            $this->assignmentService->assignStaff(
                $workorder,
                $validated,
                (int) $request->user()->id
            );

            return response()->json(['message' => 'Assign staff berhasil'], 200);
        } catch (\LogicException $e) {
            $code = str_contains($e->getMessage(), 'Hanya SPV') ? 403 : 422;
            return response()->json(['error' => $e->getMessage()], $code);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
