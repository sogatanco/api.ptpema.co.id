<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_BASE_URL'),
        'api_key' => env('WHATSAPP_API_KEY'),
    ],

    'zoom' => [
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'account_id' => env('ZOOM_ACCOUNT_ID'),
        'user_id' => env('ZOOM_USER_ID'),
    ],

    // Shared secret used to verify SSO tokens on /api/auth/login-sso; SSO login stays off while empty
    'sso' => [
        'secret' => env('SSO_SHARED_SECRET'),
    ],


    // database GeoLite2-City lokal untuk perkiraan lokasi dari IP (Setting > Lokasi User)
    'geoip' => [
        'db' => env('GEOIP_DB', storage_path('app/geoip/GeoLite2-City.mmdb')),
        // database ASN (nama jaringan/ISP), opsional; kosong = cari berkas *asn*.mmdb di folder yang sama
        'asn_db' => env('GEOIP_ASN_DB', ''),
    ],

    // titik kantor untuk Setting > Lokasi User. KANTOR_IPS = IP publik kantor (dipisah koma, boleh CIDR mis. 203.0.113.0/28):
    // pengguna yang mengakses dari IP itu ditempatkan tepat di titik kantor. Tanpa KANTOR_IPS, IP kantor dikenali otomatis
    // dari laporan lokasi browser pengguna yang berada di sekitar titik kantor.
    'kantor' => [
        'nama' => env('KANTOR_NAMA', 'Kantor PT Pema'),
        'lat' => (float) env('KANTOR_LAT', 5.5558619),
        'lng' => (float) env('KANTOR_LNG', 95.323522),
        'ips' => env('KANTOR_IPS', ''),
    ],

];
