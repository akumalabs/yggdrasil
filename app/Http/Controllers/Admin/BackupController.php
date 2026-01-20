<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProxmoxToken;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index(int $vmid)
    {
        $vm = Vm::where('vmid', $vmid)->firstOrFail();
        
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $backups = $client->listBackups($vm->node);
            
            // Filter backups for this VM only
            $vmBackups = collect($backups)->filter(function ($backup) use ($vmid) {
                return isset($backup['vmid']) && $backup['vmid'] == $vmid;
            })->values();
            
            return Inertia::render('Admin/Backup/Index', [
                'vm' => $vm,
                'backups' => $vmBackups,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Admin/Backup/Index', [
                'vm' => $vm,
                'backups' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request, int $vmid)
    {
        $validated = $request->validate([
            'storage' => 'nullable|string',
            'mode' => 'nullable|in:snapshot,suspend,stop',
        ]);

        $vm = Vm::where('vmid', $vmid)->firstOrFail();
        
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $upid = $client->createBackup(
                $vm->node,
                $vmid,
                $validated['storage'] ?? 'local',
                $validated['mode'] ?? 'snapshot'
            );
            
            return back()->with('success', "Backup started successfully. Task: {$upid}");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to create backup: {$e->getMessage()}"]);
        }
    }

    public function restore(Request $request)
    {
        $validated = $request->validate([
            'archive' => 'required|string',
            'node' => 'required|string',
            'storage' => 'nullable|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $newVmid = $client->getNextVmid();
            
            $upid = $client->restoreBackup(
                $validated['node'],
                $newVmid,
                $validated['archive'],
                $validated['storage'] ?? 'local'
            );
            
            return back()->with('success', "Restore started. New VMID: {$newVmid}. Task: {$upid}");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to restore: {$e->getMessage()}"]);
        }
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'node' => 'required|string',
            'storage' => 'required|string',
            'volid' => 'required|string',
        ]);

        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        try {
            $client->deleteBackup(
                $validated['node'],
                $validated['storage'],
                urlencode($validated['volid'])
            );
            
            return back()->with('success', 'Backup deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to delete backup: {$e->getMessage()}"]);
        }
    }
}
