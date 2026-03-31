<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tool;

use Closure;

final readonly class Tool
{
    public function __construct(
        public string $name,
        public string $description,
        public Closure $handler,
    ) {}

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
}
