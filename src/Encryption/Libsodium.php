<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use InvalidArgumentException;
use function Clue\StreamFilter\append;

class Libsodium implements EncryptionInterface
{
    public const MIN_CHUNK_SIZE = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES + 1;
    public const MAX_CHUNK_SIZE = 8192;

    /** @var string */
    private $key;

    /** @var int */
    private $chunkSize;

    public function __construct(string $encryptionKey, int $chunkSize = 1024)
    {
        if ($chunkSize < self::MIN_CHUNK_SIZE || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException('Invalid chunk size');
        }

        $this->key       = $encryptionKey;
        $this->chunkSize = $chunkSize;
    }

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
        stream_set_chunk_size($resource, $this->chunkSize);

        $counter        = 0;
        $additionalData = [];

        append(
            $resource,
            function (string $chunk) use (&$counter, &$additionalData): string {
                $payload = '';
                if ($counter === 0) {
                    [$stream, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);

                    $additionalData['stream'] = $stream;
                    $additionalData['header'] = $header;
                    $additionalData['tag']    = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                    $payload .= $header;
                }

                if (mb_strlen($chunk) < $this->chunkSize) {
                    $additionalData['tag'] = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
                }

                $encryptedChunk = sodium_crypto_secretstream_xchacha20poly1305_push($additionalData['stream'], $chunk, '', $additionalData['tag']);

                $payload .= $encryptedChunk;

                $counter += mb_strlen($chunk);

                return $payload;
            }
        );
    }

    /**
     * @param resource $resource
     */
    public function appendDecryptStreamFilter($resource): void
    {
        $header = stream_get_contents($resource, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

        if ($header === false) {
            return;
        }

        $additionalData           = [];
        $additionalData['header'] = $header;
        $additionalData['stream'] = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key);

        stream_set_chunk_size($resource, $this->chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);

        append(
            $resource,
            static function (string $chunk) use (&$additionalData): string {
                $payload = '';

                [$decryptedChunk, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($additionalData['stream'], $chunk);
                $additionalData['tag'] = $tag;

                $payload .= $decryptedChunk;

                return $payload;
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
        stream_set_chunk_size($source, $this->chunkSize);

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }
}