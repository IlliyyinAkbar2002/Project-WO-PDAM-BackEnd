<?php

namespace App\Http\Controllers;

use App\Models\FormWorkorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormWorkorderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = FormWorkorder::with("detailForm", "kpi");

            if ($request->has("jenis_workorder_id")) {
                $query->where(
                    "jenis_workorder_id",
                    $request->query("jenis_workorder_id"),
                );
                $item = $query->get();
                return response()->json($item, 200);
            }

            $list = $query->get();
            return response()->json($list, 200);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Terjadi kesalahan saat mengambil data form workorder",
                    "message" => $e->getMessage(),
                ],
                500,
            );
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
        try {
            $validator = Validator::make($request->all(), [
                "jenis_workorder_id" =>
                    "required|integer|exists:m_jenis_workorder,id",
                "kpi_id" => "required|integer|exists:m_kpi,id",
                "nama_field" => "required|string|max:255",
                "tipe_field" => "required|string",
                "tipe_data" => "nullable|string",
                // 'unit_satuan' => 'nullable|string',
                "sifat" => "required|string",
                "min" => "nullable|integer",
                "max" => "nullable|integer",
                "parent" => "nullable|integer",
                "keterangan" => "nullable|string",
                "hint_text" => "required|string",
                "order" => "required|integer",
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        "error" => "Validasi gagal",
                        "errors" => $validator->errors(),
                    ],
                    422,
                );
            }

            $formWorkorder = FormWorkorder::create($validator->validated());

            return response()->json(
                [
                    "message" => "Data form workorder berhasil dibuat",
                    "data" => $formWorkorder,
                ],
                201,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Terjadi kesalahan saat menyimpan data form workorder",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $jenis_workorder_id
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($jenis_workorder_id, $id)
    {
        try {
            $formWorkorder = FormWorkorder::with("detailForm", "kpi")
                ->where("id", $id)
                ->where("jenis_workorder_id", $jenis_workorder_id)
                ->firstOrFail();

            return response()->json($formWorkorder, 200);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Terjadi kesalahan saat mengambil data form workorder",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $jenis_workorder_id
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $jenis_workorder_id, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "jenis_workorder_id" =>
                    "required|integer|exists:m_jenis_workorder,id",
                "kpi_id" => "required|integer|exists:m_kpi,id",
                "nama_field" => "required|string|max:255",
                "tipe_field" => "required|string",
                "tipe_data" => "nullable|string",
                // 'unit_satuan' => 'nullable|string',
                "sifat" => "required|string",
                "min" => "nullable|integer",
                "max" => "nullable|integer",
                "parent" => "nullable|integer",
                "keterangan" => "nullable|string",
                "hint_text" => "required|string",
                "order" => "required|integer",
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        "error" => "Validasi gagal",
                        "errors" => $validator->errors(),
                    ],
                    422,
                );
            }

            $formWorkorder = FormWorkorder::where("id", $id)
                ->where("jenis_workorder_id", $jenis_workorder_id)
                ->firstOrFail();

            $formWorkorder->update($validator->validated());

            return response()->json(
                [
                    "message" => "Data form workorder berhasil diperbarui",
                    "data" => $formWorkorder,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Terjadi kesalahan saat memperbarui data form workorder",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $jenis_workorder_id
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($jenis_workorder_id, $id)
    {
        try {
            $formWorkorder = FormWorkorder::where("id", $id)
                ->where("jenis_workorder_id", $jenis_workorder_id)
                ->firstOrFail();

            $formWorkorder->delete();

            return response()->json(
                [
                    "message" => "Data form workorder berhasil dihapus",
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "error" =>
                        "Terjadi kesalahan saat menghapus data form workorder",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
