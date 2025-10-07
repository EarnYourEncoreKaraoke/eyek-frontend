<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$STATE_FILE = __DIR__ . '/state.json';

/** Full default state */
function default_state(): array {
  $today = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
  return [
    'serverUpdatedAt' => time(),
    'event' => [
      'venue'       => '',
      'venueCity'   => '',
      'venuePublic' => '',
      'date'        => $today,
      'kj'          => '',
      'hostName'    => '',
      'pin'         => '',
      'locked'      => false,
    ],
    'saved'   => ['venues'=>[], 'venuesCity'=>[], 'venuesPublic'=>[], 'kjs'=>[]],
    'singers' => [],
    'current' => ['id'=>null, 'name'=>'', 'songArtist'=>''],
    'voting'  => [
      'open'=>false, 'prepUntil'=>0, 'endsAt'=>0, 'extendCount'=>0,
      'counts'=>['encore'=>0,'another'=>0,'maybe'=>0],
      'lastResult'=>null,
      'voters'=>new stdClass(), // keep {} not []
    ],
    'winners' => ['encore'=>[], 'another'=>[], 'maybe'=>[]],
  ];
}

/** Load state from JSON (may be partial) */
function load_state(string $file): array {
  if (!is_file($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/** Deep merge: right side overrides left, but keeps missing keys from defaults */
function merge_deep(array $base, array $override): array {
  foreach ($override as $k => $v) {
    if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
      $base[$k] = merge_deep($base[$k], $v);
    } else {
      $base[$k] = $v;
    }
  }
  return $base;
}

$fromDisk = load_state($STATE_FILE);
$state = merge_deep(default_state(), $fromDisk);

/* Add a millisecond server clock for client clock smoothing */
$state['serverNow'] = (int) round(microtime(true) * 1000);

echo json_encode($state, JSON_UNESCAPED_SLASHES);
