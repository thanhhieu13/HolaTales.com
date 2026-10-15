<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DictionaryImportProgressedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $userUuid;

    public $importedRecords;

    public function __construct($userUuid, $importedRecords)
    {
        $this->importedRecords = $importedRecords;
        $this->userUuid = $userUuid;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('dictionary-import-progress.'.$this->userUuid);
    }
}
