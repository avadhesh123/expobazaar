<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CatalogueNotification extends Notification { use Queueable; public function __construct(public $entity, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['type' => $this->type, 'message' => "Catalogue: {$this->type}"]; } }
