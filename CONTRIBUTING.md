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

Run tests with coverage:

```bash
composer test:coverage
```

## Static Analysis

We use PHPStan for static analysis. Ensure your code passes:

```bash
composer phpstan
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
