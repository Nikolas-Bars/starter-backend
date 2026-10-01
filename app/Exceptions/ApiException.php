<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\ApiResponder;
use App\Services\Translator;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Базовое исключение API. Наследник задаёт ключ перевода и HTTP-код:
 *
 *     protected string $errorMessage = 'exceptions.auth.invalid_credentials';
 *     protected int    $statusCode   = 401;
 *
 * Ключ должен быть объявлен во всех lang/{locale}/exceptions.php.
 */
abstract class ApiException extends Exception
{
    protected string $errorMessage;

    protected int $statusCode;

    /**
     * @var array<string, mixed>
     */
    protected array $errors = [];

    /**
     * @var array<string, int|float|string>
     */
    protected array $messageReplace = [];

    public function render(): JsonResponse
    {
        return ApiResponder::error($this->getErrorMessage(), $this->statusCode, $this->errors);
    }

    public function getErrorMessage(): string
    {
        return Translator::get($this->errorMessage, $this->messageReplace);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
