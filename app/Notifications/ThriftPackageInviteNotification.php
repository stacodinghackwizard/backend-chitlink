<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ThriftPackageInvite;

class ThriftPackageInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invite;

    public function __construct(ThriftPackageInvite $invite)
    {
        $this->invite = $invite;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('You have been invited to join a Thrift Package')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have been invited to join the thrift package: ' . $this->invite->thriftPackage->name)
            ->action('View Invite', url('/')) // Replace with actual frontend URL
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable)
    {
        return [
            'invite_id' => $this->invite->id,
            'thrift_package_id' => $this->invite->thrift_package_id,
            'package_name' => $this->invite->thriftPackage->name,
            'status' => $this->invite->status,
            'invited_user_id' => $this->invite->invitedUser ? $this->invite->invitedUser->user_id : null,
            'invited_by_user_id' => $this->invite->invitedBy ? $this->invite->invitedBy->user_id : null,
        ];
    }
} 