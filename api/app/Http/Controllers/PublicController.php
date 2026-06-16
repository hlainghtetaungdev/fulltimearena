<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    public function bootstrap(): JsonResponse
    {
        $assetUrl = rtrim(config('app.asset_url') ?: env('PUBLIC_ASSET_URL', 'https://fulltimearena.com'), '/');
        $absolute = static fn (?string $path): string => $path ? $assetUrl.'/'.ltrim($path, '/') : '';
        $settings = collect(Setting::values())->only([
            'form_open', 'form_start_at', 'form_end_at', 'team_a_name', 'team_a_logo',
            'team_b_name', 'team_b_logo', 'prize_total', 'prize_each', 'site_version',
            'telegram_popup_title', 'telegram_popup_text', 'app_announcement_enabled',
            'app_announcement_title', 'app_announcement_text', 'live_refresh_seconds',
            'live_player_note', 'score_detail_enabled', 'app_guide_videos',
            'app_guide_youtube_url', 'app_update_download_url',
        ])->all();
        foreach (['team_a_logo', 'team_b_logo'] as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = $absolute($settings[$key]);
            }
        }

        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'logo_url' => $absolute('logo.png'),
            ],
            'settings' => $settings,
            'ads' => Ad::query()->where('active', true)->orderBy('sort_order')->get()
                ->map(fn (Ad $ad) => [...$ad->toArray(), 'image_url' => $absolute($ad->image_path)]),
            'categories' => Category::query()->where('active', true)->orderBy('sort_order')->get()
                ->map(fn (Category $category) => [...$category->toArray(), 'icon_url' => $absolute($category->icon_path)]),
            'notifications' => Notification::query()->where('active', true)->whereNull('agent_id')->whereNull('user_id')->latest('id')->limit(30)->get(),
            'links' => [
                'facebook' => env('FACEBOOK_URL', 'https://www.facebook.com/fulltimearena'),
                'telegram' => env('TELEGRAM_URL', 'https://t.me/fulltimearena'),
                'tiktok' => env('TIKTOK_URL', 'https://www.tiktok.com/@fulltimearena'),
            ],
        ]);
    }
}
