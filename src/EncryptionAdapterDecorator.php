<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter;

use AlexTartan\Flysystem\Adapter\ChunkEncryption\ChunkEncryption;
use AlexTartan\Flysystem\Adapter\ChunkEncryption\Libsodium;
use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use Generator;
use League\Flysystem\AdapterDecorator\DecoratorTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use function fopen;
use function fwrite;
use function rewind;

final class EncryptionAdapterDecorator implements AdapterInterface
{
    use DecoratorTrait;

    /** @var AdapterInterface */
    protected $adapter;

    /** @var ChunkEncryption */
    private $encryption;

    /**
     * @param AdapterInterface $adapter
     * @param ChunkEncryption  $encryption
     */
    public function __construct(
        AdapterInterface $adapter,
        ChunkEncryption $encryption
    ) {
        $this->adapter    = $adapter;
        $this->encryption = $encryption;
    }

    /**
     * @return AdapterInterface
     */
    protected function getDecoratedAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     *
     * @return array|false
     */
    public function write($path, $contents, Config $config)
    {
        $encryptedContent = $this->encryption->encryptResourceToGenerator(
            $this->createTemporaryStreamFromContents($contents)
        );

        $content = '';
        foreach ($encryptedContent as $chunk) {
            $content .= $chunk;
        }

        return $this->getDecoratedAdapter()->write($path, $content, $config);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     *
     * @return array|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $encryptedContent = $this->getDecoratedAdapter()->readStream($path);
        if ($encryptedContent === false) {
            return false;
        }

        $decryptedContent = $this->encryption->decryptResourceToGenerator($encryptedContent['stream']);

        $content = '';
        foreach ($decryptedContent as $chunk) {
            $content .= $chunk;
        }

        return [
            'contents' => $content,
        ];
    }

    /**
     * @param string   $path
     * @param resource $resource
     * @param Config   $config
     *
     * @return array|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->getDecoratedAdapter()->writeStream(
            $path,
            $this->createTemporaryStreamFromGenerator(
                $this->encryption->encryptResourceToGenerator($resource)
            ),
            $config
        );
    }

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        return [
            'type'   => 'file',
            'path'   => $path,
            'stream' => $this->createTemporaryStreamFromGenerator(
                $this->encryption->decryptResourceToGenerator(
                    $this->getDecoratedAdapter()->readStream($path)['stream']
                )
            ),
        ];
    }

    /**
     * @return resource
     */
    private function createTemporaryStreamFromGenerator(Generator $generator)
    {
        $handle = fopen('php://temp', 'rb+');
        if ($handle === false) {
            throw new EncryptionException();
        }

        foreach ($generator as $chunk) {
            fwrite($handle, $chunk);
        }

        rewind($handle);

        return $handle;
    }

    /**
     * @return resource
     */
    private function createTemporaryStreamFromContents(string $contents)
    {
        $source = fopen('php://memory', 'wb+');
        if ($source === false) {
            throw new EncryptionException();
        }

        fwrite($source, $contents);
        rewind($source);

        return $source;
    }
}
