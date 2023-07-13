# Laravel DTOs

Mostly just a plain object that we instantiate with the static `::factory()` method. After that we can call whatever
methods we want on it to build the state. Once we are ready, we call `->create($payload)` and the payload will be
validated with the DTOs data populated and formatted.

Minimal class:

```php
/**
 * @property string $foo
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'foo' => ['required', 'string'],
        ];
    }
}
```

To use it is simple and is reminiscent of using a laravel model.

```php
$dto = TestData::factory()->create(['foo' => 'bar']);

$dto->foo; // 'bar'
$dto->getAttribute('foo'); // 'bar'
$dto->has('foo'); // true
$dto->missing('foo'); // false
$dto->toArray(); // ['foo' => 'bar']
```

Data that has the `sometimes` rule that is not included is simply not included.

When a field is not required to be present, you can use `Missing` as a part of the union type-hint.

```php
/*
 * @property Missing|string $foo
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'foo' => ['sometimes', 'required', 'string'],
        ];
    }
}
```

```php
$dto = TestData::factory()->create([]);

$dto->foo; // Missing{key:"foo"}
$dto->has('foo'); // false
$dto->missing('foo'); // true
$dto->toArray(); // []
```

You may also transform the data after it is validated.

```php
/*
 * @property string $foo
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'foo' => ['required', 'string'],
        ];
    }

    public function format(array $validated): array
    {
        return $this->resolve([
            'foo' => strtoupper($validated['foo']),
        ]);
    }
}
```

```php
$dto = TestData::factory()->create(['foo' => 'bar']);
$dto->foo; // 'BAR'
$dto->getOriginal('foo'); // 'bar'
```

To forget a value is as easy as assigning the key to a new `Missing` class.

```php
/*
 * @property Contact $contact
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'numeric'],
        ];
    }

    public function format(array $validated): array
    {
        return $this->resolve([
            'contact_id' => new Missing('contact_id'),
            'contact' => Contact::find($validated['contact_id']),
        ]);
    }
}
```

```php
$dto = TestData::factory()->create(['contact_id' => 1]);
$dto->contact; // Contact{id:1}
$dto->contact_id; // Missing{key:"contact_id"}
$dto->getOriginal('contact_id'); // 1
$dto->toArray(); // ['contact' => Contact{id:1}]
```

Similar to a JSonResource, you can avoid errors by performing a transformation only when the field is present.

```php
/*
 * @property string $foo
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'foo' => ['sometimes', 'required', 'string'],
        ];
    }

    public function format(array $validated): array
    {
        return $this->resolve([
            'foo' => $this->when('foo', fn () => strtoupper($validated['foo'])),
        ]);
    }
}
```

```php
$dto = TestData::factory()->create(['foo' => 'bar']);
$dto->foo; // 'BAR'

$dto = TestData::factory()->create([]);
$dto->foo; // Missing{key:"foo"}
```

Since these aren't fully built and validated immediately like in `laravel-data`, we can add any properties/methods we
want to the class. This allows us to provide context to the object before creating it.

```php
/*
 * @property Contact $contact
 */
class TestData extends CoreData
{
    readonly public Account $account;

    public function account(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', Rule::exists('contacts', 'id')->where('account_id', $this->account->id)],
        ];
    }

    public function format(array $validated): array
    {
        return $this->resolve([
            'contact_id' => new Missing('contact_id'),
            'contact' => Contact::find($validated['contact_id']),
        ]);
    }
}
```

```php
$account = Account::find(1);

$dto = TestData::factory()->account($account)->create(['contact_id' => 5]);

$dto-contact; // Contact{id:5,account_id:1}
```

`$this->set()` for working with array fields where we want to process each item individually:

```php
/*
 * @property Contact $contact
 */
class TestData extends CoreData
{
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array'],
            'emails.*.value' => ['required', 'string'],
            'emails.*.type' => ['nullable', new Enum(EmailType::class)],
        ];
    }

    public function format(array $validated): array
    {
        return [
            'foo.*.type' => $this->set(function ($value) {
                if ($value instanceof Missing || is_null($value)) {
                    return EmailType::PERSONAL;
                }

                return EmailType::from($value);
            })->force(),
        ];
    }
};
```

```php
$result = TestData::factory()->create(['emails' => [
    ['value' => 'dev@givebutter.test', 'type' => 'work'],
    ['value' => 'joe@givebutter.test'],
    ['value' => 'john@givebutter.test', 'type' => null],
]]);

// $results->emails output:

[
    'emails' => [
        ['value' => '...', 'type' => EmailType::WORK],
        ['value' => '...', 'type' => EmailType::PERSONAL],
        ['value' => '...', 'type' => EmailType::PERSONAL],
]
```

The `force()` flag is useful for when you want to perform the action regardless of whether the key exists. In the last
example, we set an email type for all emails, including the 2nd one where a type wasn't provided at all. We have
to check if the value in the callback is `Missing`. Without force, it would have ignored that email item.

TODO
* casting
*
