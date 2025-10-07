<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$STATE_FILE = __DIR__ . '/state.json';

/** Load JSON safely */
function load_json(string $file): array {
  if (!is_file($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function save_json(string $file, array $data): bool {
  $tmp = $file . '.tmp';
  $ok = @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES));
  if ($ok === false) return false;
  return @rename($tmp, $file);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$choice   = isset($input['choice']) ? (string)$input['choice'] : '';
$deviceId = isset($input['deviceId']) ? (string)$input['deviceId'] : '';

if (!in_array($choice, ['encore','another','maybe'], true)) {
  http_response_code(400);
  echo json_encode(['error'=>'bad_choice']);
  exit;
}
if ($deviceId === '') {
  http_response_code(400);
  echo json_encode(['error'=>'missing_device']);
  exit;
}

$st = load_json($STATE_FILE);
$nowMs = (int) round(microtime(true)*1000);

if (!($st['voting']['open'] ?? false)) {
  http_response_code(409);
  echo json_encode(['error'=>'not_open']);
  exit;
}

if (!isset($st['voting']['voters']) || !is_array($st['voting']['voters'])) {
  $st['voting']['voters'] = [];
}
if (!isset($st['voting']['counts']) || !is_array($st['voting']['counts'])) {
  $st['voting']['counts'] = ['encore'=>0,'another'=>0,'maybe'=>0];
}

if (isset($st['voting']['voters'][$deviceId])) {
  http_response_code(409);
  echo json_encode(['error'=>'already_voted']);
  exit;
}

$st['voting']['voters'][$deviceId] = ['choice'=>$choice, 'when'=>$nowMs];
$st['voting']['counts'][$choice] = (int)($st['voting']['counts'][$choice] ?? 0) + 1;
$st['serverUpdatedAt'] = time();

if (!save_json($STATE_FILE, $st)) {
  http_response_code(500);
  echo json_encode(['error'=>'save_failed']);
  exit;
}

echo json_encode(['ok'=>true, 'counts'=>$st['voting']['counts']]);
