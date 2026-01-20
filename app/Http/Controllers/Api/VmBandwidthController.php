<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vm;
use App\Models\VmBandwidthUsage;
use Illuminate\Http\Request;

class VmBandwidthController extends Controller
{
    public function show(Request $request, int $vmid)
    {
        $vm = Vm::where('vmid', $vmid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        // Get current month usage from VM record (updated by cron)
        $usedBytes = $vm->bandwidth_usage_bytes ?? 0;
        $allocatedBytes = ($vm->bandwidth_limit ?? 0) * 1024 * 1024 * 1024 * 1024; // TB to bytes
        
        $usedTB = round($usedBytes / 1024 / 1024 / 1024 / 1024, 2);
        $allocatedTB = $vm->bandwidth_limit ?? 0;
        
        // Get daily breakdown for current month
        $currentMonthStart = now()->startOfMonth();
        $dailyUsage = VmBandwidthUsage::where('vm_id', $vm->id)
            ->where('date', '>=', $currentMonthStart)
            ->orderBy('date')
            ->get()
            ->map(fn($record) => [
                'date' => $record->date->format('Y-m-d'),
                'total_gb' => round($record->total_bytes / 1024 / 1024 / 1024, 2),
            ]);
        
        return response()->json([
            'allocated_tb' => $allocatedTB,
            'used_tb' => $usedTB,
            'remaining_tb' => max(0, $allocatedTB - $usedTB),
            'usage_percent' => $allocatedTB > 0 ? round(($usedTB / $allocatedTB) * 100, 2) : 0,
            'daily_usage' => $dailyUsage,
            'reset_date' => now()->addMonth()->startOfMonth()->format('Y-m-d'),
            
            // API format compatible with standard patterns
            'usages' => [
                'bandwidth' => $usedBytes,
            ],
            'limits' => [
                'bandwidth' => $allocatedBytes,
            ],
        ]);
    }
}
