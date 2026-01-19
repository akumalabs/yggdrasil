<?php

namespace App\Http\Controllers;

use App\Models\ProxmoxToken;
use App\Services\ProxmoxClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorageController extends Controller
{
    public function index(Request $request)
    {
        $token = ProxmoxToken::firstOrFail();
        
        // Default to first node if not specified, but typically we want to browse per node
        // Let's rely on dashboard usually, but for specific page:
        
        $client = new ProxmoxClient($token->host, "{$token->token_id}={$token->token_secret}");
        $nodes = $client->nodes()->pluck('node');
        
        $selectedNode = $request->input('node', $nodes[0]);
        
        $storageList = collect($client->storage($selectedNode))
            ->filter(fn($s) => $s['active'] == 1);
            
        $contents = [];
        foreach ($storageList as $store) {
            $content = $client->storageContent($selectedNode, $store['storage']);
            // Filter for ISOs or container templates if needed, but showing all is fine for now
            // content items have 'content' type e.g. 'iso', 'vztmpl'
            foreach ($content as $item) {
                $item['storage'] = $store['storage'];
                $contents[] = $item;
            }
        }

        return Inertia::render('Storage/Index', [
            'nodes' => $nodes,
            'selectedNode' => $selectedNode,
            'storage' => $storageList->values(),
            'contents' => $contents,
        ]);
    }
}
