# Libsodium Adapter for Flysystem

Work in progress...

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