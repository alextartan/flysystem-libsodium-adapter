<?php
declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\ChunkEncryption;

use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use Generator;
use InvalidArgumentException;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fwrite;

class NoEncryption implements ChunkEncryption
{
    public const MIN_CHUNK_SIZE = 1;
    public const MAX_CHUNK_SIZE = 8192;

    /** @var int */
    private $chunkSize;

    private function __construct(int $chunkSize)
    {
        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException('Invalid chunk size');
        }
        $this->chunkSize = $chunkSize;
    }

    public static function factory(int $chunkSize): self
    {
        return new self(
            $chunkSize
        );
    }

    public function encryptResourceToGenerator($resource): Generator
    {
        do {
            $chunk = fread($resource, $this->chunkSize);
            yield $chunk;
        } while (!feof($resource));
    }

    public function decryptResourceToGenerator($resource): Generator
    {
        do {
            $chunk = fread($resource, $this->chunkSize);
            yield $chunk;
        } while (!feof($resource));
    }
}
