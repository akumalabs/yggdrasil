<?php

namespace App\Console\Commands;

use App\Models\Vm;
use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    protected $signature = 'backups:scheduled {--retain=7 : Number of backups to retain per VM}';
    protected $description = 'Run scheduled backups for all VMs and enforce retention policy';

    public function handle()
    {
        $token = ProxmoxToken::first();
        
        if (!$token) {
            $this->error('No Proxmox token configured.');
            return 1;
        }

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $retainCount = $this->option('retain');
        
        $vms = Vm::where('status', 'running')->get();
        
        foreach ($vms as $vm) {
            try {
                // Create backup
                $this->info("Creating backup for VM {$vm->vmid} ({$vm->name})...");
                $upid = $client->createBackup($vm->node, $vm->vmid);
                $this->info("✓ Backup started: {$upid}");
                
                // Enforce retention policy
                $this->enforceRetention($client, $vm, $retainCount);
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to backup VM {$vm->vmid}: {$e->getMessage()}");
            }
        }
        
        $this->info('Scheduled backups complete!');
        return 0;
    }

    private function enforceRetention($client, $vm, $retainCount)
    {
        try {
            $backups = $client->listBackups($vm->node);
            
            // Filter backups for this VM only
            $vmBackups = collect($backups)
                ->filter(fn($b) => isset($b['vmid']) && $b['vmid'] == $vm->vmid)
                ->sortByDesc('ctime') // Sort by creation time, newest first
                ->values();
            
            if ($vmBackups->count() <= $retainCount) {
                return; // Nothing to delete
            }
            
            // Keep only the most recent backups
            $toDelete = $vmBackups->slice($retainCount);
            
            foreach ($toDelete as $backup) {
                $this->info("  Deleting old backup: {$backup['volid']}");
                $client->deleteBackup($vm->node, 'local', urlencode($backup['volid']));
            }
            
            $this->info("  Retained {$retainCount} backups, deleted " . $toDelete->count());
            
        } catch (\Exception $e) {
            $this->warn("  Could not enforce retention for VM {$vm->vmid}: {$e->getMessage()}");
        }
    }
}
