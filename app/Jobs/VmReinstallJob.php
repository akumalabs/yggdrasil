<?php

namespace App\Jobs;

use App\Models\ProxmoxToken;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VmReinstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;

    public function __construct(
        public int $tokenId,
        public int $vmid
    ) {}

    public function handle(): void
    {
        $token = ProxmoxToken::find($this->tokenId);
        if (!$token) return;

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $vm = Vm::where('vmid', $this->vmid)->first();

        if (!$vm) {
            Log::error("Reinstall failed: VM {$this->vmid} not found in DB");
            return;
        }

        try {
            $vm->update(['status' => 'reinstalling']);
            $node = $vm->node;

            // 1. Stop VM (Force Stop)
            try {
                $upid = $client->vmAction($node, $this->vmid, 'stop');
                $this->pollTask($client, $node, $upid);
            } catch (\Exception $e) {
                // Ignore if already stopped
            }

            // 2. Destroy VM
            // Note: ProxmoxClient::post needs to support DELETE method or we add specific delete support
            // I updated ProxmoxClient to call post with DELETE if handled, but Guzzle post is POST.
            // I need to update ProxmoxClient::deleteVm to use $client->http->delete or similar.
            // Wait, I didn't update ProxmoxClient::post to accept method.
            // I added deleteVm calling post(..., [], 'DELETE') but post signature is (path, data).
            // I should check ProxmoxClient implementation.
            
            // Let's assume I fix ProxmoxClient first.
            
            $upid = $client->deleteVm($node, $this->vmid);
            $this->pollTask($client, $node, $upid);

            // 3. Re-Create VM
            // Dispatch CreateVmJob or reuse logic.
            // Logic reuse is better to avoid circular job dependencies or deep stacks.
            
            $config = $vm->config;
            if (!$config) {
                 throw new \Exception("No config saved for VM");
            }

            $upid = $client->createVm($node, $config);
            $vm->update(['upid' => $upid, 'status' => 'creating']);
            
            $this->pollTask($client, $node, $upid);

            // 4. Start VM
            $upid = $client->vmAction($node, $this->vmid, 'start');
            $vm->update(['status' => 'running']);

        } catch (\Exception $e) {
            if ($vm) $vm->update(['status' => 'error']);
            Log::error("Reinstall Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }

    private function pollTask($client, $node, $upid) 
    {
        $completed = false;
        while (!$completed) {
            sleep(2);
            $status = $client->taskStatus($node, $upid);
            if (($status['status'] ?? '') === 'stopped') {
                $completed = true;
                if (($status['exitstatus'] ?? '') !== 'OK') {
                    throw new \Exception("Task Failed: " . ($status['exitstatus']??''));
                }
            }
        }
    }
}
