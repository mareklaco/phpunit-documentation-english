#!/usr/bin/env php
<?php declare(strict_types=1);
$phpunit       = $command = __DIR__ . '/../tools/phpunit';
$version       = hash('sha256', trim(PHP_VERSION . shell_exec($phpunit . ' --version')));
$processed     = processed_read();
$rootDirectory = realpath(__DIR__ . '/../src/examples') . '/';

foreach (new GlobIterator(__DIR__ . '/../src/examples/**/*Test.php') as $test) {
    $currentFile     = str_replace($rootDirectory, '', $test->getRealPath());
    $currentFileHash = $version . hash_file('sha256', $test->getRealPath());

    if (isset($processed[$currentFile]) && $processed[$currentFile] === $currentFileHash) {
        print '[skipped  ] ' . $currentFile . PHP_EOL;

        continue;
    }

    $command       = $phpunit . ' ';
    $bootstrap     = dirname($test->getRealPath()) . '/src/autoload.php';
    $hiddenOptions = '--no-configuration --do-not-cache-result ';
    $options       = '';

    if (file_exists($bootstrap)) {
        $hiddenOptions .= '--bootstrap ' . $bootstrap . ' ';
    }

    $output = shell_exec($command . $hiddenOptions . $options . $test);

    if (str_contains($output, ', Incomplete:')) {
        $options .= '--display-incomplete ';

        $output = shell_exec($command . $hiddenOptions . $options . $test);
    }

    if (str_contains($output, ', Skipped:')) {
        $options .= '--display-skipped ';

        $output = shell_exec($command . $hiddenOptions . $options . $test);
    }

    file_put_contents(
        $test . '.out',
        './tools/phpunit ' . $options . 'tests/' . $test->getBasename() . PHP_EOL .
        str_replace(
            [
                dirname($test->getRealPath()),
            ],
            [
                '/path/to/tests',
            ],
            $output
        )
    );

    $processed[$currentFile] = $currentFileHash;

    print '[processed] ' . $currentFile . PHP_EOL;
}

processed_write($processed);

function processed_write(array $processed): void
{
    file_put_contents(__DIR__ . '/processed.json', json_encode($processed, JSON_PRETTY_PRINT));
}

function processed_read(): array
{
    $filename = __DIR__ . '/processed.json';

    if (!file_exists($filename)) {
        return [];
    }

    $json = file_get_contents($filename);

    if (!$json) {
        return [];
    }

    try {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
}
