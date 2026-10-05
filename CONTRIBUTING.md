# Contributing to QBuilder

Thank you for your interest in contributing to QBuilder! This document provides guidelines and instructions for contributing.

## Code Style

We use PHP CS Fixer to maintain consistent code style. Before submitting a pull request, please run:

```bash
composer cs-fix
```

Or check without fixing:

```bash
composer cs-check
```

## Testing

All contributions must include tests. Please ensure:

1. All new features have corresponding tests
2. All tests pass: `composer test`
3. Code coverage is maintained or improved

Run tests:

```bash
composer test
```

Integration tests execute generated SQL on real databases. SQLite runs in memory whenever `pdo_sqlite` is available. MySQL/MariaDB and PostgreSQL tests are skipped unless a DSN is provided; they use temporary tables only:

```bash
QBUILDER_TEST_MYSQL_DSN='mysql:host=127.0.0.1;dbname=test;charset=utf8mb4' \
QBUILDER_TEST_MYSQL_USER=root QBUILDER_TEST_MYSQL_PASSWORD= \
QBUILDER_TEST_PGSQL_DSN='pgsql:host=127.0.0.1;dbname=test' \
QBUILDER_TEST_PGSQL_USER=postgres QBUILDER_TEST_PGSQL_PASSWORD=secret \
composer test:integration
```

Tests run on [Testo](https://php-testo.github.io): a test class carries the `#[Test]` attribute and asserts with `Testo\Assert` / `Testo\Expect`. Run a single suite with `composer test:unit` or `composer test:integration`.

Collect coverage into a Clover report (`build-dev/coverage/clover.xml`, requires PCOV or Xdebug):

```bash
composer test:coverage-clover
```

## Static Analysis

We use PHPStan (max level with strict rules, disallowed calls and dead code detection) and PHP_CodeSniffer (PSR-12, Slevomat, PHPCompatibility). Ensure your code passes:

```bash
composer phpstan
composer phpcs
```

Run all checks at once:

```bash
composer lint
```

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Ensure all tests pass
5. Run code style fixer
6. Update documentation if needed
7. Commit your changes (`git commit -m 'Add some amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## Commit Messages

Please use clear and descriptive commit messages. Include:

- What was changed
- Why it was changed (if not obvious)

## Reporting Issues

When reporting issues, please include:

- PHP version
- QBuilder version
- Description of the issue
- Steps to reproduce
- Expected behavior
- Actual behavior

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow

Thank you for contributing!
