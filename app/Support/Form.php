<?php

namespace App\Support;

use App\Dto\ValidationErrors;
use Illuminate\Support\Str;

class Form
{
    /**
     * @var array<string, array{resolver: ValueResolver, callback: callable(ValueResolver): ValueResolver}>
     */
    protected array $fields = [];

    protected array $options = [];

    protected array $arguments = [];

    protected ?ValidationErrors $errors = null;

    protected bool $isInteractive;

    protected array $prompted = [];

    protected ?string $scope = null;

    /**
     * Run the callback with every field it defines namespaced under $scope.
     *
     * A command that creates more than one resource (shipping an app also creates a
     * database cluster) would otherwise reuse one `name` field for all of them, so the
     * first prompt defined wins and every later resource is sent the wrong value.
     */
    public function withScope(string $scope, callable $callback): mixed
    {
        $previous = $this->scope;
        $this->scope = $previous === null ? $scope : $previous.'.'.$scope;

        try {
            return $callback();
        } finally {
            $this->scope = $previous;
        }
    }

    /**
     * @param  callable(ValueResolver): ValueResolver  $callback
     */
    public function prompt(string $key, ?callable $callback = null, ?string $optionOrArgKey = null): ValueResolver
    {
        if ($callback) {
            $this->define($key, $callback, $optionOrArgKey);
        }

        $key = $this->scopedKey($key);

        if (! in_array($key, $this->prompted)) {
            $this->prompted[] = $key;
        }

        $this->errors ??= new ValidationErrors;

        $result = ($this->fields[$key]['callback'])($this->fields[$key]['resolver'])->errors($this->errors);
        $result->retrieve();

        return $result;
    }

    /**
     * @param  callable(ValueResolver): ValueResolver  $callback
     */
    public function define(string $key, callable $callback, ?string $optionOrArgKey = null): ValueResolver
    {
        $scopedKey = $this->scopedKey($key);
        $optionOrArgKey = $optionOrArgKey ?? str($key)->replace('_', '-')->toString();

        $resolutionType = array_key_exists($optionOrArgKey, $this->options) ? 'option' : 'argument';

        // A scoped field belongs to a nested resource, so the command's own options are not its input.
        // An unset option must not fall through to an argument of the same name, such as Symfony's own "command".
        $argOrOptionValue = $this->scope === null
            ? ($resolutionType === 'option' ? $this->options[$optionOrArgKey] : $this->arguments[$optionOrArgKey] ?? null)
            : null;

        $this->fields[$scopedKey] ??= [
            'resolver' => new ValueResolver(
                $key,
                $optionOrArgKey,
                $this->isInteractive,
                $argOrOptionValue,
                $resolutionType,
            ),
            'callback' => $callback,
        ];

        if ($argOrOptionValue !== null && ! in_array($scopedKey, $this->prompted)) {
            $this->prompted[] = $scopedKey;
        }

        return $this->fields[$scopedKey]['resolver'];
    }

    protected function scopedKey(string $key): string
    {
        return $this->scope === null ? $key : $this->scope.'.'.$key;
    }

    public function isInteractive(bool $isInteractive): self
    {
        $this->isInteractive = $isInteractive;

        return $this;
    }

    public function errors(ValidationErrors $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function options($options): self
    {
        $this->options = $options;

        return $this;
    }

    public function arguments($arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->scopedKey($key);

        if (! array_key_exists($key, $this->fields)) {
            return $default;
        }

        return $this->fields[$key]['resolver']->value();
    }

    public function has(string $key): bool
    {
        return array_key_exists($this->scopedKey($key), $this->fields);
    }

    /**
     * The field an error belongs to: the error key itself, or the nearest parent that is a field.
     *
     * The API reports errors on nested input as dotted keys (probes.startup.path), and those
     * usually have no field of their own.
     */
    public function fieldForError(string $errorKey): ?string
    {
        $key = $errorKey;

        while (! $this->has($key)) {
            if (! str_contains($key, '.')) {
                return null;
            }

            $key = Str::beforeLast($key, '.');
        }

        return $key;
    }

    /**
     * Prompt again for every field these errors belong to, once each.
     */
    public function promptForErrors(ValidationErrors $errors): void
    {
        $this->errors = $errors;
        $prompted = [];

        foreach ($errors->all() as $errorKey => $message) {
            $key = $this->fieldForError($errorKey);

            if ($key === null || in_array($key, $prompted, true)) {
                continue;
            }

            // The resolver only asks again when the error sits under its own key.
            if (! $errors->has($key)) {
                $errors->add($key, $message);
            }

            $prompted[] = $key;

            $this->prompt($key);
        }
    }

    /**
     * Whether any of these errors belongs to a field the user can be prompted for again.
     */
    public function canPromptForAnyOf(ValidationErrors $errors): bool
    {
        foreach ($this->fields as $field) {
            $key = $field['resolver']->key;

            $belongs = collect(array_keys($errors->all()))
                ->contains(fn (string $errorKey) => $errorKey === $key || str_starts_with($errorKey, $key.'.'));

            if ($belongs && $field['resolver']->canPromptForInput()) {
                return true;
            }
        }

        return false;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $result = $this->get($key, $default);

        return ($result === null) ? null : (int) $result;
    }

    public function boolean(string $key, ?bool $default = null): ?bool
    {
        $result = $this->get($key, $default);

        // Options arrive as strings, so "false" and "0" must not cast to true.
        return ($result === null) ? null : filter_var($result, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, ValueResolver>
     */
    public function defined(): array
    {
        return collect($this->fields)->pluck('resolver')->toArray();
    }

    /**
     * @return array<string, ValueResolver>
     */
    public function filled(): array
    {
        return collect($this->prompted)->mapWithKeys(fn (string $key) => [
            $key => $this->fields[$key]['resolver'],
        ])->toArray();
    }

    public function hasAnyValues(): bool
    {
        return count($this->filled()) > 0;
    }

    public function isEmpty(): bool
    {
        return ! $this->hasAnyValues();
    }

    public function clear(): self
    {
        $this->fields = [];
        $this->prompted = [];

        return $this;
    }
}
