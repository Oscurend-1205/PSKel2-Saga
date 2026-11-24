<?php

namespace App\SagaBridge;

/**
 * Status bridge disimpan di storage/app/saga-bridge.json supaya bisa diubah
 * lewat "php artisan saga-bridge on|off" tanpa menyentuh .env atau cache config.
 *
 * Penahanan relay (lease) disimpan di file terpisah (*.hold) karena ditulis
 * tiap detik oleh presentasi; dengan begitu polling tidak pernah bisa
 * menimpa perintah "saga-bridge off".
 */
class BridgeState
{
    private ?array $cache = null;

    public function __construct(private string $path)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $data = [];
        if (is_file($this->path)) {
            $decoded = json_decode((string) @file_get_contents($this->path), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return $this->cache = array_merge([
            'enabled' => false,
            'key' => null,
            'enabled_at' => null,
            'audience_url' => null,
        ], $data);
    }

    public function allowed(): bool
    {
        return (bool) config('saga-bridge.allowed');
    }

    public function enabled(): bool
    {
        return $this->allowed() && (bool) $this->all()['enabled'];
    }

    public function key(): ?string
    {
        $key = $this->all()['key'];

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function keyMatches(?string $candidate): bool
    {
        $key = $this->key();

        return $key !== null && is_string($candidate) && hash_equals($key, strtoupper(trim($candidate)));
    }

    public function relayHeld(): bool
    {
        if (! $this->enabled() || ! is_file($this->holdPath())) {
            return false;
        }

        return (int) @file_get_contents($this->holdPath()) > time();
    }

    public function holdRelay(int $seconds): void
    {
        @file_put_contents($this->holdPath(), (string) (time() + max(3, $seconds)), LOCK_EX);
    }

    public function releaseRelay(): void
    {
        if (is_file($this->holdPath())) {
            @unlink($this->holdPath());
        }
    }

    private function holdPath(): string
    {
        return $this->path.'.hold';
    }

    public function update(array $changes): array
    {
        $this->cache = null; // baca ulang dari disk agar tidak menimpa perubahan terbaru
        $data = array_merge($this->all(), $changes);

        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $this->cache = $data;
    }
}
