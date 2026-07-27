<?php

namespace Emergence\Tests\Storage;

use Emergence\Storage\FilesystemFactory;
use Emergence\Storage\GoogleCloudStorageAdapter;
use InvalidArgumentException;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilesystemFactoryTest extends TestCase
{
    private ?string $keyFilePath = null;

    protected function tearDown(): void
    {
        if ($this->keyFilePath !== null && file_exists($this->keyFilePath)) {
            unlink($this->keyFilePath);
        }
    }

    public function testCreatesLocalFilesystem(): void
    {
        $filesystem = FilesystemFactory::createFromConfig([
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/emergence-filesystem-factory-test',
        ]);

        $this->assertInstanceOf(Filesystem::class, $filesystem);
        $this->assertInstanceOf(LocalAdapter::class, $filesystem->getAdapter());
    }

    public function testLocalDriverRequiresRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilesystemFactory::createFromConfig(['driver' => 'local']);
    }

    public function testGcsDriverRequiresBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilesystemFactory::createFromConfig(['driver' => 'gcs']);
    }

    public function testRejectsUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilesystemFactory::createFromConfig(['driver' => 'carrier-pigeon']);
    }

    /**
     * @return array<string,array{array<string,mixed>,string|null}>
     */
    public static function gcsPrefixConfigProvider(): array
    {
        return [
            'no prefix (back-compat)' => [[], null],
            'empty prefix (back-compat)' => [['prefix' => ''], null],
            'bare prefix' => [['prefix' => 'my-prefix'], 'my-prefix/'],
            'trailing slash' => [['prefix' => 'my-prefix/'], 'my-prefix/'],
            'leading slash' => [['prefix' => '/my-prefix'], 'my-prefix/'],
        ];
    }

    /**
     * @param array<string,mixed> $prefixConfig
     */
    #[DataProvider('gcsPrefixConfigProvider')]
    public function testCreatesGcsFilesystemWithNormalizedPrefix(array $prefixConfig, ?string $expectedPathPrefix): void
    {
        $filesystem = FilesystemFactory::createFromConfig($prefixConfig + [
            'driver' => 'gcs',
            'bucket' => 'example-bucket',
            'key_file' => $this->createPlaceholderKeyFile(),
        ]);

        $this->assertInstanceOf(Filesystem::class, $filesystem);

        $adapter = $filesystem->getAdapter();
        $this->assertInstanceOf(GoogleCloudStorageAdapter::class, $adapter);
        $this->assertSame($expectedPathPrefix, $adapter->getPathPrefix());
    }

    /**
     * Write a syntactically valid service-account keyfile with obviously
     * fake, non-secret placeholder values; credentials are only parsed at
     * construction, never used, since these tests perform no API calls.
     */
    private function createPlaceholderKeyFile(): string
    {
        $keyFilePath = tempnam(sys_get_temp_dir(), 'emergence-test-keyfile-');

        if ($keyFilePath === false) {
            $this->fail('could not create temporary keyfile');
        }

        file_put_contents($keyFilePath, json_encode([
            'type' => 'service_account',
            'project_id' => 'placeholder-project',
            'client_email' => 'placeholder@placeholder-project.iam.gserviceaccount.example.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nplaceholder-not-a-real-key\n-----END PRIVATE KEY-----\n",
        ]));

        return $this->keyFilePath = $keyFilePath;
    }
}
