<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\ConvertStaticToSelfRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnUnionTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/src-compat',
        __DIR__.'/handlers',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withSkip([
        // Adding declare(strict_types=1) flips argument/return coercion semantics
        // across the legacy compat layer and the web entrypoint; too risky for
        // code exercised by hundreds of legacy sites.
        SafeDeclareStrictTypesRector::class,

        // Infers `array` param types from dim-fetch/dim-assign usage, but several
        // compat APIs intentionally accept false|array (e.g. DB::extendQueryLog
        // receives false when query logging is disabled) - would introduce
        // TypeErrors.
        StrictArrayParamDimFetchRector::class,

        // Late static binding (static::) is the primary extension mechanism of
        // the Emergence class model; never rewrite it to self:: mechanically.
        ConvertStaticToSelfRector::class,

        // Infers union return types from legacy method bodies; over-constrains
        // public compat APIs and embeds classes this package does not ship
        // (e.g. Emergence\DAV\RootCollection on Site::resolvePath).
        ReturnUnionTypeRector::class,
    ]);
