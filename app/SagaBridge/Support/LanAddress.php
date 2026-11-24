<?php

namespace App\SagaBridge\Support;

/**
 * Mencari IP laptop di jaringan lokal (WiFi kelas) untuk QR audiens.
 */
class LanAddress
{
    public static function detect(): ?string
    {
        // Trik UDP: tidak ada paket yang benar-benar dikirim, OS hanya memilih
        // antarmuka jaringan yang akan dipakai, lalu kita baca alamatnya.
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
        if ($socket) {
            $name = @stream_socket_get_name($socket, false);
            fclose($socket);
            if (is_string($name) && str_contains($name, ':')) {
                $ip = substr($name, 0, (int) strrpos($name, ':'));
                if (self::usable($ip)) {
                    return $ip;
                }
            }
        }

        $hostname = gethostname();
        $ips = $hostname ? (@gethostbynamel($hostname) ?: []) : [];
        foreach ($ips as $ip) {
            if (self::usable($ip)) {
                return $ip;
            }
        }

        return null;
    }

    private static function usable(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && ! str_starts_with($ip, '127.')
            && $ip !== '0.0.0.0';
    }
}
