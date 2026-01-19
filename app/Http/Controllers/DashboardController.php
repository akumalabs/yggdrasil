<?php

namespace App\Http\Controllers;

use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // For now, take the first available token or handle no token case
        $token = ProxmoxToken::first();

        if (!$token) {
            // Redirect to setup or show empty state
            return Inertia::render('Dashboard', [
                'vms' => [],
                'nodes' => [],
                'error' => 'No Proxmox Token Configured'
            ]);
        }

        try {
            // Decrypt logic is handled by Model cast, but we need to reconstruct the full token for the client
            $fullToken = "{$token->token_id}={$token->token_secret}";
            
            $client = new ProxmoxClient($token->host, $fullToken);
            $resources = $client->clusterResources();
            
            // Filter resources to match VMs owned by the user
            $userVmIds = \App\Models\Vm::where('user_id', auth()->id())->pluck('vmid')->toArray();
            
            $vms = $resources->where('type', 'qemu')
                ->whereIn('vmid', $userVmIds)
                ->values();
            
            $nodes = $client->nodes()->pluck('node');

            return Inertia::render('Dashboard', [
                'vms' => $vms,
                'nodes' => $nodes
            ]);

        } catch (\Exception $e) {
             return Inertia::render('Dashboard', [
                'vms' => [],
                'nodes' => [],
                'error' => $e->getMessage()
            ]);
        }
    }
}
