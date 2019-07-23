# Libsodium Adapter for Flysystem

Work in progress...

Current build status
===

[![Build Status](https://travis-ci.org/alextartan/flysystem-libsodium-adapter.svg?branch=master)](https://travis-ci.org/alextartan/flysystem-libsodium-adapter)
[![Coverage Status](https://coveralls.io/repos/github/alextartan/flysystem-libsodium-adapter/badge.svg?branch=master)](https://coveralls.io/github/alextartan/flysystem-libsodium-adapter?branch=master)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/alextartan/flysystem-libsodium-adapter/master)](https://infection.github.io)
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