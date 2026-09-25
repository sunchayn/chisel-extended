<?php

namespace Laravel\Chisel\Filesystem;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class File
{
    public function __construct(protected string $directory, protected bool $safe = false)
    {
        //
    }

    public function delete(string ...$paths): void
    {
        foreach ($paths as $path) {
            $this->removePath($this->directory.'/'.$path);
        }
    }

    public function replace(string $file, string $search, string $replace): void
    {
        if (! $this->exists($file)) {
            return;
        }

        $this->write($file, str_replace($search, $replace, $this->read($file)));
    }

    public function removeLinesContaining(string $file, string $content): void
    {
        if (! $this->exists($file)) {
            return;
        }

        $lines = explode("\n", $this->read($file));
        $lines = array_values(array_filter($lines, fn (string $line): bool => ! str_contains($line, $content)));

        $this->write($file, implode("\n", $lines));
    }

    public function removeSectionMarkers(string $file, string $tag): void
    {
        $this->rewriteSection($file, $tag, keepContents: true);
    }

    public function removeSection(string $file, string $tag): void
    {
        $this->rewriteSection($file, $tag, keepContents: false);
    }

    /**
     * Inserts a new line immediately after the first line that contains a given search string.
     */
    public function insertAfter(string $file, string $search, string $insertion): void
    {
        if (! $this->exists($file)) {
            return;
        }

        $lines = explode("\n", $this->read($file));

        foreach ($lines as $index => $line) {
            if (str_contains($line, $search)) {
                array_splice($lines, $index + 1, 0, [$insertion]);
                break;
            }
        }

        $this->write($file, implode("\n", $lines));
    }

    /**
     * Strips a markdown heading and its section body,
     * up to the next heading of the same or shallower level.
     */
    public function removeMarkdownSection(string $file, string $heading): void
    {
        if (! $this->exists($file)) {
            return;
        }

        $content = $this->read($file);
        $pattern = '/\n##+ '.preg_quote($heading, '/').'\n.*?(?=\n##+ |\z)/s';

        $this->write($file, preg_replace($pattern, '', $content) ?? $content);
    }

    protected function rewriteSection(string $file, string $tag, bool $keepContents): void
    {
        if (! $this->exists($file)) {
            return;
        }

        $tag = $this->normalizeTag($tag);
        $content = $this->read($file);
        $escapedTag = preg_quote($tag, '/');

        $styles = [
            ['\{?\/\*', '\*\/\}?'],
            ['<!--', '-->'],
            ['\{\{--', '--\}\}'],
        ];

        $this->validateMarkers($content, $file, $tag, $styles, $escapedTag);

        foreach ($styles as [$open, $close]) {
            $start = $open.'\s*@'.$escapedTag.'\s*'.$close;
            $end = $open.'\s*@end-'.$escapedTag.'\s*'.$close;

            if ($keepContents) {
                // Drop marker-only lines first, then handle inline markers.
                $content = $this->pregReplace('/^\h*'.$start.'\h*\R?/m', '', $content);
                $content = $this->pregReplace('/^\h*'.$end.'\h*\R?/m', '', $content);
                $content = $this->pregReplace('/'.$start.'\h*/', '', $content);
                $content = $this->pregReplace('/\h*'.$end.'/', '', $content);
            } else {
                // Remove full blocks, including marker-only multi-line sections and inline sections.
                $content = $this->pregReplace('/^\h*'.$start.'\h*\R.*?^\h*'.$end.'\h*(?:\R|$)/ms', '', $content);
                $content = $this->pregReplace('/'.$start.'.*?'.$end.'\h*/s', '', $content);
            }
        }

        $this->write($file, $content);
    }

    /**
     * @param  list<array{string, string}>  $styles
     */
    protected function validateMarkers(string $content, string $file, string $tag, array $styles, string $escapedTag): void
    {
        $markers = [];

        foreach ($styles as [$open, $close]) {
            preg_match_all('/'.$open.'\s*@(?:end-)?'.$escapedTag.'\s*'.$close.'/', $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$match, $offset]) {
                $isEnd = (bool) preg_match('/@end-/', $match);
                $markers[] = ['offset' => $offset, 'end' => $isEnd];
            }
        }

        usort($markers, fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $counter = count($markers);

        for ($i = 1; $i < $counter; $i++) {
            if ($markers[$i]['end'] === $markers[$i - 1]['end']) {
                $type = $markers[$i]['end'] ? 'closing' : 'opening';

                throw new \RuntimeException("Consecutive {$type} markers for @{$tag} in {$file}.");
            }
        }
    }

    protected function read(string $file): string
    {
        $contents = file_get_contents($this->directory.'/'.$file);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$file}");
        }

        return $contents;
    }

    protected function write(string $file, string $contents): void
    {
        if ($this->safe && $contents === $this->read($file)) {
            throw new \RuntimeException("No changes were made to {$file}.");
        }

        $this->ensure(file_put_contents($this->directory.'/'.$file, $contents), "Unable to write file: {$file}");
    }

    protected function ensure(mixed $result, string $message): void
    {
        if ($this->safe && $result === false) {
            throw new \RuntimeException($message);
        }
    }

    protected function pregReplace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        return $result ?? throw new \RuntimeException("Regular expression failed: {$pattern}");
    }

    protected function exists(string $file): bool
    {
        if ($this->safe && ! file_exists($this->directory.'/'.$file)) {
            throw new \RuntimeException("File does not exist: {$file}");
        }

        return file_exists($this->directory.'/'.$file);
    }

    protected function normalizeTag(string $tag): string
    {
        return str_starts_with($tag, 'chisel-') ? $tag : 'chisel-'.$tag;
    }

    private function removePath(string $fullPath): void
    {
        if (! file_exists($fullPath) && ! is_link($fullPath)) {
            if ($this->safe) {
                throw new \RuntimeException("Unable to delete a path that does not exist: {$fullPath}");
            }

            return;
        }

        if (is_link($fullPath) || ! is_dir($fullPath)) {
            $this->ensure(unlink($fullPath), "Unable to delete: {$fullPath}");

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $this->ensure(
                $item->isDir() && ! $item->isLink()
                    ? rmdir($item->getPathname())
                    : unlink($item->getPathname()),
                "Unable to delete: {$item->getPathname()}",
            );
        }

        $this->ensure(rmdir($fullPath), "Unable to delete: {$fullPath}");
    }
}
