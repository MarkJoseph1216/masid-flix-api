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
            'user_id' => 'required|exists:users,id',
            'limit' => 'sometimes|integer|min:1|max:100',
            'before' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user_id;
        $limit = $request->limit ?? 50;
        $before = $request->before;

        $query = Message::betweenUsers($request->user()->id, $userId)
            ->with(['sender', 'receiver']);

        if ($before) {
            $query->where('id', '<', $before);
        }

        $messages = $query->orderBy('id', 'desc')->limit($limit)->get();
        Message::where('receiver_id', $request->user()->id)
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'messages' => $messages->reverse()->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id|different:user_id',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
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
                return $messages->first();
            })
            ->values()
            ->take(20);

        $result = $conversations->map(function ($message) {
            return [
                'user' => $message->sender_id == request()->user()->id
                    ? $message->receiver
                    : $message->sender,
                'last_message' => $message,
                'unread_count' => Message::where('receiver_id', request()->user()->id)
                    ->where('sender_id', $message->sender_id == request()->user()->id
                        ? $message->receiver_id
                        : $message->sender_id)
                    ->where('is_read', false)
                    ->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'conversations' => $result,
        ]);
    }
}