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
        'success'=>false,
        'message'=>'Profil nicht gefunden'
    ]);
    exit;
}

$profileName=
    $profile['profile_dir'];

exec(
    "sudo /opt/tkgateway/scripts/switch_vpn_profile.sh "
    . escapeshellarg($profileName),
    $output,
    $rc
);

if($rc !== 0)
{
    echo json_encode([
        'success'=>false,
        'message'=>'Umschaltung fehlgeschlagen',
        'output'=>$output
    ]);
    exit;
}

$db->exec(
"
UPDATE vpn_profiles
SET
    active=0,
    manual_selected=0
"
);

$db->exec(
"
UPDATE vpn_profiles
SET
    active=1,
    manual_selected=1
WHERE id=$id
"
);

EventLogger::log(
    'INFO',
    'VPN Profil "' .
    $profile['name'] .
    '" aktiviert'
);

echo json_encode([
    'success'=>true,
    'profile'=>$profile['name'],
    'output'=>$output
]);
