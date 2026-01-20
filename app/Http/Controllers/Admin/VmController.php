<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CloneVmJob;
use App\Models\ProxmoxToken;
use App\Models\User;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VmController extends Controller
{
    public function create()
    {
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        // Get all templates from Proxmox
        $resources = $client->clusterResources();
        $templates = $resources->filter(fn($r) => isset($r['template']) && $r['template'] == 1)->values();
        
        return Inertia::render('Admin/Vm/Create', [
            'templates' => $templates,
            'users' => User::where('role', 'client')->get(['id', 'name', 'email']),
            'nodes' => $client->nodes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'template_vmid' => 'required|integer',
            'name' => 'required|string|max:255',
            'hostname' => 'nullable|string|regex:/^[a-z0-9\.]+$/',
            'cores' => 'required|integer|min:1|max:32',
            'memory' => 'required|integer|min:1|max:256',
            'disk' => 'required|integer|min:10|max:1000',
            'bandwidth' => 'required|integer|min:1|max:100',
            'password' => 'required|string|min:8',
        ]);

        // Auto-fill hostname if empty
        if (empty($validated['hostname'])) {
            $validated['hostname'] = Str::of($validated['name'])
                ->lower()
                ->replace(' ', '.')
                ->replaceMatches('/[^a-z0-9.]/', '');
        }

        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");

        // Get next available VMID
        $vmid = $client->getNextVmid();

        // Get template info
        $resources = $client->clusterResources();
        $template = $resources->firstWhere('vmid', $validated['template_vmid']);

        if (!$template) {
            return back()->withErrors(['template_vmid' => 'Template not found']);
        }

        // Dispatch clone job with user assignment
        CloneVmJob::dispatch(
            $token->id,
            $template['node'],
            $validated['template_vmid'],
            $vmid,
            $validated['name'],
            $validated['hostname'],
            $validated['user_id'], // Assigned user
            $validated['cores'],
            $validated['memory'],
            $validated['disk'],
            $validated['password'],
            $validated['bandwidth']
        );

        return redirect()->route('admin.dashboard')
            ->with('success', "VM '{$validated['name']}' (VMID: {$vmid}) assigned to user and deploying...");
    }

    public function index()
    {
        $vms = Vm::with('user:id,name,email')->get();
        
        return Inertia::render('Admin/Vm/Index', [
            'vms' => $vms,
        ]);
    }

    public function destroy(int $vmid)
    {
        $vm = Vm::where('vmid', $vmid)->firstOrFail();
        
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            // Delete from Proxmox
            $client->post("/nodes/{$vm->node}/qemu/{$vmid}", [], 'DELETE');
            
            // Delete from database
            $vm->delete();
            
            return back()->with('success', "VM {$vmid} deleted successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to delete VM: {$e->getMessage()}"]);
        }
    }
}
