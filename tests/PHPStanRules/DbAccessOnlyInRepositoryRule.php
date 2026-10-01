<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * DB Access Only In Repository Rule.
 *
 * Все запросы к БД должны идти через слой Repository. Обращаться к БД напрямую
 * (DB-фасад, Eloquent query builder, query-методы моделей) можно только внутри
 * классов App\Modules\*\Repositories\*.
 *
 * Любой другой слой (Controllers, Actions, Tasks, Requests, Resources, Jobs,
 * Listeners и т.д.) должен получать данные через Repository.
 *
 * Разрешено везде:
 * - управление транзакциями через DB-фасад (transaction/beginTransaction/commit/rollBack/afterCommit);
 * - слой моделей (scopes, аксессоры, связи внутри самой Eloquent-модели);
 * - инфраструктурный слой миграций/сидеров/фабрик;
 * - слой Http\Filters — фильтры получают Builder от репозитория и лишь
 *   доуточняют запрос (применяются исключительно из Repository::apply()).
 *
 * @implements Rule<Node>
 */
final class DbAccessOnlyInRepositoryRule implements Rule
{
    /**
     * @var array<string, true>
     */
    private array $dbFacadeMethodsAllowedEverywhere = [
        'transaction'      => true,
        'begintransaction' => true,
        'commit'           => true,
        'rollback'         => true,
        'aftercommit'      => true,
    ];

    /**
     * Классы, экземпляры которых представляют собой обращение к БД.
     *
     * @var list<string>
     */
    private array $queryObjectClasses = [
        'Illuminate\\Database\\Eloquent\\Builder',
        'Illuminate\\Database\\Query\\Builder',
        'Illuminate\\Database\\Eloquent\\Relations\\Relation',
        'Illuminate\\Database\\Eloquent\\Model',
    ];

    /**
     * Методы, означающие построение/выполнение запроса или запись в БД.
     *
     * @var array<string, true>
     */
    private array $queryMethods = [
        // выборка
        'get'            => true,
        'first'          => true,
        'firstorfail'    => true,
        'firstwhere'     => true,
        'sole'           => true,
        'find'           => true,
        'findorfail'     => true,
        'findmany'       => true,
        'all'            => true,
        'value'          => true,
        'pluck'          => true,
        'count'          => true,
        'exists'         => true,
        'doesntexist'    => true,
        'max'            => true,
        'min'            => true,
        'sum'            => true,
        'avg'            => true,
        'aggregate'      => true,
        'paginate'       => true,
        'simplepaginate' => true,
        'cursorpaginate' => true,
        'chunk'          => true,
        'chunkbyid'      => true,
        'each'           => true,
        'cursor'         => true,
        'lazy'           => true,
        'lazybyid'       => true,
        'tobase'         => true,
        // построение
        'where'           => true,
        'wherein'         => true,
        'wherenotin'      => true,
        'wherenull'       => true,
        'wherenotnull'    => true,
        'wherebetween'    => true,
        'wheredate'       => true,
        'wherecolumn'     => true,
        'wherehas'        => true,
        'wheredoesnthave' => true,
        'orwhere'         => true,
        'having'          => true,
        'groupby'         => true,
        'orderby'         => true,
        'latest'          => true,
        'oldest'          => true,
        'limit'           => true,
        'take'            => true,
        'offset'          => true,
        'skip'            => true,
        'join'            => true,
        'leftjoin'        => true,
        'rightjoin'       => true,
        'crossjoin'       => true,
        'select'          => true,
        'selectraw'       => true,
        'addselect'       => true,
        'distinct'        => true,
        'with'            => true,
        'withcount'       => true,
        'has'             => true,
        'doesnthave'      => true,
        'query'           => true,
        'newquery'        => true,
        'newmodelquery'   => true,
        'on'              => true,
        'withtrashed'     => true,
        'onlytrashed'     => true,
        // запись / персистентность
        'insert'         => true,
        'insertgetid'    => true,
        'insertorignore' => true,
        'upsert'         => true,
        'update'         => true,
        'updateorcreate' => true,
        'updateorinsert' => true,
        'create'         => true,
        'createmany'     => true,
        'firstorcreate'  => true,
        'firstornew'     => true,
        'save'           => true,
        'savemany'       => true,
        'push'           => true,
        'delete'         => true,
        'forcedelete'    => true,
        'destroy'        => true,
        'truncate'       => true,
        'increment'      => true,
        'decrement'      => true,
        'restore'        => true,
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @param  Node                      $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Доступ к БД разрешён только в самом слое репозиториев, а также в
        // инфраструктурных слоях (миграции/сидеры/фабрики).
        if ($this->isInAllowedLayer($scope)) {
            return [];
        }

        if ($node instanceof StaticCall) {
            return $this->checkStaticCall($node, $scope);
        }

        if ($node instanceof MethodCall) {
            return $this->checkMethodCall($node, $scope);
        }

        return [];
    }

    /**
     * @param  StaticCall                $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkStaticCall(StaticCall $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $methodName = $this->methodName($node->name);
        if ($methodName === null) {
            return [];
        }

        $className = $this->resolveClassName($node->class->toString(), $scope);

        // DB-фасад: запрещены любые методы, кроме управления транзакциями.
        if ($this->isDbFacade($className)) {
            if (isset($this->dbFacadeMethodsAllowedEverywhere[$methodName])) {
                return [];
            }

            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Запрещено обращаться к БД через DB::%s() вне репозитория. Все запросы к БД должны идти через слой Repository.',
                        $methodName,
                    ),
                )->line($node->getLine())->identifier('db.access.facade')->build(),
            ];
        }

        // Статические query-методы модели: User::where(...), Game::query() и т.п.
        if ($this->isQueryObjectClass($className) && isset($this->queryMethods[$methodName])) {
            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Запрещено выполнять запрос к БД %s::%s() вне репозитория. Все запросы к БД должны идти через слой Repository.',
                        $className,
                        $methodName,
                    ),
                )->line($node->getLine())->identifier('db.access.model.static')->build(),
            ];
        }

        return [];
    }

    /**
     * @param  MethodCall                $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkMethodCall(MethodCall $node, Scope $scope): array
    {
        $methodName = $this->methodName($node->name);
        if ($methodName === null || !isset($this->queryMethods[$methodName])) {
            return [];
        }

        $callerType = $scope->getType($node->var);

        foreach ($callerType->getObjectClassNames() as $className) {
            if ($this->isQueryObjectClass($className)) {
                return [
                    RuleErrorBuilder::message(
                        \sprintf(
                            'Запрещено выполнять запрос к БД ->%s() на %s вне репозитория. Все запросы к БД должны идти через слой Repository.',
                            $methodName,
                            $className,
                        ),
                    )->line($node->getLine())->identifier('db.access.query')->build(),
                ];
            }
        }

        return [];
    }

    private function isInAllowedLayer(Scope $scope): bool
    {
        // Миграции — анонимные классы (return new class extends Migration),
        // поэтому надёжнее опираться на путь файла, а не на имя класса.
        $file = $scope->getFile();
        if (
            \str_contains($file, '/Database/Migrations/')
            || \str_contains($file, '/database/migrations/')
            || \str_contains($file, '/Database/Seeders/')
            || \str_contains($file, '/database/seeders/')
            || \str_contains($file, '/Database/Factories/')
            || \str_contains($file, '/database/factories/')
        ) {
            return true;
        }

        $classReflection = $scope->getClassReflection();

        // Вне контекста класса (глобальные функции/замыкания) не проверяем.
        if ($classReflection === null) {
            return true;
        }

        $className = $classReflection->getName();

        return \str_contains($className, '\\Repositories\\')
            || \str_ends_with($className, 'Repository')
            || \str_contains($className, '\\Http\\Filters\\')
            || \str_contains($className, '\\Models\\')
            // Подсистема полнотекстового поиска/индексации: читает модели для
            // построения поисковых документов — это read-слой доступа к данным.
            || \str_contains($className, '\\Search\\')
            // Консольные команды — служебные скрипты (разовые пересчёты и т.п.).
            || \str_contains($className, '\\Console\\Commands\\')
            || $this->isSubclassOfAny($className, [
                'Illuminate\\Database\\Eloquent\\Model',
                'Illuminate\\Database\\Migrations\\Migration',
                'Illuminate\\Database\\Seeder',
                'Illuminate\\Database\\Eloquent\\Factories\\Factory',
            ]);
    }

    /**
     * @param list<string> $parentClasses
     */
    private function isSubclassOfAny(string $className, array $parentClasses): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        foreach ($parentClasses as $parentClass) {
            if ($reflection->isSubclassOf($parentClass)) {
                return true;
            }
        }

        return false;
    }

    private function isDbFacade(string $className): bool
    {
        return $className === 'Illuminate\\Support\\Facades\\DB'
            || $className === 'DB'
            || \str_ends_with($className, '\\DB');
    }

    private function isQueryObjectClass(string $className): bool
    {
        if (\in_array($className, $this->queryObjectClasses, true)) {
            return true;
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        foreach ($this->queryObjectClasses as $queryClass) {
            if ($reflection->isSubclassOf($queryClass)) {
                return true;
            }
        }

        return false;
    }

    private function resolveClassName(string $className, Scope $scope): string
    {
        if (!\str_contains($className, '\\')) {
            $namespace = $scope->getNamespace();
            if ($namespace !== null && $this->reflectionProvider->hasClass($namespace . '\\' . $className)) {
                return $namespace . '\\' . $className;
            }
        }

        return $className;
    }

    private function methodName(Node\Identifier|Node\Expr $name): ?string
    {
        if (!$name instanceof Node\Identifier) {
            return null;
        }

        return \strtolower($name->toString());
    }
}
