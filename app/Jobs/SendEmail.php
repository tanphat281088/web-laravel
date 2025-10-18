<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
  use Queueable;

  protected $data;
  protected $users;

  /**
   * Create a new job instance.
   */
  public function __construct($data, $users)
  {
    $this->data = $data;
    $this->users = $users;
  }

  /**
   * Execute the job.
   */
  public function handle(): void
  {
    foreach ($this->users as $user) {
      Mail::to($user)->send(new $this->data($this->data['data']));
    }
  }
}