<?php

namespace App\Services\Notifikasi;

use App\Models\Structure;

/**
 * Penerima notifikasi konsumsi rapat: semua pengguna dengan role PICKonsumsi.
 * Dipakai notifikasi saat rapat dibuat/diubah/dibatalkan dan pengingat 1 jam sebelum rapat.
 */
class PicKonsumsi
{
    /** @return string[] employe_id PIC Konsumsi */
    public static function daftar(): array
    {
        return Structure::query()
            ->select('employe_id', 'roles')
            ->whereNotNull('employe_id')
            ->get()
            ->filter(fn ($s) => in_array('PICKonsumsi', self::peran($s->roles), true))
            ->pluck('employe_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function peran($roles): array
    {
        if (is_array($roles)) {
            return $roles;
        }

        if (is_string($roles) && $roles !== '') {
            $dekode = json_decode($roles, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($dekode)) {
                return $dekode;
            }

            return array_values(array_filter(array_map('trim', explode(',', $roles))));
        }

        return [];
    }
}
