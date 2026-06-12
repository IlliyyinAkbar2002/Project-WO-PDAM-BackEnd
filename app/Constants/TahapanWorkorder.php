<?php

namespace App\Constants;

class TahapanWorkorder
{
    public const PERSIAPAN = 1;
    public const PENGERJAAN = 2;
    public const PENGUJIAN = 3;
    public const DOKUMENTASI = 4;

    public const LABELS = [
        self::PERSIAPAN   => 'Persiapan',
        self::PENGERJAAN  => 'Pengerjaan',
        self::PENGUJIAN   => 'Pengujian',
        self::DOKUMENTASI => 'Dokumentasi',
    ];
}
