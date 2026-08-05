<?php

namespace Tests;

use App\Events\ChatMessageBroadcast;
use App\Events\ChatMessageDeletedBroadcast;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // QUEUE_CONNECTION=sync in tests means a ShouldBroadcast event's queued job runs
        // inline, in-request — without this, every test that sends/deletes a chat message
        // would fire a REAL network call to Pusher. Faked globally (not per test file) so
        // this holds regardless of which test happens to touch chat. This does NOT affect
        // BROADCAST_CONNECTION=pusher's config still being live for /broadcasting/auth
        // requests — channel authorization is a local HMAC signing check, no network call,
        // so tests that hit that endpoint directly still exercise the real authorization
        // rule in routes/channels.php.
        Event::fake([ChatMessageBroadcast::class, ChatMessageDeletedBroadcast::class]);
    }
}
