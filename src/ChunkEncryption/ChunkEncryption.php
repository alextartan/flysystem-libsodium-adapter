<?php

namespace AlexTartan\Flysystem\Adapter\ChunkEncryption;

use AlexTartan\Flysystem\Adapter\Exception\EncryptionException;
use Generator;

interface ChunkEncryption
{
    /**
     * @param resource $resource
     *
     * @return Generator<string>
     *
     * @throws EncryptionException
     */
    public function encryptResourceToGenerator($resource): Generator;

    /**
     * @param resource $resource
     *
     * @return Generator<string>
     *
     * @throws EncryptionException
     */
    public function decryptResourceToGenerator($resource): Generator;
}
