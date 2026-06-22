<?php

namespace App\Http\Controllers\Api;

use App\Models\WatchRoom;
use App\Models\WatchRoomParticipant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WatchRoomController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_id' => 'required',
            'imdb_id' => 'nullable|string',
            'media_type' => 'required|in:movie,tv',
            'season' => 'nullable|integer|min:1',
            'episode' => 'nullable|integer|min:1',
            'title' => 'required|string',
            'poster_path' => 'nullable|string',
            'backdrop_path' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = WatchRoom::create([
            'host_id' => $request->user()->id,
            'media_id' => $request->media_id,
            'imdb_id' => $request->imdb_id,
            'media_type' => $request->media_type,
            'season' => $request->season ?? 1,
            'episode' => $request->episode ?? 1,
            'room_code' => strtoupper(Str::random(6)),
            'title' => $request->title,
            'poster_path' => $request->poster_path,
            'backdrop_path' => $request->backdrop_path,
            'is_active' => true,
            'current_time' => 0,
            'is_playing' => false,
        ]);

        WatchRoomParticipant::create([
            'room_id' => $room->id,
            'user_id' => $request->user()->id,
            'joined_at' => now(),
        ]);

        $roomWithDetails = WatchRoom::with(['host', 'participants.user'])
            ->find($room->id);

        return response()->json([
            'status' => 'success',
            'room' => $roomWithDetails,
        ]);
    }

    public function join(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = WatchRoom::where('room_code', strtoupper($request->room_code))
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return response()->json([
                'status' => 'error',
                'message' => 'Room not found or inactive'
            ], 404);
        }

        $existing = WatchRoomParticipant::where('room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$existing) {
            WatchRoomParticipant::create([
                'room_id' => $room->id,
                'user_id' => $request->user()->id,
                'joined_at' => now(),
            ]);
        }

        $roomWithDetails = WatchRoom::with(['host', 'participants.user'])
            ->find($room->id);

        return response()->json([
            'status' => 'success',
            'room' => $roomWithDetails,
        ]);
    }

    public function leave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:watch_rooms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = WatchRoom::find($request->room_id);
        
        if (!$room) {
            return response()->json([
                'status' => 'error',
                'message' => 'Room not found'
            ], 404);
        }

        $isHost = $room->host_id === $request->user()->id;
        $participant = WatchRoomParticipant::where('room_id', $request->room_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($participant) {
            $participant->delete();
        }

        if ($isHost) {
            WatchRoomParticipant::where('room_id', $request->room_id)->delete();
            $room->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Room ended and deleted successfully',
                'is_host' => true,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Left the room successfully',
            'is_host' => false,
        ]);
    }

    public function syncPlayback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:watch_rooms,id',
            'current_time' => 'required|integer|min:0',
            'is_playing' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = WatchRoom::find($request->room_id);

        if ($room->host_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the host can sync playback'
            ], 403);
        }

        $room->update([
            'current_time' => $request->current_time,
            'is_playing' => $request->is_playing,
        ]);

        return response()->json([
            'status' => 'success',
            'room' => $room,
        ]);
    }

    public function getRoom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:watch_rooms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = WatchRoom::with(['host', 'participants.user'])
            ->find($request->room_id);

        return response()->json([
            'status' => 'success',
            'room' => $room,
        ]);
    }

    public function getActiveRooms(Request $request)
    {
        $rooms = WatchRoom::with(['host', 'participants.user'])
            ->where('is_active', true)
            ->where('created_at', '>=', now()->subHours(2))
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'rooms' => $rooms,
        ]);
    }
}