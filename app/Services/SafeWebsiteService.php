<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SafeWebsiteService
{
    public function fetch(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true))) {
            $this->reject();
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_column(dns_get_record($host, DNS_A), 'ip');
        if (! $addresses) {
            $this->reject();
        }
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->reject();
            }
        }
        // Pin validated DNS resolution, disable redirect following and bound response size.
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $r = Http::connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$addresses[0]], CURLOPT_NOPROXY => '*'],
            'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 2000000) {
                    $this->reject();
                }
            },
            'progress' => function ($total, $downloaded) {
                if ($downloaded > 2000000) {
                    $this->reject();
                }
            }])->get($url);
        if (! $r->successful() || ! str_contains(strtolower($r->header('Content-Type') ?? ''), 'text/html') || strlen($r->body()) > 2000000) {
            $this->reject();
        }

        return $r->body();
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['url' => 'Chỉ cho phép trang HTML công khai qua HTTP/HTTPS; không theo redirect hoặc địa chỉ nội bộ.']);
    }
}
