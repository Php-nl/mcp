<?php

declare(strict_types=1);

namespace Phpnl\Mcp\Tests\Unit\Prompt;

use Phpnl\Mcp\Prompt\PromptRegistry;
use Phpnl\Mcp\Protocol\ErrorCode;
use Phpnl\Mcp\Tests\TestCase;

final class PromptRegistryTest extends TestCase
{
    private PromptRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new PromptRegistry();
    }

    public function testIsEmptyByDefault(): void
    {
        $this->assertTrue($this->registry->isEmpty());
        $this->assertEmpty($this->registry->all());
    }

    public function testRegisterAndListPrompt(): void
    {
        $this->registry->register('summarize', 'Summarizes text', fn (array $args) => 'Summary');

        $this->assertFalse($this->registry->isEmpty());
        $prompts = $this->registry->all();
        $this->assertCount(1, $prompts);
        $this->assertSame('summarize', $prompts[0]['name']);
        $this->assertSame('Summarizes text', $prompts[0]['description']);
    }

    public function testGetCallsHandlerWithArguments(): void
    {
        $this->registry->register(
            'greet',
            'Greets a user',
            fn (array $args) => "Hello, {$args['name']}!",
        );

        $result = $this->registry->get('greet', ['name' => 'PHP']);

        $this->assertSame('Hello, PHP!', $result);
    }

    public function testDescriptionReturnsCorrectValue(): void
    {
        $this->registry->register('summarize', 'Summarizes text', fn (array $args) => '');

        $this->assertSame('Summarizes text', $this->registry->description('summarize'));
    }

    public function testHasReturnsTrueForRegisteredPrompt(): void
    {
        $this->registry->register('greet', 'Greets', fn (array $args) => 'Hi');

        $this->assertTrue($this->registry->has('greet'));
        $this->assertFalse($this->registry->has('farewell'));
    }

    public function testGetThrowsForUnknownPrompt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::PromptNotFound->value);

        $this->registry->get('missing', []);
    }

    public function testDescriptionReturnsEmptyForUnknownName(): void
    {
        $this->assertSame('', $this->registry->description('nonexistent'));
    }
}
