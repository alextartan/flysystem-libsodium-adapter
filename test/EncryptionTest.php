<?php

declare(strict_types=1);

namespace AlexTartanTest\Flysystem\Adapter;

use AlexTartan\Flysystem\Adapter\Encryption\Libsodium;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Util;
use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase
{
    private const KEY = 'ZG9Mc1U4ZGtlZ0thWXJxNXhtNTJTc1I5YjdjWm8vMlM1ZzlsRTJFZlNQST0=';

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

    /**
     * @param AdapterInterface|null $adapter
     * @param string                $encryptionKey
     *
     * @return Filesystem
     */
    protected function createEncryptedTestFilesystem(
        AdapterInterface $adapter = null,
        $encryptionKey = self::KEY
    ): Filesystem {
        $adapter = $adapter ?? new MemoryAdapter();

        return new Filesystem(
            new EncryptionAdapterDecorator(
                $adapter,
                new Libsodium(
                    $this->getEncryptionKeyFromEncoded($encryptionKey)
                )
            )
        );
    }

    public function testStoredContentIsEncrypted(): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = openssl_random_pseudo_bytes(20);
        if ($randomBytes === false) {
            static::fail('cannot get random bytes');
        }
        $content = bin2hex($randomBytes);

        $adapter    = new MemoryAdapter();
        $encryption = new Libsodium($this->getEncryptionKeyFromEncoded(self::KEY));
        $filesystem = new Filesystem(new EncryptionAdapterDecorator($adapter, $encryption));

        $filesystem->put($filePath, $content);

        //api
        static::assertSame($content, $filesystem->read($filePath));

        //raw data
        static::assertNotSame($content, $adapter->read(Util::normalizePath($filePath)));
    }

    public function testEncryptionAndDecryption(): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = openssl_random_pseudo_bytes(20);
        if ($randomBytes === false) {
            static::fail('cannot get random bytes');
        }
        $content = bin2hex($randomBytes);

        $sourceFilesystem = $this->createTestFilesystem();

        static::assertTrue($sourceFilesystem->put($filePath, $content));
        static::assertTrue($sourceFilesystem->has($filePath));

        $targetFilesystem = $this->createEncryptedTestFilesystem();
        $targetFilesystem->put($filePath, $sourceFilesystem->read($filePath));

        static::assertSame($content, $targetFilesystem->read($filePath));
    }

    public function testStreamedTextFile(): void
    {
        $string = 'Test text encryption!';
        $source = fopen('data://text/plain,' . $string, 'rb');

        $targetFilesystem = $this->createEncryptedTestFilesystem();

        $filePath = '/my-path.txt';

        if ($targetFilesystem->has($filePath)) {
            $targetFilesystem->delete($filePath);
        }

        $targetFilesystem->writeStream($filePath, $source);

        static::assertSame($string, stream_get_contents($targetFilesystem->readStream($filePath)));
        static::assertSame($string, $targetFilesystem->read($filePath));
    }

    public function testStreamedBinaryFile(): void
    {
        $binaryBlob = openssl_random_pseudo_bytes(2000000);

        $source = fopen('php://memory', 'wb+');
        fwrite($source, $binaryBlob);
        rewind($source);

        $targetFilesystem = $this->createEncryptedTestFilesystem();

        $filePath = '/encrypted.bin';

        if ($targetFilesystem->has($filePath)) {
            $targetFilesystem->delete($filePath);
        }

        $targetFilesystem->writeStream($filePath, $source);

        static::assertSame(
            sha1($binaryBlob),
            sha1(stream_get_contents($targetFilesystem->readStream($filePath)))
        );

        static::assertSame(
            sha1($binaryBlob),
            sha1($targetFilesystem->read($filePath))
        );
    }
}
