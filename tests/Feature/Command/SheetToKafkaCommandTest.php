<?php

declare(strict_types=1);

namespace Tests\Feature\Command;

use App\Command\SheetToKafkaCommand;
use App\Service\ManageAsins;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SheetToKafkaCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $manageAsins = $this->createMock(ManageAsins::class);
        $manageAsins->expects($this->once())
            ->method('processSheetToKafka')
            ->willReturn(5);

        $command = new SheetToKafkaCommand($manageAsins);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('5 rows', $commandTester->getDisplay());
    }

    public function testExecuteFailure(): void
    {
        $manageAsins = $this->createMock(ManageAsins::class);
        $manageAsins->expects($this->once())
            ->method('processSheetToKafka')
            ->willThrowException(new \RuntimeException('Kafka connection failed'));

        $command = new SheetToKafkaCommand($manageAsins);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Kafka connection failed', $commandTester->getDisplay());
    }
}
