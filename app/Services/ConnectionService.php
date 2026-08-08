<?php

namespace App\Services;

use App\Enums\ConnectionRequestStatus;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\NetworkingSession;
use App\Models\User;
use App\Notifications\ConnectionRequestAccepted;
use App\Notifications\ConnectionRequestReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectionService
{
    /**
     * @throws ValidationException when the pair may not connect
     */
    public function sendRequest(
        User $sender,
        User $receiver,
        ?string $message = null,
        ?int $eventId = null,
    ): ConnectionRequest {
        if ($sender->id === $receiver->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot send a connection request to yourself.',
            ]);
        }

        if ($sender->hasBlockRelationshipWith($receiver)) {
            throw ValidationException::withMessages([
                'user' => 'This connection is not available.',
            ]);
        }

        if ($sender->isConnectedWith($receiver)) {
            throw ValidationException::withMessages([
                'user' => 'You are already connected with this user.',
            ]);
        }

        $existing = ConnectionRequest::query()
            ->pendingBetween($sender->id, $receiver->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'user' => $existing->sender_id === $sender->id
                    ? 'You already have a pending request with this user.'
                    : 'This user has already sent you a request -- accept it instead.',
            ]);
        }

        $request = ConnectionRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'message' => $message,
            'event_id' => $eventId,
            'status' => ConnectionRequestStatus::Pending,
        ]);

        $receiver->notify(new ConnectionRequestReceived($request->load('sender')));

        return $request;
    }

    /**
     * Accepts a pending request and materialises the canonical connection row.
     *
     * @throws ValidationException when the request is no longer pending
     */
    public function accept(ConnectionRequest $request): Connection
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'This request has already been handled.',
            ]);
        }

        $connection = DB::transaction(function () use ($request) {
            [$one, $two] = Connection::canonicalPair($request->sender_id, $request->receiver_id);

            $connection = Connection::firstOrCreate(
                ['user_one_id' => $one, 'user_two_id' => $two],
                [
                    'connection_request_id' => $request->id,
                    'event_id' => $request->event_id,
                    'connected_at' => now(),
                ]
            );

            $request->update(['status' => ConnectionRequestStatus::Accepted]);

            return $connection;
        });

        $this->creditOpenNetworkingSessions($request->sender_id, $request->receiver_id, $request->event_id);

        $request->sender?->notify(
            new ConnectionRequestAccepted($connection, $request->receiver)
        );

        return $connection;
    }

    /** @throws ValidationException when the request is no longer pending */
    public function decline(ConnectionRequest $request): ConnectionRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'This request has already been handled.',
            ]);
        }

        $request->update(['status' => ConnectionRequestStatus::Declined]);

        return $request->refresh();
    }

    public function disconnect(Connection $connection): void
    {
        DB::transaction(function () use ($connection) {
            $connection->notes()->delete();
            $connection->favorites()->delete();
            $connection->delete();
        });
    }

    /**
     * Bumps `connections_count` on any open networking session belonging to the
     * two users, so an event recap can report "8 connections made".
     */
    private function creditOpenNetworkingSessions(int $senderId, int $receiverId, ?int $eventId): void
    {
        NetworkingSession::query()
            ->open()
            ->whereIn('user_id', [$senderId, $receiverId])
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->increment('connections_count');
    }
}
