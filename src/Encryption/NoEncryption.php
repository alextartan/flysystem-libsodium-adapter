<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use function Clue\StreamFilter\append;

class NoEncryption implements EncryptionInterface
{
    public function encrypt(string $contents): ?string
    {
        $source = $this->createTemporaryStreamFromContents($contents);
        if ($source === null) {
            return null;
        }

        $this->appendEncryptStreamFilter($source);

        $result = stream_get_contents($source);
        if ($result === false) {
            return null;
        }

        return $result;
    }

    public function decrypt(string $contents): ?string
    {
        $source = $this->createTemporaryStreamFromContents($contents);
        if ($source === null) {
            return null;
        }

        $this->appendDecryptStreamFilter($source);

        $result = stream_get_contents($source);
        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * @param resource $resource
     */
    public function appendEncryptStreamFilter($resource): void
    {
        append(
            $resource,
            static function (string $chunk): string {
                return $chunk;
            }
        );
    }

    /**
     * @param resource $resource
     */
    public function appendDecryptStreamFilter($resource): void
    {
        append(
            $resource,
            static function (string $chunk): string {
                return $chunk;
            }
        );
    }

    /**
     * @return null|resource
     */
    private function createTemporaryStreamFromContents(string $contents)
    {
        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            return null;
        }

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }
}
