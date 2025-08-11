<?php

declare(strict_types=1);

namespace Tests\Levacic\Monolog;

use Levacic\Monolog\ExceptionWithContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Levacic\Monolog\DummyExceptionWithContext;

class ExceptionWithContextProcessorTest extends TestCase
{
    public function testIgnoresRecordsWithoutContext(): void
    {
        $record = [
            'message' => 'An error message.',
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $this->assertSame($record, $processedRecord);
    }

    public function testIgnoresRecordsWithoutExceptionInContext(): void
    {
        $record = [
            'message' => 'An error message.',
            'context' => [
                'foo' => 'bar',
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $this->assertSame($record, $processedRecord);
    }

    public function testIgnoresRecordsWithNonExceptionObjectInContext(): void
    {
        $record = [
            'message' => 'An error message.',
            'context' => [
                'foo' => 'bar',
                'exception' => 'Not an exception object.',
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $this->assertSame($record, $processedRecord);
    }

    public function testProcessesRecordsWithRegularExceptionsCorrectly(): void
    {
        $exception = new RuntimeException('Just a regular exception.');

        $record = [
            'message' => 'An error message.',
            'context' => [
                'exception' => $exception,
                'foo' => 'bar',
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $expectedRecord = [
            'message' => 'An error message.',
            'context' => [
                'exception' => $exception,
                'foo' => 'bar',
            ],
            'extra' => [
                'exception_chain_with_context' => [
                    [
                        'exception' => 'RuntimeException',
                        'context' => null,
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedRecord, $processedRecord);
    }

    public function testProcessesRecordsWithExceptionsWithContext(): void
    {
        $exception = new DummyExceptionWithContext('bar');

        $record = [
            'message' => 'An error message.',
            'context' => [
                'exception' => $exception,
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $expectedRecord = [
            'message' => 'An error message.',
            'context' => [
                'foo' => 'bar',
                'exception' => $exception,
            ],
            'extra' => [
                'exception_chain_with_context' => [
                    [
                        'exception' => 'Tests\\Levacic\\Monolog\\DummyExceptionWithContext',
                        'context' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedRecord, $processedRecord);
    }

    public function testDoesntOverwriteUserProvidedContext(): void
    {
        $exception = new DummyExceptionWithContext('bar');

        $record = [
            'message' => 'An error message.',
            'context' => [
                'foo' => 'baz',
                'exception' => $exception,
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $expectedRecord = [
            'message' => 'An error message.',
            'context' => [
                'foo' => 'baz',
                'exception' => $exception,
            ],
            'extra' => [
                'exception_chain_with_context' => [
                    [
                        'exception' => 'Tests\\Levacic\\Monolog\\DummyExceptionWithContext',
                        'context' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedRecord, $processedRecord);
    }

    public function testProcessesRecordsWithChainedExceptionsWithContext(): void
    {
        $exception1 = new DummyExceptionWithContext('bar');
        $exception2 = new RuntimeException('This exception wraps the one that carries context.', 0, $exception1);

        $record = [
            'message' => 'An error message.',
            'context' => [
                'exception' => $exception2,
            ],
        ];

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $expectedRecord = [
            'message' => 'An error message.',
            'context' => [
                'exception' => $exception2,
            ],
            'extra' => [
                'exception_chain_with_context' => [
                    [
                        'exception' => 'RuntimeException',
                        'context' => null,
                    ],
                    [
                        'exception' => 'Tests\\Levacic\\Monolog\\DummyExceptionWithContext',
                        'context' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedRecord, $processedRecord);
    }

    public function testProcessesLogRecordWithRegularExceptionsCorrectly(): void
    {
        $exception = new RuntimeException('Just a regular exception.');

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'An error message.',
            context: [
                'exception' => $exception,
                'foo' => 'bar',
            ]
        );

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $this->assertInstanceOf(LogRecord::class, $processedRecord);
        $this->assertSame('test', $processedRecord->channel);
        $this->assertSame('An error message.', $processedRecord->message);
        $this->assertSame($exception, $processedRecord->context['exception']);
        $this->assertSame('bar', $processedRecord->context['foo']);
        $this->assertArrayHasKey('exception_chain_with_context', $processedRecord->extra);
        $this->assertSame([
            [
                'exception' => 'RuntimeException',
                'context' => null,
            ],
        ], $processedRecord->extra['exception_chain_with_context']);
    }

    public function testProcessesLogRecordWithExceptionsWithContext(): void
    {
        $exception = new DummyExceptionWithContext('bar');

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'An error message.',
            context: [
                'exception' => $exception,
                'baz' => 'qux',
            ]
        );

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($record);

        $this->assertInstanceOf(LogRecord::class, $processedRecord);
        $this->assertSame('test', $processedRecord->channel);
        $this->assertSame('An error message.', $processedRecord->message);
        $this->assertSame($exception, $processedRecord->context['exception']);
        $this->assertSame('qux', $processedRecord->context['baz']);
        $this->assertSame('bar', $processedRecord->context['foo']); // Added from exception context
        $this->assertArrayHasKey('exception_chain_with_context', $processedRecord->extra);
        $this->assertSame([
            [
                'exception' => 'Tests\Levacic\Monolog\DummyExceptionWithContext',
                'context' => ['foo' => 'bar'],
            ],
        ], $processedRecord->extra['exception_chain_with_context']);
    }

    public function testIgnoresLogRecordWithoutExceptionInContext(): void
    {
        $originalRecord = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'An error message.',
            context: [
                'foo' => 'bar',
            ]
        );

        $processor = new ExceptionWithContextProcessor();

        $processedRecord = $processor($originalRecord);

        $this->assertSame($originalRecord, $processedRecord);
    }
}
