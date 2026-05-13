<?php

namespace App\Tests\Application\Build\BuildStrengthHtml;

use App\Application\Build\BuildStrengthHtml\BuildStrengthHtml;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Application\BuildAppFilesTestCase;

class BuildStrengthHtmlCommandHandlerTest extends BuildAppFilesTestCase
{
    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->commandBus->dispatch(new BuildStrengthHtml(SerializableDateTime::fromString('2025-01-20 12:00:00')));
        $this->assertFileSystemWrites($this->getContainer()->get('build.storage'));
    }
}
