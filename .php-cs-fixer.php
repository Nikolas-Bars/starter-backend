<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/lang')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/database')
    ->in(__DIR__ . '/tests');

return (new Config())
    ->setRiskyAllowed(true) // Запрещаю потенциально рискованные правила (например, изменяющие поведение кода)
    ->setRules([
        '@PSR12'          => true,
        '@PHP81Migration' => true,

        // Применяет однострочные комментарии вместо многострочных
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Приводит все имена классов, интерфейсов, трейтов и функций к стилю PascalCase
        'class_attributes_separation' => ['elements' => ['method' => 'one']],

        // Удаляет неиспользуемые use импорты
        'no_unused_imports' => true,

        // Добавляет пустую строку после <?php
        'blank_line_after_opening_tag' => true,

        // Автоматически добавляет директиву `declare(strict_types=1)` в файлы
        'declare_strict_types' => false,

        // Заменяет функции `array()` на короткий синтаксис `[]`
        'array_syntax' => ['syntax' => 'short'],

        // Гарантирует, что ключевые слова `elseif` используются вместо `else if`
        'elseif' => true,

        // Добавляет запятые в конце каждого элемента многострочного массива
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

        // Добавляем правило для отступов в массивах
        'array_indentation' => true,

        // Удаляем запятую в конце однострочного массива
        'no_trailing_comma_in_singleline_array' => true,

        // Удаляем лишние пробелы внутри скобок массива
        'trim_array_spaces' => true,

        // Гарантируем правильное расположение точки с запятой
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],

        // Удаляет пробелы перед и после логических операторов (например, `&&`, `||`)
        'logical_operators' => true,

        'method_argument_space' => [
            'on_multiline'                     => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],

        'native_function_invocation' => [
            // Применять только в файлах с пространствами имён (чтобы не ломать глобальные helpers)
            'scope' => 'namespaced',
            // Не быть чрезмерно строгим (не вмешиваться, если может быть переопределение)
            'strict'  => false,
            'include' => ['@compiler_optimized', '@internal'],
        ],

        // Приводит конструкцию `function` и `fn` (анонимные функции) к одному стилю
        'function_declaration' => ['closure_function_spacing' => 'one', 'closure_fn_spacing' => 'none'],

        'class_definition' => ['space_before_parenthesis' => false],

        // Удаляет пустые строки в начале и конце класса
        'no_extra_blank_lines' => ['tokens' => ['extra']],

        // Убирает лишние пробелы внутри круглых скобок
        'no_spaces_inside_parenthesis' => true,

        // Убирает пробелы после открывающих и перед закрывающими тегами PHP (`<?php` и "?)
        'no_whitespace_in_blank_line' => true,

        // Приводит стандартные блоки `switch-case` к правильному форматированию
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space'              => true,

        // Удаляет пустые комментарии (например, `//`)
        'no_empty_comment' => true,

        // Приводит константы (например, `true`, `false`, `null`) к нижнему регистру
        'constant_case' => ['case' => 'lower'],

        // Гарантирует правильное использование фигурных скобок в многострочных конструкциях
        'braces' => [
            'position_after_functions_and_oop_constructs' => 'next',
            // Скобка на новой строке для функций, методов, классов
            'position_after_control_structures' => 'next',
            // Скобка на новой строке для if, for, while и т.д.
            'position_after_anonymous_constructs' => 'next',
            // Скобка на новой строке для анонимных классов и замыканий
            'allow_single_line_closure' => false,
            // Запрещает однострочные замыкания
            'allow_single_line_anonymous_class_with_empty_body' => false,
            // Запрещает однострочные анонимные классы
        ],

        // Требует, чтобы все файловые структуры были Unix-style (LF) вместо Windows-style (CRLF)
        'line_ending' => true,

        // Выравнивает присвоения (`=`) в столбцах, если несколько переменных инициализируются в строках друг под другом
        'align_multiline_comment' => true,

        // Устанавливает правильные отступы в файлах с отступами, состоящими из 4 пробелов
        'indentation_type'       => true,
        'binary_operator_spaces' => [
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'doctrine_annotation_spaces' => [
            'after_argument_assignments'      => false,
            'after_array_assignments_equals'  => false,
            'after_array_assignments_colon'   => true,
            'before_array_assignments_equals' => false,
            'before_array_assignments_colon'  => false,
        ],
        'doctrine_annotation_indentation' => true,
        'doctrine_annotation_braces'      => true,
        'phpdoc_align'                    => [
            'align' => 'vertical', // Выравнивает теги PHPDoc вертикально
            'tags'  => ['property', 'property-read', 'param', 'return'], // Применяется к указанным тегам
        ],
        'phpdoc_line_span' => [
            'property' => 'multi', // Свойства PHPDoc всегда многострочные
        ],
        'phpdoc_trim'  => true, // Удаляет лишние пробелы в PHPDoc
        'phpdoc_order' => true, // Сортирует теги PHPDoc в логическом порядке

        // Автоматически выносит длинные FQCN в use секцию
        'global_namespace_import' => [
            'import_classes' => true,
        ],

        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],

        // Сортирует импорты и группирует их
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
    ])
    ->setIndent('    ') // 4 пробела для отступов
    ->setFinder($finder);  // Применяем настройки поиска файлов
