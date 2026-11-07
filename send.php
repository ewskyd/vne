<?php
/**
 * ВНЕ — приём заявок с сайта и отправка на почту.
 * Положите файл в корень сайта рядом с index.html (должен открываться как https://vnemedia.ru/send.php).
 *
 * Как включить отправку через SMTP Яндекса (рекомендуется):
 * 1) Яндекс ID -> Безопасность -> Пароли приложений -> создать пароль для «Почта».
 * 2) Вставьте его в SMTP_PASS ниже.
 * Если SMTP_PASS пустой, скрипт попробует обычную функцию mail() хостинга.
 */
header('Content-Type: application/json; charset=utf-8');

const MAIL_TO   = 'vnemedia@yandex.ru';
const SMTP_HOST = 'ssl://smtp.yandex.ru';
const SMTP_PORT = 465;
const SMTP_USER = 'vnemedia@yandex.ru';
const SMTP_PASS = '';            // <- пароль приложения Яндекса

function out(bool $ok, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Метод не поддерживается', 405);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;
if (!is_array($data) || !$data) out(false, 'Пустая заявка', 400);
if (!empty($data['_honey'])) out(true);   // антиспам: бот заполнил скрытое поле

$clean = function ($v): string {
    $v = is_scalar($v) ? (string)$v : '';
    $v = str_replace("\0", '', $v);
    return trim(mb_substr($v, 0, 3000));
};

$subject = $clean($data['_subject'] ?? 'ВНЕ: заявка с сайта');
$subject = preg_replace('/[\r\n]+/', ' ', $subject);
$replyTo = $clean($data['_replyto'] ?? '');
if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = '';

$lines = [];
foreach ($data as $k => $v) {
    if (strpos((string)$k, '_') === 0) continue;
    $val = $clean($v);
    if ($val === '') continue;
    $lines[] = $clean($k) . ': ' . $val;
}
if (!$lines) out(false, 'Пустая заявка', 400);
$lines[] = '';
$lines[] = 'Отправлено с сайта: ' . ($_SERVER['HTTP_HOST'] ?? 'vnemedia.ru') . ' · ' . date('d.m.Y H:i');
$body = implode("\r\n", $lines);

function smtp_read($fp): string {
    $resp = '';
    while (($line = fgets($fp, 515)) !== false) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $resp;
}
function smtp_cmd($fp, string $cmd, string $expect): void {
    fwrite($fp, $cmd . "\r\n");
    $r = smtp_read($fp);
    if (strpos($r, $expect) !== 0) throw new Exception('SMTP: ' . trim($r));
}
function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

function send_smtp(string $subject, string $body, string $replyTo): void {
    $fp = stream_socket_client(SMTP_HOST . ':' . SMTP_PORT, $en, $es, 15);
    if (!$fp) throw new Exception('SMTP: нет соединения (' . $es . ')');
    stream_set_timeout($fp, 15);
    $r = smtp_read($fp);
    if (strpos($r, '220') !== 0) throw new Exception('SMTP: ' . trim($r));
    smtp_cmd($fp, 'EHLO vnemedia.ru', '250');
    smtp_cmd($fp, 'AUTH LOGIN', '334');
    smtp_cmd($fp, base64_encode(SMTP_USER), '334');
    smtp_cmd($fp, base64_encode(SMTP_PASS), '235');
    smtp_cmd($fp, 'MAIL FROM:<' . SMTP_USER . '>', '250');
    smtp_cmd($fp, 'RCPT TO:<' . MAIL_TO . '>', '250');
    smtp_cmd($fp, 'DATA', '354');
    $h  = 'From: ' . enc('Сайт ВНЕ') . ' <' . SMTP_USER . ">\r\n";
    $h .= 'To: <' . MAIL_TO . ">\r\n";
    if ($replyTo) $h .= 'Reply-To: <' . $replyTo . ">\r\n";
    $h .= 'Subject: ' . enc($subject) . "\r\n";
    $h .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    $h .= 'Date: ' . date('r') . "\r\n\r\n";
    $msg = $h . chunk_split(base64_encode($body)) . "\r\n.";
    smtp_cmd($fp, $msg, '250');
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

try {
    if (SMTP_PASS !== '') {
        send_smtp($subject, $body, $replyTo);
    } else {
        $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'vnemedia.ru');
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: noreply@{$host}\r\n";
        if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
        if (!mail(MAIL_TO, enc($subject), $body, $headers)) throw new Exception('mail() вернул ошибку');
    }
    out(true, 'ok');
} catch (Throwable $e) {
    error_log('send.php: ' . $e->getMessage());
    out(false, 'Не удалось отправить письмо', 500);
}
