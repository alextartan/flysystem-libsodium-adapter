<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter;

use AlexTartan\Flysystem\Adapter\ChunkEncryption\ChunkEncryption;
use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use AlexTartan\Helpers\Stream\GeneratorReadStream;
use League\Flysystem\AdapterDecorator\DecoratorTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

use function fopen;
use function fwrite;
use function rewind;

final class EncryptionAdapterDecorator implements AdapterInterface
{
    use DecoratorTrait;

    protected AdapterInterface $adapter;

    private ChunkEncryption $encryption;

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
     * @return string[]|false
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
     * @return string[]|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     *
     * @return string[]|false
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
     * @return string[]|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        $readResource = fopen(
            GeneratorReadStream::createResourceUrl(
                $this->encryption->encryptResourceToGenerator($resource)
            ),
            'rb'
        );

        if ($readResource === false) {
            return false;
        }

        return $this->getDecoratedAdapter()->writeStream(
            $path,
            $readResource,
            $config
        );
    }

    /**
     * @param string $path
     *
     * @return array<string, string|resource|false>|false
     */
    public function readStream($path)
    {
        $readSteam = $this->getDecoratedAdapter()->readStream($path);
        if ($readSteam === false) {
            return false;
        }

        return [
            'type'   => 'file',
            'path'   => $path,
            'stream' => fopen(
                GeneratorReadStream::createResourceUrl(
                    $this->encryption->decryptResourceToGenerator(
                        $readSteam['stream']
                    )
                ),
                'rb'
            ),
        ];
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
