<?php

header('Content-Type: application/json');

$db =
    new SQLite3(
        '/opt/tkgateway/database/gateway.db'
    );

$name =
    trim(
        $_POST['name'] ?? ''
    );

if($name == '')
{
    echo json_encode([
        'success'=>false,
        'message'=>'Profilname fehlt'
    ]);
    exit;
}

$directory =
    preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        strtolower($name)
    );

$profileDir =
    '/opt/tkgateway/vpn-profiles/' .
    $directory;

mkdir(
    $profileDir,
    0755,
    true
);

$type='unknown';
$provider='Unbekannt';
$endpoint='unbekannt';

if(
    !empty($_POST['vless_link'])
)
{
    $link =
        trim(
            $_POST['vless_link']
        );

    preg_match(
        '/vless:\/\/([^@]+)@([^:]+):([0-9]+)/',
        $link,
        $matches
    );

    $uuid=$matches[1] ?? '';
    $server=$matches[2] ?? '';
    $port=$matches[3] ?? '443';

    parse_str(
        parse_url(
            $link,
            PHP_URL_QUERY
        ),
        $query
    );

    $config=[
        "outbounds"=>[
            [
                "type"=>"vless",
                "tag"=>"proxy",
                "server"=>$server,
                "server_port"=>(int)$port,
                "uuid"=>$uuid,
                "flow"=>$query['flow'] ?? '',
                "tls"=>[
                    "enabled"=>true,
                    "server_name"=>$query['sni'] ?? '',
                    "reality"=>[
                        "enabled"=>true,
                        "public_key"=>$query['pbk'] ?? '',
                        "short_id"=>$query['sid'] ?? ''
                    ],
                    "utls"=>[
                        "enabled"=>true,
                        "fingerprint"=>$query['fp'] ?? 'chrome'
                    ]
                ]
            ]
        ]
    ];

    file_put_contents(
        $profileDir .
        '/config.json',
        json_encode(
            $config,
            JSON_PRETTY_PRINT
        )
    );

    $type='vless-reality';
    $provider='Amnezia XRay';
    $endpoint=$server . ':' . $port;
}
elseif(
    isset($_FILES['profile'])
)
{
    $fileName=
        basename(
            $_FILES['profile']['name']
        );

    move_uploaded_file(
        $_FILES['profile']['tmp_name'],
        $profileDir .
        '/' .
        $fileName
    );

    $provider='Datei Import';
    $type='import';
}

$stmt=
$db->prepare(
'
INSERT INTO vpn_profiles
(
    name,
    profile_dir,
    provider,
    type,
    endpoint,
    enabled,
    priority,
    active
)
VALUES
(
    :name,
    :dir,
    :provider,
    :type,
    :endpoint,
    1,
    100,
    0
)
'
);

$stmt->bindValue(':name',$name);
$stmt->bindValue(':dir',$directory);
$stmt->bindValue(':provider',$provider);
$stmt->bindValue(':type',$type);
$stmt->bindValue(':endpoint',$endpoint);

$stmt->execute();

echo json_encode([
    'success'=>true,
    'provider'=>$provider,
    'type'=>$type,
    'endpoint'=>$endpoint,
    'directory'=>$directory
]);
