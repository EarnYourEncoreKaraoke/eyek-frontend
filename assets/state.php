<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$STATE_FILE = __DIR__ . '/state.json';

/** Atomic write with lock. */
function save_state(string $file, array $state): bool {
  $tmp = $file . '.tmp';
  $json = json_encode($state, JSON_UNESCAPED_SLASHES);
  if ($json === false) return false;
  $ok = @file_put_contents($tmp, $json, LOCK_EX);
  if ($ok === false) return false;
  return @rename($tmp, $file);
}

/** Load state or default. */
function load_state(string $file): array {
  if (!is_file($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/** Read JSON body */
$raw = file_get_contents('php://input');
$incoming = json_decode($raw, true);
if (!is_array($incoming)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_json']);
  exit;
}

$current = load_state($STATE_FILE);

/* Trust the client’s whole state object (front-end always sends the full state).
   We just stamp serverUpdatedAt. */
$incoming['serverUpdatedAt'] = time();

/* Make sure structure exists (in case front-end posted a partial by accident). */
$incoming += [
  'event' => ['venue'=>'','venueCity'=>'','venuePublic'=>'','date'=>'','kj'=>'','hostName'=>'','pin'=>'','locked'=>false],
  'saved' => ['venues'=>[],'venuesCity'=>[],'venuesPublic'=>[],'kjs'=>[]],
  'singers' => [],
  'current' => ['id'=>null,'name'=>'','songArtist'=>''],
  'voting' => ['open'=>false,'prepUntil'=>0,'endsAt'=>0,'extendCount'=>0,'counts'=>['encore'=>0,'another'=>0,'maybe'=>0],'lastResult'=>null,'voters'=>new stdClass()],
  'winners' => ['encore'=>[],'another'=>[],'maybe'=>[]],
];

if (!save_state($STATE_FILE, $incoming)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'save_failed']);
  exit;
}

echo json_encode(['ok' => true]);
