<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFrontendApiRequestsAreStateful;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Http\Kernel;
use Tests\TestCase;

class HttpMiddlewarePriorityTest extends TestCase
{
    public function test_custom_stateful_api_middleware_runs_before_authentication(): void
    {
        $priority = app(Kernel::class)->getMiddlewarePriority();

        $statefulIndex = array_search(EnsureFrontendApiRequestsAreStateful::class, $priority, true);
        $authIndex = array_search(AuthenticatesRequests::class, $priority, true);

        $this->assertIsInt($statefulIndex);
        $this->assertIsInt($authIndex);
        $this->assertLessThan($authIndex, $statefulIndex);
    }
}
