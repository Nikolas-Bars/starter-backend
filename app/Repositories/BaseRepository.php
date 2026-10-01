<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Единственный слой, которому разрешено обращаться к БД.
 *
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /**
     * @return class-string<TModel>
     */
    abstract public function model(): string;

    /**
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        $model = $this->model();

        return $model::query();
    }

    /**
     * @return TModel|null
     */
    public function findById(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }
}
