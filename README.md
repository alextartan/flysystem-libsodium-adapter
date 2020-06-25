# Libsodium Adapter for Flysystem

Performing on-the-fly client-side encryption for safe storage of files.

On uploads, the content is encrypted using [Poly 1305](https://en.wikipedia.org/wiki/Poly1305) with a secret key and stored securely on the filesystem.

On downloads, the content is decrypted. 

Current build status
===

![CI](https://github.com/alextartan/flysystem-libsodium-adapter/workflows/CI/badge.svg?branch=master)
[![codecov](https://codecov.io/gh/alextartan/flysystem-libsodium-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/alextartan/flysystem-libsodium-adapter)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/alextartan/flysystem-libsodium-adapter/master)](https://infection.github.io)
[![Dependabot Status](https://api.dependabot.com/badges/status?host=github&repo=alextartan/flysystem-libsodium-adapter)](https://dependabot.com)
[![Downloads](https://img.shields.io/badge/dynamic/json.svg?url=https://repo.packagist.org/packages/alextartan/flysystem-libsodium-adapter.json&label=Downloads&query=$.package.downloads.total&colorB=orange)](https://packagist.org/packages/alextartan/flysystem-libsodium-adapter)

## Installation

```bash
composer require alextartan/flysystem-libsodium-adapter
```

## Usage

```php
use AlexTartan\Flysystem\Adapter\ChunkEncryption\Libsodium;use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;

$adapter = new MemoryAdapter();
$encryption = Libsodium::factory($encryptionKey, 4096);

$adapterDecorator = new EncryptionAdapterDecorator(
    $adapter, 
    $encryption
);

$filesystem = new Filesystem($adapterDecorator);
```

**Notice**;

Due to how AwsS3 (and probably other remote adapters) handle stream uploads, 
I had to change the way this lib worked (versions up to `v.1.0.0`)

New releases employ a `php://temp` stream in which the encryption is done 
and once that finishes, the stream is sent to `writeStream`/`readStream`

Performance wise, it handles ok from what i could see.

## Versioning

This library adheres to [semver](https://semver.org/)
