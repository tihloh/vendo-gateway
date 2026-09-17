<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;

/** One cached release snapshot shared by page loads, manual checks, and devices. */
final class ReleaseManifestService
{
    private ?array $snapshot = null;
    private bool $loaded = false;
    private ?string $error = null;

    public function __construct(
        private string $manifestUrl = 'https://github.com/tihloh/vendogate-firmware-releases/releases/latest/download/manifest.json',
        private int $cacheSeconds = 300,
        private string $releaseApiUrl = 'https://api.github.com/repos/tihloh/vendogate-firmware-releases/releases/latest',
        private ?\Closure $transport = null,
        private ?string $cacheDirectory = null,
    ) {}

    public function latest(?string $target = null, bool $refresh = false): ?array
    {
        $release = $this->load($refresh);
        if ($release === null || $target === null) return $release;
        foreach ($release['files'] as $file) {
            if ($file['target'] === $target) return $release + $file;
        }
        return null;
    }

    public function status(string $currentVersion, string $target, bool $refresh = false): array
    {
        $release = $this->load($refresh);
        $status = [
            'current_version' => $currentVersion,
            'latest_version' => $release['version'] ?? null,
            'update_available' => null,
            'target' => $target,
            'channel' => $release['channel'] ?? 'stable',
            'checked_at' => $release['checked_at'] ?? null,
            'error' => $this->error,
        ];
        if ($release === null) return $status;
        $binary = $this->latest($target);
        if ($binary === null) return array_replace($status, ['error' => 'No verified binary is available for this device target.']);
        $current = ltrim(trim($currentVersion), 'vV');
        if (!$this->validVersion($current)) return array_replace($status, ['error' => 'Installed firmware version is unknown.']);
        return array_replace($status, [
            'update_available' => version_compare($release['version'], $current, '>'),
            'url' => $binary['url'], 'sha256' => $binary['sha256'], 'size' => $binary['size'],
            'hardware_model' => $release['hardware_model'], 'hardware_revision' => $release['hardware_revision'],
            'error' => null,
        ]);
    }

    private function load(bool $refresh): ?array
    {
        if ($this->loaded && !$refresh) return $this->snapshot;
        $this->loaded = true;
        $this->error = null;
        $cache = ($this->cacheDirectory ?? sys_get_temp_dir()).'/vendo-gateway-release-v2-'.sha1($this->releaseApiUrl.'|'.$this->manifestUrl).'.json';
        if (!$refresh && is_file($cache)) {
            $stored = json_decode((string) @file_get_contents($cache), true);
            $ttl = isset($stored['release']) ? $this->cacheSeconds : min(30, $this->cacheSeconds);
            if (is_array($stored) && time() - (int) ($stored['at'] ?? 0) < $ttl) {
                $this->error = $stored['error'] ?? null;
                return $this->snapshot = $stored['release'] ?? null;
            }
        }
        try {
            $release = $this->json($this->releaseApiUrl);
            $manifestUrl = $this->manifestUrl;
            $version = null;
            if ($release !== null) {
                if (!empty($release['draft']) || !empty($release['prerelease'])) throw new \RuntimeException('The release is not a stable published release.');
                $version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
                if (!$this->validVersion($version)) throw new \RuntimeException('Invalid release version.');
                // Pin the manifest to this release. Never combine two latest releases.
                $manifestUrl = '';
                foreach ((array) ($release['assets'] ?? []) as $asset) {
                    if (($asset['name'] ?? '') === 'manifest.json') $manifestUrl = (string) ($asset['browser_download_url'] ?? '');
                }
                if ($manifestUrl === '') throw new \RuntimeException('The release has no firmware manifest.');
            }
            $manifest = $this->json($manifestUrl);
            if ($manifest === null || ($manifest['protocol'] ?? '') !== 'vendo-gateway-firmware') throw new \RuntimeException('Firmware release metadata could not be fetched.');
            $manifestVersion = ltrim((string) ($manifest['version'] ?? ''), 'vV');
            if (!$this->validVersion($manifestVersion) || ($version !== null && $manifestVersion !== $version)) throw new \RuntimeException('Release and manifest versions do not match.');
            if (($manifest['channel'] ?? 'stable') !== 'stable') throw new \RuntimeException('Unsupported firmware channel.');
            if (empty($manifest['hardware_model'])) throw new \RuntimeException('Firmware hardware model is missing.');
            $files = [];
            foreach ((array) ($manifest['files'] ?? []) as $file) {
                if (!is_array($file)) continue;
                $target = (string) ($file['target'] ?? '');
                $name = (string) ($file['file'] ?? '');
                $sha = strtolower((string) ($file['sha256'] ?? ''));
                $size = (int) ($file['size'] ?? 0);
                if (!in_array($target, ['esp32', 'esp8266'], true) || $name !== 'vendogate-'.$target.'.bin' || !preg_match('/^[a-f0-9]{64}$/', $sha) || $size < 1) continue;
                $url = 'https://github.com/tihloh/vendogate-firmware-releases/releases/download/v'.rawurlencode($manifestVersion).'/'.$name;
                if ($release !== null) {
                    $matched = false;
                    foreach ((array) ($release['assets'] ?? []) as $asset) {
                        if (($asset['name'] ?? '') !== $name) continue;
                        $digest = (string) ($asset['digest'] ?? '');
                        if ((int) ($asset['size'] ?? 0) !== $size || ($digest !== '' && strtolower($digest) !== 'sha256:'.$sha)) break;
                        $url = (string) ($asset['browser_download_url'] ?? '');
                        $matched = true;
                        break;
                    }
                    if (!$matched) continue;
                }
                if (!$this->https($url)) continue;
                $files[] = ['target' => $target, 'file' => $name, 'sha256' => $sha, 'size' => $size, 'url' => $url];
            }
            $this->snapshot = ['version' => $manifestVersion, 'channel' => 'stable',
                'hardware_model' => (string) $manifest['hardware_model'],
                'hardware_revision' => (string) ($manifest['hardware_revision'] ?? ''),
                'files' => $files, 'checked_at' => gmdate(DATE_ATOM)];
        } catch (\Throwable $e) {
            $this->snapshot = null;
            $this->error = $e->getMessage();
        }
        @file_put_contents($cache, json_encode(['at' => time(), 'release' => $this->snapshot, 'error' => $this->error], JSON_THROW_ON_ERROR), LOCK_EX);
        return $this->snapshot;
    }

    private function validVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:\+[a-zA-Z0-9.-]+)?$/', $version) === 1;
    }

    private function https(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    private function json(string $url): ?array
    {
        if (!$this->https($url)) return null;
        $raw = $this->transport !== null ? ($this->transport)($url) : $this->fetch($url);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    private function fetch(string $url): ?string
    {
        $headers = ['Accept: application/json', 'User-Agent: tihloh/vendo-gateway'];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return is_string($raw) && $code >= 200 && $code < 300 ? $raw : null;
        }
        // Follow HTTPS redirects explicitly; do not allow a silent HTTP downgrade.
        for ($i = 0; $i < 6; $i++) {
            $http_response_header = [];
            $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 0,
                'ignore_errors' => true, 'header' => implode("\r\n", $headers)."\r\n"]]);
            $raw = @file_get_contents($url, false, $context);
            preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $match);
            $code = (int) ($match[1] ?? 0);
            if ($code >= 200 && $code < 300) return is_string($raw) ? $raw : null;
            if (!in_array($code, [301, 302, 303, 307, 308], true)) return null;
            $next = '';
            foreach ($http_response_header as $header) if (stripos($header, 'Location:') === 0) $next = trim(substr($header, 9));
            if (!$this->https($next)) return null;
            $url = $next;
        }
        return null;
    }
}
