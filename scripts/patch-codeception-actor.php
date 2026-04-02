<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/tests/Support/_generated/AcceptanceTesterActions.php';
$contents = file_get_contents($path);

if (!is_string($contents)) {
    fwrite(STDERR, "Unable to read generated Codeception actor.\n");
    exit(1);
}

$patched = preg_replace_callback(
    "/\\/\\*\\*(?<doc>(?:\\R\\s*\\*[^\\n]*)*)\\R\\s*\\*\\/\\R(?<indent>\\s*)public function (?<name>\\w+)\\((?<params>[^)]*)\\)(?<suffix>[^\\n]*)\\n/",
    static function (array $matches): string {
        $parameters = parseParameters($matches['params']);
        if ($parameters === []) {
            return $matches[0];
        }

        $docLines = preg_split('/\R/', $matches['doc']) ?: [];
        $filteredLines = [];

        foreach ($docLines as $line) {
            if (preg_match('/^\s*\*\s+@param\b/', $line) === 1) {
                continue;
            }

            $filteredLines[] = $line;
        }

        $paramLines = array_map(
            static fn (array $parameter): string => '     * @param ' . parameterDocType($parameter['type'], $parameter['name']) . ' $' . $parameter['name'],
            $parameters
        );

        $rebuiltDoc = [];
        $inserted = false;

        foreach ($filteredLines as $line) {
            if (!$inserted && preg_match('/^\s*\*\s+@see\b/', $line) === 1) {
                array_push($rebuiltDoc, ...$paramLines);
                $inserted = true;
            }

            $rebuiltDoc[] = $line;
        }

        if (!$inserted) {
            array_push($rebuiltDoc, ...$paramLines);
        }

        return "/**\n"
            . implode("\n", $rebuiltDoc)
            . "\n */\n"
            . $matches['indent']
            . 'public function '
            . $matches['name']
            . '('
            . $matches['params']
            . ')'
            . $matches['suffix']
            . "\n";
    },
    $contents
);

if (!is_string($patched)) {
    fwrite(STDERR, "Unable to patch generated Codeception actor.\n");
    exit(1);
}

$patched = str_replace(
    "    public function grabMultiple(\$cssOrXpath, ?string \$attribute = null): array\n    {\n        return \$this->getScenario()->runStep(new \\Codeception\\Step\\Action('grabMultiple', func_get_args()));\n    }\n",
    "    public function grabMultiple(\$cssOrXpath, ?string \$attribute = null): array\n    {\n        /** @var string[] \$result */\n        \$result = \$this->getScenario()->runStep(new \\Codeception\\Step\\Action('grabMultiple', func_get_args()));\n\n        return \$result;\n    }\n",
    $patched
);

$patched = str_replace(
    "    public function grabPageSource(): string\n    {\n        return \$this->getScenario()->runStep(new \\Codeception\\Step\\Action('grabPageSource', func_get_args()));\n    }\n",
    "    public function grabPageSource(): string\n    {\n        /** @var string \$result */\n        \$result = \$this->getScenario()->runStep(new \\Codeception\\Step\\Action('grabPageSource', func_get_args()));\n\n        return \$result;\n    }\n",
    $patched
);

if ($patched !== $contents && file_put_contents($path, $patched) === false) {
    fwrite(STDERR, "Unable to write generated Codeception actor.\n");
    exit(1);
}

/**
 * @return list<array{name: string, type: string}>
 */
function parseParameters(string $signature): array
{
    $signature = trim($signature);
    if ($signature === '') {
        return [];
    }

    $parameters = [];

    foreach (explode(',', $signature) as $rawParameter) {
        $parameter = trim($rawParameter);
        if ($parameter === '') {
            continue;
        }

        $parameter = preg_replace('/\s*=.*$/', '', $parameter);
        if (!is_string($parameter)) {
            continue;
        }

        if (preg_match('/^(?:(?<type>[^$&]+)\s+)?(?<reference>&)?(?<variadic>\.\.\.)?\$(?<name>\w+)$/', $parameter, $matches) !== 1) {
            continue;
        }

        $parameters[] = [
            'name' => $matches['name'],
            'type' => trim((string) ($matches['type'] ?? '')),
        ];
    }

    return $parameters;
}

function parameterDocType(string $type, string $name): string
{
    if ($type === '') {
        return in_array($name, ['attributes', 'params'], true)
            ? 'array<string, mixed>'
            : 'mixed';
    }

    if ($type === '?array') {
        return 'array<string, mixed>|null';
    }

    $parts = explode('|', $type);
    $normalized = array_map(
        static fn (string $part): string => trim($part) === 'array'
            ? 'array<string, mixed>'
            : trim($part),
        $parts
    );

    return implode('|', $normalized);
}
