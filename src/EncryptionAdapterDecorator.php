<?php

declare(strict_types=1);

namespace AlexTartan\Flysystem\Adapter;

use AlexTartan\Flysystem\Adapter\Encryption\EncryptionInterface;
use League\Flysystem\AdapterDecorator\DecoratorTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

final class EncryptionAdapterDecorator implements AdapterInterface
{
    use DecoratorTrait;

    /** @var AdapterInterface */
    protected $adapter;

    /** @var EncryptionInterface */
    private $encryption;

    /**
     * @param AdapterInterface    $adapter
     * @param EncryptionInterface $encryption
     */
    public function __construct(
        AdapterInterface $adapter,
        EncryptionInterface $encryption
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
        $encryptedContent = $this->encryption->encrypt($contents);
        if ($encryptedContent === null) {
            return false;
        }

        return $this->getDecoratedAdapter()->write($path, $encryptedContent, $config);
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
        $response = $this->getDecoratedAdapter()->read($path);
        if ($response === false) {
            return false;
        }

        $response['contents'] = $this->encryption->decrypt($response['contents']);

        return $response;
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
        $this->encryption->appendEncryptStreamFilter($resource);

        return $this->getDecoratedAdapter()->writeStream($path, $resource, $config);
    }

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $resource = $this->getDecoratedAdapter()->readStream($path)['stream'];

        $this->encryption->appendDecryptStreamFilter($resource);

        return [
            'type'   => 'file',
            'path'   => $path,
            'stream' => $resource,
        ];
    }
}
