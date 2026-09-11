# Chisel Extended Guidelines

## Fork Status

Chisel Extended is a hard fork of `laravel/chisel`, Taylor Otwell's toolkit for building post-install scripts that remove unwanted starter-kit features. It is not an independent project. The `upstream` git remote points at `git@github.com:laravel/chisel.git`. The `origin` remote points at `git@github.com:sunchayn/chisel-extended.git`.

This fork status shapes every decision here. New code should stay compatible with upstream's shape where possible, so future syncs from `laravel/chisel` stay tractable. Before restructuring a file that has a direct counterpart upstream, consider whether the restructuring will make the next sync harder. See [sync-with-upstream](./skills/sync-with-upstream/SKILL.md) for the exact procedure.

## Architecture

`Laravel\Chisel\Chisel` is the entry point. It is created with `Chisel::in($directory)` and exposes methods that need the whole root directory or take two paths, such as `renamePath()`, `copyDirectory()`, `replacePlaceholders()`, and `runCommand()`.

`Laravel\Chisel\Filesystem\File` and `Laravel\Chisel\Filesystem\PendingFiles` handle path-based mutations against one or more single files, such as `replace()`, `removeLinesContaining()`, `removeSection()`, `removeSectionMarkers()`, `insertAfter()`, `removeMarkdownSection()`, and `delete()`. `Chisel::file()` and `Chisel::files()` return a `PendingFiles` instance.

`Laravel\Chisel\Ast\Source` handles AST-based PHP edits, using `nikic/php-parser`, through `removeTrait()`, `removeInterface()`, and `removeImport()`. `Chisel::php()` returns a `Source` instance. Edits are queued and applied on `save()`, which also runs automatically on destruct.

`Laravel\Chisel\Node\Npm` and `Laravel\Chisel\Node\PackageManager` handle package-manager shellouts. `Npm` detects `npm`, `yarn`, `pnpm`, or `bun` from lock files or `composer.json` scripts, then delegates the actual command to the `PackageManager` enum. `Chisel::npm()` returns the `Npm` instance for the root directory.

`Laravel\Chisel\Script`, `Laravel\Chisel\Question`, and `Laravel\Chisel\PendingAnswers` form the interactive script-definition layer. `Script` collects `Question` objects and mutation closures, then applies them against a `Chisel` instance once answers are resolved. `PendingAnswers` resolves those answers either interactively, through a registered `onQuestion` callback, or non-interactively, using defaults.

## Toolchain

Run these Composer scripts directly, they are the only supported entry points into the toolchain.

* `composer style:fix` formats the code with Pint's default Laravel preset.
* `composer phpstan` runs static analysis at level 7, configured at `tools/phpstan/phpstan.neon.dist`.
* `composer rector -- --dry-run` checks for refactoring opportunities, configured at `tools/rector/config.php`.
* `composer test` runs the PHPUnit suite, configured at `tests/phpunit.xml.dist`.
* `composer test:coverage` runs the suite and writes an HTML coverage report to `./build/coverage`.

## Writing Style

Every skill, guideline, and code comment in this project is written in Standard Technical English. That means plain, direct, unambiguous prose, built up one idea at a time instead of packed into a single dense sentence. No idioms, no metaphors, no narrative framing.

## Skills

* Writing or editing PHP under `src/`: [write-php-code](./skills/write-php-code/SKILL.md).
* Writing or editing tests under `tests/`: [write-phpunit-test](./skills/write-phpunit-test/SKILL.md).
* Pulling changes from `laravel/chisel` into this fork: [sync-with-upstream](./skills/sync-with-upstream/SKILL.md).
