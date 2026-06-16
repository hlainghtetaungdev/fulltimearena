<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\StaffAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_bootstrap_is_available_without_a_token(): void
    {
        Ad::query()->create(['image_path' => 'uploads/ads/example.jpg', 'sort_order' => 1, 'active' => true]);
        Category::query()->create(['name' => 'Live', 'link_url' => 'live.php', 'sort_order' => 1, 'active' => true]);

        $this->getJson('/api/public/bootstrap')
            ->assertOk()
            ->assertJsonPath('app.name', config('app.name'))
            ->assertJsonCount(1, 'ads')
            ->assertJsonCount(1, 'categories');
    }

    public function test_agent_token_cannot_access_super_routes(): void
    {
        $agent = StaffAccount::query()->create([
            'role' => 'agent',
            'username' => 'agent-test',
            'display_name' => 'Agent Test',
            'password_hash' => bcrypt('password'),
            'promo_code' => 'AGENTTEST',
            'active' => true,
        ]);
        Sanctum::actingAs($agent, ['agent']);

        $this->getJson('/api/super/dashboard')->assertForbidden();
    }

    public function test_user_token_can_access_user_routes(): void
    {
        $user = User::query()->create([
            'full_name' => 'Test User',
            'phone_country' => 'my',
            'phone_number' => '091234567',
            'phone_e164' => '+9591234567',
            'password_hash' => bcrypt('password'),
            'active' => true,
        ]);
        Sanctum::actingAs($user, ['user']);

        $this->getJson('/api/user/notifications')->assertOk();
    }
}
