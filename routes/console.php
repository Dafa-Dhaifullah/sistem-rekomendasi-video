<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


$bulanSekarang = now()->month;
$tahunSekarang = now()->year;


if ($bulanSekarang >= 8 && $bulanSekarang <= 12) {
    $tahunAkademik = $tahunSekarang;
    $kodeSemester  = 1;
} elseif ($bulanSekarang == 1) {
    $tahunAkademik = $tahunSekarang - 1;
    $kodeSemester  = 1;
} else {
    $tahunAkademik = $tahunSekarang - 1;
    $kodeSemester  = 2;
}

$kodeTahunAjaran = $tahunAkademik . $kodeSemester;

Schedule::command("app:sync-materi {$kodeTahunAjaran}")
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-materi.log'));