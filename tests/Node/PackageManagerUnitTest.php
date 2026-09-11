<?php

declare(strict_types=1);

namespace Tests\Node;

use Generator;
use Laravel\Chisel\Node\PackageManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageManager::class)]
class PackageManagerUnitTest extends TestCase
{
    #[DataProvider('packageManagerProvider')]
    public function test_it_returns_the_expected_commands_for_each_package_manager(
        PackageManager $packageManager,
        array $installCommand,
        array $runCommand,
        array $runCommandWithArguments,
        array $removeCommand,
    ): void {
        // Act & Assert

        $this->assertEquals($installCommand, $packageManager->installCommand());
        $this->assertEquals($runCommand, $packageManager->runCommand('lint'));
        $this->assertEquals($runCommandWithArguments, $packageManager->runCommand('lint', '--fix'));
        $this->assertEquals($removeCommand, $packageManager->removeCommand('vite'));
    }

    public static function packageManagerProvider(): Generator
    {
        yield 'npm' => [
            PackageManager::NPM,
            ['npm', 'install'],
            ['npm', 'run', 'lint'],
            ['npm', 'run', 'lint', '--', '--fix'],
            ['npm', 'remove', 'vite'],
        ];

        yield 'yarn' => [
            PackageManager::YARN,
            ['yarn', 'install'],
            ['yarn', 'lint'],
            ['yarn', 'lint', '--fix'],
            ['yarn', 'remove', 'vite'],
        ];

        yield 'pnpm' => [
            PackageManager::PNPM,
            ['pnpm', 'install'],
            ['pnpm', 'lint'],
            ['pnpm', 'lint', '--fix'],
            ['pnpm', 'remove', 'vite'],
        ];

        yield 'bun' => [
            PackageManager::BUN,
            ['bun', 'install'],
            ['bun', 'run', 'lint'],
            ['bun', 'run', 'lint', '--fix'],
            ['bun', 'remove', 'vite'],
        ];
    }
}
