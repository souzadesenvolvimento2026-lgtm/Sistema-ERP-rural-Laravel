<?php

namespace App\Services;

use Illuminate\Http\Request;

class RequestContextService
{
    /**
     * @return array{ip_cliente: ?string, ip_proxy: ?string, user_agent: ?string, cf_ray: ?string, host: ?string, rota: ?string, metodo: ?string}
     */
    public function auditContext(?Request $request = null): array
    {
        $request ??= request();

        return [
            'ip_cliente' => $this->clientIp($request),
            'ip_proxy' => $this->proxyIp($request),
            'user_agent' => $this->limit($request->userAgent(), 1000),
            'cf_ray' => $this->limit($request->headers->get('CF-Ray'), 80),
            'host' => $this->limit($request->getHost(), 190),
            'rota' => $this->limit('/'.ltrim($request->path(), '/'), 255),
            'metodo' => $this->limit($request->method(), 10),
        ];
    }

    public function clientIp(?Request $request = null): ?string
    {
        $request ??= request();

        if ($this->canTrustProxyHeaders($request)) {
            foreach (['CF-Connecting-IP', 'True-Client-IP'] as $header) {
                $ip = $this->validIp($request->headers->get($header));
                if ($ip !== null) {
                    return $ip;
                }
            }

            $forwarded = $this->firstForwardedIp($request->headers->get('X-Forwarded-For'));
            if ($forwarded !== null) {
                return $forwarded;
            }

            $realIp = $this->validIp($request->headers->get('X-Real-IP'));
            if ($realIp !== null) {
                return $realIp;
            }
        }

        return $this->validIp($request->ip())
            ?? $this->validIp((string) $request->server('REMOTE_ADDR'));
    }

    public function proxyIp(?Request $request = null): ?string
    {
        $request ??= request();

        return $this->validIp((string) $request->server('REMOTE_ADDR'))
            ?? $this->validIp($request->ip());
    }

    private function firstForwardedIp(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (explode(',', $value) as $candidate) {
            $ip = $this->validIp(trim($candidate));
            if ($ip !== null) {
                return $ip;
            }
        }

        return null;
    }

    private function canTrustProxyHeaders(Request $request): bool
    {
        $proxyIp = $this->proxyIp($request);

        if ($proxyIp === null) {
            return false;
        }

        return $this->isPrivateOrLocal($proxyIp) || in_array($proxyIp, $this->trustedProxyIps(), true);
    }

    /**
     * @return list<string>
     */
    private function trustedProxyIps(): array
    {
        $configured = (string) env('TRUSTED_PROXY_IPS', '');

        return array_values(array_filter(array_map(
            fn (string $ip): ?string => $this->validIp(trim($ip)),
            explode(',', $configured),
        )));
    }

    private function isPrivateOrLocal(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return $ip === '127.0.0.1' || $ip === '::1';
    }

    private function validIp(?string $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function limit(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
