<?php

declare(strict_types=1);

namespace Luna\Network;

final class HostResolver
{
    public static function resolveForTcp(string $host): string
    {
        $host = trim($host);

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $addresses = gethostbynamel($host);

        if ($addresses === false) {
            return $host;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $address;
            }
        }

        return $host;
    }
}
