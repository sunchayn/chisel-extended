<?php

namespace Tests\Node;

use Closure;
use Generator;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Node\Npm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(Npm::class)]
class NpmFunctionalTest extends TestCase
{
    #[DataProvider('npmRemoveCommandProvider')]
    public function test_it_runs_package_manager_remove_in_the_project_directory(?string $lockFile, string $binary): void
    {
        // Arrange

        if ($lockFile !== null) {
            file_put_contents($this->tempDir.'/'.$lockFile, '');
        }

        $log = $this->shimBinary($binary);

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->remove('@laravel/passkeys', 'input-otp'));

        // Assert

        $contents = file_get_contents($log);

        $this->assertStringContainsString(realpath($this->tempDir), $contents);
        $this->assertStringContainsString('remove @laravel/passkeys input-otp', $contents);
    }

    public static function npmRemoveCommandProvider(): Generator
    {
        yield 'defaults to npm' => [null, 'npm'];

        yield 'uses pnpm when lock file exists' => ['pnpm-lock.yaml', 'pnpm'];
    }

    #[DataProvider('npmRunCommandProvider')]
    public function test_it_runs_package_manager_scripts_in_the_project_directory(?string $lockFile, string $binary): void
    {
        // Arrange

        if ($lockFile !== null) {
            file_put_contents($this->tempDir.'/'.$lockFile, '');
        }

        $log = $this->shimBinary($binary);

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->run('lint'));

        // Assert

        $contents = file_get_contents($log);

        $this->assertStringContainsString(realpath($this->tempDir), $contents);
        $this->assertStringContainsString('lint', $contents);
    }

    public static function npmRunCommandProvider(): Generator
    {
        yield 'defaults to npm' => [null, 'npm'];

        yield 'uses yarn when lock file exists' => ['yarn.lock', 'yarn'];

        yield 'uses bun when lock file exists' => ['bun.lockb', 'bun'];
    }

    public function test_it_runs_package_manager_scripts_with_additional_arguments(): void
    {
        // Arrange

        $log = $this->shimBinary('npm');

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->run('lint', '--fix'));

        // Assert

        $contents = file_get_contents($log);

        $this->assertStringContainsString(realpath($this->tempDir), $contents);
        $this->assertStringContainsString('run lint -- --fix', $contents);
    }

    public function test_it_installs_dependencies_with_the_detected_package_manager(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/pnpm-lock.yaml', '');

        $log = $this->shimBinary('pnpm');

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->install());

        // Assert

        $contents = file_get_contents($log);

        $this->assertStringContainsString(realpath($this->tempDir), $contents);
        $this->assertStringContainsString('install', $contents);
    }

    #[DataProvider('lockFileDetectionProvider')]
    public function test_it_detects_the_package_manager_from_lock_files(string $lockFile, string $binary): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/'.$lockFile, '');

        $log = $this->shimBinary($binary);

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->remove('vite'));

        // Assert

        $this->assertStringContainsString('remove vite', file_get_contents($log));
    }

    public static function lockFileDetectionProvider(): Generator
    {
        yield 'yarn.lock' => ['yarn.lock', 'yarn'];

        yield 'pnpm-lock.yaml' => ['pnpm-lock.yaml', 'pnpm'];

        yield 'bun.lock' => ['bun.lock', 'bun'];

        yield 'bun.lockb' => ['bun.lockb', 'bun'];
    }

    #[DataProvider('composerScriptDetectionProvider')]
    public function test_it_detects_the_package_manager_from_composer_scripts_when_no_lock_file_exists(string $script, string $binary): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'scripts' => [
                'dev' => [
                    'Composer\\Config::disableProcessTimeout',
                    $script,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $log = $this->shimBinary($binary);

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->remove('vite'));

        // Assert

        $this->assertStringContainsString('remove vite', file_get_contents($log));
    }

    public static function composerScriptDetectionProvider(): Generator
    {
        yield 'yarn' => ['yarn run dev', 'yarn'];

        yield 'pnpm' => ['pnpm dev', 'pnpm'];

        yield 'bun' => ['bun run dev', 'bun'];
    }

    public function test_it_defaults_to_npm_when_composer_scripts_are_missing(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/composer.json', json_encode([], JSON_THROW_ON_ERROR));

        $log = $this->shimBinary('npm');

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->remove('vite'));

        // Assert

        $this->assertStringContainsString('remove vite', file_get_contents($log));
    }

    public function test_it_ignores_non_string_composer_script_entries_while_detecting_the_package_manager(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'scripts' => [
                'dev' => [
                    ['bun run dev'],
                    'npm run dev',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $log = $this->shimBinary('npm');

        // Act

        $this->withShimmedPath(fn () => Chisel::in($this->tempDir)->npm()->remove('vite'));

        // Assert

        $this->assertStringContainsString('remove vite', file_get_contents($log));
    }

    private function shimBinary(string $binary): string
    {
        $bin = $this->tempDir.'/bin';
        $log = $this->tempDir.'/'.$binary.'.log';

        if (! is_dir($bin)) {
            mkdir($bin, 0777, true);
        }

        file_put_contents($bin.'/'.$binary, "#!/bin/sh\nprintf '%s\n' \"$(pwd)|$*\" > \"$log\"\n");
        chmod($bin.'/'.$binary, 0755);

        return $log;
    }

    private function withShimmedPath(Closure $callback): void
    {
        $originalPath = getenv('PATH') ?: '';
        putenv('PATH='.$this->tempDir.'/bin:'.$originalPath);

        try {
            $callback();
        } finally {
            putenv('PATH='.$originalPath);
        }
    }
}
