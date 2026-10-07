<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_the_api_health_endpoint_reports_a_database_connection(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()->assertExactJson([
            'data' => [
                'status' => 'ok',
                'database' => 'connected',
            ],
        ]);
    }
}
