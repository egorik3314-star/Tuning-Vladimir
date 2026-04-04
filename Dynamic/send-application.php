<?php
/**
 * AutoBodyKits — Обработчик заявок с формы
 * Отправляет данные на egorik3314@gmail.com
 * 
 * Путь: /Dynamic/send-application.php
 */

// === Настройки ===
$CONFIG = [
    'admin_email' => 'egorik3314@gmail.com',
    'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'autobodykits.ru'),
    'from_name' => 'AutoBodyKits — Заявка с сайта',
    'allowed_origins' => [
        'localhost',
        '127.0.0.1',
        '.autobodykits.ru', // все поддомены
        // Добавьте ваш домен: 'yoursite.ru'
    ],
    'rate_limit_seconds' => 30, // защита от спама: 1 заявка в 30 сек с одного IP
];

// === Заголовки для JSON API ===
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// === CORS (только разрешённые домены) ===
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = parse_url($origin, PHP_URL_HOST) ?? $origin;

$allowed = false;
foreach ($CONFIG['allowed_origins'] as $pattern) {
    if ($pattern[0] === '.') {
        // Поддомены: .example.com → example.com и *.example.com
        if (str_ends_with($host, ltrim($pattern, '.'))) {
            $allowed = true;
            break;
        }
    } elseif ($host === $pattern) {
        $allowed = true;
        break;
    }
}

if ($allowed) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Обработка preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['success' => true, 'message' => 'Preflight OK']));
}

// === Только POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// === Чтение JSON ===
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid JSON']));
}

// === Санитизация и валидация ===
function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

$fields = [
    'name' => ['required' => true, 'min' => 2, 'max' => 50],
    'phone' => ['required' => true, 'pattern' => '/^[\d\+\-\(\)\s]{10,20}$/'],
    'carBrand' => ['required' => false, 'max' => 30],
    'carModel' => ['required' => false, 'max' => 50],
    'serviceType' => ['required' => false, 'max' => 50],
    'comment' => ['required' => false, 'max' => 1000],
    'agree' => ['required' => true, 'type' => 'boolean'],
];

$errors = [];
$clean = [];

foreach ($fields as $key => $rules) {
    $value = $data[$key] ?? null;
    
    // Required
    if ($rules['required'] && (empty($value) || ($rules['type'] ?? '') === 'boolean' && $value !== true)) {
        $errors[] = "Поле '$key' обязательно";
        continue;
    }
    
    if (empty($value) && !$rules['required']) {
        $clean[$key] = '—';
        continue;
    }
    
    // Type check
    if (($rules['type'] ?? '') === 'boolean') {
        $clean[$key] = $value === true ? 'Да' : 'Нет';
        continue;
    }
    
    // String sanitization
    $value = sanitize($value);
    
    // Length
    if (isset($rules['min']) && mb_strlen($value) < $rules['min']) {
        $errors[] = "Поле '$key' слишком короткое";
    }
    if (isset($rules['max']) && mb_strlen($value) > $rules['max']) {
        $errors[] = "Поле '$key' слишком длинное";
    }
    
    // Pattern
    if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
        $errors[] = "Поле '$key' имеет неверный формат";
    }
    
    $clean[$key] = $value;
}

if (!empty($errors)) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'errors' => $errors]));
}

// === Rate limiting (простая защита от спама) ===
$ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_file = sys_get_temp_dir() . "/abk_rate_{$ip}";
$now = time();

if (file_exists($rate_file)) {
    $last = (int)file_get_contents($rate_file);
    if ($now - $last < $CONFIG['rate_limit_seconds']) {
        http_response_code(429);
        exit(json_encode(['success' => false, 'error' => 'Пожалуйста, подождите перед следующей заявкой']));
    }
}
file_put_contents($rate_file, $now);

// === Формирование письма ===
$subject = "🚗 Новая заявка: {$clean['name']} — {$clean['serviceType']}";

// HTML-версия письма
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 25px; border-radius: 0 0 10px 10px; }
        .field { margin: 12px 0; padding: 10px; background: white; border-left: 3px solid #e74c3c; border-radius: 0 5px 5px 0; }
        .label { font-weight: 600; color: #555; display: block; margin-bottom: 4px; }
        .value { color: #222; font-size: 1.05rem; }
        .footer { text-align: center; color: #888; font-size: 0.9rem; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
        .badge { display: inline-block; background: #e74c3c; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0">🔧 AutoBodyKits — Новая заявка</h2>
        <p style="margin:5px 0 0; opacity:0.9">С сайта: {$_SERVER['HTTP_HOST'] ?? 'unknown'}</p>
    </div>
    <div class="content">
        <div class="field"><span class="label">👤 Имя клиента</span><span class="value">{$clean['name']}</span></div>
        <div class="field"><span class="label">📞 Телефон</span><span class="value"><a href="tel:{$clean['phone']}">{$clean['phone']}</a></span></div>
        <div class="field"><span class="label">🚗 Марка авто</span><span class="value">{$clean['carBrand']}</span></div>
        <div class="field"><span class="label">🔖 Модель / год</span><span class="value">{$clean['carModel']}</span></div>
        <div class="field"><span class="label">🛠️ Услуга</span><span class="badge">{$clean['serviceType']}</span></div>
        <div class="field"><span class="label">💬 Комментарий</span><span class="value">{$clean['comment']}</span></div>
        <div class="field"><span class="label">✅ Согласие на обработку</span><span class="value">{$clean['agree']}</span></div>
        
        <div class="footer">
            <p><strong>Метаданные:</strong></p>
            <p>IP: {$ip} • Страница: {$data['page'] ?? '—'} • Время: {$data['timestamp'] ?? date('Y-m-d H:i:s')}</p>
            <p style="margin-top:15px;font-size:0.85rem;color:#aaa">Это письмо отправлено автоматически. Не отвечайте на него.</p>
        </div>
    </div>
</body>
</html>
HTML;

// Текст-версия (фолбэк)
$text = "НОВАЯ ЗАЯВКА — AutoBodyKits\n" . str_repeat('=', 40) . "\n\n" .
    "👤 Имя: {$clean['name']}\n" .
    "📞 Телефон: {$clean['phone']}\n" .
    "🚗 Марка: {$clean['carBrand']}\n" .
    "🔖 Модель: {$clean['carModel']}\n" .
    "🛠️ Услуга: {$clean['serviceType']}\n" .
    "💬 Комментарий: {$clean['comment']}\n" .
    "✅ Согласие: {$clean['agree']}\n\n" .
    "IP: {$ip}\nСтраница: " . ($data['page'] ?? '—') . "\nВремя: " . ($data['timestamp'] ?? date('Y-m-d H:i:s'));

// === Отправка письма ===
$headers = [
    "From: {$CONFIG['from_name']} <{$CONFIG['from_email']}>",
    "Reply-To: {$CONFIG['from_email']}",
    "MIME-Version: 1.0",
    "Content-Type: multipart/alternative; boundary=\"=_auto_body_kits_" . time() . "\"",
    "X-Mailer: PHP/" . phpversion(),
    "X-Priority: 3"
];

$boundary = "=_auto_body_kits_" . time();
$body = "--$boundary\r\n" .
    "Content-Type: text/plain; charset=utf-8\r\n" .
    "Content-Transfer-Encoding: 7bit\r\n\r\n" .
    $text . "\r\n\r\n" .
    "--$boundary\r\n" .
    "Content-Type: text/html; charset=utf-8\r\n" .
    "Content-Transfer-Encoding: 7bit\r\n\r\n" .
    $html . "\r\n\r\n" .
    "--$boundary--";

// Попытка отправки
$sent = mail($CONFIG['admin_email'], $subject, $body, implode("\r\n", $headers));

// === Ответ клиенту ===
if ($sent) {
    // Логирование (опционально)
    $log = date('Y-m-d H:i:s') . " | {$ip} | {$clean['name']} | {$clean['phone']}\n";
    @file_put_contents(__DIR__ . '/applications.log', $log, FILE_APPEND | LOCK_EX);
    
    echo json_encode(['success' => true, 'message' => 'Заявка отправлена']);
} else {
    // Ошибка отправки — логируем и возвращаем фолбэк
    error_log("AutoBodyKits: mail() failed for {$clean['phone']}");
    
    // В продакшене лучше не раскрывать детали ошибки
    echo json_encode(['success' => false, 'error' => 'Не удалось отправить письмо']);
}
?>