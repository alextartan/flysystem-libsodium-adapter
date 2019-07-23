<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use function Clue\StreamFilter\append;
use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

class NoEncryption implements EncryptionInterface
{
    public function encrypt(string $contents): string
    {
        $source = $this->createTemporaryStreamFromContents($contents);

        $this->appendEncryptStreamFilter($source);

        $result = stream_get_contents($source);
        if ($result === false) {
            throw new EncryptionException();
        }

        return $result;
    }

    public function decrypt(string $contents): string
    {
        $source = $this->createTemporaryStreamFromContents($contents);

        $this->appendDecryptStreamFilter($source);

        $result = stream_get_contents($source);
        if ($result === false) {
            throw new EncryptionException();
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
     * @return resource
     */
    private function createTemporaryStreamFromContents(string $contents)
    {
        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            throw new EncryptionException();
        }

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }
}
