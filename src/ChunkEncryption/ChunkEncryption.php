<?php

namespace AlexTartan\Flysystem\Adapter\ChunkEncryption;

use Generator;

interface ChunkEncryption
{
    /**
     * @param resource $resource
     */
    public function encryptResourceToGenerator($resource): Generator;

    /**
     * @param resource $resource
     */
    public function decryptResourceToGenerator($resource): Generator;
}