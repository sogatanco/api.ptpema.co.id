<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;

// Uploads are stored under public/, where the web server would run a script file, so names that
// could be executed (also hidden behind a second extension, e.g. "x.php.jpg") are refused
class SafeUpload
{
    private const BLOCKED = '/\.(php\d*|phtml|phar|pht|phps|pgif|shtml|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htpasswd|user\.ini|ini|exe|bat|cmd|svg|html?)(\.|$)/i';

    public static function name(string $original, array $allowedExtensions = []): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = preg_replace('/[^A-Za-z0-9._ ()-]/', '', $name);
        $name = ltrim($name, '.');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($name === '' || preg_match(self::BLOCKED, $name) || ($allowedExtensions && !in_array($ext, $allowedExtensions, true))) {
            throw new HttpResponseException(response()->json([
                'status' => false,
                'message' => 'Jenis file tidak diizinkan.',
            ], 422));
        }

        return $name;
    }

    public static function extension(string $ext, array $allowedExtensions): string
    {
        $ext = strtolower(trim($ext));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new HttpResponseException(response()->json([
                'status' => false,
                'message' => 'Jenis file tidak diizinkan.',
            ], 422));
        }

        return $ext;
    }
}
