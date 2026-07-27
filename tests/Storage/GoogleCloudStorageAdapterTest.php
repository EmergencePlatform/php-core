<?php

namespace Emergence\Tests\Storage;

use Emergence\Storage\GoogleCloudStorageAdapter;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use League\Flysystem\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GoogleCloudStorageAdapterTest extends TestCase
{
    /**
     * @return array<string,array{string|null,string|null}>
     */
    public static function prefixNormalizationProvider(): array
    {
        return [
            'bare' => ['my-prefix', 'my-prefix/'],
            'trailing slash' => ['my-prefix/', 'my-prefix/'],
            'leading slash' => ['/my-prefix', 'my-prefix/'],
            'leading and trailing slashes' => ['/my-prefix/', 'my-prefix/'],
            'redundant slashes' => ['//my-prefix//', 'my-prefix/'],
            'nested' => ['tenants/site-a', 'tenants/site-a/'],
            'nested with slashes' => ['/tenants/site-a/', 'tenants/site-a/'],
            'unset' => [null, null],
            'empty string' => ['', null],
            'lone slash' => ['/', null],
        ];
    }

    /**
     * `my-prefix`, `my-prefix/`, and `/my-prefix` must configure the same
     * `my-prefix/` object prefix; empty-equivalent values must leave the
     * adapter unprefixed (root-relative).
     */
    #[DataProvider('prefixNormalizationProvider')]
    public function testConstructorNormalizesPathPrefix(?string $prefix, ?string $expectedPathPrefix): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            $this->createMock(StorageClient::class),
            $this->createMock(Bucket::class),
            $prefix
        );

        $this->assertSame($expectedPathPrefix, $adapter->getPathPrefix());
    }

    #[DataProvider('prefixNormalizationProvider')]
    public function testSetPathPrefixNormalizes(?string $prefix, ?string $expectedPathPrefix): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            $this->createMock(StorageClient::class),
            $this->createMock(Bucket::class)
        );

        $adapter->setPathPrefix((string) $prefix);

        $this->assertSame($expectedPathPrefix, $adapter->getPathPrefix());
    }

    public function testPrefixedReadAddressesObjectUnderPrefix(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('exists')->willReturn(true);

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('object')
            ->with('my-prefix/original/123.jpg')
            ->willReturn($object);

        $adapter = new GoogleCloudStorageAdapter(
            $this->createMock(StorageClient::class),
            $bucket,
            '/my-prefix' // deliberately denormalized
        );

        $this->assertTrue($adapter->has('original/123.jpg'));
    }

    public function testPrefixedWriteAddressesObjectUnderPrefix(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->method('name')->willReturn('my-prefix/original/123.jpg');
        $object->method('info')->willReturn([
            'updated' => '2026-01-01T00:00:00Z',
            'contentType' => 'image/jpeg',
            'size' => 4,
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('upload')
            ->with(
                'data',
                $this->callback(
                    static fn (array $options): bool => $options['name'] === 'my-prefix/original/123.jpg'
                )
            )
            ->willReturn($object);

        $adapter = new GoogleCloudStorageAdapter(
            $this->createMock(StorageClient::class),
            $bucket,
            'my-prefix/' // deliberately denormalized
        );

        $result = $adapter->write('original/123.jpg', 'data', new Config());

        $this->assertIsArray($result);

        // callers keep addressing prefix-relative paths
        $this->assertSame('original/123.jpg', $result['path']);
    }

    /**
     * Back-compat: without a prefix, reads and writes must address exactly
     * the caller-supplied root-relative object names, as before the prefix
     * option existed.
     */
    public function testUnprefixedReadAndWriteStayRootRelative(): void
    {
        $readObject = $this->createMock(StorageObject::class);
        $readObject->method('exists')->willReturn(true);

        $writtenObject = $this->createMock(StorageObject::class);
        $writtenObject->method('name')->willReturn('original/123.jpg');
        $writtenObject->method('info')->willReturn([
            'updated' => '2026-01-01T00:00:00Z',
            'contentType' => 'image/jpeg',
            'size' => 4,
        ]);

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())
            ->method('object')
            ->with('original/123.jpg')
            ->willReturn($readObject);
        $bucket->expects($this->once())
            ->method('upload')
            ->with(
                'data',
                $this->callback(
                    static fn (array $options): bool => $options['name'] === 'original/123.jpg'
                )
            )
            ->willReturn($writtenObject);

        $adapter = new GoogleCloudStorageAdapter(
            $this->createMock(StorageClient::class),
            $bucket
        );

        $this->assertNull($adapter->getPathPrefix());
        $this->assertTrue($adapter->has('original/123.jpg'));

        $result = $adapter->write('original/123.jpg', 'data', new Config());

        $this->assertIsArray($result);
        $this->assertSame('original/123.jpg', $result['path']);
    }
}
