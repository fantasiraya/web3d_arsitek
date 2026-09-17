<?php

namespace App\Domains\Project\Notifications;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public User $inviter
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = url('/login'); // We could prefill email or direct to project page

        return (new MailMessage)
            ->subject("You've been invited to view a 3D project: {$this->project->name}")
            ->greeting('Hello!')
            ->line("{$this->inviter->name} has invited you to view their 3D architectural project '{$this->project->name}'.")
            ->line('To view the project and provide feedback, please log in or sign up using this email address.')
            ->action('Access Project', $loginUrl)
            ->line('Thank you for using our application!');
    }
}
