<?php

declare(strict_types=1);

$handler = $GLOBALS['__sims_console_exit_handler'] ?? null;

if (is_callable($handler)) {
    $handler((int) ($__sims_exit_code ?? 0));
    return;
}

exit((int) ($__sims_exit_code ?? 0));
