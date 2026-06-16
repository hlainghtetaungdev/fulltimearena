<?php

namespace App\Http\Controllers;

use App\Events\RealtimeUpdated;
use App\Models\AgentCategoryPermission;
use App\Models\AgentContactProfile;
use App\Models\AgentIbetRule;
use App\Models\AgentPaymentAccount;
use App\Models\AgentProviderConfig;
use App\Models\Category;
use App\Models\Notification;
use App\Models\UnitRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;
        $requests = UnitRequest::query()->where('agent_id', $agentId);

        return response()->json(['data' => [
            'total_users' => User::query()->where('agent_id', $agentId)->count(),
            'new_users_today' => User::query()->where('agent_id', $agentId)->whereDate('created_at', today())->count(),
            'total_requests' => (clone $requests)->count(),
            'pending_requests' => (clone $requests)->where('status', 'pending')->count(),
            'deposit_total' => (clone $requests)->where('request_type', 'deposit')->where('status', 'approved')->sum('amount'),
            'withdraw_total' => (clone $requests)->where('request_type', 'withdraw')->where('status', 'approved')->sum('amount'),
        ]]);
    }

    public function users(Request $request): JsonResponse
    {
        $items = User::query()->where('agent_id', $request->user()->id)->latest('id')->paginate(100);
        return response()->json($items);
    }

    public function requests(Request $request): JsonResponse
    {
        $items = UnitRequest::query()->with('user:id,full_name,phone_e164')
            ->where('agent_id', $request->user()->id)->latest('id')->paginate(100);
        return response()->json($items);
    }

    public function updateRequest(Request $request, UnitRequest $unitRequest): JsonResponse
    {
        abort_unless((int) $unitRequest->agent_id === (int) $request->user()->id, 404);
        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected'], 'admin_note' => ['nullable', 'string']]);
        $unitRequest->update([...$data, 'reviewed_at' => now(), 'reviewed_by_agent_id' => $request->user()->id]);
        RealtimeUpdated::dispatch('unit_requests', $unitRequest->id);
        return response()->json(['message' => 'Request updated.', 'data' => $unitRequest]);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string']]);
        $notification = Notification::query()->create([...$data, 'agent_id' => $request->user()->id, 'active' => true]);
        RealtimeUpdated::dispatch('notifications', $notification->id);
        return response()->json(['message' => 'Notification sent.', 'data' => $notification], 201);
    }

    public function section(Request $request, string $resource): JsonResponse
    {
        $agentId = $request->user()->id;
        $data = match ($resource) {
            'payments' => AgentPaymentAccount::query()->where('agent_id', $agentId)->latest('id')->get(),
            'providers' => AgentProviderConfig::query()->where('agent_id', $agentId)->latest('id')->get(),
            'categories' => [
                'items' => Category::query()->orderBy('sort_order')->get(),
                'permissions' => AgentCategoryPermission::query()->where('agent_id', $agentId)->get(),
            ],
            'ibet_rules' => AgentIbetRule::query()->where('agent_id', $agentId)->first(),
            'contact' => AgentContactProfile::query()->where('agent_id', $agentId)->first(),
            'notifications' => Notification::query()->where('agent_id', $agentId)->latest('id')->get(),
            default => abort(404),
        };

        return response()->json(['data' => $data]);
    }
}
