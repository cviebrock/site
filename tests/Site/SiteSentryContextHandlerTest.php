<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;

/**
 * @internal
 */
#[CoversClass(SiteSentryContextHandler::class)]
class SiteSentryContextHandlerTest extends TestCase
{
    private SiteSentryContextHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new SiteSentryContextHandler();
    }

    #[Test]
    public function testHandlerAlwaysReturnsTheEvent(): void
    {
        $event = Event::createEvent();

        $result = ($this->handler)($event, null);

        $this->assertSame($event, $result);
    }

    #[Test]
    public function testHandlerSetsContextFromSiteException(): void
    {
        $exception = (new SiteException('Oops'))
            ->withContext('order', ['id' => 123, 'total' => 99.99])
            ->withContext('customer', ['id' => 456]);

        $hint = new EventHint();
        $hint->exception = $exception;

        $event = Event::createEvent();
        ($this->handler)($event, $hint);

        $this->assertSame(
            [
                'order'    => ['id' => 123, 'total' => 99.99],
                'customer' => ['id' => 456],
            ],
            $event->getContexts(),
        );
    }

    #[Test]
    public function testHandlerDoesNothingForStandardException(): void
    {
        $hint = new EventHint();
        $hint->exception = new RuntimeException('A plain exception');

        $event = Event::createEvent();
        ($this->handler)($event, $hint);

        $this->assertSame([], $event->getContexts());
    }

    #[Test]
    public function testHandlerDoesNothingWhenHintIsNull(): void
    {
        $event = Event::createEvent();
        ($this->handler)($event, null);

        $this->assertSame([], $event->getContexts());
    }

    #[Test]
    public function testHandlerDoesNothingWhenHintHasNoException(): void
    {
        $event = Event::createEvent();
        ($this->handler)($event, new EventHint());

        $this->assertSame([], $event->getContexts());
    }

    #[Test]
    public function testHandlerDoesNothingWhenSiteExceptionHasNoContext(): void
    {
        $hint = new EventHint();
        $hint->exception = new SiteException('No context added');

        $event = Event::createEvent();
        ($this->handler)($event, $hint);

        $this->assertSame([], $event->getContexts());
    }

    #[Test]
    public function testHandlerSetsTagsFromSiteException(): void
    {
        $exception = (new SiteException('Oops'))
            ->withTag('size', 'large')
            ->withTag('color', 'red');

        $hint = new EventHint();
        $hint->exception = $exception;

        $event = Event::createEvent();
        ($this->handler)($event, $hint);

        $this->assertSame(
            ['size' => 'large', 'color' => 'red'],
            $event->getTags(),
        );
    }

    #[Test]
    public function testHandlerDoesNotClearPreexistingEventTags(): void
    {
        $exception = (new SiteException('Oops'))
            ->withTag('size', 'large');

        $hint = new EventHint();
        $hint->exception = $exception;

        $event = Event::createEvent();
        $event->setTags(['existing_tag' => 'existing_value']);

        ($this->handler)($event, $hint);

        $this->assertSame(
            [
                'existing_tag' => 'existing_value',
                'size'         => 'large',
            ],
            $event->getTags(),
        );
    }
}
