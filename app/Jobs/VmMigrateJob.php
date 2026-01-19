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

class VmMigrateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 mins

    public function __construct(
        public int $tokenId,
        public string $sourceNode,
        public int $vmid,
        public string $targetNode
    ) {}

    public function handle(): void
    {
        $token = ProxmoxToken::find($this->tokenId);
        if (!$token) return;

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $vm = Vm::where('vmid', $this->vmid)->first();

        try {
            if ($vm) $vm->update(['status' => 'migrating']);

            $upid = $client->migrate($this->sourceNode, $this->vmid, $this->targetNode);

            // Poll
            $completed = false;
            while (!$completed) {
                sleep(2);
                $status = $client->taskStatus($this->sourceNode, $upid);
                
                if (($status['status'] ?? '') === 'stopped') {
                    $completed = true;
                    if (($status['exitstatus'] ?? '') === 'OK') {
                        // Success -> Update Node in DB
                        if ($vm) $vm->update(['node' => $this->targetNode, 'status' => 'running']);
                    } else {
                        if ($vm) $vm->update(['status' => 'error']);
                    }
                }
            }
        } catch (\Exception $e) {
            if ($vm) $vm->update(['status' => 'error']);
            Log::error("Migration Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
