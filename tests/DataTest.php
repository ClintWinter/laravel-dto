<?php

namespace Tests;

use Clintwinter\LaravelDto\Data;
use Clintwinter\LaravelDto\Missing;
use Tests\TestCase;

class DataTest extends TestCase
{
    public function test_sanity()
    {
        $TestData = new class extends Data {
            public function rules(): array {
                return ['name' => ['required', 'string']];
            }
        };

        $result = $TestData::factory()->create(['name' => 'test']);

        $this->assertSame(['name' => 'test'], $result->toArray());
        $this->assertSame('test', $result->name);
        $this->assertSame('test', $result->getAttribute('name'));
        $this->assertSame('test', $result->getOriginal('name'));
        $this->assertTrue($result->has('name'));
        $this->assertFalse($result->missing('name'));
    }

    public function test_missing()
    {
        $TestData = new class extends Data {
            public function rules(): array {
                return ['name' => ['sometimes', 'string']];
            }
        };

        $result = $TestData::factory()->create([]);

        $this->assertSame([], $result->toArray());
        $this->assertInstanceOf(Missing::class, $result->name);
        $this->assertInstanceOf(Missing::class, $result->getAttribute('name'));
        $this->assertInstanceOf(Missing::class, $result->getOriginal('name'));
        $this->assertFalse($result->has('name'));
        $this->assertTrue($result->missing('name'));
    }

    public function test_null_value()
    {
        $TestData = new class extends Data {
            public function rules(): array {
                return ['name' => ['nullable', 'string']];
            }
        };

        $result = $TestData::factory()->create(['name' => null]);

        $this->assertSame(['name' => null], $result->toArray());
        $this->assertNull($result->name);
        $this->assertNull($result->getAttribute('name'));
        $this->assertNull($result->getOriginal('name'));
        $this->assertTrue($result->has('name'));
        $this->assertFalse($result->missing('name'));
    }
}
