<?php

namespace App\Services;

use App\Services\CryptoService;

class SignatureService
{
    public static function signDocument($employe_id, $document)
    {
        // Ambil Private Key
        $privateKey = CryptoService::getPrivateKey($employe_id);

        // Encode JSON secara konsisten sebelum di-hash
        $normalizedJson = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

        // Hash JSON
        $documentHash = hash("sha256", $normalizedJson);

        // Tanda tangani hash dengan Private Key
        openssl_sign($documentHash, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public static function verifySignature($employe_id, $document, $signature)
    {
        // Ambil Public Key user
        $publicKey = CryptoService::getPublicKey($employe_id);

        // Encode JSON secara konsisten sebelum di-hash
        $normalizedJson = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

        // Hash JSON
        $documentHash = hash("sha256", $normalizedJson);

        // Verifikasi tanda tangan
        $signature = base64_decode($signature);
        $isValid = openssl_verify($documentHash, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $isValid === 1;
    }
}
