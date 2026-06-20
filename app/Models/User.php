<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_admin',
        'is_online',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function setOnline()
    {
        $this->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
    }

    public function setOffline()
    {
        $this->update([
            'is_online' => false,
            'last_seen_at' => now(),
        ]);
    }

    public function updateLastSeen()
    {
        $this->update([
            'last_seen_at' => now(),
        ]);
    }

    public function getLastSeenAttribute($value)
    {
        if ($this->is_online) {
            return 'Online';
        }

        if ($value) {
            $diff = now()->diffInMinutes($value);
            if ($diff < 1) {
                return 'Just now';
            } elseif ($diff < 60) {
                return $diff . ' minutes ago';
            } elseif ($diff < 1440) {
                return floor($diff / 60) . ' hours ago';
            } else {
                return floor($diff / 1440) . ' days ago';
            }
        }

        return 'Offline';
    }
}