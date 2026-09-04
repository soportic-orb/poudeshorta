<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    private function value(string $field): mixed
    {
        $v = $this->data[$field] ?? null;
        return is_string($v) ? trim($v) : $v;
    }

    public function required(string $field, string $message): self
    {
        $v = $this->value($field);
        if ($v === null || $v === '' || (is_array($v) && $v === [])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function email(string $field, string $message): self
    {
        $v = $this->value($field);
        if (is_string($v) && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $message): self
    {
        $v = $this->value($field);
        if (is_string($v) && mb_strlen($v) > $max) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function minLen(string $field, int $min, string $message): self
    {
        $v = $this->value($field);
        if (is_string($v) && $v !== '' && mb_strlen($v) < $min) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function accepted(string $field, string $message): self
    {
        $v = $this->value($field);
        if (!in_array((string) $v, ['1', 'on', 'true', 'yes'], true)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function check(string $field, bool $condition, string $message): self
    {
        if (!$condition && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? (string) reset($this->errors) : null;
    }
}
