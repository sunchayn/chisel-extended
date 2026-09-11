---
name: sync-with-upstream

description: Procedure for pulling changes from laravel/chisel, the upstream remote, into this hard fork, since the fork's structure has diverged too far for an automatic merge.

license: MIT

metadata:

  author: sunchayn

---

# Sync With Upstream

## Primary Goal

Port every relevant upstream change from `laravel/chisel` into this fork, reviewed commit by commit, without losing this fork's own identity or its structural differences.

## Workflow

1. Run `git fetch upstream` to fetch the latest commits from `git@github.com:laravel/chisel.git`.
2. Find the last synced upstream commit. There is no separate marker file, it is recorded in the message of this fork's most recent sync commit, which follows the pattern `sync: pull upstream changes through laravel/chisel@<short-sha>`. Find it with `git log --grep='^sync: pull upstream changes'` on this fork's history, then extract the `<short-sha>` it names.
3. Run `git log <that-sha>..upstream/main` and `git diff <that-sha>..upstream/main` to see every upstream commit and change since the last sync.
4. Evaluate every upstream commit found in step 3 individually, never as a batch. For a changed file that has a direct one-to-one match in this fork with no structural difference, cherry-pick it with `git cherry-pick -n <commit>`, resolving any conflicts by hand. The `-n` flag leaves the change unstaged so it can be squashed later.
5. For a changed file that was restructured in this fork, apply the upstream change by hand into the corresponding fork location instead of cherry-picking it directly. The known restructured paths are as follows. Upstream's root-level `phpunit.xml.dist` maps to this fork's `tests/phpunit.xml.dist`. Upstream's root-level `phpstan.neon.dist` maps to `tools/phpstan/phpstan.neon.dist`. Upstream's root-level `rector.php` maps to `tools/rector/config.php`. Upstream's Pest test files under `tests/*.php` map to this fork's `*UnitTest.php` and `*FunctionalTest.php` PHPUnit files, ported by following the `write-phpunit-test` skill exactly, never copied verbatim in Pest syntax.
6. Ignore every upstream change to the `name` field in `composer.json`. Upstream's package is `laravel/chisel`, this fork's package is intentionally `sunchayn/chisel-extended`, and a sync must never overwrite that.
7. Ignore every upstream change to `LICENSE.md`'s copyright line. The maintainer has deliberately kept upstream's original copyright line unchanged, and a sync must never let a new upstream copyright line overwrite that choice without the maintainer's explicit review.
8. After every relevant upstream commit from step 3 has been ported, squash everything ported in this sync into exactly one commit on this fork.
9. Collect every original author of every ported upstream commit with `git log --format='%an <%ae>' <old-sha>..<new-sha>`, run against the individual commits actually ported, never blindly against the whole range from step 3, since not every upstream commit necessarily gets ported.
10. Add one `Co-authored-by: Name <email>` trailer per distinct original author found in step 9 to the squashed commit message.
11. Write the squashed commit message as `sync: pull upstream changes through laravel/chisel@<short-sha-of-newest-ported-upstream-commit>`, with a body summarizing what was ported, followed by the `Co-authored-by:` trailers from step 10.
12. Before finalizing the sync commit, run `composer style:fix`, `composer phpstan`, `composer rector -- --dry-run`, and `composer test`, in that order, and fix anything they report.
13. Never run a raw `git merge upstream/main` or a `git rebase` onto upstream. This whole procedure is manual and reviewed commit by commit, because the fork's structure, its folder layout, its PHPUnit tests where upstream has Pest, and its own `composer.json` identity, has diverged too far for an automatic merge to be safe.

## References

- `composer.json`
- `LICENSE.md`
- `tests/phpunit.xml.dist`
- `tools/phpstan/phpstan.neon.dist`
- `tools/rector/config.php`
- `.ai/skills/write-phpunit-test/SKILL.md`
- `.github/CONTRIBUTING.md`

## Examples

- Upstream adds a new method to `src/Chisel.php` with no structural counterpart change, cherry-pick that commit with `git cherry-pick -n <commit>`.
- Upstream adds a new Pest test file under `tests/`, port its coverage by hand into a new `*UnitTest.php` or `*FunctionalTest.php` file, written per the `write-phpunit-test` skill.
- Upstream bumps a dependency version in `composer.json`, cherry-pick that change directly, but skip any hunk in the same commit that touches the `name` field.
- Upstream changes its `phpstan.neon.dist` rule set, apply the equivalent change by hand to `tools/phpstan/phpstan.neon.dist`.
- Three upstream commits get ported by two different original authors, add two `Co-authored-by:` trailers to the single squashed sync commit, one per author.

## Anti-Patterns

- Running `git merge upstream/main` or `git rebase upstream/main` directly against this fork's `base` branch.
- Cherry-picking a commit that touches a file with no direct structural match in this fork, instead of porting it by hand.
- Letting a sync overwrite `composer.json`'s `name` field with `laravel/chisel`.
- Letting a sync overwrite `LICENSE.md`'s copyright line without the maintainer's explicit review.
- Copying an upstream Pest test file into this fork verbatim, instead of rewriting it as PHPUnit per `write-phpunit-test`.
- Creating more than one commit for a single sync instead of squashing every ported change into one.
- Computing `Co-authored-by:` trailers from the full `<old-sha>..upstream/main` range instead of only the commits actually ported.
- Committing a sync before `composer style:fix`, `composer phpstan`, `composer rector -- --dry-run`, and `composer test` all pass.
