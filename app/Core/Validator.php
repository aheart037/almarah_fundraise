<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Server-side validator. Every rule that matters to security (money, status,
 * role, gateway, currency, environment) is validated by allowlist.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $clean = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    /**
     * @param array<string,string> $rules  field => "required|max:255|email"
     * @param array<string,string> $labels field => "Friendly name"
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data);
        $validator->validate($rules, $labels);
        return $validator;
    }

    /** @param array<string,string> $rules */
    public function validate(array $rules, array $labels = []): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $data = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            foreach (explode('|', $ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }

                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
                $label = $labels[$field] ?? ucfirst(str_replace(['_', '.'], ' ', $field));

                if ($name === 'nullable') {
                    if ($value === null || $value === '' ) {
                        $this->clean[$field] = null;
                        break;
                    }
                    continue;
                }

                $isAbsent = $value === null || $value === '';

                if ($name === 'required' && $isAbsent) {
                    $this->addError($field, "{$label} is required.");
                    break;
                }

                if ($isAbsent) {
                    continue;
                }

                $this->applyRule($field, $label, $name, $argument, $value);
            }

            if (!array_key_exists($field, $this->clean) && !isset($this->errors[$field])) {
                $this->clean[$field] = is_string($data) ? trim($data) : $data;
            }
        }
    }

    private function applyRule(string $field, string $label, string $name, ?string $argument, mixed $value): void
    {
        switch ($name) {
            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} must be a valid email address.");
                }
                break;

            case 'min':
                if (mb_strlen((string) $value) < (int) $argument) {
                    $this->addError($field, "{$label} must be at least {$argument} characters.");
                }
                break;

            case 'max':
                if (mb_strlen((string) $value) > (int) $argument) {
                    $this->addError($field, "{$label} may not be longer than {$argument} characters.");
                }
                break;

            case 'min_value':
                if (!is_numeric($value) || (float) $value < (float) $argument) {
                    $this->addError($field, "{$label} must be at least {$argument}.");
                }
                break;

            case 'max_value':
                if (!is_numeric($value) || (float) $value > (float) $argument) {
                    $this->addError($field, "{$label} may not exceed {$argument}.");
                }
                break;

            case 'int':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "{$label} must be a whole number.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "{$label} must be a number.");
                }
                break;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, "{$label} confirmation does not match.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $argument);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, "{$label} is not an accepted value.");
                }
                break;

            case 'slug':
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                    $this->addError($field, "{$label} may only contain lowercase letters, numbers and hyphens.");
                }
                break;

            case 'url':
                $url = (string) $value;
                if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    $this->addError($field, "{$label} must be a valid URL.");
                }
                break;

            case 'https':
                if (!str_starts_with(strtolower((string) $value), 'https://')) {
                    $this->addError($field, "{$label} must use HTTPS.");
                }
                break;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, "{$label} must be a valid date.");
                }
                break;

            case 'after':
                if (strtotime((string) $value) <= strtotime((string) $argument)) {
                    $this->addError($field, "{$label} must be after {$argument}.");
                }
                break;

            case 'money':
                if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $value)) {
                    $this->addError($field, "{$label} must be a valid amount.");
                }
                break;

            case 'boolean':
                if (!in_array($value, [true, false, 1, 0, '1', '0', 'on', 'off'], true)) {
                    $this->addError($field, "{$label} must be true or false.");
                }
                break;

            case 'token':
                if (!preg_match('/^[A-Za-z0-9_\-]{16,128}$/', (string) $value)) {
                    $this->addError($field, "{$label} is not a valid token.");
                }
                break;

            default:
                break;
        }

        if (!isset($this->errors[$field])) {
            $this->clean[$field] = $value;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
        unset($this->clean[$field]);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->clean;
    }

    public function validatedOrFail(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
        return $this->clean;
    }
}
