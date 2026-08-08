<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connections\IndexConnectionRequestsRequest;
use App\Http\Resources\ConnectionRequestResource;
use App\Http\Resources\ConnectionResource;
use App\Models\ConnectionRequest;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConnectionRequestController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function index(IndexConnectionRequestsRequest $request): JsonResponse
    {
        $user = $request->user();
        $direction = $request->input('direction', 'incoming');

        $query = ConnectionRequest::query()
            ->with(['sender.profile', 'sender.contactPreferences', 'receiver.profile', 'receiver.contactPreferences', 'event'])
            ->when(
                $direction === 'outgoing',
                fn ($q) => $q->outgoingFor($user->id),
                fn ($q) => $q->incomingFor($user->id),
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest();

        $results = $query->paginate($this->perPage());

        return $this->paginated(ConnectionRequestResource::collection($results));
    }

    public function accept(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $this->ensureReceiver($request, $connectionRequest);

        $connection = $this->connections->accept(
            $connectionRequest->load(['sender', 'receiver'])
        );

        return $this->ok(
            new ConnectionResource($connection->load(['userOne.profile', 'userTwo.profile', 'event'])),
            'Connection accepted.'
        );
    }

    public function decline(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $this->ensureReceiver($request, $connectionRequest);

        $declined = $this->connections->decline($connectionRequest);

        return $this->ok(new ConnectionRequestResource($declined), 'Connection request declined.');
    }

    /** Only the receiver may accept or decline; the sender can only cancel. */
    private function ensureReceiver(Request $request, ConnectionRequest $connectionRequest): void
    {
        if ($connectionRequest->receiver_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('You cannot act on this connection request.');
        }
    }
}
