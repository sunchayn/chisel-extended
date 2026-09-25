<?php

namespace Laravel\Chisel;

use FilesystemIterator;
use Laravel\Chisel\Ast\Source;
use Laravel\Chisel\Filesystem\File;
use Laravel\Chisel\Filesystem\PendingFiles;
use Laravel\Chisel\Node\Npm;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** @phpstan-consistent-constructor */
class Chisel
{
    protected ?Npm $npm = null;

    protected bool $safe = false;

    /** @var list<string> */
    private array $modifiedFiles = [];

    /** @var list<string> */
    private array $removedPaths = [];

    protected function __construct(protected string $directory)
    {
        //
    }

    public static function in(string $directory): static
    {
        return new static($directory);
    }

    public static function script(string $directory): Script
    {
        return new Script($directory);
    }

    /**
     * Makes every operation throw when it yields no update.
     */
    public function safe(bool $safe = true): static
    {
        $this->safe = $safe;

        return $this;
    }

    public function rootDir(): string
    {
        return $this->directory;
    }

    public function files(string ...$paths): PendingFiles
    {
        return new PendingFiles(new File($this->directory, $this->safe), $paths);
    }

    public function file(string $path): PendingFiles
    {
        return $this->files($path);
    }

    public function npm(): Npm
    {
        return $this->npm ??= new Npm($this->directory);
    }

    public function php(string $path): Source
    {
        return new Source($this->path($path), $this->safe);
    }

    public function renamePath(string $from, string $to): static
    {
        $source = $this->absolutePath($from);
        $destination = $this->absolutePath($to);

        if (! file_exists($source) && $this->safe) {
            throw new \RuntimeException("Unable to rename a path that does not exist: {$from}");
        }

        if (! file_exists($source) || $source === $destination) {
            return $this;
        }

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        rename($source, $destination);

        $this->trackRemoved($source);
        $this->trackModified($destination);

        return $this;
    }

    public function copyDirectory(string $source, string $destination): static
    {
        $source = $this->absolutePath($source);
        $destination = $this->absolutePath($destination);

        if (! is_dir($source) && $this->safe) {
            throw new \RuntimeException("Unable to copy a directory that does not exist: {$source}");
        }

        if (! is_dir($source)) {
            return $this;
        }

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination.'/'.substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            copy($item->getPathname(), $target);

            $this->trackModified($target);
        }

        return $this;
    }

    /**
     * Replaces every key with its matching value,
     * across every text file under the root directory.
     *
     * @param  array<string, string>  $replacements
     */
    public function replacePlaceholders(array $replacements): void
    {
        $pattern = sprintf(
            '/%s/',
            implode(
                '|',
                array_map(
                    static fn (string $placeholder): string => preg_quote($placeholder, '/'),
                    array_keys($replacements),
                ),
            ),
        );

        $found = [];

        foreach ($this->textFiles() as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $updated = preg_replace_callback(
                pattern: $pattern,
                callback: function (array $matches) use ($replacements, &$found): string {
                    $found[(string) $matches[0]] = true;

                    return $replacements[(string) $matches[0]];
                },
                subject: $contents,
            ) ?? $contents;

            if ($updated === $contents) {
                continue;
            }

            file_put_contents($file, $updated);

            $this->trackModified($file);
        }

        $missing = array_diff(array_keys($replacements), array_keys($found));

        if ($this->safe && $missing !== []) {
            throw new \RuntimeException('Placeholders not found: '.implode(', ', $missing));
        }
    }

    /**
     * @param  list<string>  $command  The argv-style command to execute.
     * @return array<string, bool|string> Whether the command succeeded, and its combined output.
     */
    public function runCommand(array $command, ?string $cwd = null): array
    {
        $process = proc_open(
            command: $command,
            descriptor_spec: [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            pipes: $pipes,
            cwd: $cwd ?? $this->directory,
        );

        if (! is_resource($process)) {
            return ['success' => false, 'output' => 'Unable to start process.'];
        }

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

        fclose($pipes[1]);

        fclose($pipes[2]);

        return ['success' => proc_close($process) === 0, 'output' => trim($output)];
    }

    public function relativePath(string $path): string
    {
        $dir = str_replace('\\', '/', $this->directory);
        $path = str_replace('\\', '/', $path);

        if (! str_starts_with($path, $dir.'/')) {
            return ltrim(preg_replace('#^\./#', '', $path) ?? $path, '/');
        }

        $relativePath = ltrim(substr($path, strlen($dir)), '/');

        return preg_replace('#^\./#', '', $relativePath) ?? $relativePath;
    }

    /**
     * @return array<string, list<string>>
     */
    public function summary(): array
    {
        sort($this->modifiedFiles);
        sort($this->removedPaths);

        return [
            'modified_files' => $this->modifiedFiles,
            'removed_paths' => $this->removedPaths,
        ];
    }

    private function path(string $path): string
    {
        return $this->directory.'/'.$path;
    }

    private function absolutePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', $this->directory);

        return str_starts_with($normalizedPath, $normalizedRoot.'/') || $normalizedPath === $normalizedRoot
            ? $path
            : $this->directory.'/'.ltrim($path, '/');
    }

    /**
     * @return list<string>
     */
    private function textFiles(): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! $this->isTextFile($file->getPathname())) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function isTextFile(string $path): bool
    {
        $relativePath = $this->relativePath($path);

        if ($this->isSkippedPath($relativePath)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        // A one kilobyte sample is enough to tell a text file from a binary one.
        $sample = fread($handle, 1024);
        fclose($handle);

        // A null byte anywhere in the sample is a reliable signal for binary content.
        return $sample !== false && ! str_contains($sample, "\0");
    }

    private function isSkippedPath(string $relativePath): bool
    {
        $skipped = [
            '.git',
            '.idea',
            'vendor',
            'node_modules',
        ];

        foreach ($skipped as $path) {
            if ($relativePath === $path || str_starts_with($relativePath, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    private function trackModified(string $path): void
    {
        $this->addToSummary($this->modifiedFiles, $this->relativePath($path));
    }

    private function trackRemoved(string $path): void
    {
        $this->addToSummary($this->removedPaths, $this->relativePath($path));
    }

    /**
     * @param  list<string>  $list
     */
    private function addToSummary(array &$list, string $value): void
    {
        $list[] = $value;

        $list = array_values(array_unique($list));
    }
}
