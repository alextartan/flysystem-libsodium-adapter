<?php

declare(strict_types=1);

namespace AlexTartanTest\Flysystem\Adapter\Encryption;

use AlexTartan\Flysystem\Adapter\ChunkEncryption\NoEncryption;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Util;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;

/**
 * @covers \AlexTartan\Flysystem\Adapter\ChunkEncryption\NoEncryption
 * @covers \AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator
 */
class NoEncryptionTest extends TestCase
{
    private function createEncryptedTestFilesystem(
        AdapterInterface $adapter
    ): Filesystem {
        return new Filesystem(
            new EncryptionAdapterDecorator(
                $adapter,
                NoEncryption::factory(4096)
            )
        );
    }

    public function testStoredContentIsNotEncrypted(): void
    {
        $filePath    = '/demo.txt';
        $randomBytes = random_bytes(20);
        $content     = bin2hex($randomBytes);

        $filesystem = $this->createEncryptedTestFilesystem(new MemoryAdapter());

        $filesystem->put($filePath, $content);

        //api
        static::assertSame($content, $filesystem->read($filePath));

        //raw data
        $readContent = $filesystem->getAdapter()->read(Util::normalizePath($filePath));
        if ($readContent === false) {
            static::fail('cannot read file');
        }
        static::assertSame($content, $readContent['contents']);
    }
}
