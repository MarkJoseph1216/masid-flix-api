<?php

namespace App\Http\Controllers\Api;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required_without:party_id|exists:users,id',
            'party_id' => 'required_without:user_id|string',
            'limit' => 'sometimes|integer|min:1|max:100',
            'before' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {  
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Message::query()->with(['sender', 'receiver']);

        if ($request->has('party_id')) {
            $query->where('party_id', $request->party_id);
        } else {
            $query->betweenUsers($request->user()->id, $request->user_id);
        }

        if ($request->has('before')) {
            $query->where('id', '<', $request->before);
        }

        $messages = $query->orderBy('id', 'desc')->limit($request->limit ?? 50)->get();

        // Mark private messages as read if necessary
        if ($request->has('user_id')) {
            Message::where('receiver_id', $request->user()->id)
                ->where('sender_id', $request->user_id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json([
            'status' => 'success',
            'messages' => $messages->reverse()->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'nullable|exists:users,id',
            'party_id' => 'nullable|string',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$request->receiver_id && !$request->party_id) {
            return response()->json(['error' => 'Receiver or Party ID is required'], 422);
        }

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'party_id' => $request->party_id,
            'message' => $request->message,
        ]);

        $message->load(['sender', 'receiver']);

        return response()->json([
            'status' => 'success',
            'message' => $message,
        ], 201);
    }

    public function unreadCount(Request $request)
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $count,
        ]);
    }

    public function conversations(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($message) use ($userId) {
                return $message->sender_id == $userId
                    ? $message->receiver_id
                    : $message->sender_id;
            })
            ->map(function ($messages) {
                return $messages->sortByDesc('created_at')->first();
            })
            ->filter()
            ->values();

        $result = $conversations->map(function ($message) use ($userId) {
            $otherUser = $message->sender_id == $userId
                ? $message->receiver
                : $message->sender;

            $unreadCount = Message::where('receiver_id', $userId)
                ->where('sender_id', $otherUser->id)
                ->where('is_read', false)
                ->count();

            return [
                'user' => $otherUser,
                'last_message' => $message,
                'unread_count' => $unreadCount,
            ];
        });

        $result = $result->sortByDesc(function ($conversation) {
            return $conversation['last_message']->created_at;
        })->values();

        return response()->json([
            'status' => 'success',
            'conversations' => $result,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Message::where('receiver_id', $request->user()->id)
            ->where('sender_id', $request->user_id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Messages marked as read',
        ]);
    }
}