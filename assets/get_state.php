<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$STATE_FILE = __DIR__ . '/state.json';

/** Load state from disk or return a sane default. */
function default_state(): array {
  $today = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
  return [
    'serverUpdatedAt' => time(),
    'event' => [
      'venue' => '',
      'venueCity' => '',
      'venuePublic' => '',
      'date' => $today,
      'kj' => '',
      'hostName' => '',
      'pin' => '',
      'locked' => false,
    ],
    'saved' => ['venues' => [], 'venuesCity' => [], 'venuesPublic' => [], 'kjs' => []],
    'singers' => [],
    'current' => ['id' => null, 'name' => '', 'songArtist' => ''],
    'voting' => [
      'open' => false,
      'prepUntil' => 0,
      'endsAt' => 0,
      'extendCount' => 0,
      'counts' => ['encore' => 0, 'another' => 0, 'maybe' => 0],
      'lastResult' => null,
      'voters' => new stdClass(), // keep as object for JSON {} not []
    ],
    'winners' => ['encore' => [], 'another' => [], 'maybe' => []],
  ];
}

function load_state(string $file): array {
  if (!is_file($file)) return default_state();
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return default_state();
  $data = json_decode($raw, true);
  if (!is_array($data)) return default_state();
  return $data;
}

$state = load_state($STATE_FILE);

/* Add a millisecond server clock for client clock smoothing */
$state['serverNow'] = (int) round(microtime(true) * 1000);

echo json_encode($state, JSON_UNESCAPED_SLASHES);
