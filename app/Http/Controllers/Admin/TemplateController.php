<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProxmoxToken;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index()
    {
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        $templates = $client->listTemplates();
        
        return Inertia::render('Admin/Template/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vmid' => 'required|integer|exists:vms,vmid',
        ]);

        $vm = Vm::where('vmid', $validated['vmid'])->firstOrFail();
        
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            // Convert VM to template (irreversible)
            $client->convertToTemplate($vm->node, $vm->vmid);
            
            // Update local record
            $vm->update(['status' => 'template']);
            
            return back()->with('success', "VM {$vm->vmid} converted to template successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to convert: {$e->getMessage()}"]);
        }
    }
}
