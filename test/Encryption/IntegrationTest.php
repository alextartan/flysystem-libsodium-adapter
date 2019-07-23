<?php
declare(strict_types=1);

namespace AlexTartanTest\Flysystem\Adapter\Encryption;

use AlexTartan\Flysystem\Adapter\Encryption\Libsodium;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPStan\Testing\TestCase;
use function base64_decode;
use function file_get_contents;
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

        $this->encryptedFs = new Filesystem(
            new EncryptionAdapterDecorator(
                new Local(self::STORAGE_LOCATION),
                new Libsodium(
                    base64_decode(base64_decode('ZG9Mc1U4ZGtlZ0thWXJxNXhtNTJTc1I5YjdjWm8vMlM1ZzlsRTJFZlNQST0=')),
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

        if ($this->encryptedFs->has('somepath')) {
            $this->encryptedFs->delete('somepath');
        }
        $this->encryptedFs->writeStream(
            'somepath',
            fopen($file, 'rb')
        );

        self::assertNotEquals(
            file_get_contents($file),
            file_get_contents(__DIR__ . '/../data/storage/somepath'),
        );
    }

    public function testFileGetsEncryptedAsStreamAndDecryptedBack(): void
    {
        $file = __DIR__ . '/../data/file1';

        if ($this->encryptedFs->has('somepath')) {
            $this->encryptedFs->delete('somepath');
        }
        if ($this->plaintextFs->has('original')) {
            $this->plaintextFs->delete('original');
        }
        $this->encryptedFs->writeStream(
            'somepath',
            fopen($file, 'rb')
        );

        $this->plaintextFs->write(
            'original',
            stream_get_contents($this->encryptedFs->readStream('somepath'))
        );

        self::assertFileEquals(
            $file,
            __DIR__ . '/../data/storage/original',
        );
    }
}
