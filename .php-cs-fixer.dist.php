<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => ['=' => 'align_single_space_minimal', '=>' => 'align_single_space_minimal'],
        ],
    ])
    ->setFinder($finder);
