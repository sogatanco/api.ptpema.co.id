<?php

namespace App\Http\Controllers\Layar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Layar\Layar;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;

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

        // Pastikan direktori uploads/layar ada di storage/app/public
        $dir = 'uploads/layar';
        if (!Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        foreach ($items as $item) {
            $fileName = $item['fileName'] ?? uniqid('layar_') . '.png';
            $base64 = $item['image'] ?? '';
            $url = '';

            if ($base64) {
                // Ekstrak base64 jika ada prefix data:image/xxx;base64,
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                    $base64 = substr($base64, strpos($base64, ',') + 1);
                    $ext = strtolower($type[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $ext;
                } else {
                    $ext = 'png';
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.png';
                }
                // Hilangkan whitespace pada base64
                $base64 = preg_replace('/\s+/', '', $base64);
                $fileContent = base64_decode($base64);
                // Simpan file ke storage/app/public/uploads/layar
                $saved = Storage::disk('public')->put($dir . '/' . $fileName, $fileContent);
                if ($saved) {
                    // Buat url publik
                    $url = url('storage/' . $dir . '/' . $fileName);
                } else {
                    // Fallback: coba simpan manual ke public/uploads/layar jika Storage gagal
                    $publicDir = public_path('uploads/layar');
                    if (!is_dir($publicDir)) {
                        mkdir($publicDir, 0777, true);
                    }
                    $filePath = $publicDir . '/' . $fileName;
                    if (file_put_contents($filePath, $fileContent)) {
                        $url = url('uploads/layar/' . $fileName);
                    }
                }
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
