<?php

namespace App\Http\Controllers;

use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirewallController extends Controller
{
    public function index(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $rules = $client->getFirewallRules($vm->node, $vmid);

        return Inertia::render('Vm/Firewall', [
            'vm' => $vm,
            'rules' => $rules,
        ]);
    }

    public function store(Request $request, int $vmid)
    {
        $request->validate([
            'type' => 'required|in:in,out',
            'action' => 'required|in:ACCEPT,DROP,REJECT',
            'proto' => 'nullable|string',
            'source' => 'nullable|string',
            'dest' => 'nullable|string',
            'sport' => 'nullable|string',
            'dport' => 'nullable|string',
            'comment' => 'nullable|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            // Map request data to Proxmox API fields
            // Proxmox API uses 'type' (in/out), 'action', etc.
            // Note: 'enable' defaults to 1 usually.
            
            $ruleData = $request->only(['type', 'action', 'proto', 'source', 'dest', 'sport', 'dport', 'comment']);
            $ruleData['enable'] = 1;

            $client->addFirewallRule($vm->node, $vmid, $ruleData);
            
            return redirect()->back()->with('success', 'Firewall rule added.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to add rule: ' . $e->getMessage());
        }
    }
}
