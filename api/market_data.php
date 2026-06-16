<?php
declare(strict_types=1);

$requestedLang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if (!in_array($requestedLang, ['en', 'my', 'jp', 'th'], true)) {
    $requestedLang = 'en';
}
define('FTA_LANG', $requestedLang);

require_once __DIR__ . '/../app/layout.php';

fta_send_security_headers(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-FTA-Auth, X-Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const FTA_RATE_API_BASE = 'https://api.hlainghtetaung.com/rate/api.php';

function fta_market_regions(): array
{
    return [
        1 => t('region_yangon', 'Yangon'),
        2 => t('region_mandalay', 'Mandalay'),
        3 => t('region_naypyidaw', 'Naypyidaw'),
        4 => t('region_bago', 'Bago'),
        5 => t('region_ayeyarwady', 'Ayeyarwady'),
        6 => t('region_mon', 'Mon'),
        7 => t('region_sagaing', 'Sagaing'),
        8 => t('region_magway', 'Magway'),
        9 => t('region_shan', 'Shan'),
    ];
}

function fta_market_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fta_market_query(array $params): string
{
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value === true) {
            $parts[] = rawurlencode((string) $key);
            continue;
        }
        $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
    return implode('&', $parts);
}

function fta_market_fetch(array $params): array
{
    $url = FTA_RATE_API_BASE . '?' . fta_market_query($params);
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FullTimeArena/1.0'],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    }
    if (!is_string($raw) || trim($raw) === '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\nUser-Agent: FullTimeArena/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
    }
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Rate API returned an empty response.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Rate API returned invalid JSON.');
    }
    return $json;
}

function fta_market_float($value): float
{
    return (float) preg_replace('/[^\d\.\-]/', '', (string) $value);
}

function fta_market_change_tone(float $value): string
{
    if ($value > 0) {
        return 'up';
    }
    if ($value < 0) {
        return 'down';
    }
    return 'same';
}

function fta_market_currency_meta(string $code): array
{
    $code = strtoupper(trim($code));
    $items = [
        'USD' => ['country_code' => 'US', 'flag_emoji' => '🇺🇸'],
        'EUR' => ['country_code' => 'EU', 'flag_emoji' => '🇪🇺'],
        'GBP' => ['country_code' => 'GB', 'flag_emoji' => '🇬🇧'],
        'SGD' => ['country_code' => 'SG', 'flag_emoji' => '🇸🇬'],
        'THB' => ['country_code' => 'TH', 'flag_emoji' => '🇹🇭'],
        'CNY' => ['country_code' => 'CN', 'flag_emoji' => '🇨🇳'],
        'MYR' => ['country_code' => 'MY', 'flag_emoji' => '🇲🇾'],
        'JPY' => ['country_code' => 'JP', 'flag_emoji' => '🇯🇵'],
        'KRW' => ['country_code' => 'KR', 'flag_emoji' => '🇰🇷'],
        'AED' => ['country_code' => 'AE', 'flag_emoji' => '🇦🇪'],
        'TWD' => ['country_code' => 'TW', 'flag_emoji' => '🇹🇼'],
        'AUD' => ['country_code' => 'AU', 'flag_emoji' => '🇦🇺'],
        'NZD' => ['country_code' => 'NZ', 'flag_emoji' => '🇳🇿'],
        'CAD' => ['country_code' => 'CA', 'flag_emoji' => '🇨🇦'],
        'HKD' => ['country_code' => 'HK', 'flag_emoji' => '🇭🇰'],
        'INR' => ['country_code' => 'IN', 'flag_emoji' => '🇮🇳'],
        'MOP' => ['country_code' => 'MO', 'flag_emoji' => '🇲🇴'],
        'VND' => ['country_code' => 'VN', 'flag_emoji' => '🇻🇳'],
        'LAK' => ['country_code' => 'LA', 'flag_emoji' => '🇱🇦'],
        'KHR' => ['country_code' => 'KH', 'flag_emoji' => '🇰🇭'],
        'PHP' => ['country_code' => 'PH', 'flag_emoji' => '🇵🇭'],
    ];
    return $items[$code] ?? ['country_code' => '', 'flag_emoji' => ''];
}

function fta_market_currency_name(string $code, string $fallback): string
{
    $lang = fta_current_lang();
    $code = strtoupper(trim($code));
    $names = [
        'my' => [
            'USD' => 'အမေရိကန် ဒေါ်လာ',
            'EUR' => 'ယူရို',
            'GBP' => 'ဗြိတိန် ပေါင်',
            'SGD' => 'စင်ကာပူ ဒေါ်လာ',
            'THB' => 'ထိုင်း ဘတ်',
            'CNY' => 'တရုတ် ယွမ်',
            'MYR' => 'မလေးရှား ရင်းဂစ်',
            'JPY' => 'ဂျပန် ယန်း',
            'KRW' => 'တောင်ကိုရီးယား ဝမ်',
            'AED' => 'ယူအေအီး ဒီဟမ်',
            'TWD' => 'ထိုင်ဝမ် ဒေါ်လာ',
            'AUD' => 'ဩစတြေးလျ ဒေါ်လာ',
            'NZD' => 'နယူးဇီလန် ဒေါ်လာ',
            'CAD' => 'ကနေဒါ ဒေါ်လာ',
            'HKD' => 'ဟောင်ကောင် ဒေါ်လာ',
            'INR' => 'အိန္ဒိယ ရူပီး',
            'MOP' => 'မကာအို ပတကာ',
            'VND' => 'ဗီယက်နမ် ဒေါင်',
            'LAK' => 'လာအို ကစ်',
            'KHR' => 'ကမ္ဘောဒီးယား ရီရယ်',
            'PHP' => 'ဖိလစ်ပိုင် ပီဆို',
        ],
        'jp' => [
            'USD' => '米ドル',
            'EUR' => 'ユーロ',
            'GBP' => '英ポンド',
            'SGD' => 'シンガポールドル',
            'THB' => 'タイバーツ',
            'CNY' => '中国人民元',
            'MYR' => 'マレーシアリンギット',
            'JPY' => '日本円',
            'KRW' => '韓国ウォン',
            'AED' => 'UAEディルハム',
            'TWD' => '台湾ドル',
            'AUD' => '豪ドル',
            'NZD' => 'ニュージーランドドル',
            'CAD' => 'カナダドル',
            'HKD' => '香港ドル',
            'INR' => 'インドルピー',
            'MOP' => 'マカオパタカ',
            'VND' => 'ベトナムドン',
            'LAK' => 'ラオスキープ',
            'KHR' => 'カンボジアリエル',
            'PHP' => 'フィリピンペソ',
        ],
        'th' => [
            'USD' => 'ดอลลาร์สหรัฐ',
            'EUR' => 'ยูโร',
            'GBP' => 'ปอนด์อังกฤษ',
            'SGD' => 'ดอลลาร์สิงคโปร์',
            'THB' => 'บาทไทย',
            'CNY' => 'หยวนจีน',
            'MYR' => 'ริงกิตมาเลเซีย',
            'JPY' => 'เยนญี่ปุ่น',
            'KRW' => 'วอนเกาหลีใต้',
            'AED' => 'เดอร์แฮมสหรัฐอาหรับเอมิเรตส์',
            'TWD' => 'ดอลลาร์ไต้หวัน',
            'AUD' => 'ดอลลาร์ออสเตรเลีย',
            'NZD' => 'ดอลลาร์นิวซีแลนด์',
            'CAD' => 'ดอลลาร์แคนาดา',
            'HKD' => 'ดอลลาร์ฮ่องกง',
            'INR' => 'รูปีอินเดีย',
            'MOP' => 'ปาตากามาเก๊า',
            'VND' => 'ดองเวียดนาม',
            'LAK' => 'กีบลาว',
            'KHR' => 'เรียลกัมพูชา',
            'PHP' => 'เปโซฟิลิปปินส์',
        ],
    ];
    return (string) ($names[$lang][$code] ?? $fallback);
}

function fta_market_rates(string $type, int $regionId): array
{
    if ($type === 'currency') {
        $json = fta_market_fetch(['currencies' => true]);
        $items = [];
        foreach ((array) ($json['currencies'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $currency = is_array($row['currency'] ?? null) ? $row['currency'] : [];
            if (array_key_exists('isVisible', $currency) && !$currency['isVisible']) {
                continue;
            }
            $buyChange = fta_market_float($row['buyRateChange'] ?? 0);
            $sellChange = fta_market_float($row['sellRateChange'] ?? 0);
            $code = (string) ($currency['code'] ?? '');
            $meta = fta_market_currency_meta($code);
            $items[] = [
                'id' => (int) ($row['currencyId'] ?? $row['id'] ?? 0),
                'title' => $code,
                'subtitle' => trim(fta_market_currency_name($code, (string) ($currency['name'] ?? '')) . ' ' . (string) ($currency['symbol'] ?? '')),
                'flag_code' => $meta['country_code'],
                'flag_emoji' => $meta['flag_emoji'],
                'buy_rate' => (string) ($row['buyRate'] ?? ''),
                'sell_rate' => (string) ($row['sellRate'] ?? ''),
                'buy_change' => $buyChange,
                'sell_change' => $sellChange,
                'change_tone' => fta_market_change_tone($buyChange + $sellChange),
                'updated_at' => (string) ($row['updatedAt'] ?? ''),
            ];
        }
        return [
            'status' => 'success',
            'type' => 'currency',
            'last_updated' => (string) ($json['last_updated'] ?? ''),
            'items' => $items,
            'regions' => fta_market_regions(),
            'source' => 'Rate API Documentation © Hlaing Htet Aung',
        ];
    }

    if ($type === 'fuel') {
        $regions = fta_market_regions();
        $regionId = array_key_exists($regionId, $regions) ? $regionId : 1;
        $json = fta_market_fetch(['petrols' => true, 'region_id' => $regionId]);
        $petrols = is_array($json['petrols'] ?? null) ? $json['petrols'] : [];
        $region = is_array($petrols['region'] ?? null) ? $petrols['region'] : ['id' => $regionId, 'name' => $regions[$regionId]];
        $regionName = (string) ($regions[(int) ($region['id'] ?? $regionId)] ?? $regions[$regionId]);
        $items = [];
        foreach ((array) ($petrols['rates'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $petrol = is_array($row['petrol'] ?? null) ? $row['petrol'] : [];
            if (array_key_exists('isVisible', $petrol) && !$petrol['isVisible']) {
                continue;
            }
            $change = fta_market_float($row['rateChange'] ?? 0);
            $items[] = [
                'id' => (int) ($row['petrolId'] ?? $row['id'] ?? 0),
                'title' => (string) ($petrol['name'] ?? ''),
                'subtitle' => $regionName,
                'rate' => (string) ($row['rate'] ?? ''),
                'rate_change' => $change,
                'change_tone' => fta_market_change_tone($change),
                'updated_at' => (string) ($row['updatedAt'] ?? ''),
            ];
        }
        return [
            'status' => 'success',
            'type' => 'fuel',
            'last_updated' => (string) ($json['last_updated'] ?? ''),
            'region' => ['id' => (int) ($region['id'] ?? $regionId), 'name' => $regionName],
            'items' => $items,
            'regions' => $regions,
            'source' => 'Rate API Documentation © Hlaing Htet Aung',
        ];
    }

    $json = fta_market_fetch(['gold' => true]);
    $items = [];
    foreach ((array) ($json['gold'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (array_key_exists('isVisible', $row) && !$row['isVisible']) {
            continue;
        }
        $buyChange = fta_market_float($row['buyRateChange'] ?? 0);
        $sellChange = fta_market_float($row['sellRateChange'] ?? 0);
        $items[] = [
            'id' => (int) ($row['goldId'] ?? $row['id'] ?? 0),
            'title' => (string) ($row['gold'] ?? ''),
            'subtitle' => t('myanmar_gold', 'Myanmar Gold'),
            'buy_rate' => (string) ($row['buyRate'] ?? ''),
            'sell_rate' => (string) ($row['sellRate'] ?? ''),
            'buy_change' => $buyChange,
            'sell_change' => $sellChange,
            'change_tone' => fta_market_change_tone($buyChange + $sellChange),
            'updated_at' => '',
        ];
    }
    return [
        'status' => 'success',
        'type' => 'gold',
        'last_updated' => (string) ($json['last_updated'] ?? ''),
        'items' => $items,
        'regions' => fta_market_regions(),
        'source' => 'Rate API Documentation © Hlaing Htet Aung',
    ];
}

function fta_market_history(string $type, int $id, int $regionId, int $days, string $date): array
{
    $days = max(1, min(30, $days));
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    if ($id <= 0) {
        throw new RuntimeException('Rate item is invalid.');
    }

    if ($type === 'currency') {
        $json = fta_market_fetch(['currencies_history' => true, 'id' => $id, 'days' => $days, 'date' => $date]);
    } elseif ($type === 'fuel') {
        $regionId = array_key_exists($regionId, fta_market_regions()) ? $regionId : 1;
        $json = fta_market_fetch(['petrols_history' => true, 'id' => $id, 'region_id' => $regionId, 'days' => $days, 'date' => $date]);
    } else {
        $json = fta_market_fetch(['gold_history' => true, 'id' => $id, 'days' => $days, 'date' => $date]);
        $type = 'gold';
    }

    $rates = [];
    foreach ((array) ($json['rates'] ?? []) as $group) {
        if (!is_array($group)) {
            continue;
        }
        $rows = is_array($group['rates'] ?? null) ? $group['rates'] : [];
        $row = is_array($rows[0] ?? null) ? $rows[0] : [];
        if (!$row) {
            continue;
        }
        $buyChange = fta_market_float($row['buyRateChange'] ?? 0);
        $sellChange = fta_market_float($row['sellRateChange'] ?? 0);
        $rateChange = fta_market_float($row['rateChange'] ?? 0);
        $rates[] = [
            'date' => (string) ($group['date'] ?? ''),
            'buy_rate' => (string) ($row['buyRate'] ?? ''),
            'sell_rate' => (string) ($row['sellRate'] ?? ''),
            'rate' => (string) ($row['rate'] ?? ''),
            'buy_change' => $buyChange,
            'sell_change' => $sellChange,
            'rate_change' => $rateChange,
            'change_tone' => fta_market_change_tone($rateChange ?: ($buyChange + $sellChange)),
            'created_at' => (string) ($row['createdAt'] ?? ''),
        ];
    }

    return [
        'status' => 'success',
        'type' => $type,
        'id' => $id,
        'region_id' => $regionId,
        'rates' => $rates,
        'next_start_date' => (string) ($json['nextStartDate'] ?? ''),
        'source' => 'Rate API Documentation © Hlaing Htet Aung',
    ];
}

try {
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'rates')));
    $type = strtolower(trim((string) ($_GET['type'] ?? 'gold')));
    if (!in_array($type, ['gold', 'currency', 'fuel'], true)) {
        $type = 'gold';
    }
    $regionId = max(1, min(9, (int) ($_GET['region_id'] ?? 1)));

    if ($mode === 'history') {
        fta_market_response(200, fta_market_history(
            $type,
            (int) ($_GET['id'] ?? 0),
            $regionId,
            (int) ($_GET['days'] ?? 7),
            trim((string) ($_GET['date'] ?? date('Y-m-d')))
        ));
    }

    fta_market_response(200, fta_market_rates($type, $regionId));
} catch (Throwable $error) {
    fta_market_response(502, [
        'status' => 'error',
        'message' => t('market_error_message', 'Could not load market data right now. Please try again.'),
    ]);
}
