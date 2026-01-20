<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VmInstallationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $vmid,
        public int $userId,
        public string $step,
        public int $progress,
        public string $status = 'running' // running, success, error
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("user.{$this->userId}.vm.{$this->vmid}");
    }

    public function broadcastAs(): string
    {
        return 'installation.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'step' => $this->step,
            'progress' => $this->progress,
            'status' => $this->status,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
