<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use App\Services\ProxmoxClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ProxmoxClientTest extends TestCase
{
    public function test_it_can_fetch_nodes()
    {
        // Mock Guzzle Response
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    ['node' => 'pve1', 'status' => 'online'],
                    ['node' => 'pve2', 'status' => 'online'],
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        
        // Inject Custom Client (We need to refactor ProxmoxClient to accept a client or handler)
        // For now, let's subclass or modify ProxmoxClient to allow injection.
        // Or simpler: We can mock the Guzzle Client if it was injected. 
        // Our ProxmoxClient creates `new Client()` in constructor. This is hard to test.
        // We will modify ProxmoxClient to accept an optional client in constructor.
        
        $client = new ProxmoxClient('pve.test', 'token=123', ['handler' => $handlerStack]);
        
        $nodes = $client->nodes();
        
        $this->assertCount(2, $nodes);
        $this->assertEquals('pve1', $nodes[0]['node']);
    }

    public function test_it_handles_api_errors()
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $this->expectException(\Exception::class);
        
        $client = new ProxmoxClient('pve.test', 'token=123', ['handler' => $handlerStack]);
        $client->nodes();
    }
}
