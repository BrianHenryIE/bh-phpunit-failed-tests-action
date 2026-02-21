<?php

/**
 * PHPUnit log parser.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Extracts failed test names from PHPUnit console output.
 */
class LogParser
{
    /**
     * Extract failed test names from PHPUnit log output.
     *
     * Matches PHPUnit's failure listing format:
     *   1) Namespace\ClassName::testMethodName
     *
     * @param string $log Raw PHPUnit console output.
     *
     * @return string[] Array of "Namespace\Class::method" strings.
     */
    public function extractFailedTests(string $log): array
    {
        preg_match_all('/\d+\)\s+([A-Za-z_\\\\]+::[A-Za-z_]+)/', $log, $matches);

        return $matches[1];
    }
}
