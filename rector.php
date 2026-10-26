<?php

declare(strict_types=1);

use Rector\Configuration\RectorConfigBuilder;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;

/** @var RectorConfigBuilder $rectorConfig */
$rectorConfig = require 'vendor/justbetter/magento2-coding-standard/rector.php'; // phpcs:ignore

$rectorConfig->withPaths([
    __DIR__.'/etc',
    __DIR__.'/Model',
    __DIR__.'/Plugin',
]);

/** Define additional rules here.
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=php
 *
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=core
 */
$rectorConfig->withSkip([
    RemoveExtraParametersRector::class,
    RemoveUselessVarTagRector::class,
]);

$rectorConfig->withPreparedSets(
    typeDeclarations: true,  // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-type-declarations
    codeQuality: true,       // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-code-quality
    deadCode: true,          // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-dead-code
    instanceOf: true,       // https://getrector.com/find-rule?rectorSet=core-instanceof&activeRectorSetGroup=core
    earlyReturn: true,      // https://getrector.com/find-rule?rectorSet=core-early-return&activeRectorSetGroup=core
);

return $rectorConfig;
