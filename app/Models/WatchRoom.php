<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WatchRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'media_id',
        'imdb_id',
        'media_type',
        'season',
        'episode',
        'room_code',
        'title',
        'poster_path',
        'backdrop_path',
        'is_active',
        'current_time',
        'is_playing',
        'max_participants',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_playing' => 'boolean',
        'current_time' => 'integer',
        'season' => 'integer',
        'episode' => 'integer',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants()
    {
        return $this->hasMany(WatchRoomParticipant::class, 'room_id');
    }

    public function getParticipantCountAttribute()
    {
        return $this->participants()->count();
    }
}