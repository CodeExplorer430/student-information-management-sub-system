<?php

declare(strict_types=1);

if ($argc < 5) {
    fwrite(STDERR, "Usage: php scripts/check-coverage-threshold.php <clover-file> <min-line-coverage> <min-method-coverage> <min-class-coverage>\n");
    exit(1);
}

$cloverFile = $argv[1];
$minimumLineCoverage = (float) $argv[2];
$minimumMethodCoverage = (float) $argv[3];
$minimumClassCoverage = (float) $argv[4];

if (!is_file($cloverFile)) {
    fwrite(STDERR, sprintf("Coverage report not found: %s\n", $cloverFile));
    exit(1);
}

$document = new DOMDocument();
$loaded = $document->load($cloverFile);

if ($loaded === false) {
    fwrite(STDERR, sprintf("Unable to parse coverage report: %s\n", $cloverFile));
    exit(1);
}

$xpath = new DOMXPath($document);
$metrics = $xpath->query('//project/metrics')->item(0);

if (!$metrics instanceof DOMElement) {
    fwrite(STDERR, sprintf("Coverage report is missing project metrics: %s\n", $cloverFile));
    exit(1);
}

$statementCount = (int) $metrics->getAttribute('statements');
$coveredStatementCount = (int) $metrics->getAttribute('coveredstatements');
$methodCount = (int) $metrics->getAttribute('methods');
$coveredMethodCount = (int) $metrics->getAttribute('coveredmethods');

$classCount = 0;
$coveredClassCount = 0;
foreach ($xpath->query('//class') as $classNode) {
    if (!$classNode instanceof DOMElement) {
        continue;
    }

    $classMetrics = $classNode->getElementsByTagName('metrics')->item(0);
    if (!$classMetrics instanceof DOMElement) {
        continue;
    }

    $classCount++;

    $classStatements = (int) $classMetrics->getAttribute('statements');
    $classCoveredStatements = (int) $classMetrics->getAttribute('coveredstatements');
    $classMethods = (int) $classMetrics->getAttribute('methods');
    $classCoveredMethods = (int) $classMetrics->getAttribute('coveredmethods');

    $statementsCovered = $classStatements === 0 || $classCoveredStatements === $classStatements;
    $methodsCovered = $classMethods === 0 || $classCoveredMethods === $classMethods;

    if ($statementsCovered && $methodsCovered) {
        $coveredClassCount++;
    }
}

if ($statementCount <= 0) {
    fwrite(STDERR, "Coverage report did not contain executable statements.\n");
    exit(1);
}

$lineCoverage = ($coveredStatementCount / $statementCount) * 100;
$methodCoverage = $methodCount > 0 ? ($coveredMethodCount / $methodCount) * 100 : 100.0;
$classCoverage = $classCount > 0 ? ($coveredClassCount / $classCount) * 100 : 100.0;
$summary = implode(PHP_EOL, [
    sprintf(
        "Line coverage: %.2f%% (%d/%d); minimum required: %.2f%%",
        $lineCoverage,
        $coveredStatementCount,
        $statementCount,
        $minimumLineCoverage
    ),
    sprintf(
        "Method coverage: %.2f%% (%d/%d); minimum required: %.2f%%",
        $methodCoverage,
        $coveredMethodCount,
        $methodCount,
        $minimumMethodCoverage
    ),
    sprintf(
        "Class coverage: %.2f%% (%d/%d); minimum required: %.2f%%",
        $classCoverage,
        $coveredClassCount,
        $classCount,
        $minimumClassCoverage
    ),
]) . PHP_EOL;

$belowThreshold = $lineCoverage + 0.00001 < $minimumLineCoverage
    || $methodCoverage + 0.00001 < $minimumMethodCoverage
    || $classCoverage + 0.00001 < $minimumClassCoverage;

if ($belowThreshold) {
    fwrite(STDERR, $summary);
    exit(1);
}

fwrite(STDOUT, $summary);
