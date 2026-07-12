<?php

class VpnProfileDetector
{
    public static function detect(string $content): array
    {
        /*
         * Amnezia WireGuard
         */
        if(
            strpos($content,'Jc =') !== false &&
            strpos($content,'Jmin =') !== false &&
            strpos($content,'[Peer]') !== false
        )
        {
            return [
                'type' => 'amnezia-awg',
                'provider' => 'Amnezia AWG'
            ];
        }

        /*
         * VLESS Reality
         */
        if(
            strpos($content,'"protocol": "vless"') !== false &&
            strpos($content,'"reality"') !== false
        )
        {
            return [
                'type' => 'vless-reality',
                'provider' => 'Amnezia XRay'
            ];
        }

        /*
         * Sing-Box JSON
         */
        if(
            strpos($content,'"outbounds"') !== false &&
            strpos($content,'"inbounds"') !== false
        )
        {
            return [
                'type' => 'sing-box',
                'provider' => 'Sing-Box'
            ];
        }

        /*
         * WireGuard
         */
        if(
            strpos($content,'PrivateKey =') !== false &&
            strpos($content,'AllowedIPs =') !== false &&
            strpos($content,'Endpoint =') !== false
        )
        {
            return [
                'type' => 'wireguard',
                'provider' => 'WireGuard'
            ];
        }

        /*
         * OpenVPN
         */
        if(
            strpos($content,'client') !== false &&
            strpos($content,'remote ') !== false
        )
        {
            return [
                'type' => 'openvpn',
                'provider' => 'OpenVPN'
            ];
        }

        return [
            'type' => 'unknown',
            'provider' => 'Unbekannt'
        ];
    }
}
