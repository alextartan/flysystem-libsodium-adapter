<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter\Encryption;

use function Clue\StreamFilter\append;

class Libsodium implements EncryptionInterface
{
    public const CHUNK_SIZE = 1024;

    /** @var string */
    private $key;

    public function __construct(string $encryptionKey)
    {
        $this->key = $encryptionKey;
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
        stream_set_chunk_size($resource, self::CHUNK_SIZE);

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

                if (mb_strlen($chunk) < self::CHUNK_SIZE) {
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

        stream_set_chunk_size($resource, self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);

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
        stream_set_chunk_size($source, self::CHUNK_SIZE);

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }
}
