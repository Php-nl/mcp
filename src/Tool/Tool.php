<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

use Closure;
use Phpnl\Mcp\Exception\InvalidToolArgumentsException;

final readonly class Tool
{
    public function __construct(
        public string $name,
        public string $description,
        public Closure $handler,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $reflection = new \ReflectionFunction($this->handler);
        $properties = [];
        $required = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $property = ['type' => $this->resolveJsonSchemaType($parameter->getType())];

            $descriptionAttribute = $parameter->getAttributes(Description::class)[0] ?? null;

            if ($descriptionAttribute !== null) {
                $property['description'] = $descriptionAttribute->newInstance()->value;
            }

            $properties[$name] = $property;

            if (! $parameter->isOptional()) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Validates arguments against the tool's JSON Schema before invocation.
     *
     * @param array<string, mixed> $arguments
     *
     * @throws InvalidToolArgumentsException if a required argument is missing or has the wrong type
     */
    public function validate(array $arguments): void
    {
        $schema = $this->schema();

        foreach ($schema['required'] as $name) {
            if (! array_key_exists($name, $arguments)) {
                throw new InvalidToolArgumentsException("Missing required argument: {$name}");
            }
        }

        foreach ($schema['properties'] as $name => $property) {
            if (! array_key_exists($name, $arguments)) {
                continue;
            }

            $expectedType = $property['type'];

            if (! $this->matchesType($arguments[$name], $expectedType)) {
                $label = is_array($expectedType) ? implode('|', $expectedType) : $expectedType;

                throw new InvalidToolArgumentsException(
                    "Argument '{$name}' must be of type {$label}, got " . get_debug_type($arguments[$name]),
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(array $arguments): mixed
    {
        $reflection = new \ReflectionFunction($this->handler);

        $ordered = array_map(
            fn (\ReflectionParameter $parameter) => $arguments[$parameter->getName()]
                ?? ($parameter->isOptional() ? $parameter->getDefaultValue() : null),
            $reflection->getParameters(),
        );

        return ($this->handler)(...$ordered);
    }

    /**
     * @return string|list<string>
     */
    private function resolveJsonSchemaType(?\ReflectionType $type): string|array
    {
        if ($type instanceof \ReflectionNamedType) {
            $jsonType = $this->mapPhpType($type->getName());

            if ($type->allowsNull() && $type->getName() !== 'null') {
                return [$jsonType, 'null'];
            }

            return $jsonType;
        }

        if ($type instanceof \ReflectionUnionType) {
            $namedTypes = array_filter(
                $type->getTypes(),
                fn (\ReflectionType $t) => $t instanceof \ReflectionNamedType && $t->getName() !== 'null',
            );

            $hasNull = count($namedTypes) < count($type->getTypes());

            if (count($namedTypes) === 1) {
                /** @var \ReflectionNamedType $singleType */
                $singleType = array_values($namedTypes)[0];
                $mapped = $this->mapPhpType($singleType->getName());

                return $hasNull ? [$mapped, 'null'] : $mapped;
            }
        }

        return 'string';
    }

    private function mapPhpType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * @param string|list<string> $type
     */
    private function matchesType(mixed $value, string|array $type): bool
    {
        if (is_array($type)) {
            foreach ($type as $t) {
                if ($this->matchesType($value, $t)) {
                    return true;
                }
            }

            return false;
        }

        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'null' => $value === null,
            default => true,
        };
    }
}
