<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, $message = '')
    {
        $this->task = $task->load(['creator', 'assignments.assignable']);
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('company.' . $this->task->company_id)];

        // Also broadcast to specific users assigned to this task
        foreach ($this->task->assignments as $assignment) {
            if ($assignment->assignable_type === \App\Models\User::class) {
                $channels[] = new PrivateChannel('user.' . $assignment->assignable_id);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }
}
