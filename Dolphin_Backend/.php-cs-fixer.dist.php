<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/routes')
    ->in(__DIR__ . '/database')
    ->in(__DIR__ . '/config')
    // Exclude directories that shouldn't be formatted
    ->exclude('vendor')
    ->exclude('bootstrap/cache')
    ->exclude('storage');

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true, // Use the modern PSR-12 standard
        'array_syntax' => ['syntax' => 'short'], // Enforce [] instead of array()
        'ordered_imports' => true, // Sort your 'use' statements alphabetically
        'single_quote' => true, // Use single quotes for simple strings
    ])
    ->setFinder($finder);