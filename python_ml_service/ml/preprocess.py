"""
Preprocessing untuk dataset penjualan WMBusiness99 Medan.

Bertanggung jawab untuk:
- Normalisasi nama kolom (lowercase, spasi -> underscore)
- Parsing tanggal (auto-detect ISO / Indonesia), konversi harga ke numerik
- Standarisasi kategori dan nama produk
- Cleaning: buang baris kosong dan duplikat
- Pelabelan tren: Naik/Turun per produk (fallback kalau CSV tidak punya label)
- Split kronologis 80:20
"""
import re
import datetime
import numpy as np
import pandas as pd

# Kolom wajib untuk upload
REQUIRED_COLUMNS = ["tanggal", "nama_produk", "jumlah_terjual", "harga"]

# Mapping alias kolom (agar user boleh pakai nama berbeda)
COLUMN_ALIASES = {
    "tanggal":        ["tanggal", "tanggal_transaksi", "date", "tgl"],
    "nama_produk":    ["nama_produk", "produk", "nama_barang", "product", "item"],
    "kategori":       ["kategori", "category", "kategori_produk"],
    "jumlah_terjual": ["jumlah_terjual", "jumlah", "qty", "quantity", "terjual"],
    "harga":          ["harga", "harga_satuan", "price", "harga_jual"],
}


def normalize_column(name):
    """Lowercase, ganti spasi/tanda hubung jadi underscore, buang karakter aneh."""
    name = str(name).strip().lower()
    name = re.sub(r"[\s\-]+", "_", name)
    name = re.sub(r"[^a-z0-9_]", "", name)
    return name


def map_columns(df):
    """
    Rename kolom DataFrame supaya sesuai REQUIRED_COLUMNS.
    Robust terhadap tabrakan alias.
    """
    df = df.copy()
    original_cols = list(df.columns)
    normalized = [normalize_column(c) for c in original_cols]
    df.columns = normalized

    used_targets = set()
    rename_map = {}
    for target, aliases in COLUMN_ALIASES.items():
        for alias in aliases:
            if alias in normalized and target not in used_targets:
                rename_map[alias] = target
                used_targets.add(target)
                break

    df = df.rename(columns=rename_map)
    return df


def validate_columns(df):
    """Pastikan semua kolom wajib ada. Return list kolom yang hilang."""
    missing = [c for c in REQUIRED_COLUMNS if c not in df.columns]
    return missing


def parse_tanggal_smart(value):
    """
    Parse tanggal dengan deteksi format otomatis.
    Prioritas:
    1. Sudah Timestamp/date/datetime -> langsung
    2. ISO format (YYYY-MM-DD) -> paling tidak ambigu, coba dulu
    3. Format Indonesia (DD-MM-YYYY) -> dayfirst=True
    4. Format US (MM-DD-YYYY) -> dayfirst=False
    """
    if value is None or (isinstance(value, float) and pd.isna(value)):
        return pd.NaT
    if isinstance(value, pd.Timestamp):
        return value
    if isinstance(value, (datetime.datetime, datetime.date)):
        return pd.Timestamp(value)

    s = str(value).strip()
    if not s:
        return pd.NaT

    # 1. Coba ISO dulu
    iso_formats = (
        "%Y-%m-%d",
        "%Y/%m/%d",
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%dT%H:%M:%S",
        "%Y%m%d",
    )
    for fmt in iso_formats:
        try:
            return pd.Timestamp(datetime.datetime.strptime(s, fmt))
        except (ValueError, TypeError):
            continue

    # 2. Fallback: coba dayfirst=True (format Indonesia DD-MM-YYYY)
    try:
        return pd.to_datetime(s, dayfirst=True, errors="raise")
    except (ValueError, TypeError):
        pass

    # 3. Fallback terakhir: coba dayfirst=False (format US MM-DD-YYYY)
    try:
        return pd.to_datetime(s, dayfirst=False, errors="raise")
    except (ValueError, TypeError):
        return pd.NaT


def parse_harga(value):
    """
    Konversi nilai harga ke float.
    Mendukung: 15500, "15.500", "15,500", "Rp 15.500", "15500.0".
    """
    if value is None:
        return np.nan
    if isinstance(value, float) and pd.isna(value):
        return np.nan
    if isinstance(value, (int, float, np.integer, np.floating)):
        return float(value)
    s = str(value).strip()
    if not s:
        return np.nan
    s = re.sub(r"(?i)^(rp\.?|idr)\s*", "", s)
    s = s.replace(" ", "")
    # Format Indonesia: titik ribuan + koma desimal
    if "." in s and "," in s:
        s = s.replace(".", "").replace(",", ".")
    elif "." in s:
        parts = s.split(".")
        if len(parts) > 2 or (len(parts) == 2 and len(parts[1]) == 3):
            s = s.replace(".", "")
    elif "," in s:
        s = s.replace(",", ".")
    try:
        return float(s)
    except (ValueError, TypeError):
        return np.nan


def standardize_kategori(value):
    if pd.isna(value):
        return ""
    return str(value).strip().title()


def standardize_produk(value):
    if pd.isna(value):
        return ""
    s = re.sub(r"\s+", " ", str(value).strip())
    return s


def clean_dataframe(df):
    """
    Cleaning lengkap:
    - Map kolom
    - Validasi kolom wajib
    - Parse tanggal (auto) & harga
    - Standarisasi kategori & produk
    - Buang baris kosong & duplikat
    Return dict dengan dataframe bersih + statistik.
    """
    df = df.copy()
    df = map_columns(df)

    missing = validate_columns(df)
    if missing:
        return {
            "ok": False,
            "error": f"Kolom wajib tidak ditemukan: {', '.join(missing)}",
        }

    n_raw = len(df)

    # Standarisasi string
    df["nama_produk"] = df["nama_produk"].apply(standardize_produk)
    if "kategori" in df.columns:
        df["kategori"] = df["kategori"].apply(standardize_kategori)
    else:
        df["kategori"] = ""

    # Parse tanggal dengan deteksi otomatis
    df["tanggal"] = df["tanggal"].apply(parse_tanggal_smart)

    # Parse harga
    df["harga"] = df["harga"].apply(parse_harga)

    # Parse jumlah terjual
    df["jumlah_terjual"] = pd.to_numeric(df["jumlah_terjual"], errors="coerce")

    # Buang baris dengan nilai kosong pada kolom krusial
    mask_valid = (
        df["tanggal"].notna()
        & df["nama_produk"].ne("")
        & df["jumlah_terjual"].notna()
        & df["harga"].notna()
    )
    n_kosong = int((~mask_valid).sum())
    df = df[mask_valid].copy()

    # Buang duplikat
    before_dedup = len(df)
    df = df.drop_duplicates(
        subset=["tanggal", "nama_produk", "jumlah_terjual", "harga"], keep="first"
    )
    n_duplikat = before_dedup - len(df)

    # Urutkan stabil: produk, lalu tanggal
    df = df.sort_values(["nama_produk", "tanggal"], kind="stable").reset_index(drop=True)

    return {
        "ok": True,
        "df": df,
        "n_raw": n_raw,
        "n_kosong": n_kosong,
        "n_duplikat": n_duplikat,
        "n_final": len(df),
    }


def label_tren(df):
    """
    Beri label tren per produk (fallback kalau CSV tidak punya label).
    Naik jika jumlah_terjual > transaksi sebelumnya (produk sama), selain itu Turun.
    Transaksi pertama tiap produk -> label None.
    """
    df = df.copy()
    df["label_tren"] = None

    for produk, group in df.groupby("nama_produk", sort=False):
        idx = group.index.tolist()
        prev_jumlah = None
        for i in idx:
            jumlah = df.at[i, "jumlah_terjual"]
            if prev_jumlah is None:
                df.at[i, "label_tren"] = None
            elif jumlah > prev_jumlah:
                df.at[i, "label_tren"] = "Naik"
            else:
                df.at[i, "label_tren"] = "Turun"
            prev_jumlah = jumlah

    return df


def split_kronologis(df, ratio=0.8):
    """
    Split kronologis 80:20 berdasarkan urutan waktu global.
    Hanya menggunakan baris yang punya label_tren.
    """
    df_labeled = df[df["label_tren"].notna()].copy()
    df_labeled = df_labeled.sort_values("tanggal", kind="stable").reset_index(drop=True)

    n = len(df_labeled)
    if n < 2:
        return df_labeled, df_labeled.iloc[0:0].copy()

    n_latih = max(1, min(int(round(n * ratio)), n - 1))

    df_latih = df_labeled.iloc[:n_latih].copy()
    df_uji = df_labeled.iloc[n_latih:].copy()

    return df_latih, df_uji


def preprocess_pipeline(rows):
    """
    Entry point utama untuk /api/preprocess.
    Input: list of dict {id, tanggal, produk, kategori, jumlah, harga, label}
    Output: dict sesuai kontrak API.

    PRIORITAS:
    1. Pakai kolom 'label' dari input (CSV/DB) kalau ada — semua 800 baris berlabel
    2. Fallback hitung ulang dengan label_tren() kalau tidak ada
    """
    if not rows:
        return {"ok": False, "error": "Data kosong."}

    df = pd.DataFrame(rows)
    df = df.rename(columns={
        "produk": "nama_produk",
        "jumlah": "jumlah_terjual",
    })

    cleaned = clean_dataframe(df)
    if not cleaned["ok"]:
        return cleaned

    df_clean = cleaned["df"]

    # ============================================================
    # PRIORITAS 1: Pakai label dari input (CSV/DB) kalau ada
    # ============================================================
    if "label" in df_clean.columns:
        df_labeled = df_clean.copy()
        df_labeled["label_tren"] = (
            df_labeled["label"].astype(str).str.strip().str.title()
        )
        # Bersihkan nilai yang bukan Naik/Turun
        df_labeled.loc[
            ~df_labeled["label_tren"].isin(["Naik", "Turun"]), "label_tren"
        ] = None

        # Hitung ulang untuk baris yang masih kosong (kalau ada)
        if df_labeled["label_tren"].isna().any():
            df_recalc = label_tren(df_clean)
            df_labeled.loc[
                df_labeled["label_tren"].isna(), "label_tren"
            ] = df_recalc.loc[df_labeled["label_tren"].isna(), "label_tren"]
    else:
        # PRIORITAS 2: hitung ulang
        df_labeled = label_tren(df_clean)

    # Buang baris tanpa label (kalau masih ada)
    df_labeled = df_labeled[df_labeled["label_tren"].notna()].reset_index(drop=True)

    n_naik = int((df_labeled["label_tren"] == "Naik").sum())
    n_turun = int((df_labeled["label_tren"] == "Turun").sum())

    df_latih, df_uji = split_kronologis(df_labeled)

    # Monthly aggregation untuk grafik tren
    df_labeled["bulan"] = df_labeled["tanggal"].dt.strftime("%Y-%m")
    monthly = (
        df_labeled.groupby("bulan")["jumlah_terjual"]
        .sum()
        .reset_index()
        .rename(columns={"jumlah_terjual": "total"})
        .to_dict(orient="records")
    )

    # Labels untuk dikembalikan ke PHP
    labels = []
    for _, row in df_labeled.iterrows():
        labels.append({
            "id": int(row["id"]) if "id" in row.index and pd.notna(row["id"]) else None,
            "label": row["label_tren"] if pd.notna(row["label_tren"]) else None,
        })

    n_produk = int(df_labeled["nama_produk"].nunique())

    return {
        "ok": True,
        "n_raw": cleaned["n_raw"],
        "n_kosong": cleaned["n_kosong"],
        "n_duplikat": cleaned["n_duplikat"],
        "n_final": cleaned["n_final"],
        "n_produk": n_produk,
        "n_naik": n_naik,
        "n_turun": n_turun,
        "n_latih": len(df_latih),
        "n_uji": len(df_uji),
        "labels": labels,
        "monthly": monthly,
    }