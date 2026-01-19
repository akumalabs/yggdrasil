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

class VmPowerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(
        public int $tokenId,
        public string $node,
        public int $vmid,
        public string $action
    ) {}

    public function handle(): void
    {
        $token = ProxmoxToken::find($this->tokenId);
        if (!$token) return;

        $fullToken = "{$token->token_id}={$token->token_secret}";
        $client = new ProxmoxClient($token->host, $fullToken);

        $vm = Vm::where('vmid', $this->vmid)->first();

        try {
            if ($vm) $vm->update(['status' => ($this->action === 'start' ? 'starting' : 'stopping')]);

            $upid = $client->vmAction($this->node, $this->vmid, $this->action);
            
            // Poll
            $completed = false;
            while (!$completed) {
                sleep(2);
                $status = $client->taskStatus($this->node, $upid);
                
                if (($status['status'] ?? '') === 'stopped') {
                    $completed = true;
                    $exitStatus = $status['exitstatus'] ?? 'unknown';
                    
                    if ($exitStatus === 'OK') {
                        // We rely on background sync or next refresh to get exact status, 
                        // but we can optimistic update here
                        $finalStatus = match($this->action) {
                            'start', 'resume' => 'running',
                            'stop', 'shutdown' => 'stopped',
                            'pause' => 'paused',
                            default => 'unknown'
                        };
                        if ($vm) $vm->update(['status' => $finalStatus]);
                    } else {
                        if ($vm) $vm->update(['status' => 'error']);
                        // Log failure
                    }
                }
            }
        } catch (\Exception $e) {
            if ($vm) $vm->update(['status' => 'error']);
            Log::error("VM Power Action Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
