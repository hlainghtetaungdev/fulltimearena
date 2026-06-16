<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Submission;
use App\Models\UnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = Notification::query()->where('active', true)->where(function ($query) use ($user) {
            $query->whereNull('agent_id')->whereNull('user_id')
                ->orWhere('agent_id', $user->agent_id)
                ->orWhere('user_id', $user->id);
        })->latest('id')->limit(100)->get();

        return response()->json(['data' => $items]);
    }

    public function predictions(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->submissions()->latest('id')->limit(100)->get()]);
    }

    public function submitPrediction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ht_result' => ['required', 'string', 'max:50'],
            'ft_result' => ['required', 'string', 'max:50'],
            'first_scorer' => ['required', 'string', 'max:120'],
            'wallet_type' => ['required', 'string', 'max:30'],
            'wallet_name' => ['required', 'string', 'max:120'],
            'wallet_number' => ['required', 'string', 'max:80'],
        ]);

        $submission = Submission::query()->create([
            ...$data,
            'public_id' => strtoupper(Str::random(16)),
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result_status' => 'pending',
            'is_winner' => false,
        ]);

        return response()->json(['message' => 'Prediction submitted.', 'data' => $submission], 201);
    }

    public function unitRequests(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->unitRequests()->latest('id')->limit(100)->get()]);
    }

    public function submitUnitRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_type' => ['required', 'in:deposit,withdraw'],
            'amount' => ['required', 'numeric', 'min:1'],
            'game_account_id' => ['nullable', 'integer'],
            'payment_account_id' => ['nullable', 'integer'],
            'proof' => ['nullable', 'image', 'max:10240'],
        ]);
        $user = $request->user();
        $proof = $request->file('proof')?->store('payment-proofs', 'public');
        $unit = UnitRequest::query()->create([
            ...$data,
            'public_id' => strtoupper(Str::random(16)),
            'user_id' => $user->id,
            'agent_id' => $user->agent_id,
            'proof_path' => $proof,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Unit request submitted.', 'data' => $unit], 201);
    }
}
