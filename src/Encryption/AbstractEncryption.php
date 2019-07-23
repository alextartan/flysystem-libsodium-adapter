<?php
declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

abstract class AbstractEncryption implements EncryptionInterface
{
    /** @param resource $resource */
    abstract public function appendEncryptStreamFilter($resource): void;

    /** @param resource $resource */
    abstract public function appendDecryptStreamFilter($resource): void;

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
     * @return null|resource
     */
    protected function createTemporaryStreamFromContents(string $contents)
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
