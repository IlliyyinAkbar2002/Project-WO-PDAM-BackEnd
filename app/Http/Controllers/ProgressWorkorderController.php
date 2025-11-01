<?php

namespace App\Http\Controllers;

use App\Models\DetailForm;
use App\Models\DetailProgress;
use App\Models\ProgressWorkorder;
use App\Services\ProgressWorkorderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProgressWorkorderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = ProgressWorkorder::with('dokumentasiProgress', 'detailProgress');

            if ($request->has('workorder_id')) {
                $query->where('workorder_id', $request->query('workorder_id'))
                    ->orderBy('order', 'asc');
                $item = $query->get();
                return response()->json($item, 200);
            }
            $list = $query->get();
            return response()->json($list, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data progress workorder',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $progressWorkorder = ProgressWorkorder::with('dokumentasiProgress', 'detailProgress')->findOrFail($id);
            return response()->json($progressWorkorder);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Detail progress not found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        Log::info('Incoming request headers:', $request->header());
        Log::info('Incoming request data:', $request->all());
        Log::info('Incoming files:', $request->file());
        Log::info('Raw request body:', [$request->getContent()]);
        Log::info('POST data:', $_POST);
        Log::info('FILES data:', $_FILES);
        Log::info('Method:', [$request->method()]);

        $progressWorkorder = ProgressWorkorder::findOrFail($id);
        $tipeProgress = $progressWorkorder->tipe_progress;

        $rules = [
            'hasil_pengerjaan' => 'required|string|max:255',
            'waktu_submit' => 'required|date',
            'foto' => 'required|array|min:1',
            'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];

        if ($tipeProgress === 'Selesai') {
            $rules['detail_progress'] = 'nullable|array';
            $rules['detail_progress.*.detail_form_id'] = 'required_if:detail_progress,array|integer|exists:detail_form,id';
            $rules['detail_progress.*.value'] = 'nullable';
            $rules['detail_progress_images.*'] = 'nullable|image|mimes:jpeg,png,jpg|max:2048';
        }

        $validatedData = $request->validate($rules);

        DB::beginTransaction();
        try {
            // Update progress_workorder
            $progressWorkorder->update([
                'waktu_submit' => now(),
                'hasil_pengerjaan' => $validatedData['hasil_pengerjaan'],
            ]);

            // Simpan foto (array)
            if ($request->hasFile('foto')) {
                foreach ($request->file('foto') as $file) {
                    $path = $file->store('dokumentasi_progress', 'public');
                    $progressWorkorder->dokumentasiProgress()->create(['url' => $path]);
                }
            }

            // Proses detail_progress untuk Selesai
            if ($tipeProgress === 'Selesai' && isset($validatedData['detail_progress'])) {
                foreach ($validatedData['detail_progress'] as $item) {
                    $detailForm = DetailForm::findOrFail($item['detail_form_id']);
                    $value = $item['value'] ?? null;

                    if ($detailForm->tipe_field === 'image') {
                        // Simpan satu gambar dari detail_progress_images[detail_form_id]
                        if ($request->hasFile("detail_progress_images.{$item['detail_form_id']}")) {
                            $file = $request->file("detail_progress_images.{$item['detail_form_id']}");
                            $value = $file->store('detail_progress_images', 'public');
                            Log::info("🖼️ Saved image for detail_form_id {$item['detail_form_id']}: $value");
                        } else {
                            $value = null; // Flutter tangani mandatory
                        }
                    }

                    // Update detail_progress
                    DetailProgress::where('progress_workorder_id', $id)
                        ->where('detail_form_id', $item['detail_form_id'])
                        ->update(['value' => $value]);
                }
            }

            (new ProgressWorkorderService())->updateStatusOnSubmit($progressWorkorder->id);

            DB::commit();
            return response()->json($progressWorkorder->load('dokumentasiProgress', 'detailProgress'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update error:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Terjadi kesalahan saat memperbarui data'], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update error:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Terjadi kesalahan saat memperbarui data'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Manual run to add progress for active workorders
     *
     * @return \Illuminate\Http\Response
     */
    public function manualRun()
    {
        try {
            $service = new ProgressWorkorderService();
            $workorders = \App\Models\Workorder::where('status_id', 7)->get();

            foreach ($workorders as $workorder) {
                $service->addWorkorderProgress($workorder->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Progress ditambahkan untuk semua workorder aktif'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menjalankan manual run',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
