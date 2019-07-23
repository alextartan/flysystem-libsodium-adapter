<?php
declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

interface EncryptionInterface
{
    public function encrypt(string $plainText): ?string;

    public function decrypt(string $encryptedText): ?string;

    /** @param resource $resource */
    public function appendEncryptStreamFilter($resource): void;

    /** @param resource $resource */
    public function appendDecryptStreamFilter($resource): void;
}
