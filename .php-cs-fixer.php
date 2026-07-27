<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/src',
        __DIR__.'/src-compat',
        __DIR__.'/handlers',
        __DIR__.'/tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setRiskyAllowed(false)
    ->setFinder($finder);
