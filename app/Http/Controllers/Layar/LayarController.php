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

        // Kosongkan folder layar pada disk public_layar
        $files = Storage::disk('public_layar')->files();
        foreach ($files as $file) {
            Storage::disk('public_layar')->delete($file);
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
                $saved = Storage::disk('public_layar')->put( $fileName, $fileContent);
                if ($saved) {
                    // Buat url publik
                    $url = 'layar/' . $fileName;
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

    public function getLayar()
    {
        $data = [];
        $items = Layar::all();

        foreach ($items as $item) {
            $filePath = Storage::disk('public_layar')->path(basename($item->url));
            $base64 = '';
            if (file_exists($filePath)) {
                $mime = mime_content_type($filePath);
                $imageData = file_get_contents($filePath);
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($imageData);
            }
            $data[] = [
                'url' => $item->url,
                'image' => $base64,
                'duration' => $item->duration,
                'fileName' => $item->name,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data layar berhasil diambil',
            'data' => $data
        ]);
    }
}
