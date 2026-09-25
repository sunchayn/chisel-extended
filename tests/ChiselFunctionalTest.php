<?php

declare(strict_types=1);

namespace Tests;

use Laravel\Chisel\Chisel;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(Chisel::class)]
class ChiselFunctionalTest extends TestCase
{
    public function test_it_throws_in_safe_mode_when_renaming_a_missing_path(): void
    {
        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to rename a path that does not exist: nope');

        // Act

        Chisel::in($this->tempDir)->safe()->renamePath('nope', 'other');
    }

    public function test_it_does_not_throw_outside_safe_mode_when_renaming_a_missing_path(): void
    {
        // Act

        Chisel::in($this->tempDir)->renamePath('nope', 'other');

        // Assert

        $this->assertFileDoesNotExist($this->tempDir.'/other');
    }

    public function test_it_throws_in_safe_mode_when_copying_a_missing_directory(): void
    {
        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to copy a directory that does not exist');

        // Act

        Chisel::in($this->tempDir)->safe()->copyDirectory('nope', 'other');
    }

    public function test_it_throws_in_safe_mode_when_a_placeholder_is_not_found(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello {{name}}');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Placeholders not found: {{missing}}');

        // Act

        Chisel::in($this->tempDir)->safe()->replacePlaceholders([
            '{{name}}' => 'world',
            '{{missing}}' => 'x',
        ]);
    }

    public function test_it_replaces_placeholders_in_safe_mode_when_all_are_found(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello {{name}}');

        // Act

        Chisel::in($this->tempDir)->safe()->replacePlaceholders(['{{name}}' => 'world']);

        // Assert

        $this->assertEquals('hello world', file_get_contents($this->tempDir.'/a.txt'));
    }
}
