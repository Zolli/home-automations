<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Action;

use App\Tests\Shared\Notification\NotificationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Condition\CompareCondition;
use Shared\Notification\Action\ActionListener;
use Shared\Notification\Action\NotificationAction;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Action\NotificationDismissal;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\NotificationId;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\NotifyServiceSender;
use Shared\Notification\SentNotification;
use Stewart\Contracts\Time\Duration;

final class ActionRouterTest extends TestCase
{
    private const int LISTENING_TTL_HOURS = 2;

    private NotificationFixture $fixture;
    private NotificationDispatcher $notifier;

    protected function setUp(): void
    {
        $this->fixture = new NotificationFixture();
        $this->notifier = new NotificationDispatcher(
            DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender()]),
            $this->fixture->ha,
            $this->fixture->createActionRouter(),
            new NullLogger(),
        );
    }

    public function testRoutesPressedButtonToItsCallback(): void
    {
        $pressed = [];

        $this->listen('vacuum-bin')
            ->onAction('DONE', function (NotificationAction $action) use (&$pressed): void {
                $pressed[] = $action;
            })
            ->onAction('NEXT', fn() => self::fail('NEXT was not pressed'));

        $this->fixture->pressAction('vacuum-bin:DONE', ['reply_text' => 'emptied'], 'user-1');

        self::assertCount(1, $pressed);
        self::assertSame('vacuum-bin', $pressed[0]->notificationId->value);
        self::assertSame('DONE', $pressed[0]->action);
        self::assertSame('emptied', $pressed[0]->getReplyText());
        self::assertSame('user-1', $pressed[0]->userId);
    }

    public function testAnyActionCallbackSeesEveryButton(): void
    {
        $pressed = [];

        $this->listen('vacuum-bin')->onAnyAction(function (NotificationAction $action) use (&$pressed): void {
            $pressed[] = $action->action;
        });

        $this->fixture->pressAction('vacuum-bin:DONE');
        $this->fixture->pressAction('vacuum-bin:NEXT');

        self::assertSame(['DONE', 'NEXT'], $pressed);
    }

    public function testRoutesDismissalByTag(): void
    {
        $dismissed = [];
        $this->listen('vacuum-bin')
            ->onDismissed(function (NotificationDismissal $dismissal) use (&$dismissed): void {
                $dismissed[] = $dismissal;
            })
            ->onAnyAction(fn() => self::fail('Dismissal is not an action'));

        $this->fixture->dismissNotification(['tag' => 'vacuum-bin', 'message' => 'Empty me'], 'user-1');
        $this->fixture->dismissNotification(['data' => ['tag' => 'vacuum-bin']]);
        $this->fixture->dismissNotification(['tag' => 'laundry']);
        $this->fixture->dismissNotification(['message' => 'No tag']);

        self::assertCount(2, $dismissed);
        self::assertSame('vacuum-bin', $dismissed[0]->notificationId->value);
        self::assertSame('user-1', $dismissed[0]->userId);
        self::assertSame('Empty me', $dismissed[0]->eventData['message']);
    }

    public function testIgnoresOtherNotificationsAndForeignActions(): void
    {
        $this->listen('vacuum-bin')->onAnyAction(fn() => self::fail('Should not be called'));

        $this->fixture->pressAction('laundry:DONE');
        $this->fixture->pressAction('DONE');

        $this->expectNotToPerformAssertions();
    }

    public function testRoutesEachPressOnceWithSharedContext(): void
    {
        $pressed = [];
        $vacuum = $this->listen('vacuum-bin')->onAnyAction(function (NotificationAction $action) use (&$pressed): void {
            $pressed[] = $action->notificationId->value;
        });
        $this->listen('laundry')->onAnyAction(function (NotificationAction $action) use (&$pressed): void {
            $pressed[] = $action->notificationId->value;
        });

        $this->fixture->pressAction('vacuum-bin:DONE');
        $vacuum->stopListening();
        $this->fixture->pressAction('vacuum-bin:DONE');
        $this->fixture->pressAction('laundry:DONE');

        self::assertSame(['vacuum-bin', 'laundry'], $pressed);
    }

    public function testStoppingOlderListenerKeepsNewerWithSameId(): void
    {
        $pressed = 0;
        $older = $this->listen('vacuum-bin')->onAction('DONE', fn() => self::fail('Older listener was replaced'));
        $newer = $this->listen('vacuum-bin')->onAction('DONE', function () use (&$pressed): void {
            $pressed++;
        });

        $older->stopListening();
        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertSame(1, $pressed);
        self::assertFalse($older->isListening());
        self::assertTrue($newer->isListening());
    }

    public function testExpiredListenerNoLongerReceivesActions(): void
    {
        $listener = $this->listen('vacuum-bin')->onAction('DONE', fn() => self::fail('Should have expired'));

        $this->fixture->advanceTime(Duration::hours(self::LISTENING_TTL_HOURS));
        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertFalse($listener->isListening());
    }

    public function testFailingCallbackDoesNotSkipOthers(): void
    {
        $pressed = [];

        $this->listen('vacuum-bin')
            ->onAction('DONE', fn() => throw new RuntimeException('boom'))
            ->onAnyAction(function (NotificationAction $action) use (&$pressed): void {
                $pressed[] = $action->action;
            });

        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertSame(['DONE'], $pressed);
    }

    public function testSubscribesThroughGivenAppContext(): void
    {
        $app = new NotificationFixture();
        $pressed = 0;

        $this->send('vacuum-bin')->listenForActions($app->ha)->onAction('DONE', function () use (&$pressed): void {
            $pressed++;
        });
        $app->pressAction('vacuum-bin:DONE');
        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertSame(1, $pressed);
    }

    public function testIgnoresPressesFromOtherAppContext(): void
    {
        $other = new NotificationFixture();
        $this->listen('vacuum-bin')->onAnyAction(fn() => self::fail('Pressed through another app'));
        $this->send('laundry')->listenForActions($other->ha);

        $other->pressAction('vacuum-bin:DONE');

        $this->expectNotToPerformAssertions();
    }

    public function testUndeliveredNotificationGetsStoppedListener(): void
    {
        $sent = $this->notifier->send(NotificationBuilder::create()
            ->withBody('Hi')
            ->withAppendedButtons(new NotificationButton('DONE', 'Done'))
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify')->withAppendedConditions(CompareCondition::equals('input_boolean.away', 'on')))
            ->buildNotification());

        self::assertFalse($sent->wasDelivered());
        self::assertFalse($sent->listenForActions($this->fixture->ha)->onAnyAction(fn() => self::fail('Stopped listener was called'))->isListening());
        $this->fixture->pressAction($sent->notification->meta->id->value . ':DONE');
    }

    private function listen(string $id): ActionListener
    {
        return $this->send($id)->listenForActions($this->fixture->ha);
    }

    private function send(string $id): SentNotification
    {
        return $this->notifier->send(NotificationBuilder::create()
            ->withBody('Hi')
            ->withId(NotificationId::fromString($id))
            ->withAppendedButtons(new NotificationButton('DONE', 'Done'))
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification());
    }
}
