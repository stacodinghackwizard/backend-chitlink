<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ThriftPackageApplication;

class ThriftPackageApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $application;

    public function __construct(ThriftPackageApplication $application)
    {
        $this->application = $application;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Application to Your Thrift Package')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A user has applied to join your thrift package: ' . $this->application->thriftPackage->name)
            ->action('View Applications', url('/')) // Replace with actual frontend URL
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable)
    {
        return [
            'application_id' => $this->application->id,
            'thrift_package_id' => $this->application->thrift_package_id,
            'package_name' => $this->application->thriftPackage->name,
            'status' => $this->application->status,
            'user_id' => $this->application->user ? $this->application->user->user_id : null,
        ];
    }
} 