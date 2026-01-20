<?php

namespace App\Jobs;

use App\Events\VmInstallationProgress;
use App\Models\IpAddress;
use App\Models\ProxmoxToken;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloneVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tokenId,
        public string $node,
        public int $sourceVmid,
        public int $newVmid,
        public string $name,
        public string $hostname,
        public int $userId,
        public int $cores,
        public int $memory, // GB
        public int $disk, // GB
        public string $password,
        public ?int $bandwidth = null // TB
    ) {}

    public function handle(): void
    {
        $token = ProxmoxToken::find($this->tokenId);
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");

        $this->broadcast('Preparing to clone template...', 5);

        // Step 1: Clone template
        $this->broadcast("Cloning from template VMID {$this->sourceVmid}...", 10);
        $upid = $client->post("/nodes/{$this->node}/qemu/{$this->sourceVmid}/clone", [
            'newid' => $this->newVmid,
            'name' => $this->hostname,
            'full' => 1,
            'target' => $this->node,
        ]);
        
        // Create local DB record
        $vm = Vm::create([
            'vmid' => $this->newVmid,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'node' => $this->node,
            'status' => 'cloning',
            'user_id' => $this->userId,
            'upid' => $upid,
            'bandwidth_limit' => $this->bandwidth,
            'bandwidth_reset_date' => now()->startOfMonth(),
        ]);

        // Poll clone task
        $this->broadcast('Cloning VM...', 30);
        $this->pollTask($client, $upid);
        
        // Step 2: Assign IP Address
        $this->broadcast('Assigning IP address...', 50);
        $ip = IpAddress::free()->first();
        if (!$ip) {
            $this->broadcast('No free IPs available', 0, 'error');
            $this->fail(new \Exception('No free IPs available'));
            return;
        }
        $ip->update(['vm_id' => $vm->id, 'is_reserved' => true]);
        $this->broadcast("Assigned IP: {$ip->ip}/{$ip->netmask}", 55);
        
        // Step 3: Customize resources
        $this->broadcast('Configuring resources...', 60);
        $client->setConfig($this->node, $this->newVmid, [
            'cores' => $this->cores,
            'memory' => $this->memory * 1024,
            'ipconfig0' => "ip={$ip->ip}/{$ip->netmask},gw={$ip->gateway}",
            'cipassword' => $this->password,
            'agent' => 'enabled=1',
        ]);
        
        // Resize disk if needed
        if ($this->disk > 20) {
            $this->broadcast("Resizing disk to {$this->disk}GB...", 70);
            $upidResize = $client->post("/nodes/{$this->node}/qemu/{$this->newVmid}/resize", [
                'disk' => 'scsi0',
                'size' => "+{$this->disk}G",
            ]);
            $this->pollTask($client, $upidResize);
        }
        
        // Apply bandwidth limit
        if ($this->bandwidth) {
            $this->broadcast("Applying bandwidth limit ({$this->bandwidth} TB/month)...", 80);
            $this->applyBandwidthLimit($client);
        }
        
        // Step 4: Start VM
        $this->broadcast('Starting VM...', 90);
        $upidStart = $client->post("/nodes/{$this->node}/qemu/{$this->newVmid}/status/start", []);
        $this->pollTask($client, $upidStart);
        
        $vm->update(['status' => 'running']);
        $this->broadcast('✓ VM deployed successfully!', 100, 'success');
    }

    private function applyBandwidthLimit($client)
    {
        $mbps = ($this->bandwidth * 1024 * 8) / (30 * 24 * 60 * 60);
        
        $client->setConfig($this->node, $this->newVmid, [
            'net0' => "virtio,bridge=vmbr0,rate={$mbps}"
        ]);
    }

    private function pollTask($client, $upid)
    {
        $completed = false;
        $maxAttempts = 300;
        $attempt = 0;

        while (!$completed && $attempt < $maxAttempts) {
            sleep(2);
            $attempt++;
            
            $status = $client->taskStatus($this->node, $upid);
            
            if ($status['status'] === 'stopped') {
                if ($status['exitstatus'] !== 'OK') {
                    $this->broadcast('Task failed', 0, 'error');
                    $this->fail(new \Exception("Task failed: {$upid}"));
                }
                $completed = true;
            }
        }

        if (!$completed) {
            $this->broadcast('Task timeout', 0, 'error');
            $this->fail(new \Exception("Task timeout: {$upid}"));
        }
    }

    private function broadcast(string $step, int $progress, string $status = 'running')
    {
        event(new VmInstallationProgress($this->newVmid, $this->userId, $step, $progress, $status));
    }
}
