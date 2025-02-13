<?php

namespace App\Services;
use App\Models\Employe;

use Illuminate\Support\Facades\Storage;

class CryptoService
{
    public static function generateKeys($employe_id)
    {

        // Generate pasangan kunci RSA (2048-bit)
        $keyPair = openssl_pkey_new([
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);

        // Ekstrak Private Key
        openssl_pkey_export($keyPair, $privateKey);

        // Ekstrak Public Key
        $publicKey = openssl_pkey_get_details($keyPair)['key'];

        // Simpan Public Key di Database
        $user = Employe::where('employe_id', $employe_id)->first();
        $user->public_key = $publicKey;
        $user->save();

        // Simpan Private Key secara terenkripsi di storage Laravel
        Storage::put("keys/private_{$employe_id}.pem", encrypt($privateKey));
    }

    public static function getPrivateKey($employe_id)
    {
        $encryptedPrivateKey = Storage::get("keys/private_{$employe_id}.pem");
        return decrypt($encryptedPrivateKey);
    }

    public static function getPublicKey($employe_id)
    {
        $user = Employe::where('employe_id', $employe_id)->first();
        return $user->public_key;
    }
}
