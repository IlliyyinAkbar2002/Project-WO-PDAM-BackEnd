<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class ProgressLemburController extends ProgressWorkorderController
{
    public function review(Request $request)
    {
        $this->hydrateInputFromBody($request);

        $request->validate([
            'decision' => 'required|in:accept,reject',
        ]);

        if ($request->input('decision') === 'reject') {
            $request->merge(['decision' => 'revisi']);
        }

        return parent::review($request);
    }
}
