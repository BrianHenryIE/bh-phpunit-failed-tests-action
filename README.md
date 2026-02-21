![PHP](https://img.shields.io/badge/PHP-7.4-777BB4?logo=php&logoColor=white) [![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/bh-wp-logger/) [![PHPStan ](https://img.shields.io/badge/PHPStan-Level%2010%20-2a5ea7.svg)](https://github.com/szepeviktor/phpstan-wordpress)

# PHPUnit Failed Tests Action

A composite GitHub Action that checks recent CI runs for failed PHPUnit tests and re-runs them first, giving faster feedback on whether previous failures have been fixed.

## How it works

1. **Find failures**: Queries the GitHub API for recent failed workflow runs, downloads their logs, and extracts failed test names from PHPUnit output (e.g. `Namespace\ClassName::testMethod`)
2. **Re-run failures first**: Runs PHPUnit with `--filter` targeting only the previously failed tests
3. **Run full suite**: Runs the complete test suite regardless of the re-run result

This means you know within seconds whether the tests that failed last time are now passing, instead of waiting for the entire suite to reach those tests.

## Usage

### Basic

```yaml
- name: Run tests (previously failed first)
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@master
  with:
    phpunit-command: vendor/bin/phpunit
```

### With additional PHPUnit arguments

```yaml
- name: Run tests (previously failed first)
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@master
  with:
    phpunit-command: vendor/bin/phpunit
    phpunit-args: '--stop-on-failure --order-by=random'
```

### Specify workflow and branch

```yaml
- name: Run tests (previously failed first)
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@master
  with:
    phpunit-command: vendor/bin/phpunit
    workflow-name: main.yml
    branch: master
    max-runs: '10'
```

### Using the outputs

```yaml
- name: Run tests
  id: tests
  uses: BrianHenryIE/bh-phpunit-failed-tests-action@master
  with:
    phpunit-command: vendor/bin/phpunit

- name: Report
  if: always()
  run: |
    echo "Previously failed: ${{ steps.tests.outputs.previously-failed }}"
    echo "Re-run result: ${{ steps.tests.outputs.rerun-result }}"
```

## Inputs

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

## License

GPL-2.0-or-later
