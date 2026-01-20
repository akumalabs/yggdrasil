<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class ProxmoxClient
{
    protected Client $client;

    public function __construct(string $host, string $token, array $options = [])
    {
        $defaultOptions = [
            'base_uri' => "https://{$host}:8006/api2/json/",
            'headers' => [
                'Authorization' => "PVEAPIToken={$token}",
                'Accept' => 'application/json',
            ],
            'verify' => env('PROXMOX_SSL_VERIFY', true),
            'timeout' => 30,
        ];

        $this->client = new Client(array_merge($defaultOptions, $options));
    }

    public function get(string $path): array
    {
        $path = ltrim($path, '/');
        $response = $this->client->get($path);
        return json_decode($response->getBody(), true)['data'] ?? [];
    }

    public function post(string $path, array $data, string $method = 'POST'): array|string
    {
        $path = ltrim($path, '/');
        $response = $this->client->request($method, $path, [
            'form_params' => $data
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['data'] ?? $result;
    }

    // Nodes
    public function nodes(): Collection { return collect($this->get('/nodes')); }

    // Cluster Resources (VMs only)
    public function clusterResources(): Collection { return collect($this->get('/cluster/resources?type=vm')); }

    public function vmConfig(string $node, int $vmid): array { return $this->get("/nodes/{$node}/qemu/{$vmid}/config"); }
    
    public function createVm(string $node, array $config): string 
    { 
        return $this->post("/nodes/{$node}/qemu", $config); 
    }

    public function taskStatus(string $node, string $upid): array 
    { 
        return $this->get("/nodes/{$node}/tasks/{$upid}/status"); 
    }

    public function vmAction(string $node, int $vmid, string $action): string
    {
        // actions: start, stop, shutdown, reboot, suspend, resume, reset
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/{$action}", []);
    }

    public function migrate(string $node, int $vmid, string $target, bool $online = true): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/migrate", [
            'target' => $target,
            'online' => $online ? 1 : 0,
            'with-local-disks' => 1, 
        ]);
    }

    public function deleteVm(string $node, int $vmid): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}", [], 'DELETE');
    }

    public function vncProxy(string $node, int $vmid): array
    {
        // 1. Create Ticket
        $res = $this->post("/nodes/{$node}/qemu/{$vmid}/vncproxy", [
            'websocket' => 1
        ]);
        
        return $res; // contains ticket, port, cert, user, etc.
    }

    public function storage(string $node): array 
    { 
        return $this->get("/nodes/{$node}/storage"); 
    }

    public function storageContent(string $node, string $storage): array 
    { 
        return $this->get("/nodes/{$node}/storage/{$storage}/content"); 
    }

    public function setConfig(string $node, int $vmid, array $data): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/config", $data);
    }

    public function getFirewallRules(string $node, int $vmid): array
    {
        return $this->get("/nodes/{$node}/qemu/{$vmid}/firewall/rules");
    }

    public function addFirewallRule(string $node, int $vmid, array $rule): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/firewall/rules", $rule);
    }

    public function getSnapshots(string $node, int $vmid): array
    {
        // Proxmox returns a tree, but often flat list with 'parent' field.
        // Endpoint: /nodes/{node}/qemu/{vmid}/snapshot
        $snapshots = $this->get("/nodes/{$node}/qemu/{vmid}/snapshot");
        // Filter out 'current' state if needed, but UI might want to show it.
        return $snapshots;
    }

    public function createSnapshot(string $node, int $vmid, string $snapname, string $description = ''): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/snapshot", [
            'snapname' => $snapname,
            'description' => $description,
            'vmstate' => 1 // Include RAM
        ]);
    }

    public function rollbackSnapshot(string $node, int $vmid, string $snapname): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}/rollback", []);
    }

    public function deleteSnapshot(string $node, int $vmid, string $snapname): string
    {
        // DELETE method is not supported by our helper yet properly as 'post' defaults to POST.
        // We need to support DELETE.
        return $this->post("/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}", [], 'DELETE');
    }

    public function setBootOrder(string $node, int $vmid, string $order): string
    {
        // order example: 'ide2;scsi0'
        return $this->post("/nodes/{$node}/qemu/{$vmid}/config", [
            'boot' => "order={$order}"
        ]);
    }

    // QEMU Guest Agent
    public function getGuestAgentInfo(string $node, int $vmid): array
    {
        try {
            return $this->get("/nodes/{$node}/qemu/{$vmid}/agent/info");
        } catch (\Exception $e) {
            return ['error' => 'Guest agent not running'];
        }
    }

    public function getGuestOsInfo(string $node, int $vmid): array
    {
        return $this->get("/nodes/{$node}/qemu/{$vmid}/agent/get-osinfo");
    }

    public function getGuestDiskUsage(string $node, int $vmid): array
    {
        return $this->get("/nodes/{$node}/qemu/{$vmid}/agent/get-fsinfo");
    }

    public function getGuestNetworkInterfaces(string $node, int $vmid): array
    {
        return $this->get("/nodes/{$node}/qemu/{$vmid}/agent/network-get-interfaces");
    }

    // Helper: Get Next Available VMID
    public function getNextVmid(): int
    {
        $resources = $this->clusterResources();
        $existingVmids = $resources->pluck('vmid')->toArray();
        
        // Start from 100, find first available
        $vmid = 100;
        while (in_array($vmid, $existingVmids)) {
            $vmid++;
        }
        
        return $vmid;
    }

    // Templates
    public function convertToTemplate(string $node, int $vmid): string
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/template", []);
    }

    public function listTemplates(): array
    {
        $resources = $this->clusterResources();
        return $resources->filter(fn($r) => isset($r['template']) && $r['template'] == 1)->values()->toArray();
    }

    // Backups
    public function createBackup(string $node, int $vmid, string $storage = 'local', string $mode = 'snapshot'): string
    {
        return $this->post("/nodes/{$node}/vzdump", [
            'vmid' => $vmid,
            'storage' => $storage,
            'mode' => $mode, // snapshot, suspend, stop
            'compress' => 'zstd',
        ]);
    }

    public function listBackups(string $node, string $storage = 'local'): array
    {
        return $this->get("/nodes/{$node}/storage/{$storage}/content?content=backup");
    }

    public function restoreBackup(string $node, int $newVmid, string $archive, string $storage = 'local'): string
    {
        return $this->post("/nodes/{$node}/qemu", [
            'vmid' => $newVmid,
            'archive' => $archive,
            'storage' => $storage,
        ]);
    }

    public function deleteBackup(string $node, string $storage, string $volid): string
    {
        return $this->post("/nodes/{$node}/storage/{$storage}/content/{$volid}", [], 'DELETE');
    }
}
