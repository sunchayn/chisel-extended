<?php

declare(strict_types=1);

namespace Tests\Filesystem;

use Laravel\Chisel\Chisel;
use Laravel\Chisel\Filesystem\File;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(File::class)]
class FileFunctionalTest extends TestCase
{
    public function test_it_replaces_matching_content_in_a_file(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/composer.json', '"laravel/fortify": "dev-add-passkey-support#242c342"');

        // Act

        Chisel::in($this->tempDir)->file('composer.json')->replace(
            '"laravel/fortify": "dev-add-passkey-support#242c342"',
            '"laravel/fortify": "^1.30"',
        );

        // Assert

        $this->assertEquals('"laravel/fortify": "^1.30"', file_get_contents($this->tempDir.'/composer.json'));
    }

    public function test_it_removes_matching_single_lines_from_a_file(): void
    {
        // Arrange

        mkdir($this->tempDir.'/php', 0777, true);

        file_put_contents(
            $this->tempDir.'/php/file.php',
            "Features::registration(),\nFeatures::emailVerification(),\nFeatures::resetPasswords(),\n",
        );

        // Act

        Chisel::in($this->tempDir)->file('php/file.php')->removeLinesContaining('Features::emailVerification()');

        // Assert

        $this->assertEquals(
            "Features::registration(),\nFeatures::resetPasswords(),\n",
            file_get_contents($this->tempDir.'/php/file.php'),
        );
    }

    public function test_it_removes_section_markers_while_keeping_content_in_vue_comments(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file.vue',
            "<!-- @chisel-passkeys -->\n<div>Passkey settings</div>\n<!-- @end-chisel-passkeys -->\n",
        );

        // Act

        Chisel::in($this->tempDir)
            ->file('js/file.vue')
            ->removeSectionMarkers('passkeys');

        // Assert

        $this->assertEquals("<div>Passkey settings</div>\n", file_get_contents($this->tempDir.'/js/file.vue'));
    }

    public function test_it_removes_tagged_sections_from_multiple_files(): void
    {
        // Arrange

        mkdir($this->tempDir.'/php', 0777, true);
        mkdir($this->tempDir.'/blade', 0777, true);

        file_put_contents($this->tempDir.'/php/file1.php', "before\n/* @chisel-2fa */\nremove me\n/* @end-chisel-2fa */\nafter\n");
        file_put_contents($this->tempDir.'/php/file2.php', "start\n/* @chisel-2fa */\nremove me too\n/* @end-chisel-2fa */\nfinish\n");
        file_put_contents($this->tempDir.'/blade/file.blade.php', "hello\n{{-- @chisel-2fa --}}\nremove blade section\n{{-- @end-chisel-2fa --}}\nworld\n");

        // Act

        Chisel::in($this->tempDir)->files(
            'php/file1.php',
            'php/file2.php',
            'blade/file.blade.php',
        )->removeSection('2fa');

        // Assert

        $this->assertEquals("before\nafter\n", file_get_contents($this->tempDir.'/php/file1.php'));
        $this->assertEquals("start\nfinish\n", file_get_contents($this->tempDir.'/php/file2.php'));
        $this->assertEquals("hello\nworld\n", file_get_contents($this->tempDir.'/blade/file.blade.php'));
    }

    public function test_it_deletes_multiple_files(): void
    {
        // Arrange

        mkdir($this->tempDir.'/tmp', 0777, true);

        file_put_contents($this->tempDir.'/tmp/file1.php', 'x');
        file_put_contents($this->tempDir.'/tmp/file2.php', 'y');

        // Act

        Chisel::in($this->tempDir)->files(
            'tmp/file1.php',
            'tmp/file2.php',
        )->delete();

        // Assert

        $this->assertFileDoesNotExist($this->tempDir.'/tmp/file1.php');
        $this->assertFileDoesNotExist($this->tempDir.'/tmp/file2.php');
    }

    public function test_it_ignores_missing_files_when_removing_section_markers_from_multiple_targets(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file1.tsx',
            "{/* @chisel-passkeys */}\n<button>Passkey</button>\n{/* @end-chisel-passkeys */}\n",
        );

        // Act

        Chisel::in($this->tempDir)->files(
            'js/file1.tsx',
            'js/file2.tsx',
        )->removeSectionMarkers('passkeys');

        // Assert

        $this->assertEquals("<button>Passkey</button>\n", file_get_contents($this->tempDir.'/js/file1.tsx'));
        $this->assertFileDoesNotExist($this->tempDir.'/js/file2.tsx');
    }

    public function test_it_ignores_missing_files_when_removing_tagged_sections_from_multiple_targets(): void
    {
        // Arrange

        mkdir($this->tempDir.'/php', 0777, true);

        file_put_contents(
            $this->tempDir.'/php/file1.php',
            "before\n/* @chisel-2fa */\nremove me\n/* @end-chisel-2fa */\nafter\n",
        );

        // Act

        Chisel::in($this->tempDir)->files(
            'php/file1.php',
            'php/file2.php',
        )->removeSection('2fa');

        // Assert

        $this->assertEquals("before\nafter\n", file_get_contents($this->tempDir.'/php/file1.php'));
        $this->assertFileDoesNotExist($this->tempDir.'/php/file2.php');
    }

    public function test_it_ignores_missing_files_when_deleting_multiple_targets(): void
    {
        // Arrange

        mkdir($this->tempDir.'/tmp', 0777, true);

        file_put_contents($this->tempDir.'/tmp/file1.php', 'x');

        // Act

        Chisel::in($this->tempDir)->files(
            'tmp/file1.php',
            'tmp/file2.php',
        )->delete();

        // Assert

        $this->assertFileDoesNotExist($this->tempDir.'/tmp/file1.php');
        $this->assertFileDoesNotExist($this->tempDir.'/tmp/file2.php');
    }

    public function test_it_removes_react_section_markers_when_chisel_markers_share_a_line_with_code(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file.tsx',
            "/* @chisel-2fa-or-passkeys */ props: Props /* @end-chisel-2fa-or-passkeys */,\n",
        );

        // Act

        Chisel::in($this->tempDir)
            ->file('js/file.tsx')
            ->removeSectionMarkers('2fa-or-passkeys');

        // Assert

        $this->assertEquals("props: Props,\n", file_get_contents($this->tempDir.'/js/file.tsx'));
    }

    public function test_it_does_not_double_prefix_tags_that_already_start_with_chisel(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file.tsx',
            "/* @chisel-passkeys */\n<button>Passkey</button>\n/* @end-chisel-passkeys */\n",
        );

        // Act

        Chisel::in($this->tempDir)
            ->file('js/file.tsx')
            ->removeSectionMarkers('chisel-passkeys');

        // Assert

        $this->assertEquals("<button>Passkey</button>\n", file_get_contents($this->tempDir.'/js/file.tsx'));
    }

    public function test_it_removes_adjacent_react_sections_when_multiple_chisel_blocks_share_a_line(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file.tsx',
            "props: { /* @chisel-2fa */ foo, /* @end-chisel-2fa*/ /* @chisel-passkeys */ bar, /* @end-chisel-passkeys*/ }\n",
        );

        $chisel = Chisel::in($this->tempDir);

        // Act

        $chisel->file('js/file.tsx')->removeSection('2fa');
        $chisel->file('js/file.tsx')->removeSectionMarkers('passkeys');

        // Assert

        $this->assertEquals("props: { bar, }\n", file_get_contents($this->tempDir.'/js/file.tsx'));
    }

    public function test_it_does_not_rewrite_unrelated_content_when_section_tag_is_missing(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        $contents = "const x = \"a  b\";\n\nconst y = 1;\n";

        file_put_contents($this->tempDir.'/js/file.tsx', $contents);

        // Act

        Chisel::in($this->tempDir)
            ->file('js/file.tsx')
            ->removeSectionMarkers('missing-tag');

        // Assert

        $this->assertEquals($contents, file_get_contents($this->tempDir.'/js/file.tsx'));
    }

    public function test_it_can_remove_nested_sections_with_different_tags(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/file.tsx',
            <<<'TSX'
            /* @chisel-2fa-or-passkeys */
            type Props = Record<string, never> & {
                /* @chisel-2fa */
                canManageTwoFactor?: boolean;
                requiresConfirmation?: boolean;
                twoFactorEnabled?: boolean;
                /* @end-chisel-2fa */
                /* @chisel-passkeys */
                canManagePasskeys?: boolean;
                passkeys?: Passkey[];
                /* @end-chisel-passkeys */
            };
            /* @end-chisel-2fa-or-passkeys */
            TSX,
        );

        $chisel = Chisel::in($this->tempDir);
        $file = 'js/file.tsx';

        // Act

        $chisel->file($file)->removeSection('2fa');
        $chisel->file($file)->removeSectionMarkers('passkeys');
        $chisel->file($file)->removeSectionMarkers('2fa-or-passkeys');

        // Assert

        $this->assertEquals(
            "type Props = Record<string, never> & {\n    canManagePasskeys?: boolean;\n    passkeys?: Passkey[];\n};\n",
            file_get_contents($this->tempDir.'/'.$file),
        );
    }

    public function test_it_throws_on_consecutive_opening_markers(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/bad.tsx',
            "/* @chisel-feat */\n/* @chisel-feat */\ncontent\n/* @end-chisel-feat */\n",
        );

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Consecutive opening markers for @chisel-feat in js/bad.tsx.');

        // Act

        Chisel::in($this->tempDir)
            ->file('js/bad.tsx')
            ->removeSection('feat');
    }

    public function test_it_throws_on_consecutive_closing_markers(): void
    {
        // Arrange

        mkdir($this->tempDir.'/js', 0777, true);

        file_put_contents(
            $this->tempDir.'/js/bad.tsx',
            "/* @chisel-feat */\ncontent\n/* @end-chisel-feat */\n/* @end-chisel-feat */\n",
        );

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Consecutive closing markers for @chisel-feat in js/bad.tsx.');

        // Act

        Chisel::in($this->tempDir)
            ->file('js/bad.tsx')
            ->removeSectionMarkers('feat');
    }

    public function test_it_throws_in_safe_mode_when_replacing_in_a_missing_file(): void
    {
        // Arrange

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File does not exist: a.txt');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->replace('a', 'b');
    }

    public function test_it_throws_in_safe_mode_when_the_replaced_content_does_not_exist(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.txt.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->replace('missing', 'x');
    }

    public function test_it_throws_in_safe_mode_when_no_line_contains_the_content(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'one\ntwo');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.txt.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->removeLinesContaining('missing');
    }

    public function test_it_throws_in_safe_mode_when_the_insert_anchor_does_not_exist(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'one');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.txt.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->insertAfter('missing', 'x');
    }

    public function test_it_throws_in_safe_mode_when_the_markdown_heading_does_not_exist(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.md', '# Title\n\n## Other\ntext\n');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.md.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.md')->removeMarkdownSection('Missing');
    }

    public function test_it_throws_in_safe_mode_when_the_section_markers_do_not_exist(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'one');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.txt.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->removeSection('feat');
    }

    public function test_it_throws_in_safe_mode_when_removing_section_markers_that_do_not_exist(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'one');

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No changes were made to a.txt.');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->removeSectionMarkers('feat');
    }

    public function test_it_throws_in_safe_mode_when_deleting_a_missing_path(): void
    {
        // Arrange

        // Anticipate

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to delete a path that does not exist');

        // Act

        Chisel::in($this->tempDir)->safe()->file('missing.txt')->delete();
    }

    public function test_it_does_not_throw_outside_safe_mode_when_nothing_changes(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello');

        // Act

        Chisel::in($this->tempDir)->file('a.txt')->replace('missing', 'x');
        Chisel::in($this->tempDir)->file('missing.txt')->replace('a', 'b');

        // Assert

        $this->assertEquals('hello', file_get_contents($this->tempDir.'/a.txt'));
    }

    public function test_it_applies_updates_in_safe_mode_when_something_changes(): void
    {
        // Arrange

        file_put_contents($this->tempDir.'/a.txt', 'hello');

        // Act

        Chisel::in($this->tempDir)->safe()->file('a.txt')->replace('hello', 'bye');

        // Assert

        $this->assertEquals('bye', file_get_contents($this->tempDir.'/a.txt'));
    }
}
