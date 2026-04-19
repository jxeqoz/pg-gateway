<?php

class GameGateway
{
    private string $gameUrl = 'https://m.pgf-thek63.com/';
    private string $staticUrl = 'https://static.pgf-thek63.com';
    private string $lobbyUrl = 'https://public.pgf-thek63.com';
    private string $historyUrl = 'https://public.pgf-asqb7a.com';

    private function getMimeType(string $ext): string
    {
        $mimes = [
            'html' => 'text/html',
            'json' => 'application/json',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'css' => 'text/css'
        ];
        return $mimes[strtolower($ext)] ?? 'application/octet-stream';
    }

    private function randomStr(int $length = 10): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($chars, 5)), 0, $length);
    }

    private function proxyRequest(string $url): void
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mime = $this->getMimeType($ext);

        if (!$ext && str_replace('web-lobby/games', '', $url) !== $url) {
            $mime = 'text/html';
            $path .= 'index.html';
        }

        $cache = __DIR__ . '/storage/' . ltrim($path, '/');

        if (file_exists($cache) && filesize($cache) > 0) {
            $response = file_get_contents($cache);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                if (str_replace('index.html', '', $url) !== $url) {
                    $script = "<script>
                        function getMainUrlPart() {
                            const p = new URL(window.location.href).hostname.split('.');
                            return p.length > 1 ? p[p.length - 2] : p[0];
                        }
                        const replacementSubstring = getMainUrlPart();
                        const forceChangeUrl = false;
                        const specificSub = 'false';
                    </script>";

                    $response = str_replace(
                        '<body>',
                        "<body>\n{$script}\n<script src=\"/assets/pg-verify.js\"></script>\n",
                        $response
                    );
                }

                if (!is_dir(dirname($cache))) {
                    mkdir(dirname($cache), 0777, true);
                }
                file_put_contents($cache, $response);
            }
        }

        header("Content-Type: {$mime}");
        header("Access-Control-Allow-Origin: *");
        echo $response;
        exit;
    }

    private function serveMockSession(): void
    {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");

        $token = $this->randomStr(32);
        $user = "PGUSER_" . $this->randomStr(4);

        echo json_encode([
            "dt" => [
                "oj" => ["jid" => 1],
                "pid" => "NiEjOD0Itc",
                "pcd" => $user,
                "tk" => $token,
                "st" => 1,
                "geu" => "game-api/mahjong-ways2/",
                "lau" => "game-api/lobby/",
                "bau" => "web-api/game-proxy/",
                "cc" => "THB",
                "cs" => "฿",
                "nkn" => $user,
                "gm" => [
                    [
                        "gid" => 74,
                        "msdt" => 1586144943000,
                        "medt" => 1586144943000,
                        "st" => 1,
                        "amsg" => ""
                    ]
                ],
                "uiogc" => [
                    "bb" => 1,
                    "gec" => 1,
                    "cbu" => 0,
                    "cl" => 0,
                    "mr" => 0,
                    "phtr" => 0,
                    "vc" => 0,
                    "il" => 0,
                    "rp" => 2,
                    "gc" => 0,
                    "ign" => 0,
                    "tsn" => 0,
                    "we" => 0,
                    "gsc" => 0,
                    "bu" => 0,
                    "pwr" => 0,
                    "hd" => 0,
                    "igv" => 0,
                    "grt" => 0,
                    "ivs" => 1,
                    "ir" => 0,
                    "hn" => 1,
                    "grtp" => 0,
                    "bf" => 0,
                    "et" => 0,
                    "np" => 0,
                    "as" => 1000,
                    "asc" => 1,
                    "std" => 0,
                    "hnp" => 0,
                    "ts" => 1,
                    "smpo" => 0,
                    "swf" => 0,
                    "sp" => 0,
                    "rcf" => 0,
                    "sbb" => 0,
                    "hwl" => 0,
                    "sfb" => 0
                ],
                "ec" => [
                    [
                        "n" => "c5869829a5",
                        "v" => "7",
                        "il" => 0,
                        "om" => 0,
                        "uie" => ["tpu" => "1"]
                    ]
                ],
                "occ" => [
                    "rurl" => "",
                    "tcm" => "",
                    "tsc" => 0,
                    "ttp" => 0,
                    "tlb" => "",
                    "trb" => ""
                ],
                "gcv" => "1.17.0.3",
                "ioph" => "322738b3ce47",
                "sdn" => "api"
            ],
            "err" => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function dispatch(): void
    {
        $uri = $_SERVER['REQUEST_URI'];
        $base = dirname($_SERVER['SCRIPT_NAME']);

        if (str_starts_with($uri, $_SERVER['SCRIPT_NAME'])) {
            $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
        } elseif (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '';

        if (empty($path) || $path === '/') {
            if (isset($_GET['url']))
                $this->proxyRequest($_GET['url']);
            return;
        }

        if (str_contains($path, '/verifyOperatorPlayerSession')) {
            $this->serveMockSession();
        } elseif (preg_match('/^\/\d+\/index\.html$/', $path)) {
            $this->proxyRequest($this->gameUrl . ltrim($uri, '/'));
        } elseif (str_starts_with($path, '/shared')) {
            $this->proxyRequest($this->staticUrl . $uri);
        } elseif (str_starts_with($path, '/web-lobby')) {
            $this->proxyRequest($this->lobbyUrl . $uri);
        } elseif (str_starts_with($path, '/public')) {
            $parts = explode('/public', $uri, 2);
            $this->proxyRequest($this->lobbyUrl . ($parts[1] ?: $uri));
        } elseif (str_starts_with($path, '/history')) {
            $this->proxyRequest($this->historyUrl . $uri);
        } else {
            $this->proxyRequest($this->staticUrl . $uri);
        }
    }
}
