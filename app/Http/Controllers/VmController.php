<?php

namespace App\Http\Controllers;

use App\Jobs\CreateVmJob;
use App\Models\ProxmoxToken;
use Illuminate\Http\Request;

class VmController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'node' => 'required|string',
            'name' => 'required|string',
            'memory' => 'required|integer|min:512',
            'cores' => 'required|integer|min:1',
            'vmid' => 'required|integer|unique:vms,vmid',
            'password' => 'nullable|string',
            'sshkeys' => 'nullable|string',
        ]);

        // Simple single-tenant assumption for now
        $token = ProxmoxToken::firstOrFail();

        // Prepare config for Proxmox
        $config = [
            'name' => $validated['name'],
            'memory' => $validated['memory'],
            'cores' => $validated['cores'],
            'ostype' => 'l26', // Default to Linux 2.6+
            'net0' => 'virtio,bridge=vmbr0', // Default network
            'scsi0' => 'local-lvm:32', // Default 32GB disk on local-lvm
            'ide2' => 'local:iso/ubuntu-22.04.1-live-server-amd64.iso,media=cdrom', // simplified ISO selection
        ];

        // Cloud Init params
        if (!empty($validated['password'])) {
            $config['cipassword'] = $validated['password'];
        }
        if (!empty($validated['sshkeys'])) {
            $config['sshkeys'] = urlencode($validated['sshkeys']);
        }
        
        // IP Config will be handled by Job (Static IP assignment)
        // $config['ipconfig0'] = 'ip=dhcp';

        // Dispatch Job
        CreateVmJob::dispatch($token->id, $validated['node'], $validated['vmid'], $config, auth()->id());

        return redirect()->back()->with('success', 'VM Creation Started (ID: ' . $validated['vmid'] . ')');
    }

    public function power(Request $request, int $vmid)
    {
        $validated = $request->validate([
            'action' => 'required|in:start,stop,shutdown,reboot,reset,suspend,resume',
            'node' => 'required|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();

        \App\Jobs\VmPowerJob::dispatch(
            $token->id, 
            $validated['node'], 
            $vmid, 
            $validated['action']
        );

        return redirect()->back()->with('success', "VM {$validated['action']} command sent.");
    }

    public function migrate(Request $request, int $vmid)
    {
        $validated = $request->validate([
            'target_node' => 'required|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        // Ensure ownership
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $sourceNode = $request->input('source_node'); 
        if (!$sourceNode) {
             return redirect()->back()->with('error', 'Source node required');
        }

        \App\Jobs\VmMigrateJob::dispatch(
            $token->id, 
            $sourceNode, 
            $vmid, 
            $validated['target_node']
        );

        return redirect()->back()->with('success', "Migration to {$validated['target_node']} started.");
    }

    public function reinstall(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();

        \App\Jobs\VmReinstallJob::dispatch($token->id, $vmid);

        return redirect()->back()->with('success', "Reinstall started for VM {$vmid}.");
    }

    public function console(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        $node = $vm->node;

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $vnc = $client->vncProxy($node, $vmid);

        return Inertia::render('Console', [
            'vmid' => $vmid,
            'node' => $node,
            'host' => $token->host, 
            'ticket' => $vnc['ticket'],
            'port' => $vnc['port'],
            'cert' => $vnc['cert'],
            'user' => $vnc['user'],
        ]);
    }

    public function show(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $config = $client->vmConfig($vm->node, $vmid);
        
        return Inertia::render('Vm/Show', [
            'vm' => $vm,
            'config' => $config,
        ]);
    }

    public function rescue(Request $request, int $vmid)
    {
        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $action = $request->input('enable') ? 'enable' : 'disable';
        $rescueIso = env('RESCUE_ISO', 'local:iso/debian-live.iso');
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            if ($action === 'enable') {
                // Mount Rescue ISO and set Boot Order to CD-ROM (ide2)
                $client->setConfig($vm->node, $vmid, [
                    'ide2' => "{$rescueIso},media=cdrom",
                    'boot' => 'order=ide2;scsi0'
                ]);
                $msg = "Rescue Mode Enabled. Mounted {$rescueIso}. Please Reboot.";
            } else {
                // Unmount ISO and restore Boot Order to Disk (scsi0)
                $client->setConfig($vm->node, $vmid, [
                    'ide2' => 'none,media=cdrom',
                    'boot' => 'order=scsi0;ide2'
                ]);
                $msg = 'Rescue Mode Disabled. ISO Unmounted. Please Reboot.';
            }
            
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to toggle rescue mode: ' . $e->getMessage());
        }
    }

    public function update(Request $request, int $vmid)
    {
        $request->validate([
            'cores' => 'nullable|integer|min:1',
            'memory' => 'nullable|integer|min:128',
            'cipassword' => 'nullable|string|min:6',
            'sshkeys' => 'nullable|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $vm = \App\Models\Vm::where('vmid', $vmid)->where('user_id', auth()->id())->firstOrFail();
        
        $data = $request->only(['cores', 'memory', 'cipassword', 'sshkeys']);
        
        // Filter out nulls
        $data = array_filter($data, fn($v) => !is_null($v));

        if (isset($data['sshkeys'])) {
            $data['sshkeys'] = urlencode($data['sshkeys']);
        }

        if (empty($data)) {
            return redirect()->back()->with('error', 'No changes provided.');
        }

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $client->setConfig($vm->node, $vmid, $data);
            
            // Update local model cache if we were storing it there
            $vm->update(['config' => array_merge($vm->config ?? [], $data)]);
            
            return redirect()->back()->with('success', 'VM configuration updated. Requires reboot for some changes if hotplug is not enabled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update VM: ' . $e->getMessage());
        }
    }
}
