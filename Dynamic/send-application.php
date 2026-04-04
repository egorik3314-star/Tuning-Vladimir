<?php
/**
 * send-application.php - Исправленная версия с отладкой
 */

// Включаем отображение всех ошибок для диагностики
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логирование
$log_file = __DIR__ . '/mail_debug.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

writeLog("=== НОВЫЙ ЗАПРОС ===");
writeLog("Метод: " . $_SERVER['REQUEST_METHOD']);

// Получаем данные
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
}

writeLog("Данные: " . json_encode($input, JSON_UNESCAPED_UNICODE));

// Настройки получателя
$to = "egorik3314@gmail.com";
$site_name = "AutoBodyKits";

// Формируем письмо
$subject = "=?UTF-8?B?" . base64_encode("Новая заявка с AutoBodyKits") . "?=";

// Тело письма
$message = "Новая заявка с сайта AutoBodyKits\n\n";
$message .= "----------------------------------------\n";

if ($input && isset($input['type'])) {
    if ($input['type'] == 'main_request') {
        $message .= "ТИП: Основная заявка\n";
        $message .= "Имя: " . ($input['name'] ?? 'не указано') . "\n";
        $message .= "Телефон: " . ($input['phone'] ?? 'не указан') . "\n";
        $message .= "Марка авто: " . ($input['carBrand'] ?? 'не указана') . "\n";
        $message .= "Модель: " . ($input['carModel'] ?? 'не указана') . "\n";
        $message .= "Услуга: " . ($input['service'] ?? 'не указана') . "\n";
        $message .= "Комментарий: " . ($input['comment'] ?? 'нет') . "\n";
    } elseif ($input['type'] == 'quick_request') {
        $message .= "ТИП: Быстрая заявка\n";
        $message .= "Имя: " . ($input['name'] ?? 'не указано') . "\n";
        $message .= "Телефон: " . ($input['phone'] ?? 'не указан') . "\n";
        $message .= "Марка: " . ($input['brand'] ?? 'не указана') . "\n";
    }
} else {
    $message .= "Данные формы:\n";
    foreach ($input as $key => $value) {
        if (is_array($value)) continue;
        $message .= "$key: $value\n";
    }
}

$message .= "----------------------------------------\n";
$message .= "Время: " . date('d.m.Y H:i:s') . "\n";
$message .= "IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
$message .= "\n---\nAutoBodyKits";

// Заголовки
$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";
$headers .= "Reply-To: " . ($input['email'] ?? 'no-reply@' . $_SERVER['HTTP_HOST']) . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// Пробуем отправить
writeLog("Попытка отправки на: $to");
writeLog("Заголовки: " . str_replace("\r\n", " | ", $headers));

$result = mail($to, $subject, $message, $headers, "-f no-reply@" . $_SERVER['HTTP_HOST']);

if ($result) {
    writeLog("✅ Письмо успешно отправлено!");
    echo json_encode([
        'success' => true,
        'message' => 'Заявка отправлена на почту'
    ]);
} else {
    writeLog("❌ Ошибка отправки письма!");
    
    // Пробуем без дополнительных параметров
    $result2 = mail($to, $subject, $message, $headers);
    
    if ($result2) {
        writeLog("✅ Вторая попытка успешна!");
        echo json_encode([
            'success' => true,
            'message' => 'Заявка отправлена'
        ]);
    } else {
        writeLog("❌ Вторая попытка тоже не удалась");
        
        // Сохраняем заявку в файл
        $pending_dir = __DIR__ . '/pending';
        if (!file_exists($pending_dir)) {
            mkdir($pending_dir, 0777, true);
        }
        $filename = $pending_dir . '/request_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($filename, json_encode([
            'data' => $input,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => false,
            'error' => 'Письмо не отправлено, но заявка сохранена',
            'saved_to' => $filename
        ]);
    }
}

writeLog("=== КОНЕЦ ЗАПРОСА ===\n");
?>