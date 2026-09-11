---
name: write-phpunit-test

description: Rules for writing or editing tests under tests/ in Chisel Extended, covering PHPUnit-only conventions, file naming, CoversClass, test method naming, comment sections, and data providers.

license: MIT

metadata:

  author: sunchayn

---

# Write PHPUnit Test

## Primary Goal

Write a test that reads exactly like the existing test suite, using PHPUnit attributes and structure only.

## Workflow

1. Use PHPUnit only. This project does not use Pest, never write `it()`, `test()`, `->with()`, `dataset()`, or any other Pest-only function.
2. Name a test file `<Name>UnitTest.php` when it has no filesystem or subprocess side effects, such as `PackageManagerUnitTest.php`. Name it `<Name>FunctionalTest.php` when it touches the real filesystem or runs a real subprocess, such as `FileFunctionalTest.php`, `SourceFunctionalTest.php`, or `NpmFunctionalTest.php`.
3. Extend `PHPUnit\Framework\TestCase` directly for a `UnitTest` class that needs no temporary directory, following `PackageManagerUnitTest`.
4. Extend `Tests\TestCase` for a test class that needs `$this->tempDir`, following `FileFunctionalTest`, `SourceFunctionalTest`, `NpmFunctionalTest`, and `ChiselUnitTest`. `Tests\TestCase` creates a fresh directory under `tests/.sandbox` in `setUp()` and deletes it in `tearDown()`.
5. Add `#[CoversClass(TargetClass::class)]` to every test class, naming the one class under test, importing `PHPUnit\Framework\Attributes\CoversClass`.
6. Name every test method `test_it_<snake_case_description>(): void`, describing the behavior under test, never the implementation detail.
7. Split every test body into short comment-labeled sections, each on its own line followed by a blank line. Use `// Arrange`, `// Act`, and `// Assert`. Collapse to `// Act & Assert` when the assertion follows the call with nothing worth separating. Use `// Anticipate` immediately before `expectException()` or `expectExceptionMessage()` calls that precede the real `// Act` section.
8. Write a data provider as `public static function providerName(): Generator`, importing `Generator`, yielding one labeled case per `yield 'case label' => [...]`, then reference it with `#[DataProvider('providerName')]`, importing `PHPUnit\Framework\Attributes\DataProvider`.
9. Use `#[TestWith([...], 'Case Name')]`, importing `PHPUnit\Framework\Attributes\TestWith`, for a handful of simple inline cases that do not justify a full data provider.
10. Write assertions with PHPUnit's native methods, such as `assertEquals()`, `assertTrue()`, `assertFalse()`, `assertStringContainsString()`, `assertStringNotContainsString()`, and `assertFileDoesNotExist()`.
11. Run `composer test` before committing. It runs the suite through `tests/phpunit.xml.dist` with `--testdox --colors=always`.

## References

- `tests/TestCase.php`
- `tests/ChiselUnitTest.php`
- `tests/Filesystem/FileFunctionalTest.php`
- `tests/Ast/SourceFunctionalTest.php`
- `tests/Node/NpmFunctionalTest.php`
- `tests/Node/PackageManagerUnitTest.php`
- `tests/phpunit.xml.dist`

## Examples

- Testing `PackageManager::installCommand()` across every enum case, use a `#[DataProvider]` returning a `Generator` of labeled cases, following `PackageManagerUnitTest::packageManagerProvider()`.
- Testing that `File::removeSection()` throws on malformed markers, use `// Anticipate` with `expectException()` and `expectExceptionMessage()` before the `// Act` section, following `FileFunctionalTest::test_it_throws_on_consecutive_opening_markers()`.
- Testing a method that writes to a real file, extend `Tests\TestCase` and use `$this->tempDir`, following `FileFunctionalTest`.
- Testing a pure value object or enum with no filesystem interaction, extend `PHPUnit\Framework\TestCase` directly, following `PackageManagerUnitTest`.

## Anti-Patterns

- Writing a Pest-style test, or importing any Pest function.
- Naming a file `FooTest.php` instead of `FooUnitTest.php` or `FooFunctionalTest.php`.
- Extending `Tests\TestCase` when the test never touches `$this->tempDir`.
- Extending `PHPUnit\Framework\TestCase` directly when the test needs a temporary directory.
- Omitting `#[CoversClass]`, or naming more than one class in it.
- Naming a test method without the `test_it_` prefix, or without a `: void` return type.
- Writing a test body with no `// Arrange`, `// Act`, `// Assert` sections, or with sections not on their own line.
