<?php

namespace Clintwinter\LaravelDto;

use Exception;
use Illuminate\Support\Arr;

class Setter
{
    protected bool $force = false;

    public function __construct(
        readonly public mixed $value,
        protected ?string $key = null
    ) {
        //
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function force(): self
    {
        $this->force = true;

        return $this;
    }

    public function set(array &$arr)
    {
        if (! $this->key) {
            throw new Exception('No key set');
        }

        return $this->setRecursive($arr, explode('.', $this->key), $this->value);
    }

    private function setRecursive(array &$arr, array $segments, mixed $value)
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! Arr::accessible($arr)) {
                $arr = [];
            }

            if ($segments) {
                foreach ($arr as &$inner) {
                    $this->setRecursive($inner, $segments, $value);
                }
            } else {
                foreach ($arr as &$inner) {
                    $inner = value($value, $inner);
                }
            }
        } elseif (Arr::accessible($arr)) {
            if ($segments) {
                if (! Arr::exists($arr, $segment)) {
                    if ($this->force) {
                        $arr[$segment] = [];
                    } else {
                        return $arr;
                    }
                }

                $this->setRecursive($arr[$segment], $segments, $value);
            } elseif ($this->force && ! Arr::exists($arr, $segment)) {
                $arr[$segment] = value($value, $arr[$segment] ?? new Missing($segment));
            } elseif (Arr::exists($arr, $segment)) {
                $arr[$segment] = value($value, $arr[$segment]);
            }
        } else {
            $arr = [];

            if ($segments) {
                $this->setRecursive($arr[$segment], $segments, $value);
            } else {
                $arr[$segment] = value($value, $arr[$segment] ?? null);
            }
        }

        return $arr;
    }
}
