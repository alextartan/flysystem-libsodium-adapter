<?php
declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\ChunkEncryption;

use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use Exception;
use Generator;
use InvalidArgumentException;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fwrite;

class Libsodium implements ChunkEncryption
{
    public const MIN_CHUNK_SIZE = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES + 1;
    public const MAX_CHUNK_SIZE = 8192;

    /** @var string */
    private $key;

    /** @var int */
    private $chunkSize;

    private function __construct(string $key, int $chunkSize)
    {
        $this->key       = $key;
        $this->chunkSize = $chunkSize;
    }

    public static function factory(string $key, int $chunkSize): self
    {
        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException('Invalid chunk size');
        }

        return new self(
            $key,
            $chunkSize
        );
    }

    public function encryptResourceToGenerator($resource): Generator
    {
        [$stream, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);

        yield $header;

        $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
        do {
            $chunk = fread($resource, $this->chunkSize);
            if ($chunk === false) {
                throw new Exception('Cannot encrypt file');
            }

            if (feof($resource)) {
                $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            $encrypted_chunk = sodium_crypto_secretstream_xchacha20poly1305_push($stream, $chunk, '', $tag);

            yield $encrypted_chunk;
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    }

    public function decryptResourceToGenerator($resource): Generator
    {
        $header = fread($resource, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false) {
            throw new Exception('Cannot encrypt file');
        }

        $stream = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);

        do {
            $chunk = fread($resource, $this->chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
            if ($chunk === false) {
                throw new Exception('Cannot encrypt file');
            }

            [$decryptedChunk, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($stream, $chunk);

            yield $decryptedChunk;
        } while (!feof($resource) && $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

        $ok = feof($resource);

        if (!$ok) {
            throw new EncryptionException('Cannot decrypt the file');
        }
    }
}
