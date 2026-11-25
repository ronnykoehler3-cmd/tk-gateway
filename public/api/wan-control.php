<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
$ip=$_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip,['127.0.0.1','::1'],true) && !preg_match('/^192\.168\.50\.[0-9]{1,3}$/D',$ip)) {http_response_code(403); exit;}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') {http_response_code(405); exit;}
$d=json_decode(file_get_contents('php://input',false,null,0,4096),true);
if (!is_array($d)) {http_response_code(400); exit;}
$m=$d['mode'] ?? '';
if (!in_array($m,['AUTO','PRIMARY','BACKUP','LTE'],true)) {http_response_code(400); exit;}
exec('sudo -n /usr/local/sbin/tkgateway-wan-control '.escapeshellarg($m).' 2>&1',$out,$rc);
if ($rc !== 0) http_response_code(409);
echo json_encode(['ok'=>$rc===0]);
