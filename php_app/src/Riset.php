<?php
/**
 * Helper untuk baca/tulis tb_riset.
 * Semua hasil proses disimpan sebagai JSON di kolom data_json.
 */
class Riset
{
    /**
     * Simpan hasil ke tb_riset.
     * Return: id_riset (int)
     */
    public static function simpan($tipe, $judul, $data, $deskripsi = null, $jumlah = null)
    {
        $jumlah = $jumlah ?? (is_array($data) && isset($data['n_final']) ? $data['n_final'] : 0);

        return Db::insert(
            'INSERT INTO tb_riset
             (judul, deskripsi, tipe_riset, data_json, jumlah_data, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $judul,
                $deskripsi,
                $tipe,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                (int) $jumlah,
                'completed',
                $_SESSION['id_user'] ?? null,
            ]
        );
    }

    /**
     * Ambil riset terbaru berdasarkan tipe (exact match).
     * Return: array|null (data_json sudah didecode)
     */
    public static function terbaru($tipe)
    {
        $row = Db::fetchOne(
            'SELECT * FROM tb_riset
             WHERE tipe_riset = ?
             ORDER BY id_riset DESC LIMIT 1',
            [$tipe]
        );

        if (!$row) return null;

        $row['data'] = json_decode($row['data_json'], true);
        return $row;
    }

    /**
     * Ambil riset terbaru berdasarkan prefix tipe (LIKE).
     * Return: array|null
     */
    public static function terbaruPrefix($prefix)
    {
        $row = Db::fetchOne(
            'SELECT * FROM tb_riset
             WHERE tipe_riset LIKE ?
             ORDER BY id_riset DESC LIMIT 1',
            [$prefix . '%']
        );

        if (!$row) return null;

        $row['data'] = json_decode($row['data_json'], true);
        return $row;
    }

    /**
     * Ambil beberapa riset terbaru (untuk daftar).
     * Return: array
     */
    public static function daftar($limit = 20, $tipe = null)
    {
        if ($tipe) {
            return Db::fetchAll(
                'SELECT * FROM tb_riset
                 WHERE tipe_riset LIKE ?
                 ORDER BY id_riset DESC LIMIT ?',
                [$tipe . '%', (int) $limit]
            );
        }
        return Db::fetchAll(
            'SELECT * FROM tb_riset ORDER BY id_riset DESC LIMIT ?',
            [(int) $limit]
        );
    }

    /**
     * Simpan summary (untuk Dashboard & Perbandingan).
     */
    public static function simpanSummary($summary)
    {
        return self::simpan(
            'summary',
            'Ringkasan ' . (isset($summary['skenario']) ? count($summary['skenario']) : 0) . ' skenario',
            $summary,
            'Summary dari /api/summary',
            0
        );
    }

    /**
     * Simpan latest (untuk Hasil & Rekomendasi).
     */
    public static function simpanLatest($latest)
    {
        return self::simpan(
            'latest',
            'Prediksi terakhir (' . ($latest['model'] ?? '?') . ')',
            $latest,
            'Latest dari /api/latest',
            count($latest['items'] ?? [])
        );
    }

    /**
     * Ambil summary terbaru.
     */
    public static function getSummary()
    {
        $r = self::terbaru('summary');
        return $r ? $r['data'] : null;
    }

    /**
     * Ambil latest terbaru.
     */
    public static function getLatest()
    {
        $r = self::terbaru('latest');
        return $r ? $r['data'] : null;
    }
}