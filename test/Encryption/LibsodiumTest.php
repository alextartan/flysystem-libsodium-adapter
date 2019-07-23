<?php

declare(strict_types=1);

namespace AlexTartanTest\Flysystem\Adapter\Encryption;

use AlexTartan\Flysystem\Adapter\Encryption\Libsodium;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use InvalidArgumentException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Util;
use PHPUnit\Framework\TestCase;
use function base64_decode;
use function bin2hex;
use function fopen;
use function fwrite;
use function openssl_random_pseudo_bytes;
use function rewind;
use function sha1;
use function stream_get_contents;
use function stream_set_chunk_size;

/**
 * @covers \AlexTartan\Flysystem\Adapter\Encryption\Libsodium
 * @covers \AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator
 */
class LibsodiumTest extends TestCase
{
    private const KEY = 'ZG9Mc1U4ZGtlZ0thWXJxNXhtNTJTc1I5YjdjWm8vMlM1ZzlsRTJFZlNQST0=';

    public function chunkSizeProvider(): array
    {
        $chunks = [
            1024,
            2048,
            4096,
            8192,
        ];

        $lengths = [
            2,
            20,
            200,
            2000,
            20000,
            200000,
            2000000,
        ];

        $ret = [];
        foreach ($chunks as $chunk) {
            foreach ($lengths as $length) {
                $ret[] = [$chunk, $length];
            }
        }

        return $ret;
    }

    public function invalidChunkSizeProvider(): array
    {
        return [
            [24],
            [8193],
        ];
    }

    private function createTestFilesystem(): Filesystem
    {
        return new Filesystem(new MemoryAdapter());
    }

    private function getEncryptionKeyFromEncoded(string $encryptionKey): string
    {
        // double b64 decode with all safety in place
        $key = base64_decode($encryptionKey, true);
        if ($key === false) {
            static::fail('cannot b64decode key');
        }
        $key = base64_decode($key, true);
        if ($key === false) {
            static::fail('cannot b64decode key');
        }

        return $key;
    }

    private function createEncryptedTestFilesystem(
        AdapterInterface $adapter,
        string $encryptionKey,
        int $chunkSize
    ): Filesystem {
        return new Filesystem(
            new EncryptionAdapterDecorator(
                $adapter,
                new Libsodium(
                    $this->getEncryptionKeyFromEncoded($encryptionKey),
                    $chunkSize
                )
            )
        );
    }


    /**
     * @return null|resource
     */
    private function createTemporaryStreamFromContents(string $contents, int $chunkSize)
    {
        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            return null;
        }
        stream_set_chunk_size($source, $chunkSize);

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }

    /** @dataProvider invalidChunkSizeProvider */
    public function testInvalidChinkSize(int $chunkSize): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk size');

        $this->createEncryptedTestFilesystem(new MemoryAdapter(), self::KEY, $chunkSize);
    }

    /** @dataProvider chunkSizeProvider */
    public function testStoredContentIsEncrypted(int $chunkSize, int $contentLength): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = openssl_random_pseudo_bytes($contentLength);
        if ($randomBytes === false) {
            static::fail('cannot get random bytes');
        }
        $content = bin2hex($randomBytes);

        $adapter    = new MemoryAdapter();
        $encryption = new Libsodium($this->getEncryptionKeyFromEncoded(self::KEY), $chunkSize);
        $filesystem = new Filesystem(new EncryptionAdapterDecorator($adapter, $encryption));

        $filesystem->put($filePath, $content);

        //api
        static::assertSame($content, $filesystem->read($filePath));

        //raw data
        $readContent = $adapter->read(Util::normalizePath($filePath));
        if ($readContent === false) {
            static::fail('cannot read file');
        }
        static::assertNotSame($content, $readContent['contents']);
    }

    /** @dataProvider chunkSizeProvider */
    public function testEncryptionAndDecryption(int $chunkSize, int $contentLength): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = openssl_random_pseudo_bytes($contentLength);
        if ($randomBytes === false) {
            static::fail('cannot get random bytes');
        }
        $content = bin2hex($randomBytes);

        $sourceFilesystem = $this->createTestFilesystem();

        static::assertTrue($sourceFilesystem->put($filePath, $content));
        static::assertTrue($sourceFilesystem->has($filePath));

        $targetFilesystem = $this->createEncryptedTestFilesystem(new MemoryAdapter(), self::KEY, $chunkSize);

        $contents = $sourceFilesystem->read($filePath);
        if ($contents === false) {
            static::fail('cannot read file');
        }

        $targetFilesystem->put($filePath, $contents);

        static::assertSame($content, $targetFilesystem->read($filePath));
    }

    /** @dataProvider chunkSizeProvider */
    public function testEncryptionAndDecryptionUpdateText(int $chunkSize, int $contentLength): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = openssl_random_pseudo_bytes($contentLength);
        if ($randomBytes === false) {
            static::fail('cannot get random bytes');
        }
        $content = bin2hex($randomBytes);

        $sourceFilesystem = $this->createTestFilesystem();

        static::assertTrue($sourceFilesystem->put($filePath, ''));
        static::assertTrue($sourceFilesystem->update($filePath, $content));
        static::assertTrue($sourceFilesystem->has($filePath));

        $targetFilesystem = $this->createEncryptedTestFilesystem(new MemoryAdapter(), self::KEY, $chunkSize);
        $targetFilesystem->put($filePath, '');

        $parsedContent = $sourceFilesystem->read($filePath);
        if ($parsedContent === false) {
            static::fail('cannot read data');
        }

        $targetFilesystem->update($filePath, $parsedContent);

        static::assertSame($content, $targetFilesystem->read($filePath));
    }

    /** @dataProvider chunkSizeProvider */
    public function testStreamedTextFile(int $chunkSize, int $contentLength): void
    {
        $string = 'Test text encryption!';
        $source = $this->createTemporaryStreamFromContents($string, $chunkSize);
        if ($source === null) {
            static::fail('cannot get stream');
        }

        $targetFilesystem = $this->createEncryptedTestFilesystem(new MemoryAdapter(), self::KEY, $chunkSize);

        $filePath = '/my-path.txt';

        if ($targetFilesystem->has($filePath)) {
            $targetFilesystem->delete($filePath);
        }

        $targetFilesystem->writeStream($filePath, $source);

        $resource = $targetFilesystem->readStream($filePath);
        if ($resource === false) {
            static::fail('cannot get resource');
        }

        static::assertSame($string, stream_get_contents($resource));
        static::assertSame($string, $targetFilesystem->read($filePath));
    }

    /** @dataProvider chunkSizeProvider */
    public function testStreamedBinaryFile(int $chunkSize, int $contentLength): void
    {
        $binaryBlob = openssl_random_pseudo_bytes($contentLength);
        if ($binaryBlob === false) {
            static::fail('cannot get random bytes');
        }

        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            static::fail('cannot get stream');
        }
        fwrite($source, $binaryBlob);
        rewind($source);

        $targetFilesystem = $this->createEncryptedTestFilesystem(new MemoryAdapter(), self::KEY, $chunkSize);

        $filePath = '/encrypted.bin';

        if ($targetFilesystem->has($filePath)) {
            $targetFilesystem->delete($filePath);
        }

        $targetFilesystem->writeStream($filePath, $source);

        $resource = $targetFilesystem->readStream($filePath);
        if ($resource === false) {
            static::fail('cannot get resource');
        }

        $temp1 = stream_get_contents($resource);
        if ($temp1 === false) {
            static::fail('cannot read stream');
        }
        static::assertSame(
            sha1($binaryBlob),
            sha1($temp1)
        );

        $temp2 = $targetFilesystem->read($filePath);
        if ($temp2 === false) {
            static::fail('cannot read stream');
        }
        static::assertSame(
            sha1($binaryBlob),
            sha1($temp2)
        );
    }
}
