<?php
declare(strict_types=1);

require_once __DIR__ . '/provider_service.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fta_provider_proxy_response(int $status, array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    if (!is_string($json)) {
        $status = 500;
        $json = '{"status":"error","message":"Provider proxy JSON encoding failed."}';
    }

    http_response_code($status);
    echo $json;
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        fta_provider_proxy_response(405, ['status' => 'error', 'message' => 'POST required.']);
    }

    $staff = fta_current_staff(['super', 'agent']);
    if (!$staff) {
        fta_provider_proxy_response(401, ['status' => 'error', 'message' => 'Staff login required.']);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : $_POST;
    $action = trim((string) ($input['action'] ?? 'health-check'));
    $providerKey = strtolower(trim((string) ($input['provider_key'] ?? '')));
    $agentId = (string) ($staff['role'] ?? '') === 'super'
        ? (int) ($input['agent_id'] ?? $staff['id'])
        : (int) $staff['id'];

    if ($action !== 'health-check') {
        fta_provider_proxy_response(400, ['status' => 'error', 'message' => 'Unsupported provider proxy action.']);
    }

    $result = fta_run_provider_health_check($agentId, $providerKey);
    fta_provider_proxy_response($result['status'] === 'ok' ? 200 : 422, [
        'status' => $result['status'] === 'ok' ? 'success' : 'error',
        'message' => $result['message'],
    ]);
} catch (Throwable $error) {
    fta_provider_proxy_response(500, ['status' => 'error', 'message' => 'Provider proxy failed.']);
}
