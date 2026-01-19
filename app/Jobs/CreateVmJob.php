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

class CreateVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes max

    public function __construct(
        public int $tokenId,
        public string $node,
        public int $vmid,
        public array $config,
        public int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = ProxmoxToken::find($this->tokenId);
        if (!$token) {
            $this->fail(new \Exception("Token not found"));
            return;
        }

        $fullToken = "{$token->token_id}={$token->token_secret}";
        $client = new ProxmoxClient($token->host, $fullToken);

        // Assign IP Address
        // We do this inside the job to ensure atomicity during execution (rudimentary check here)
        // Ideally we grab it before dispatch, but let's do it here.
        $ip = \App\Models\IpAddress::free()->first();
        
        if (!$ip) {
            $this->fail(new \Exception("No free IP addresses available in the pool."));
            return;
        }

        // Reserve it preliminarily (though we check vm creation first)
        // Actually, we should assign it to the VM record we are about to create.

        // Update config with static IP
        // Format: ip=CIDR,gw=GATEWAY
        $this->config['ipconfig0'] = "ip={$ip->ip}/{$ip->netmask},gw={$ip->gateway}";

        // create local record
        $vm = Vm::create([
            'vmid' => $this->vmid,
            'name' => $this->config['name'] ?? "vm-{$this->vmid}",
            'node' => $this->node,
            'status' => 'creating',
            'config' => $this->config,
            'user_id' => $this->userId,
        ]);

        // Lock IP to VM
        $ip->update(['vm_id' => $vm->id, 'is_reserved' => true]);

        try {
            // Merge vmid into config
            $this->config['vmid'] = $this->vmid;
            
            // Start Creation
            $upid = $client->createVm($this->node, $this->config);
            $vm->update(['upid' => $upid]);

            // Poll
            $completed = false;
            while (!$completed) {
                // Wait 2 seconds
                sleep(2);

                $status = $client->taskStatus($this->node, $upid);
                
                if (($status['status'] ?? '') === 'stopped') {
                    $completed = true;
                    $exitStatus = $status['exitstatus'] ?? 'unknown';
                    
                    if ($exitStatus === 'OK') {
                        $vm->update(['status' => 'stopped']); // Created successfully (stopped by default)
                    } else {
                        $vm->update(['status' => 'error']);
                        $this->fail(new \Exception("Proxmox Task Failed: " . $exitStatus));
                    }
                }
            }

        } catch (\Exception $e) {
            $vm->update(['status' => 'error']);
            Log::error("VM Creation Failed: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
