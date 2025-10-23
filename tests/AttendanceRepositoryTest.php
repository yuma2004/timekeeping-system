<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use App\Repositories\AttendanceRepository;
use App\Support\Config;

function withAppTimezone(string $timezone, callable $callback): void
{
    $original = Config::all();
    $updated = $original;

    if (!isset($updated['app']) || !is_array($updated['app'])) {
        $updated['app'] = [];
    }

    $updated['app']['timezone'] = $timezone;
    Config::init($updated);

    try {
        $callback();
    } finally {
        Config::init($original);
    }
}

function createRepository(PDO $pdo): AttendanceRepository
{
    return new AttendanceRepository($pdo);
}

function createInMemoryPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        'CREATE TABLE attendance_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            kind TEXT NOT NULL,
            occurred_at TEXT NOT NULL,
            ip TEXT NULL,
            user_agent TEXT NULL,
            raw_payload TEXT NULL,
            prev_hmac TEXT NULL,
            hmac_link TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function insertEvent(PDO $pdo, string $eventId, int $userId, string $kind, DateTimeImmutable $occurredUtc): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO attendance_events (
            event_id, user_id, kind, occurred_at, ip, user_agent, raw_payload, prev_hmac, hmac_link, created_at
        ) VALUES (
            :event_id, :user_id, :kind, :occurred_at, NULL, NULL, NULL, NULL, :hmac_link, :created_at
        )'
    );

    $timestamp = $occurredUtc->format('Y-m-d H:i:s');
    $stmt->execute([
        ':event_id' => $eventId,
        ':user_id' => $userId,
        ':kind' => $kind,
        ':occurred_at' => $timestamp,
        ':hmac_link' => 'hmac-' . $eventId,
        ':created_at' => $timestamp,
    ]);
}

function resetEvents(PDO $pdo): void
{
    $pdo->exec('DELETE FROM attendance_events');
}

function assertSameCount(int $expected, array $records, string $message): void
{
    $actual = count($records);

    if ($actual !== $expected) {
        throw new RuntimeException($message . " Expected {$expected}, got {$actual}.");
    }
}

function assertContainsEventIds(array $expectedIds, array $records, string $message): void
{
    $actualIds = array_map(static fn(array $row) => $row['event_id'], $records);
    sort($expectedIds);
    sort($actualIds);

    if ($expectedIds !== $actualIds) {
        throw new RuntimeException($message . ' Expected IDs ' . json_encode($expectedIds) . ', got ' . json_encode($actualIds) . '.');
    }
}

function expectException(string $className, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $className) {
            return;
        }

        throw new RuntimeException(
            sprintf('Expected exception of type %s but received %s.', $className, get_class($exception)),
            0,
            $exception
        );
    }

    throw new RuntimeException(sprintf('Expected exception of type %s but none was thrown.', $className));
}

function runTests(): void
{
    $pdo = createInMemoryPdo();
    $repository = createRepository($pdo);
    $utc = new DateTimeZone('UTC');

    $tests = [
        'includes_event_just_after_local_midnight' => function () use ($pdo, $repository, $utc): void {
            withAppTimezone('Asia/Tokyo', function () use ($pdo, $repository, $utc): void {
                resetEvents($pdo);

                $localZone = new DateTimeZone('Asia/Tokyo');
                $startOfMonth = new DateTimeImmutable('2024-06-01 00:15:00', $localZone);
                $previousMonth = new DateTimeImmutable('2024-05-31 23:45:00', $localZone);

                insertEvent($pdo, 'start_of_month', 1, 'in', $startOfMonth->setTimezone($utc));
                insertEvent($pdo, 'previous_month', 1, 'out', $previousMonth->setTimezone($utc));

                $records = $repository->findEventsForMonth(1, '2024-06');
                assertSameCount(1, $records, 'Events just after local midnight should be included in the month range.');
                assertContainsEventIds(['start_of_month'], $records, 'Only start_of_month event should be returned for June.');
            });
        },
        'includes_event_at_local_month_end' => function () use ($pdo, $repository, $utc): void {
            withAppTimezone('Asia/Tokyo', function () use ($pdo, $repository, $utc): void {
                resetEvents($pdo);

                $localZone = new DateTimeZone('Asia/Tokyo');
                $endOfMonth = new DateTimeImmutable('2024-06-30 23:59:59', $localZone);
                $nextMonth = new DateTimeImmutable('2024-07-01 00:00:00', $localZone);

                insertEvent($pdo, 'end_of_month', 1, 'out', $endOfMonth->setTimezone($utc));
                insertEvent($pdo, 'next_month_start', 1, 'in', $nextMonth->setTimezone($utc));

                $records = $repository->findEventsForMonth(1, '2024-06');
                assertSameCount(1, $records, 'Events at the local month end should be included in the month range.');
                assertContainsEventIds(['end_of_month'], $records, 'Only end_of_month event should be returned for June.');
            });
        },
        'respects_configured_timezone' => function () use ($pdo, $repository, $utc): void {
            withAppTimezone('America/Los_Angeles', function () use ($pdo, $repository, $utc): void {
                resetEvents($pdo);

                $localZone = new DateTimeZone('America/Los_Angeles');
                $event = new DateTimeImmutable('2024-03-01 00:15:00', $localZone);
                $previousDay = new DateTimeImmutable('2024-02-29 23:50:00', $localZone);

                insertEvent($pdo, 'la_month_start', 99, 'in', $event->setTimezone($utc));
                insertEvent($pdo, 'la_prev_month', 99, 'out', $previousDay->setTimezone($utc));

                $records = $repository->findEventsForMonth(99, '2024-03');
                assertSameCount(1, $records, 'Configured timezone should determine the monthly boundary.');
                assertContainsEventIds(['la_month_start'], $records, 'Only la_month_start event should be returned for March.');
            });
        },
        'throws_for_invalid_year_month' => function () use ($repository): void {
            withAppTimezone('Asia/Tokyo', function () use ($repository): void {
                expectException(InvalidArgumentException::class, function () use ($repository): void {
                    $repository->findEventsForMonth(1, '2024-6');
                });
            });
        },
    ];

    $passed = 0;

    foreach ($tests as $name => $test) {
        $test();
        $passed++;
        echo "✓ {$name}\n";
    }

    echo "\nAll {$passed} tests passed.\n";
}

runTests();
