<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$STATE_FILE = __DIR__ . '/state.json';

function load_state(string $file): array {
  if (!is_file($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function save_state(string $file, array $state): bool {
  $tmp = $file . '.tmp';
  $json = json_encode($state, JSON_UNESCAPED_SLASHES);
  if ($json === false) return false;
  $ok = @file_put_contents($tmp, $json, LOCK_EX);
  if ($ok === false) return false;
  return @rename($tmp, $file);
}

/* Read JSON body */
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$choice = $payload['choice'] ?? '';
$device = $payload['deviceId'] ?? '';

if (!in_array($choice, ['encore','another','maybe'], true) || !$device) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_request']);
  exit;
}

/* Load state */
$state = load_state($STATE_FILE);
if (!isset($state['voting']) || !is_array($state['voting'])) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'no_vote_round']);
  exit;
}

/* Only allow while open */
if (empty($state['voting']['open'])) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'closed']);
  exit;
}

/* Ensure voters map exists as object */
if (!isset($state['voting']['voters']) || !is_array($state['voting']['voters'])) {
  $state['voting']['voters'] = [];
}

/* One vote per device per round */
if (!empty($state['voting']['voters'][$device])) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'already_voted']);
  exit;
}

if (!isset($state['voting']['counts']) || !is_array($state['voting']['counts'])) {
  $state['voting']['counts'] = ['encore'=>0,'another'=>0,'maybe'=>0];
}

$state['voting']['counts'][$choice] = (int)($state['voting']['counts'][$choice] ?? 0) + 1;
$state['voting']['voters'][$device] = 1;
$state['serverUpdatedAt'] = time();

if (!save_state($STATE_FILE, $state)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'save_failed']);
  exit;
}

echo json_encode(['ok'=>true]);
