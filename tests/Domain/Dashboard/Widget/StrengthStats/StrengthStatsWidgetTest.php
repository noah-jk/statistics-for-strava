<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\Widget\StrengthStats;

use App\Domain\Dashboard\Widget\StrengthStats\StrengthStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class StrengthStatsWidgetTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private StrengthStatsWidget $widget;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-01-20'),
            configuration: WidgetConfiguration::empty(),
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderReturnsNullWhenNoStrengthData(): void
    {
        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-01-20'),
            configuration: WidgetConfiguration::empty(),
        );
        $this->assertNull($render);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(StrengthStatsWidget::class);
    }
}
