<?php

namespace CodeHuiter\Pattern\Result;

class ClientResult
{
    public const SUCCESS = 1;
    public const INCORRECT_FIELD = 2;
    public const ERROR = 3;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string[]
     */
    protected $fields;

    /**
     * Result constructor.
     * @param int $type
     * @param string $message
     * @param string[] $fields
     */
    private function __construct(int $type, string $message, array $fields)
    {
        $this->type = $type;
        $this->message = $message;
        $this->fields = $fields;
    }

    /**
     * @param string $message
     * @return ClientResult
     */
    public static function createSuccess(string $message = ''): ClientResult
    {
        return new ClientResult(self::SUCCESS, $message, []);
    }

    /**
     * @param string $message
     * @param string $field
     * @return ClientResult
     */
    public static function createIncorrectField(string $message, string $field): ClientResult
    {
        return new ClientResult(self::INCORRECT_FIELD, $message, [$field]);
    }

    /**
     * @param string $message
     * @return ClientResult
     */
    public static function createError(string $message): ClientResult
    {
        return new ClientResult(self::ERROR, $message, []);
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->type === self::SUCCESS;
    }

    public function isIncorrectField(): bool
    {
        return $this->type === self::INCORRECT_FIELD;
    }

    public function isError(): bool
    {
        return $this->type === self::ERROR;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}