<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app');
        config()->set('broadcasting.connections.reverb.options.host', '127.0.0.1');
        config()->set('broadcasting.connections.reverb.options.port', 8080);
        config()->set('broadcasting.connections.reverb.options.scheme', 'http');
    }

    public function test_notification_service_dispatches_realtime_event(): void
    {
        Event::fake([NotificationCreated::class]);

        $recipient = User::factory()->create();
        $service = app(NotificationService::class);

        $notification = $service->createContestWinner(
            (int) $recipient->id,
            15,
            'Realtime contest',
            42
        );

        $this->assertNotNull($notification);

        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($recipient) {
            $channels = $event->broadcastOn();
            $payload = $event->broadcastWith();

            return ($channels[0]->name ?? null) === 'private-users.' . $recipient->id
                && (int) ($payload['notification']['id'] ?? 0) > 0
                && ($payload['notification']['type'] ?? null) === 'contest_winner';
        });
    }

    public function test_user_cannot_authenticate_another_users_private_channel(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $kernel = $this->app->make(HttpKernel::class);
        $request = Request::create('/broadcasting/auth', 'POST', [
                'channel_name' => 'private-users.' . $owner->id,
                'socket_id' => '1234.5678',
            ]);
        $request->setUserResolver(static fn () => $intruder);
        $request->headers->set('Accept', 'application/json');

        $response = $kernel->handle($request);

        $this->assertSame(403, $response->getStatusCode());
    }

}
