# GitHub Actions

This repository uses CI to reduce regression and secret exposure risk.

## Workflows

### `ci.yml`
Runs on pushes and pull requests.

Checks included:
- PHP/Laravel test run
- frontend dependency install + build
- secret scanning with Gitleaks

## Why

This workflow supports CK-10:
- catch application regressions earlier
- fail fast on newly committed secrets

## Notes

- The test job uses SQLite in CI for speed and simplicity.
- Secret scanning is intended to block accidental secret commits before merge.
- You can extend this later with linting, Pint, dependency auditing, and build artifacts.
