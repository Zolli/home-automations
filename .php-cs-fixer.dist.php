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
        '@PER-CS3x0'                                       => true,
        '@PER-CS3x0:risky'                                 => true,
        '@PHP8x5Migration'                                 => true,
        '@PHP8x5Migration:risky'                           => true,
        'binary_operator_spaces'                           => ['default' => 'at_least_single_space', 'operators' => ['=>' => 'align_single_space_minimal_by_scope', '=' => 'align_single_space_minimal']],
        'blank_line_before_statement'                      => ['statements' => ['return', 'throw', 'try']],
        'class_attributes_separation'                      => ['elements' => ['const' => 'none', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none', 'case' => 'none']],
        'explicit_string_variable'                         => true,
        'fully_qualified_strict_types'                     => ['import_symbols' => true],
        'global_namespace_import'                          => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'method_chaining_indentation'                      => true,
        'multiline_promoted_properties'                    => ['minimum_number_of_parameters' => 1],
        'native_function_invocation'                       => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced', 'strict' => true],
        'no_empty_phpdoc'                                  => true,
        'no_superfluous_elseif'                            => true,
        'no_superfluous_phpdoc_tags'                       => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'no_unused_imports'                                => true,
        'no_useless_else'                                  => true,
        'no_useless_return'                                => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'ordered_class_elements'                           => ['order' => ['use_trait', 'case', 'constant', 'property', 'construct', 'phpunit', 'method_public_static', 'method_public', 'method_protected_static', 'method_protected', 'method_private_static', 'method_private']],
        'ordered_imports'                                  => ['imports_order' => ['class', 'function', 'const'], 'case_sensitive' => true],
        'ordered_types'                                    => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'php_unit_attributes'                              => true,
        'php_unit_data_provider_method_order'              => ['placement' => 'before'],
        'php_unit_data_provider_static'                    => true,
        'php_unit_test_case_static_method_calls'           => ['call_type' => 'self'],
        'phpdoc_line_span'                                 => ['case' => 'single', 'class' => 'single', 'const' => 'single', 'function' => 'single', 'method' => 'single', 'property' => 'single'],
        'phpdoc_trim'                                      => true,
        'phpdoc_types_order'                               => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'return_assignment'                                => true,
        'self_accessor'                                    => true,
        'simplified_if_return'                             => true,
        'single_quote'                                     => true,
        'static_lambda'                                    => true,
        'strict_comparison'                                => true,
        'strict_param'                                     => true,
        'trailing_comma_in_multiline'                      => ['after_heredoc' => true, 'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters']],
        'use_arrow_functions'                              => true,
    ])
    ->setFinder($finder);
