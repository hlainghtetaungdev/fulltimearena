<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{
    private const SOURCES = [
        'live' => 'https://api.hlainghtetaung.com/football/live/',
        'result' => 'https://api.hlainghtetaung.com/football/result/',
        'news' => 'https://api.hlainghtetaung.com/football/news/',
        'market' => 'https://api.hlainghtetaung.com/rate/api.php',
        'odds' => 'https://api.hlainghtetaung.com/football/mmodds/',
    ];

    public function show(Request $request, string $source): JsonResponse
    {
        abort_unless(array_key_exists($source, self::SOURCES), 404);
        $response = Http::acceptJson()->timeout(15)->retry(2, 250)
            ->get(self::SOURCES[$source], $request->query());

        return $this->forward($response);
    }

    private function forward(Response $response): JsonResponse
    {
        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upstream service is unavailable.',
                'upstream_status' => $response->status(),
            ], 502);
        }

        return response()->json($response->json() ?? ['data' => $response->body()]);
    }
}
