<?php
declare(strict_types=1);

namespace AlexTartanTest\Flysystem\Adapter\Encryption;

use AlexTartan\Flysystem\Adapter\ChunkEncryption\Libsodium;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPStan\Testing\TestCase;
use function base64_decode;
use function file_get_contents;
use function fopen;
use function stream_get_contents;

class IntegrationTest extends TestCase
{
    private const STORAGE_LOCATION = __DIR__ . '/../data/storage';

    /** @var Filesystem */
    private $encryptedFs;

    /** @var Filesystem */
    private $plaintextFs;

    public function setUp(): void
    {
        parent::setUp();

        $keyPart1 = base64_decode('ZG9Mc1U4ZGtlZ0thWXJxNXhtNTJTc1I5YjdjWm8vMlM1ZzlsRTJFZlNQST0=', true);
        if ($keyPart1 === false) {
            self::fail('cannot extract key');
        }
        $key = base64_decode($keyPart1, true);
        if ($key === false) {
            self::fail('cannot extract key');
        }

        $this->encryptedFs = new Filesystem(
            new EncryptionAdapterDecorator(
                new Local(self::STORAGE_LOCATION),
                Libsodium::factory(
                    $key,
                    8192
                )
            )
        );

        $this->plaintextFs = new Filesystem(
            new Local(self::STORAGE_LOCATION)
        );
    }

    public function testFileGetsEncryptedAsStream(): void
    {
        $file = __DIR__ . '/../data/file1';

        if ($this->encryptedFs->has('somePath')) {
            $this->encryptedFs->delete('somePath');
        }
        $resource = fopen($file, 'rb');
        if ($resource === false) {
            self::fail('cannot open file');
        }
        $this->encryptedFs->writeStream('somePath', $resource);

        self::assertNotEquals(
            file_get_contents($file),
            file_get_contents(__DIR__ . '/../data/storage/somePath')
        );
    }

    public function testFileGetsEncryptedAsStreamAndDecryptedBack(): void
    {
        $file = __DIR__ . '/../data/file1';

        if ($this->encryptedFs->has('somePath')) {
            $this->encryptedFs->delete('somePath');
        }
        if ($this->plaintextFs->has('original')) {
            $this->plaintextFs->delete('original');
        }

        $resource = fopen($file, 'rb');
        if ($resource === false) {
            self::fail('cannot open file');
        }
        $this->encryptedFs->writeStream('somePath', $resource);

        $readStream = $this->encryptedFs->readStream('somePath');
        if ($readStream === false) {
            self::fail('cannot read file stream');
        }

        $contents = stream_get_contents($readStream);
        if ($contents === false) {
            self::fail('cannot open file');
        }

        $this->plaintextFs->write(
            'original',
            $contents
        );

        self::assertFileEquals(
            $file,
            __DIR__ . '/../data/storage/original'
        );
    }

    public function testFileGetsEncryptedInMemoryAndDecryptedBack(): void
    {
        $file = __DIR__ . '/../data/file1';

        if ($this->encryptedFs->has('somePath')) {
            $this->encryptedFs->delete('somePath');
        }
        if ($this->plaintextFs->has('original')) {
            $this->plaintextFs->delete('original');
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            self::fail('cannot read file');
        }
        $this->encryptedFs->write('somePath', $contents);

        $contents = $this->encryptedFs->read('somePath');
        if ($contents === false) {
            self::fail('cannot open file');
        }

        $this->plaintextFs->write(
            'original',
            $contents
        );

        self::assertFileEquals(
            $file,
            __DIR__ . '/../data/storage/original'
        );
    }
}
