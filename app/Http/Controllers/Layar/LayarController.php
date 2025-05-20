<?php

namespace App\Http\Controllers\Layar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Layar\Layar;
use App\Http\Resources\PostResource;

class LayarController extends Controller
{
    /**
     * Insert data layar dari frontend, hapus semua data sebelum insert baru.
     * Data format: [{image, duration, fileName}, ...]
     * File gambar dari base64, simpan ke direktori, url-nya disimpan di kolom url.
     */
    public function insert(Request $request)
    {
        $items = $request->all();

        // Hapus semua data lama
        Layar::truncate();

        // Direktori penyimpanan file gambar
        $dir = public_path('uploads/layar');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($items as $item) {
            $fileName = $item['fileName'] ?? uniqid('layar_') . '.png';
            $base64 = $item['image'] ?? '';
            $url = '';

            if ($base64) {
                // Ekstrak base64 jika ada prefix data:image/xxx;base64,
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                    $base64 = substr($base64, strpos($base64, ',') + 1);
                    $ext = $type[1];
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $ext;
                } else {
                    $ext = 'png';
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.png';
                }
                $filePath = $dir . '/' . $fileName;
                file_put_contents($filePath, base64_decode($base64));
                $url = url('uploads/layar/' . $fileName);
            }

            Layar::create([
                'url' => $url,
                'name' => $fileName,
                'duration' => $item['duration'] ?? 0,
            ]);
        }

        return new PostResource(true, 'Data layar berhasil disimpan', []);
    }
}
