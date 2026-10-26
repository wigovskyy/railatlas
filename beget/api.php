<?php
// RailAtlas: серверное хранилище маршрутов и объектов.
// Каждая запись лежит отдельным JSON-файлом в папке data/. Читать может любой, писать — только тот, кто знает ключ.

// Задайте свой ключ редактирования (длинная случайная строка). Пока он не изменён, запись отключена.
$EDIT_KEY = 'ЗАМЕНИТЕ_НА_СВОЙ_КЛЮЧ';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$base = __DIR__ . '/data';
$colls = ['routes', 'pois'];

function fail($code, $msg) { http_response_code($code); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); exit; }
function can_write() {
    global $EDIT_KEY;
    if ($EDIT_KEY === 'ЗАМЕНИТЕ_НА_СВОЙ_КЛЮЧ' || strlen($EDIT_KEY) < 8) return false;
    $got = $_SERVER['HTTP_X_EDIT_KEY'] ?? '';
    return is_string($got) && hash_equals($EDIT_KEY, $got);
}
foreach ($colls as $c) { if (!is_dir("$base/$c") && !@mkdir("$base/$c", 0755, true) && !is_dir("$base/$c")) fail(500, 'Не удаётся создать папку data'); }
if (!file_exists("$base/.htaccess")) @file_put_contents("$base/.htaccess", "Require all denied\nDeny from all\n");

// Первый запуск: если рядом лежит data.json, переносим его в хранилище
if (!file_exists("$base/.seeded")) {
    $seed = __DIR__ . '/data.json';
    if (is_file($seed)) {
        $d = json_decode(file_get_contents($seed), true);
        if (is_array($d)) {
            foreach (['routes', 'pois'] as $c) foreach (($d[$c] ?? []) as $o) {
                if (!is_array($o) || !isset($o['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $o['id'])) continue;
                $id = $o['id']; unset($o['id']);
                file_put_contents("$base/$c/$id.json", json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }
    @file_put_contents("$base/.seeded", date('c'));
}

$action = $_GET['action'] ?? 'all';
if ($action === 'all') {
    $out = '{"format":"railatlas-server"';
    foreach ($colls as $c) {
        $out .= ',"' . $c . '":[';
        $first = true;
        foreach (glob("$base/$c/*.json") ?: [] as $f) {
            $raw = file_get_contents($f); $id = basename($f, '.json');
            if ($raw === false || $raw === '' || $raw[0] !== '{') continue;
            $out .= ($first ? '' : ',') . '{"id":' . json_encode($id) . ($raw === '{}' ? '' : ',' . substr($raw, 1));
            $first = false;
        }
        $out .= ']';
    }
    echo $out . '}';
    exit;
}
if ($action === 'check') { echo json_encode(['canWrite' => can_write()]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Нужен POST');
if (!can_write()) fail(403, 'Неверный ключ редактирования');
$coll = $_GET['coll'] ?? ''; $id = $_GET['id'] ?? '';
if (!in_array($coll, $colls, true) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) fail(400, 'Неверные параметры');
$file = "$base/$coll/$id.json";
if ($action === 'put') {
    $body = file_get_contents('php://input');
    if (strlen($body) > 6 * 1024 * 1024) fail(413, 'Запись слишком большая');
    $d = json_decode($body, true);
    if (!is_array($d)) fail(400, 'Нужен JSON-объект');
    unset($d['id']);
    $tmp = $file . '.tmp' . getmypid();
    if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !rename($tmp, $file)) fail(500, 'Не удалось записать файл');
    echo '{"ok":true}'; exit;
}
if ($action === 'delete') { if (is_file($file)) unlink($file); echo '{"ok":true}'; exit; }
fail(400, 'Неизвестное действие');
