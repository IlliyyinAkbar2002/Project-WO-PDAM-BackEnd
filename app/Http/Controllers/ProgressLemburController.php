<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProgressLemburController extends ProgressWorkorderController
{
    public function review(Request $request)
    {
        $this->hydrateInputFromBody($request);

        // Backward-compat: klien lama mengirim 'reject' untuk minta revisi.
        // Validasi penuh (accept|revisi + alasan_revisi) ditangani parent::review().
        if ($request->input('decision') === 'reject') {
            $request->merge(['decision' => 'revisi']);
        }

        return parent::review($request);
    }
}
