<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/examples')
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'no_extra_blank_lines' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
