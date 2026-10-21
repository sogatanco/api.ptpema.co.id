<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateDocusignSigningCertificate extends Command
{
    protected $signature = 'docusign:generate-signing-certificate';

    protected $description = 'Generate a self-signed certificate for DocuSign PDF signatures';

    public function handle(Filesystem $files): int
    {
        $certificatePath = config('docusign.signing_certificate');
        $privateKeyPath = config('docusign.signing_private_key');

        if ($certificatePath === $privateKeyPath) {
            $this->error('The signing certificate and private key must use different file paths.');

            return self::FAILURE;
        }

        if (file_exists($certificatePath) || file_exists($privateKeyPath)) {
            $this->error('A signing certificate or private key already exists. Refusing to overwrite it.');

            return self::FAILURE;
        }

        $certificateDirectory = dirname($certificatePath);
        $privateKeyDirectory = dirname($privateKeyPath);
        $files->ensureDirectoryExists($certificateDirectory, 0700, true);
        $files->ensureDirectoryExists($privateKeyDirectory, 0700, true);
        $publicDirectory = realpath(public_path());
        $privateKeyDirectoryPath = realpath($privateKeyDirectory);
        if ($publicDirectory !== false && $privateKeyDirectoryPath !== false
            && str_starts_with(
                strtolower($privateKeyDirectoryPath . DIRECTORY_SEPARATOR),
                strtolower($publicDirectory . DIRECTORY_SEPARATOR)
            )) {
            $this->error('The signing private key must be stored outside the public web directory.');

            return self::FAILURE;
        }

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 3072,
        ]);
        if ($key === false) {
            throw new RuntimeException('Unable to generate the DocuSign signing key: ' . self::opensslError());
        }

        $name = (string) config('docusign.signing_name');
        $organization = (string) config('docusign.signing_organization');
        $request = openssl_csr_new([
            'commonName' => $name,
            'organizationName' => $organization,
            'countryName' => 'ID',
        ], $key, ['digest_alg' => 'sha256']);
        if ($request === false) {
            throw new RuntimeException('Unable to create the signing certificate request: ' . self::opensslError());
        }

        $certificate = openssl_csr_sign($request, null, $key, 3650, ['digest_alg' => 'sha256']);
        if ($certificate === false) {
            throw new RuntimeException('Unable to sign the certificate request: ' . self::opensslError());
        }

        if (!openssl_pkey_export($key, $privateKeyPem)) {
            throw new RuntimeException('Unable to export the signing private key: ' . self::opensslError());
        }
        if (!openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('Unable to export the signing certificate: ' . self::opensslError());
        }

        $suffix = Str::random(12);
        $temporaryKeyPath = $privateKeyPath . '.' . $suffix . '.tmp';
        $temporaryCertificatePath = $certificatePath . '.' . $suffix . '.tmp';
        $keyInstalled = false;

        try {
            if (file_put_contents($temporaryKeyPath, $privateKeyPem, LOCK_EX) === false
                || file_put_contents($temporaryCertificatePath, $certificatePem, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the signing certificate files.');
            }

            if (!chmod($temporaryKeyPath, 0600) || !chmod($temporaryCertificatePath, 0644)) {
                throw new RuntimeException('Unable to set secure permissions on the signing certificate files.');
            }
            if (!rename($temporaryKeyPath, $privateKeyPath)) {
                throw new RuntimeException('Unable to install the signing private key.');
            }
            $keyInstalled = true;
            if (!rename($temporaryCertificatePath, $certificatePath)) {
                throw new RuntimeException('Unable to install the signing certificate.');
            }
            if (!chmod($privateKeyPath, 0600) || !chmod($certificatePath, 0644)) {
                throw new RuntimeException('Unable to set secure permissions on the installed signing certificate files.');
            }
        } catch (\Throwable $e) {
            if ($keyInstalled && !file_exists($certificatePath)) {
                @unlink($privateKeyPath);
            }
            throw $e;
        } finally {
            if (file_exists($temporaryKeyPath)) {
                @unlink($temporaryKeyPath);
            }
            if (file_exists($temporaryCertificatePath)) {
                @unlink($temporaryCertificatePath);
            }
        }

        $this->info('Self-signed DocuSign PDF certificate generated.');
        $this->line('Certificate: ' . $certificatePath);
        $this->line('Private key: ' . $privateKeyPath);
        $this->warn('Keep the private key private and back it up securely. Do not commit it to source control.');
        $this->warn('PDF Readers will not trust this self-signed certificate unless users trust it manually.');

        return self::SUCCESS;
    }

    private static function opensslError(): string
    {
        $error = openssl_error_string();

        return $error ?: 'unknown OpenSSL error';
    }
}
