"""
Pipeline utama ML service.
"""
import io
import numpy as np
import pandas as pd

from .preprocess import (
    clean_dataframe, label_tren, split_kronologis, validate_columns,
    parse_harga, parse_tanggal_smart, map_columns, standardize_kategori,
)
from .glm import fit_glm, predict_glm
from .knn import fit_scaler, transform, predict_knn
from .metrics import compute_metrics

MAX_ROWS = 20000
MAX_UPLOAD_BYTES = 5 * 1024 * 1024


# ============================================================
# PARSE UPLOAD
# ============================================================
def parse_upload_bytes(file_bytes, filename):
    """
    Parse file CSV atau XLSX menjadi list of dict.
    Return: {"ok": True, "filename", "n_baris", "rows": [...]} atau {"ok": False, "error"}
    """
    if not file_bytes:
        return {"ok": False, "error": "Berkas kosong."}

    if len(file_bytes) > MAX_UPLOAD_BYTES:
        return {"ok": False, "error": "Ukuran berkas melebihi 5 MB."}

    ext = filename.lower().rsplit(".", 1)[-1] if "." in filename else ""
    if ext not in ("csv", "xlsx", "xls"):
        return {"ok": False, "error": "Format berkas harus CSV atau XLSX."}

    try:
        if ext == "csv":
            for enc in ("utf-8", "utf-8-sig", "latin-1"):
                try:
                    df = pd.read_csv(io.BytesIO(file_bytes), encoding=enc)
                    break
                except UnicodeDecodeError:
                    continue
            else:
                return {"ok": False, "error": "Gagal membaca CSV (encoding tidak dikenali)."}
        else:
            df = pd.read_excel(io.BytesIO(file_bytes), engine="openpyxl")
    except Exception as e:
        return {"ok": False, "error": f"Gagal membaca berkas: {e}"}

    if len(df) == 0:
        return {"ok": False, "error": "Berkas tidak berisi data."}

    if len(df) > MAX_ROWS:
        return {"ok": False, "error": f"Jumlah baris melebihi batas {MAX_ROWS}."}

    # Normalisasi nama kolom & validasi
    df = map_columns(df)
    missing = validate_columns(df)
    if missing:
        return {
            "ok": False,
            "error": f"Kolom wajib tidak ditemukan: {', '.join(missing)}. "
                     f"Kolom wajib: tanggal, nama_produk, jumlah_terjual, harga.",
        }

    rows = []
    for _, row in df.iterrows():
        # Parse tanggal
        tgl = parse_tanggal_smart(row.get("tanggal"))
        if pd.isna(tgl):
            continue
        tgl_str = tgl.strftime("%Y-%m-%d")

        # Parse harga
        harga = parse_harga(row.get("harga"))
        if pd.isna(harga):
            continue

        # Parse jumlah
        try:
            jumlah = int(float(row.get("jumlah_terjual")))
        except (ValueError, TypeError):
            continue

        # Ambil id_produk jika ada
        id_produk = None
        if "id_produk" in df.columns:
            v = row.get("id_produk")
            if pd.notna(v):
                try:
                    id_produk = int(float(v))
                except (ValueError, TypeError):
                    id_produk = None

        # Ambil kategori jika ada (opsional)
        kategori = ""
        if "kategori" in df.columns:
            v = row.get("kategori")
            if pd.notna(v):
                kategori = standardize_kategori(v)

        # Ambil label_tren jika ada (opsional)
        label_tren_val = None
        if "label_tren" in df.columns:
            v = row.get("label_tren")
            if pd.notna(v):
                v = str(v).strip().title()
                if v in ("Naik", "Turun"):
                    label_tren_val = v

        rows.append({
            "tanggal": tgl_str,
            "id_produk": id_produk,
            "nama_produk": str(row.get("nama_produk", "")).strip(),
            "kategori": kategori,
            "jumlah_terjual": jumlah,
            "harga": float(harga),
            "label_tren": label_tren_val,
        })

    if not rows:
        return {"ok": False, "error": "Tidak ada baris valid setelah parsing."}

    return {
        "ok": True,
        "filename": filename,
        "n_baris": len(rows),
        "rows": rows,
    }


# ============================================================
# HELPER: Bangun DataFrame dengan label
# ============================================================
def _build_dataframe(rows, with_label=True):
    """
    Bangun DataFrame dari list of dict.
    Kolom input: id, tanggal, produk, kategori, jumlah, harga, label (opsional)

    PRIORITAS label:
    1. Pakai kolom 'label' dari input kalau ada (semua 800 baris berlabel)
    2. Fallback hitung ulang dengan label_tren()
    """
    df = pd.DataFrame(rows)
    df = df.rename(columns={"produk": "nama_produk", "jumlah": "jumlah_terjual"})

    df["tanggal"] = df["tanggal"].apply(parse_tanggal_smart)
    df["nama_produk"] = df["nama_produk"].astype(str).str.strip()
    df["jumlah_terjual"] = pd.to_numeric(df["jumlah_terjual"], errors="coerce")
    df["harga"] = df["harga"].apply(parse_harga)

    df = df.dropna(subset=["tanggal", "jumlah_terjual", "harga"]).reset_index(drop=True)

    if with_label:
        # PRIORITAS 1: pakai label dari input kalau ada
        if "label" in df.columns:
            df["label_tren"] = df["label"].astype(str).str.strip().str.title()
            df.loc[~df["label_tren"].isin(["Naik", "Turun"]), "label_tren"] = None

        # Kalau tidak ada label sama sekali, hitung ulang
        if "label_tren" not in df.columns or df["label_tren"].isna().all():
            df["label_tren"] = label_tren(df)["label_tren"]

        # Kalau sebagian kosong, hitung ulang untuk baris yang kosong
        if df["label_tren"].isna().any():
            df_recalc = label_tren(df)
            df.loc[df["label_tren"].isna(), "label_tren"] = \
                df_recalc.loc[df["label_tren"].isna(), "label_tren"]

        # Buang baris tanpa label (kalau masih ada)
        df = df[df["label_tren"].notna()].reset_index(drop=True)
        df["y"] = (df["label_tren"] == "Naik").astype(int)
    else:
        df["label_tren"] = label_tren(df)["label_tren"]
        df["y"] = (df["label_tren"] == "Naik").astype(int)

    return df


# ============================================================
# HELPER: Split skenario
# ============================================================
def _split_scenario(df, skenario):
    """
    Return (df_latih, df_uji) sesuai skenario.
    - "semua": latih = uji = df (in-sample)
    - "latih": latih = uji = 80% awal (in-sample)
    - "uji"  : latih = 80% awal, uji = 20% akhir (out-of-sample)
    """
    df_sorted = df.sort_values("tanggal", kind="stable").reset_index(drop=True)
    n = len(df_sorted)
    n_latih = max(1, min(int(round(n * 0.8)), n - 1)) if n > 1 else n

    if skenario == "semua":
        return df_sorted, df_sorted
    elif skenario == "latih":
        df_latih = df_sorted.iloc[:n_latih].copy()
        return df_latih, df_latih
    elif skenario == "uji":
        return df_sorted.iloc[:n_latih].copy(), df_sorted.iloc[n_latih:].copy()
    else:
        raise ValueError(f"Skenario tidak dikenal: {skenario}")


# ============================================================
# EVALUASI GLM
# ============================================================
def _evaluate_glm(df_latih, df_uji):
    X1_train = df_latih["jumlah_terjual"].values
    X2_train = df_latih["harga"].values
    y_train = df_latih["y"].values

    model = fit_glm(X1_train, X2_train, y_train)

    X1_test = df_uji["jumlah_terjual"].values
    X2_test = df_uji["harga"].values
    y_test = df_uji["y"].values

    pred_out = predict_glm(X1_test, X2_test, model["b0"], model["b1"], model["b2"])
    metrics = compute_metrics(y_test, pred_out["pred"])

    # Sampel untuk UI (maks 10 baris)
    n_sampel = min(10, len(df_uji))
    sampel = []
    for i in range(n_sampel):
        row = df_uji.iloc[i]
        sampel.append({
            "no": int(row.get("id", i + 1)) if "id" in row else i + 1,
            "produk": str(row["nama_produk"]),
            "x1": int(row["jumlah_terjual"]),
            "x2": float(row["harga"]),
            "p_naik": round(float(pred_out["p"][i]), 4),
            "prediksi": "Naik" if pred_out["pred"][i] == 1 else "Turun",
            "confidence": round(float(pred_out["confidence"][i]), 4),
            "aktual": "Naik" if y_test[i] == 1 else "Turun",
        })

    return {
        "koefisien": {
            "b0": round(model["b0"], 10),
            "b1": round(model["b1"], 10),
            "b2": round(model["b2"], 10),
        },
        "n_iter": model["n_iter"],
        "converged": model["converged"],
        "metrik": {
            "accuracy": metrics["accuracy"],
            "precision": metrics["precision"],
            "recall": metrics["recall"],
            "f1": metrics["f1"],
            "error_rate": metrics["error_rate"],
        },
        "confusion": metrics["confusion"],
        "sampel": sampel,
    }


# ============================================================
# EVALUASI KNN
# ============================================================
def _evaluate_knn(df_latih, df_uji, k_values=(3, 5, 7)):
    X_train = df_latih[["jumlah_terjual", "harga"]].values.astype(float)
    y_train = df_latih["y"].values
    X_test = df_uji[["jumlah_terjual", "harga"]].values.astype(float)
    y_test = df_uji["y"].values

    scaler = fit_scaler(X_train)
    X_train_s = transform(scaler, X_train)
    X_test_s = transform(scaler, X_test)

    per_k = {}
    best_k = None
    best_f1 = -1.0

    for k in k_values:
        res = predict_knn(X_train_s, y_train, X_test_s, k)
        metrics = compute_metrics(y_test, res["pred"])
        per_k[str(k)] = {
            "metrik": {
                "accuracy": metrics["accuracy"],
                "precision": metrics["precision"],
                "recall": metrics["recall"],
                "f1": metrics["f1"],
                "error_rate": metrics["error_rate"],
            },
            "confusion": metrics["confusion"],
        }
        if metrics["f1"] > best_f1:
            best_f1 = metrics["f1"]
            best_k = k

    # Contoh detail tetangga: baris uji terakhir
    contoh = None
    if len(df_uji) > 0:
        last_i = len(df_uji) - 1
        res_last = predict_knn(X_train_s, y_train, X_test_s[last_i:last_i + 1], best_k)
        idxs = res_last["neighbors_idx"][0]
        dists = res_last["distances"][0]
        tetangga = []
        for urut, (idx, d) in enumerate(zip(idxs, dists), start=1):
            row = df_latih.iloc[idx]
            tetangga.append({
                "urutan": urut,
                "no": int(row.get("id", idx + 1)) if "id" in row else idx + 1,
                "produk": str(row["nama_produk"]),
                "jarak": round(float(d), 4),
                "label": str(row["label_tren"]),
            })
        row_last = df_uji.iloc[last_i]
        contoh = {
            "no": int(row_last.get("id", last_i + 1)) if "id" in row_last else last_i + 1,
            "produk": str(row_last["nama_produk"]),
            "x1": int(row_last["jumlah_terjual"]),
            "x2": float(row_last["harga"]),
            "x1_scaled": round(float(X_test_s[last_i][0]), 4),
            "x2_scaled": round(float(X_test_s[last_i][1]), 4),
            "k": best_k,
            "tetangga": tetangga,
            "prediksi": "Naik" if res_last["pred"][0] == 1 else "Turun",
            "suara": res_last["votes"][0],
            "aktual": "Naik" if y_test[last_i] == 1 else "Turun",
        }

    return {"per_k": per_k, "k_terbaik": best_k, "contoh": contoh}


# ============================================================
# EVALUATE PIPELINE
# ============================================================
def evaluate_pipeline(rows, skenario="uji", k=None, produk=None):
    """
    Entry point /api/evaluate.
    rows: list of dict {id, tanggal, produk, kategori, jumlah, harga, label}
    """
    if not rows:
        return {"ok": False, "error": "Data kosong."}
    if skenario not in ("semua", "latih", "uji"):
        return {"ok": False, "error": "Skenario harus 'semua', 'latih', atau 'uji'."}
    if len(rows) > MAX_ROWS:
        return {"ok": False, "error": f"Jumlah baris melebihi batas {MAX_ROWS}."}

    df = _build_dataframe(rows, with_label=True)

    # Filter produk jika diminta
    if produk:
        df = df[df["nama_produk"] == produk].reset_index(drop=True)
        if len(df) == 0:
            return {"ok": False, "error": f"Produk '{produk}' tidak ditemukan."}

    df_latih, df_uji = _split_scenario(df, skenario)

    if len(df_latih) == 0 or len(df_uji) == 0:
        return {"ok": False, "error": "Data latih atau uji kosong setelah split."}

    glm_result = _evaluate_glm(df_latih, df_uji)
    knn_result = _evaluate_knn(df_latih, df_uji)

    # Tentukan model terbaik
    glm_f1 = glm_result["metrik"]["f1"]
    knn_best_k = knn_result["k_terbaik"]
    knn_f1 = knn_result["per_k"][str(knn_best_k)]["metrik"]["f1"]

    if glm_f1 >= knn_f1:
        model_terbaik = "GLM"
        f1_terbaik = glm_f1
    else:
        model_terbaik = f"KNN (K={knn_best_k})"
        f1_terbaik = knn_f1

    return {
        "ok": True,
        "skenario": skenario,
        "n_latih": len(df_latih),
        "n_uji": len(df_uji),
        "glm": glm_result,
        "knn": knn_result,
        "model_terbaik": model_terbaik,
        "f1_terbaik": f1_terbaik,
    }


# ============================================================
# SUMMARY PIPELINE
# ============================================================
def summary_pipeline(rows):
    """
    Ringkasan 3 skenario untuk Dashboard & Perbandingan Model.
    Model terbaik global ditentukan dari skenario UJI (out-of-sample).
    """
    if not rows:
        return {"ok": False, "error": "Data kosong."}
    if len(rows) > MAX_ROWS:
        return {"ok": False, "error": f"Melebihi batas {MAX_ROWS}."}

    df = _build_dataframe(rows, with_label=True)

    hasil = {}
    for skenario in ("semua", "latih", "uji"):
        df_latih, df_uji = _split_scenario(df, skenario)
        if len(df_latih) == 0 or len(df_uji) == 0:
            continue

        glm_res = _evaluate_glm(df_latih, df_uji)
        knn_res = _evaluate_knn(df_latih, df_uji)

        glm_f1 = glm_res["metrik"]["f1"]
        knn_best_k = knn_res["k_terbaik"]
        knn_f1 = knn_res["per_k"][str(knn_best_k)]["metrik"]["f1"]

        model_terbaik = "GLM" if glm_f1 >= knn_f1 else f"KNN (K={knn_best_k})"
        f1_terbaik = max(glm_f1, knn_f1)

        hasil[skenario] = {
            "n_latih": len(df_latih),
            "n_uji": len(df_uji),
            "glm": glm_res["metrik"],
            "knn": knn_res["per_k"][str(knn_best_k)]["metrik"],
            "knn_k_terbaik": knn_best_k,
            "model_terbaik": model_terbaik,
            "f1_terbaik": f1_terbaik,
        }

    # Model terbaik GLOBAL — dari skenario uji
    if "uji" in hasil:
        model_terbaik_global = hasil["uji"]["model_terbaik"]
        f1_terbaik_global = hasil["uji"]["f1_terbaik"]

        n_glm_unggul = sum(1 for s in hasil.values() if s["model_terbaik"] == "GLM")
        n_knn_unggul = sum(1 for s in hasil.values() if s["model_terbaik"] != "GLM")
    else:
        model_terbaik_global = None
        f1_terbaik_global = None
        n_glm_unggul = 0
        n_knn_unggul = 0

    return {
        "ok": True,
        "skenario": hasil,
        "model_terbaik_global": model_terbaik_global,
        "f1_terbaik_global": f1_terbaik_global,
        "n_glm_unggul": n_glm_unggul,
        "n_knn_unggul": n_knn_unggul,
    }


# ============================================================
# LATEST PIPELINE
# ============================================================
def latest_pipeline(rows):
    """
    Prediksi transaksi terakhir tiap produk memakai model terbaik pada skenario uji.
    Return: {model, k, f1, accuracy, metrik_glm, metrik_knn,
             items: [{produk, kategori, tanggal, p_naik, tren}]}
    """
    if not rows:
        return {"ok": False, "error": "Data kosong."}
    if len(rows) > MAX_ROWS:
        return {"ok": False, "error": f"Melebihi batas {MAX_ROWS}."}

    df = _build_dataframe(rows, with_label=True)

    if len(df) < 5:
        return {"ok": False, "error": "Data terlalu sedikit (minimal 5 baris)."}

    df_latih, df_uji = _split_scenario(df, "uji")

    glm_res = _evaluate_glm(df_latih, df_uji)
    knn_res = _evaluate_knn(df_latih, df_uji)

    glm_f1 = glm_res["metrik"]["f1"]
    knn_best_k = knn_res["k_terbaik"]
    knn_f1 = knn_res["per_k"][str(knn_best_k)]["metrik"]["f1"]

    if glm_f1 >= knn_f1:
        model = "GLM"
        f1 = glm_f1
        accuracy = glm_res["metrik"]["accuracy"]
    else:
        model = f"KNN (K={knn_best_k})"
        f1 = knn_f1
        accuracy = knn_res["per_k"][str(knn_best_k)]["metrik"]["accuracy"]

    # Prediksi transaksi terakhir tiap produk
    items = []
    for produk, group in df.groupby("nama_produk", sort=False):
        group_sorted = group.sort_values("tanggal", kind="stable")
        last = group_sorted.iloc[-1]

        x1 = float(last["jumlah_terjual"])
        x2 = float(last["harga"])

        if model == "GLM":
            out = predict_glm(
                np.array([x1]), np.array([x2]),
                glm_res["koefisien"]["b0"],
                glm_res["koefisien"]["b1"],
                glm_res["koefisien"]["b2"],
            )
            p_naik = float(out["p"][0])
            tren = "Naik" if out["pred"][0] == 1 else "Turun"
        else:
            # KNN: fit scaler pada seluruh data lalu prediksi baris terakhir
            X_all = df[["jumlah_terjual", "harga"]].values.astype(float)
            y_all = df["y"].values
            scaler = fit_scaler(X_all)
            X_all_s = transform(scaler, X_all)
            res = predict_knn(X_all_s, y_all, X_all_s[-1:], knn_best_k)
            p_naik = float(res["votes"][0]["naik"]) / knn_best_k
            tren = "Naik" if res["pred"][0] == 1 else "Turun"

        items.append({
            "produk": str(last["nama_produk"]),
            "kategori": str(last.get("kategori", "")),
            "tanggal": last["tanggal"].strftime("%Y-%m-%d"),
            "p_naik": round(p_naik, 4),
            "tren": tren,
        })

    return {
        "ok": True,
        "model": model,
        "k": knn_best_k if model != "GLM" else None,
        "f1": f1,
        "accuracy": accuracy,
        "metrik_glm": glm_res["metrik"],
        "metrik_knn": knn_res["per_k"][str(knn_best_k)]["metrik"],
        "items": items,
    }