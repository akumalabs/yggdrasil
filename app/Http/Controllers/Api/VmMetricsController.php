<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProxmoxToken;
use App\Models\Vm;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;

class VmMetricsController extends Controller
{
    public function show(Request $request, int $vmid)
    {
        $vm = Vm::where('vmid', $vmid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        $token = ProxmoxToken::firstOrFail();
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        
        // Try guest agent first (more accurate)
        $guestAgentAvailable = false;
        try {
            $agentInfo = $client->getGuestAgentInfo($vm->node, $vmid);
            $guestAgentAvailable = !isset($agentInfo['error']);
        } catch (\Exception $e) {
            $guestAgentAvailable = false;
        }
        
        $metrics = $guestAgentAvailable 
            ? $this->getGuestMetrics($client, $vm->node, $vmid)
            : $this->getHypervisorMetrics($client, $vm->node, $vmid);
        
        return response()->json([
            'source' => $guestAgentAvailable ? 'guest_agent' : 'hypervisor',
            'current' => $metrics,
            'history' => $this->getHistoricalMetrics($client, $vm->node, $vmid, $request->input('timeframe', 'hour')),
        ]);
    }

    private function getGuestMetrics($client, $node, $vmid): array
    {
        // Get OS info
        try {
            $osInfo = $client->getGuestOsInfo($node, $vmid);
        } catch (\Exception $e) {
            $osInfo = ['result' => []];
        }
        
        // Get disk usage from guest agent
        try {
            $fsInfo = $client->getGuestDiskUsage($node, $vmid);
            $diskUsage = collect($fsInfo['result'] ?? [])->map(function ($fs) {
                $total = $fs['total-bytes'] ?? 0;
                $used = $fs['used-bytes'] ?? 0;
                return [
                    'mountpoint' => $fs['mountpoint'],
                    'total' => $total,
                    'used' => $used,
                    'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $diskUsage = [];
        }
        
        // Get network interfaces
        try {
            $netInterfaces = $client->getGuestNetworkInterfaces($node, $vmid);
            $interfaces = collect($netInterfaces['result'] ?? [])->map(fn($iface) => [
                'name' => $iface['name'],
                'hardware_address' => $iface['hardware-address'] ?? 'N/A',
                'ip_addresses' => collect($iface['ip-addresses'] ?? [])->pluck('ip-address')->toArray(),
            ])->toArray();
        } catch (\Exception $e) {
            $interfaces = [];
        }
        
        // Get hypervisor status for CPU/memory
        $status = $client->getVmStatus($node, $vmid);
        
        return [
            'cpu' => round(($status['cpu'] ?? 0) * 100, 2),
            'memory' => [
                'used' => $status['mem'] ?? 0,
                'total' => $status['maxmem'] ?? 0,
                'usage_percent' => ($status['maxmem'] ?? 0) > 0 
                    ? round((($status['mem'] ?? 0) / $status['maxmem']) * 100, 2) 
                    : 0,
            ],
            'disk' => $diskUsage,
            'network' => [
                'interfaces' => $interfaces,
                'traffic_in' => $status['netin'] ?? 0,
                'traffic_out' => $status['netout'] ?? 0,
            ],
            'uptime' => $status['uptime'] ?? 0,
            'os' => [
                'name' => $osInfo['result']['name'] ?? 'Unknown',
                'version' => $osInfo['result']['version'] ?? 'Unknown',
            ],
        ];
    }

    private function getHypervisorMetrics($client, $node, $vmid): array
    {
        $status = $client->getVmStatus($node, $vmid);
        
        return [
            'cpu' => round(($status['cpu'] ?? 0) * 100, 2),
            'memory' => [
                'used' => $status['mem'] ?? 0,
                'total' => $status['maxmem'] ?? 0,
                'usage_percent' => ($status['maxmem'] ?? 0) > 0 
                    ? round((($status['mem'] ?? 0) / $status['maxmem']) * 100, 2) 
                    : 0,
            ],
            'disk' => [
                'read' => $status['diskread'] ?? 0,
                'write' => $status['diskwrite'] ?? 0,
            ],
            'network' => [
                'traffic_in' => $status['netin'] ?? 0,
                'traffic_out' => $status['netout'] ?? 0,
            ],
            'uptime' => $status['uptime'] ?? 0,
        ];
    }

    private function getHistoricalMetrics($client, $node, $vmid, $timeframe): array
    {
        try {
            $rrd = $client->getVmMetrics($node, $vmid, $timeframe);
            
            return array_map(function ($point) {
                return [
                    'time' => $point['time'],
                    'cpu' => round(($point['cpu'] ?? 0) * 100, 2),
                    'memory' => ($point['maxmem'] ?? 0) > 0 
                        ? round((($point['mem'] ?? 0) / $point['maxmem']) * 100, 2) 
                        : 0,
                    'disk_read' => $point['diskread'] ?? 0,
                    'disk_write' => $point['diskwrite'] ?? 0,
                    'net_in' => $point['netin'] ?? 0,
                    'net_out' => $point['netout'] ?? 0,
                ];
            }, $rrd);
        } catch (\Exception $e) {
            return [];
        }
    }
}
