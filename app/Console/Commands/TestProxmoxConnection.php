<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProxmoxClient;

class TestProxmoxConnection extends Command
{
    protected $signature = 'pve:test {host} {token_id} {token_secret}';
    protected $description = 'Test connection to Proxmox Node';

    public function handle()
    {
        $host = $this->argument('host');
        // Token format expected by ProxmoxClient constructor is usually full token string or we handle composition there?
        // ProxmoxClient expects $token. 
        // In implementation plan: Authorization header is "PVEAPIToken={$this->token}"
        // PVE Token format: USER@REALM!TOKENID=UUID
        // So $token should be "USER@REALM!TOKENID=UUID"
        
        // The command arguments are split distinct parts for clarity, but we need to combine them carefully or just ask for full token string.
        // Let's assume user passes "user@pam!tokenid" and "secretuuid".
        
        $tokenId = $this->argument('token_id');
        $tokenSecret = $this->argument('token_secret');
        
        $fullToken = "{$tokenId}={$tokenSecret}";

        $this->info("Connecting to {$host} with token {$tokenId}...");

        try {
            $client = new ProxmoxClient($host, $fullToken);
            $nodes = $client->nodes();
            
            $this->info("Connection Successful! Found nodes:");
            foreach ($nodes as $node) {
                $this->line("- " . $node['node'] . " (Status: " . $node['status'] . ")");
            }
        } catch (\Exception $e) {
            $this->error("Connection Failed: " . $e->getMessage());
            $this->error("Ensure host is reachable and token is correct.");
        }
    }
}
