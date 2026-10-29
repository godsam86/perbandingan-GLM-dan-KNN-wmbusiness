<?php
/**
 * Client HTTP ke Python ML Service.
 */
class MlClient
{
    private $baseUrl;
    private $apiKey;
    private $timeout;

    public function __construct($baseUrl = null, $apiKey = null, $timeout = null)
    {
        $this->baseUrl = $baseUrl ?? ML_BASE_URL;
        $this->apiKey  = $apiKey  ?? ML_API_KEY;
        $this->timeout = $timeout ?? ML_TIMEOUT;
    }

    public function health()
    {
        return $this->request('GET', '/health');
    }

    public function preprocess($rows)
    {
        return $this->request('POST', '/api/preprocess', ['rows' => $rows]);
    }

    public function evaluate($rows, $skenario = 'uji', $k = null, $produk = null)
    {
        return $this->request('POST', '/api/evaluate', [
            'rows'     => $rows,
            'skenario' => $skenario,
            'k'        => $k,
            'produk'   => $produk,
        ]);
    }

    public function summary($rows)
    {
        return $this->request('POST', '/api/summary', ['rows' => $rows]);
    }

    public function latest($rows)
    {
        return $this->request('POST', '/api/latest', ['rows' => $rows]);
    }

    public function parseUpload($filePath, $originalName)
    {
        $url = rtrim($this->baseUrl, '/') . '/api/parse-upload';
        $cfile = new CURLFile($filePath, mime_content_type($filePath), $originalName);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $cfile],
            CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => 'Layanan ML tidak aktif. Jalankan python app.py'];
        }
        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Respons ML tidak valid.'];
        }
        return $decoded;
    }

    private function request($method, $path, $payload = null)
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if ($method === 'POST' && $payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => 'Layanan ML tidak aktif. Jalankan python app.py'];
        }
        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Respons ML tidak valid.'];
        }
        return $decoded;
    }
}