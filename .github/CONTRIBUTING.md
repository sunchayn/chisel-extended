# Contribution Guide

Thank you for considering contributing to Chisel Extended! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project.
2. Create a new branch.
3. Code, test, commit, and push.
4. Open a pull request detailing your changes.

## Guidelines

- Ensure the coding style passes by running `composer style:fix`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- Write commit messages in the [Conventional Commits](https://www.conventionalcommits.org/) format, since `release-please` reads them to determine version bumps and changelog sections.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Fix the coding style:

```bash
composer style:fix
```

## Static Analysis

```bash
composer phpstan
```

## Refactoring

```bash
composer rector -- --dry-run
```

## Tests

Run all tests:

```bash
composer test
```
