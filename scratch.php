<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$wos = \App\Models\Workorder::whereDoesntHave('progressWorkorder', function($query) {
    $query->whereNotNull('tahapan');
})->with('status')->get()->groupBy(function($wo) {
    return optional($wo->status)->kode ?? 'UNKNOWN';
})->map->count();

echo json_encode($wos);
