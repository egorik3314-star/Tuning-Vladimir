<?php
// test.php - проверка PHP и mail()
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Тест PHP и mail()</h2>";
echo "Версия PHP: " . phpversion() . "<br>";

// Проверяем функцию mail()
if (function_exists('mail')) {
    echo "✅ Функция mail() доступна<br>";
} else {
    echo "❌ Функция mail() НЕ доступна!<br>";
}

// Отправляем тестовое письмо
$to = "egorik3314@gmail.com";
$subject = "Тест с сайта AutoBodyKits";
$message = "Это тестовое письмо для проверки работы почты на вашем хостинге.";
$headers = "From: test@autobodykits.ru\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$result = mail($to, $subject, $message, $headers);

if ($result) {
    echo "✅ Тестовое письмо отправлено на egorik3314@gmail.com<br>";
    echo "Проверьте почту (возможно в спаме)!<br>";
} else {
    echo "❌ Ошибка отправки тестового письма!<br>";
}

// Показываем настройки mail
echo "<h3>Настройки почты:</h3>";
echo "<pre>";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "</pre>";
?>