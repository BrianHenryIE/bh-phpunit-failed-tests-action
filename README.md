![PHP](https://img.shields.io/badge/PHP-7.4-777BB4?logo=php&logoColor=white) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/bh-wp-logger/) [![PHPStan ](https://img.shields.io/badge/PHPStan-Level%2010%20-2a5ea7.svg)](https://github.com/szepeviktor/phpstan-wordpress) [![PHPCS PEAR](https://img.shields.io/badge/PHPCS-PEAR%7CPSR%2012%20-4e9a06.svg)](https://pear.php.net/manual/en/standards.php)

# PHPUnit Failed Tests Action

A composite GitHub Action that checks recent CI runs for failed PHPUnit tests and re-runs them first, giving fast feedback on whether previous failures have been fixed.

## Quickstart

Add this above your existing PHPUnit CI step:

```yaml
- name: Run previously workflows runs' failed tests first
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main
```

> ⚠️ Do not use `--stop-on-failure` on your main PHPUnit step
>
> Without it, all failing tests will be logged on the first run, otherwise there could be multiple tests failing that won't reveal themselves without multiple lengthy test runs and the fail-fast benefit of this action will be lost.

## Why

What prompted this was tests passing locally but failing in GitHub Actions in a test run that takes 30+ minutes to finish.

## How it works

1. Queries the GitHub API for recent failed workflow runs, downloads their logs, and extracts failed test names from PHPUnit output (e.g. `Namespace\ClassName::testMethod`)
2. Runs PHPUnit with `--filter` targeting only the previously failed tests

### Note 

The fact this is a "composite" GitHub Action means it runs under the same `setup-php` etc. as your workflow, so I (through Claude) made it compatible back to PHP 7.4. 

## Usage

### Basic

```yaml
- name: Run previously workflows runs' failed tests first
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main
```

### With additional PHPUnit arguments

```yaml
- name: Run previously workflows runs' failed tests first
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main
  with:
    phpunit-command: vendor/bin/phpunit
    phpunit-args: '--dont-report-useless-tests --order-by=random'
```

### Without running PHPUnit again, just return the filter

```yaml
- name: Parse previously workflows runs' failed tests
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main
  with:
    phpunit-command: false
```

### Specify workflow and branch

```yaml
- name: Run previously workflows runs' failed tests first
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main
  with:
    workflow-name: unit-tests.yml
    branch: main
    max-runs: '10'
```

### Using the outputs

```yaml
- name: Run tests
  id: tests
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@main

- name: Report
  if: always()
  run: |
    echo "Previously failed: ${{ steps.tests.outputs.previously-failed }}"
    echo "Re-run result: ${{ steps.tests.outputs.rerun-result }}"
```

## Inputs

Claude did all this, I haven't used these options myself!

| Name | Description | Required | Default |
|------|-------------|----------|---------|
| `phpunit-command` | The PHPUnit command to run | No | `vendor/bin/phpunit` |
| `phpunit-args` | Additional arguments for PHPUnit | No | `''` |
| `workflow-name` | Workflow file to check for failures | No | Current workflow |
| `branch` | Branch to check for failures | No | Default branch |
| `max-runs` | Max recent runs to search | No | `5` |
| `token` | GitHub token for API access | No | `${{ github.token }}` |

## Outputs

| Name | Description |
|------|-------------|
| `previously-failed` | Comma-separated list of failed test names |
| `rerun-result` | `pass`, `fail`, or `skip` |

## How test names are extracted

The action downloads job logs from failed runs and looks for PHPUnit's failure output format:

```
There was 1 failure:

1) BrianHenryIE\Strauss\Tests\Integration\CopierIntegrationTest::testsCopy
Failed asserting that ...
```

It extracts the `Namespace\Class::method` pattern and builds a `--filter` regex for PHPUnit.
