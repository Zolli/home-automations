<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/apps', __DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__DIR__ . '/services.php', __FILE__]);

return new Config()
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer.cache')
    ->setRules([
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x5Migration' => true,
        '@PHP8x5Migration:risky' => true,
        'no_unused_imports' => true,
        'method_chaining_indentation' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'case_sensitive' => true],
    ])
    ->setFinder($finder);
