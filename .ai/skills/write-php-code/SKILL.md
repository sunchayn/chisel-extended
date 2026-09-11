---
name: write-php-code

description: Rules for writing or editing PHP source code under src/ in Chisel Extended, covering PHPStan compliance, Pint formatting, method placement between Chisel and Filesystem\File, and comment style.

license: MIT

metadata:

  author: sunchayn

---

# Write PHP Code

## Primary Goal

Write PHP under `src/` that reads exactly like the rest of the codebase and passes the toolchain without hand-tuning.

## Workflow

1. Never add `declare(strict_types=1)` to a file under `src/`. No source file in this codebase declares strict types. This is a deliberate, consistent project convention, not an oversight to fix.
2. Run `composer phpstan` after any change under `src/`, and treat every reported error as a blocker before the change is done. The project runs PHPStan at level 7, configured at `tools/phpstan/phpstan.neon.dist`.
3. Never hand-format code. Run `composer style:fix` before committing. It runs Pint under its default Laravel preset, there is no `pint.json` overriding that preset.
4. Place a new method on `Chisel` itself only when it needs the whole root directory or takes two paths. `renamePath()`, `copyDirectory()`, `relativePath()`, `replacePlaceholders()`, and `runCommand()` all fit this shape, they resolve paths against the root directory, or take a source and a destination, or scan every file under the root.
5. Place a new method on `Filesystem\File`, exposed through `Filesystem\PendingFiles`, when it targets one file path at a time. `replace()`, `removeLinesContaining()`, `removeSection()`, `removeSectionMarkers()`, `insertAfter()`, `removeMarkdownSection()`, and `delete()` all fit this shape, each one operates on a single given path, and `PendingFiles` fans the call out across `Chisel::files()`'s list of paths.
6. Place a new AST edit as a visitor class under `src/Ast/Visitors`, then expose it through a new method on `Ast\Source`, following the existing shape of `removeTrait()`, `removeInterface()`, and `removeImport()`, each of which queues a visitor and returns `static` for chaining.
7. Comment why code exists, never what it does. Before writing a comment, check whether it explains a decision or a workaround, or whether it only restates the line below it. Delete a comment that only restates the next line.
8. Never join two independent clauses with a colon or a semicolon inside a comment. Split the comment into two sentences instead.
9. Keep a function or class doc block to at most 4 lines, and never name a specific variable inside it. A doc block states the contract, an inline comment explains a specific line.
10. Keep an inline comment to at most 3 lines. When an inline comment wraps across more than one line, its last line carries at least 4 words, never a short trailing fragment.

## References

- `src/Chisel.php`
- `src/Filesystem/File.php`
- `src/Filesystem/PendingFiles.php`
- `src/Ast/Source.php`
- `src/Node/Npm.php`
- `src/Node/PackageManager.php`
- `tools/phpstan/phpstan.neon.dist`
- `tools/rector/config.php`

## Examples

- Adding a method that deletes a directory tree given one path, add it to `Filesystem\File`, not to `Chisel`.
- Adding a method that moves a file from one location to another, add it to `Chisel`, since it takes two paths.
- Adding a new kind of AST removal, such as removing a class constant, add a visitor class under `src/Ast/Visitors` and a matching method on `Ast\Source`.
- Explaining why a regular expression skips a particular edge case, put that explanation in an inline comment next to the pattern, not in the class doc block.

## Anti-Patterns

- Adding `declare(strict_types=1)` to a file under `src/`.
- Hand-formatting whitespace or import order instead of running `composer style:fix`.
- Adding a method that operates on a single file path to `Chisel` instead of `Filesystem\File`.
- Adding a method that needs the whole root directory or two paths to `Filesystem\File` instead of `Chisel`.
- Writing a comment that narrates what the next line already says.
- Writing a doc block that names a specific variable, or that runs past 4 lines.
