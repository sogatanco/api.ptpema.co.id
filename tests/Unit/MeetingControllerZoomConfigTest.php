<?php

namespace Tests\Unit;

use App\Http\Controllers\Meeting\MeetingController;
use Tests\TestCase;

class MeetingControllerZoomConfigTest extends TestCase
{
    public function test_resolve_zoom_credentials_prefers_configured_service_values(): void
    {
        config()->set('services.zoom.client_id', 'config-client-id');
        config()->set('services.zoom.client_secret', 'config-client-secret');
        config()->set('services.zoom.account_id', 'config-account-id');

        $controller = new class extends MeetingController {
            public function __construct() {}

            public function exposeResolveZoomCredentials(): array
            {
                return $this->resolveZoomCredentials();
            }
        };

        $this->assertSame([
            'client_id' => 'config-client-id',
            'client_secret' => 'config-client-secret',
            'account_id' => 'config-account-id',
        ], $controller->exposeResolveZoomCredentials());
    }
}
