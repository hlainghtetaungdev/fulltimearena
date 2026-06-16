<?php

namespace App\Http\Controllers;

use App\Events\RealtimeUpdated;
use App\Models\Ad;
use App\Models\Category;
use App\Models\LiveMatch;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\StaffAccount;
use App\Models\Submission;
use App\Models\UnitRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => [
            'agents' => StaffAccount::query()->where('role', 'agent')->count(),
            'users' => User::query()->count(),
            'pending_requests' => UnitRequest::query()->where('status', 'pending')->count(),
            'predictions' => Submission::query()->count(),
            'ads' => Ad::query()->count(),
            'categories' => Category::query()->count(),
        ]]);
    }

    public function agents(): JsonResponse
    {
        return response()->json(['data' => StaffAccount::query()->where('role', 'agent')->latest('id')->get()]);
    }

    public function saveAgent(Request $request, ?StaffAccount $staff = null): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'display_name' => ['nullable', 'string', 'max:140'],
            'promo_code' => ['required', 'string', 'max:80'],
            'password' => [$staff?->exists ? 'nullable' : 'required', 'string', 'min:8'],
            'active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);
        if (!$staff?->exists) {
            $staff = new StaffAccount(['role' => 'agent']);
        }
        $staff->fill(collect($data)->except('password')->all());
        if (!empty($data['password'])) {
            $staff->password_hash = Hash::make($data['password']);
        }
        $staff->role = 'agent';
        $staff->active = $data['active'] ?? true;
        $staff->save();
        RealtimeUpdated::dispatch('agents', $staff->id);
        return response()->json(['message' => 'Agent saved.', 'data' => $staff], $staff->wasRecentlyCreated ? 201 : 200);
    }

    public function settings(Request $request): JsonResponse
    {
        if ($request->isMethod('put')) {
            foreach ($request->all() as $key => $value) {
                Setting::query()->updateOrCreate(['setting_key' => $key], ['setting_value' => is_scalar($value) ? (string) $value : json_encode($value)]);
            }
            RealtimeUpdated::dispatch('settings');
        }
        return response()->json(['data' => Setting::values()]);
    }

    public function collection(string $resource): JsonResponse
    {
        $model = $this->resourceModel($resource);
        return response()->json(['data' => $model::query()->latest('id')->limit(300)->get()]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $model = $this->resourceModel($resource);
        $item = $model::query()->create($request->except('id'));
        RealtimeUpdated::dispatch($resource, $item->id);
        return response()->json(['message' => ucfirst($resource).' created.', 'data' => $item], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $model = $this->resourceModel($resource);
        $item = $model::query()->findOrFail($id);
        $item->update($request->except('id'));
        RealtimeUpdated::dispatch($resource, $item->id);
        return response()->json(['message' => ucfirst($resource).' updated.', 'data' => $item]);
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $model = $this->resourceModel($resource);
        $model::query()->findOrFail($id)->delete();
        RealtimeUpdated::dispatch($resource, $id);
        return response()->json(['message' => ucfirst($resource).' deleted.']);
    }

    private function resourceModel(string $resource): string
    {
        return match ($resource) {
            'ads' => Ad::class,
            'categories' => Category::class,
            'notifications' => Notification::class,
            'submissions' => Submission::class,
            'live_matches' => LiveMatch::class,
            default => abort(404),
        };
    }
}
