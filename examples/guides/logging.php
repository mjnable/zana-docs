<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Logging\Formatter\LineFormatter;
use Zana\Logging\Handler\ArrayHandler;
use Zana\Logging\Handler\StreamHandler;
use Zana\Logging\LogContext;
use Zana\Logging\Logger;
use Zana\Logging\LogLevel;
use Zana\Logging\Processor\ContextProcessor;

$clock = static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-10-25 09:00:00 UTC');

// region: logger
$context = new LogContext();                       // values added to every record, e.g. correlation_id
$memory = new ArrayHandler();
$logger = new Logger('app', [
    new StreamHandler('php://stdout', LogLevel::Info, new LineFormatter()),
    $memory,
], [new ContextProcessor($context)], $clock);

$context->set('correlation_id', 'req-42');
$logger->info('Patient {id} registered', ['id' => 7]);
$logger->debug('Not written to stdout: below Info');
$logger->warning('BMI {bmi} is high', ['bmi' => 31.2]);
// endregion: logger

// region: records
foreach ($memory->records() as $record) {
    echo strtoupper($record->level->value), ' | ', $record->message, ' | ', json_encode($record->extra), "\n";
}
echo var_export($memory->hasRecord(LogLevel::Warning, 'is high'), true), "\n";
// endregion: records
