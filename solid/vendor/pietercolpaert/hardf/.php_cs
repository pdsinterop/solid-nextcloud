<?php

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'no_empty_phpdoc' => true,
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => true,
        'ordered_imports' => true,
        'phpdoc_summary' => false,
        'protected_to_private' => false,
        'combine_nested_dirname' => true,
     ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
        ->in(__DIR__.'/bin')
        ->in(__DIR__.'/perf')
        ->in(__DIR__.'/src')
        ->in(__DIR__.'/test')
        ->name('*.php')
        ->append([
            __FILE__,
        ])
    );
