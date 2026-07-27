<?php

namespace Emergence\Storage;

use Google\Cloud\Storage\StorageClient;
use InvalidArgumentException;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;

/**
 * Builds Flysystem 1.x filesystems from declarative config arrays, so a
 * deployment can back any storage bucket with local disk or Google Cloud
 * Storage without code changes:
 *
 *     FilesystemFactory::createFromConfig([
 *         'driver' => 'local',
 *         'root' => '/var/data/media',
 *     ]);
 *
 *     FilesystemFactory::createFromConfig([
 *         'driver' => 'gcs',
 *         'bucket' => 'my-site-media',
 *
 *         // optional path prefix within the bucket, so multiple
 *         // deployments can share one bucket under distinct prefixes.
 *         // Normalized to `{prefix}/` form: leading slashes are trimmed
 *         // and exactly one trailing slash is ensured, so `my-prefix`,
 *         // `my-prefix/`, and `/my-prefix` are all equivalent. Omit (or
 *         // pass '') to address the bucket root, unchanged from before
 *         // this option existed.
 *         'prefix' => '',
 *
 *         // optional, else resolved from credentials
 *         'project_id' => null,
 *
 *         // optional path to a service-account JSON key; omit to use
 *         // Application Default Credentials (e.g. the runtime service
 *         // account on GCE/Cloud Run, or GOOGLE_APPLICATION_CREDENTIALS)
 *         'key_file' => null,
 *     ]);
 */
class FilesystemFactory
{
    /**
     * @param array<string,mixed> $config
     */
    public static function createFromConfig(array $config): FilesystemInterface
    {
        $driver = isset($config['driver']) && is_string($config['driver'])
            ? $config['driver']
            : 'local';

        return match ($driver) {
            'local' => new Filesystem(static::createLocalAdapter($config)),
            'gcs', 'google-cloud-storage' => new Filesystem(static::createGoogleCloudStorageAdapter($config)),
            default => throw new InvalidArgumentException("unknown storage driver: {$driver}"),
        };
    }

    /**
     * @param array<string,mixed> $config
     */
    protected static function createLocalAdapter(array $config): AdapterInterface
    {
        $root = static::getStringOption($config, 'root');

        if ($root === null) {
            throw new InvalidArgumentException('local storage driver requires a root path');
        }

        return new LocalAdapter($root);
    }

    /**
     * @param array<string,mixed> $config
     */
    protected static function createGoogleCloudStorageAdapter(array $config): AdapterInterface
    {
        $bucket = static::getStringOption($config, 'bucket');

        if ($bucket === null) {
            throw new InvalidArgumentException('gcs storage driver requires a bucket name');
        }

        // bucket-scoped operations don't require a project id (buckets are
        // globally addressable); silence the client's keyfile notice when
        // credentials (e.g. authorized_user ADC) don't carry one
        $clientConfig = ['suppressKeyFileNotice' => true];

        if (($projectId = static::getStringOption($config, 'project_id')) !== null) {
            $clientConfig['projectId'] = $projectId;
        }

        if (($keyFilePath = static::getStringOption($config, 'key_file')) !== null) {
            $clientConfig['keyFilePath'] = $keyFilePath;
        }

        $client = new StorageClient($clientConfig);

        return new GoogleCloudStorageAdapter(
            $client,
            $client->bucket($bucket),
            static::getStringOption($config, 'prefix')
        );
    }

    /**
     * @param array<string,mixed> $config
     */
    protected static function getStringOption(array $config, string $key): ?string
    {
        if (isset($config[$key]) && is_string($config[$key]) && $config[$key] !== '') {
            return $config[$key];
        }

        return null;
    }
}
