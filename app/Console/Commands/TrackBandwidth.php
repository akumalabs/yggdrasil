<?php

namespace App\Console\Commands;

use App\Models\Vm;
use App\Models\VmBandwidthUsage;
use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Console\Command;

class TrackBandwidth extends Command
{
    protected $signature = 'bandwidth:track';
    protected $description = 'Track daily bandwidth usage for all VMs using RRD data';

    public function handle()
    {
        $token = ProxmoxToken::first();
        
        if (!$token) {
            $this->error('No Proxmox token configured. Run: php artisan tinker and create a ProxmoxToken.');
            return 1;
        }

        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $vms = Vm::all();
        
        foreach ($vms as $vm) {
            try {
                // Check if month rolled over - reset counter
                if ($vm->bandwidth_reset_date && $vm->bandwidth_reset_date->month !== now()->month) {
                    $vm->update([
                        'bandwidth_usage_bytes' => 0,
                        'bandwidth_reset_date' => now()->startOfMonth(),
                    ]);
                    $this->info("Reset bandwidth for VM {$vm->vmid} (new month)");
                }
                
                // Get monthly bandwidth from RRD
                $rrdData = $client->getVmMetrics($vm->node, $vm->vmid, 'month');
                
                if (empty($rrdData)) {
                    $this->warn("No RRD data for VM {$vm->vmid}");
                    continue;
                }
                
                // Filter to current month only
                $currentMonthStart = strtotime(now()->startOfMonth()->toDateString());
                $currentMonthData = array_filter($rrdData, fn($point) => $point['time'] >= $currentMonthStart);
                
                if (empty($currentMonthData)) {
                    $this->warn("No current month data for VM {$vm->vmid}");
                    continue;
                }
                
                // Calculate total bandwidth from first to last point
                $firstPoint = reset($currentMonthData);
                $lastPoint = end($currentMonthData);
                
                $bytesIn = max(0, ($lastPoint['netin'] ?? 0) - ($firstPoint['netin'] ?? 0));
                $bytesOut = max(0, ($lastPoint['netout'] ?? 0) - ($firstPoint['netout'] ?? 0));
                $totalBytes = $bytesIn + $bytesOut;
                
                // Update VM record
                $vm->update([
                    'bandwidth_usage_bytes' => $totalBytes,
                    'bandwidth_reset_date' => $vm->bandwidth_reset_date ?? now()->startOfMonth(),
                ]);
                
                // Store daily snapshot
                VmBandwidthUsage::updateOrCreate(
                    [
                        'vm_id' => $vm->id,
                        'date' => now()->toDateString(),
                    ],
                    [
                        'bytes_in' => $bytesIn,
                        'bytes_out' => $bytesOut,
                        'total_bytes' => $totalBytes,
                    ]
                );
                
                $this->info("✓ Tracked bandwidth for VM {$vm->vmid}: " . round($totalBytes / 1024 / 1024 / 1024, 2) . " GB");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to track VM {$vm->vmid}: {$e->getMessage()}");
            }
        }
        
        // Cleanup old records (keep 3 months)
        $deleted = VmBandwidthUsage::where('date', '<', now()->subMonths(3))->delete();
        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} old bandwidth records");
        }
        
        $this->info('Bandwidth tracking complete!');
        return 0;
    }
}
