<?php

namespace App\Http\Controllers;

use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SnapshotController extends Controller
{
    public function index(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $snapshots = $client->getSnapshots($vm->node, $vmid);

        return Inertia::render('Vm/Snapshots', [
            'vm' => $vm,
            'snapshots' => $snapshots,
        ]);
    }

    public function store(Request $request, int $vmid)
    {
        $request->validate([
            'name' => 'required|string|max:40|regex:/^[a-zA-Z0-9_\-]+$/',
            'description' => 'nullable|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $client->createSnapshot($vm->node, $vmid, $request->name, $request->description ?? '');
            return redirect()->back()->with('success', 'Snapshot creation started (Task queued).');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create snapshot: ' . $e->getMessage());
        }
    }

    public function rollback(Request $request, int $vmid, string $snapname)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $client->rollbackSnapshot($vm->node, $vmid, $snapname);
            return redirect()->back()->with('success', "Rollback to '{$snapname}' started.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to rollback: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, int $vmid, string $snapname)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $client->deleteSnapshot($vm->node, $vmid, $snapname);
            return redirect()->back()->with('success', "Snapshot '{$snapname}' deletion started.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete snapshot: ' . $e->getMessage());
        }
    }
}
