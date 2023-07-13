<?php

namespace Clintwinter\LaravelDto;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class Data implements Arrayable
{
    protected array $original = [];

    protected array $attributes = [];

    protected bool $created = false;

    public static function factory(): static
    {
        return app(static::class);
    }

    public function create(array $data): self
    {
        $validator = Validator::make(
            $data, $this->rules(), $this->messages(), $this->attributes(),
        );

        $this->original = $validator->validate();
        $this->attributes = $validator->validate();

        $this->created = true;

        $this->afterValidation();

        return $this;
    }

    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function getOriginal(?string $key): mixed
    {
        $this->checkCreated();

        if (! $key) {
            return null;
        }

        if (! Arr::has($this->original, $key)) {
            if (! Arr::exists($this->rules(), $key)) {
                throw new Exception("Property \"{$key}\" does not exist.");
            } else {
                return new Missing($key);
            }
        }

        return Arr::get($this->original, $key);
    }

    public function getAttribute(?string $key): mixed
    {
        $this->checkCreated();

        if (! $key) {
            return null;
        }

        if (! Arr::has($this->attributes, $key)) {
            if (! Arr::exists($this->rules(), $key)) {
                throw new Exception("Property \"{$key}\" does not exist.");
            } else {
                return new Missing($key);
            }
        }

        return Arr::get($this->attributes, $key);
    }

    public function has(?string $key): bool
    {
        $this->checkCreated();

        if (! $key) {
            return false;
        }

        return ! $this->getAttribute($key) instanceof Missing;
    }

    public function missing(?string $key): bool
    {
        $this->checkCreated();

        if (! $key) {
            return true;
        }

        return $this->getAttribute($key) instanceof Missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->checkCreated();

        return $this->attributes;
    }

    protected function resolve(array $changes): array
    {
        return [
            ...$this->attributes,
            ...$changes,
        ];
    }

    protected function when(string $key, callable $callback): mixed
    {
        if (! Arr::exists($this->attributes, $key)) {
            return new Missing($key);
        }

        return $callback();
    }

    protected function afterValidation(): void
    {
        //
    }

    protected function replace(callable $callback): void
    {
        $result = $this->attributes;

        $response = $callback($this->attributes);

        foreach ($response as $key => $value) {
            if ($value instanceof Setter) {
                if (! $value->getKey()) {
                    $value->key($key);
                }

                $value->set($result);

                continue;
            }

            if ($value instanceof Missing) {
                Arr::forget($result, $key);

                continue;
            }

            data_set($result, $key, value($value, Arr::get($this->attributes, $key, null)));
        }

        $this->attributes = $result;
    }

//     protected function formatAttributes(): void
//     {
//         $result = $this->original;
//         foreach ($this->format($this->original) as $key => $value) {
//             if ($value instanceof Setter) {
//                 if (! $value->getKey()) {
//                     $value->key($key);
//                 }

//                 $value->set($result);
//             }
//         }

//         $this->attributes = $result;
//     }

    /**
     * @throws Exception
     */
    protected function checkCreated(): void
    {
        if (! $this->created) {
            throw new Exception('Must finish creating the DTO via create().');
        }
    }

    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    protected function set(mixed $value): Setter
    {
        return new Setter($value);
    }
}
