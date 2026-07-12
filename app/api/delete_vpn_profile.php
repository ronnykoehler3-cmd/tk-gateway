<?php

header('Content-Type: application/json');

require_once
'/opt/tkgateway/backend/classes/EventLogger.php';

if(!isset($_GET['id']))
{
    echo json_encode([
        'success' => false,
        'message' => 'Keine ID übergeben'
    ]);
    exit;
}

$id=(int)$_GET['id'];

$db=new SQLite3(
    '/opt/tkgateway/database/gateway.db'
);

$profile=$db->querySingle(
"
SELECT *
FROM vpn_profiles
WHERE id=$id
",
true
);

if(!$profile)
{
    echo json_encode([
        'success' => false,
        'message' => 'Profil nicht gefunden'
    ]);
    exit;
}

if($profile['active']==1)
{
    echo json_encode([
        'success' => false,
        'message' => 'Aktives Profil kann nicht gelöscht werden'
    ]);
    exit;
}

$count=$db->querySingle(
"
SELECT COUNT(*)
FROM vpn_profiles
"
);

if($count <= 1)
{
    echo json_encode([
        'success' => false,
        'message' => 'Das letzte Profil kann nicht gelöscht werden'
    ]);
    exit;
}

$profileDir=
    '/opt/tkgateway/vpn-profiles/' .
    $profile['profile_dir'];

if(is_dir($profileDir))
{
    exec(
        'rm -rf ' .
        escapeshellarg(
            $profileDir
        )
    );
}

$db->exec(
"
DELETE FROM vpn_profiles
WHERE id=$id
"
);

EventLogger::log(
    'WARNING',
    'VPN Profil "' .
    $profile['name'] .
    '" gelöscht'
);

echo json_encode([
    'success' => true,
    'message' => 'Profil gelöscht'
]);
