<?php

namespace Clintwinter\LaravelDto;

class Missing
{
    public function __construct(
        readonly public string $key,
    ) {
        //
    }
}
