# Releasing

Maintainer guide for publishing `formable/formable-sdk` to Packagist. Consumers should follow the [README](README.md).

Use semver. Composer packages do not store a version in `composer.json`. The published version is the git tag with the leading `v` stripped (`v0.1.0` → `0.1.0`).

## One-time setup

1. Submit [github.com/FormableDocs/formable-php](https://github.com/FormableDocs/formable-php) at [packagist.org/packages/submit](https://packagist.org/packages/submit).
2. On Packagist, connect the GitHub webhook so new tags are picked up automatically.

## Publish a version

1. Run tests:

   ```bash
   composer install
   composer test
   ```

2. Commit any release changes on `main`.
3. Tag and push:

   ```bash
   git tag v0.1.0
   git push origin main v0.1.0
   ```

Packagist indexes the tag from GitHub. The package is at [packagist.org/packages/formable/formable-sdk](https://packagist.org/packages/formable/formable-sdk).
