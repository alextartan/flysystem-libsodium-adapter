# Libsodium Adapter for Flysystem

Work in progress... (see notice below)

Current build status
===

[![Build Status](https://travis-ci.org/alextartan/flysystem-libsodium-adapter.svg?branch=master)](https://travis-ci.org/alextartan/flysystem-libsodium-adapter)
[![Coverage Status](https://coveralls.io/repos/github/alextartan/flysystem-libsodium-adapter/badge.svg?branch=master)](https://coveralls.io/github/alextartan/flysystem-libsodium-adapter?branch=master)
[![Mutation testing badge](https://badge.stryker-mutator.io/github.com/alextartan/flysystem-libsodium-adapter/master)](https://stryker-mutator.github.io)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/alextartan/flysystem-libsodium-adapter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/alextartan/flysystem-libsodium-adapter/?branch=master)
[![Downloads](https://img.shields.io/badge/dynamic/json.svg?url=https://repo.packagist.org/packages/alextartan/flysystem-libsodium-adapter.json&label=Downloads&query=$.package.downloads.total&colorB=orange)](https://packagist.org/packages/alextartan/flysystem-libsodium-adapter)

## Installation

```bash
composer require alextartan/flysystem-libsodium-adapter
```

## Usage

```php
use AlexTartan\Flysystem\Adapter\Encryption\Libsodium;
use AlexTartan\Flysystem\Adapter\EncryptionAdapterDecorator;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;

$adapter = new MemoryAdapter();
$encryption = new Libsodium($encryptionKey);

$adapterDecorator = new EncryptionAdapterDecorator(
    $adapter, 
    $encryption
);

$filesystem = new Filesystem($adapterDecorator);
```

**Notice**;

Encryption does not work with `writeStream`/`readStream` and `AWS S3` (and probably other remote adapters). 

When using non-local adapters, use `write`/`read`. The downside of this is the high memory usage, as files 
are entirely loaded in memory.

The issue (as far as I've investigated) is related to `ContentLenght` which is not properly calculated.
The encrypted result is bigger than the original.

This is still a work in progress. I hope to get this working with S3 soon.


## Versoning

This library adheres to [semver](https://semver.org/)