<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$LOG_FILE = __DIR__ . '/performance.log';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

$line = json_encode([
  'ts' => gmdate('c'),
  'action' => $data['action'] ?? '',
  'when' => $data['when'] ?? '',
  'singer' => $data['singer'] ?? '',
  'song' => $data['song'] ?? '',
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

@file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);

echo json_encode(['ok'=>true]);
