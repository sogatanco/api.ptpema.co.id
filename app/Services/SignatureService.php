<?php

namespace App\Services;

use App\Services\CryptoService;

class SignatureService
{
    public static function normalizeJson($data)
    {
        if (is_array($data)) {
            ksort($data); // Urutkan kunci JSON
            foreach ($data as &$value) {
                if (is_array($value)) {
                    $value = self::normalizeJson($value); // Rekursi untuk array dalam JSON
                }
            }
        }
        return $data;
    }
    public static function signDocument($userId, $document)
    {
        // Ambil Private Key user
        $privateKey = CryptoService::getPrivateKey($userId);

        // Normalisasi JSON sebelum hashing
        $normalizedJson = json_encode(self::normalizeJson($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Hash JSON menggunakan SHA-256
        $documentHash = hash("sha256", $normalizedJson);

        // Tanda tangani hash dengan Private Key
        openssl_sign($documentHash, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public static function verifySignature($userId, $document, $signature)
    {
        // Ambil Public Key user
        $publicKey = CryptoService::getPublicKey($userId);

        // Normalisasi JSON sebelum hashing
        $normalizedJson = json_encode(self::normalizeJson($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Hash JSON menggunakan SHA-256
        $documentHash = hash("sha256", $normalizedJson);

        // Decode Base64 dari Signature
        $signature = base64_decode($signature);

        // Verifikasi tanda tangan
        $isValid = openssl_verify($documentHash, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $isValid === 1;
    }

}
