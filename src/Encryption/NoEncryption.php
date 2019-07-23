<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use function Clue\StreamFilter\append;

class NoEncryption extends AbstractEncryption
{
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
}
