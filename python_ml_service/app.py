"""
Flask REST API untuk ML service WMBusiness99 Medan.
"""
import os
from functools import wraps

from flask import Flask, request, jsonify
from dotenv import load_dotenv

from ml.pipeline import (
    parse_upload_bytes, evaluate_pipeline, summary_pipeline, latest_pipeline,
)
from ml.preprocess import preprocess_pipeline

load_dotenv()

app = Flask(__name__)

API_KEY = os.getenv("API_KEY", "wm99-secret-key-change-me")
PORT = int(os.getenv("PORT", 5001))


def require_api_key(f):
    @wraps(f)
    def wrapper(*args, **kwargs):
        if request.headers.get("X-API-Key") != API_KEY:
            return jsonify({"ok": False, "error": "Unauthorized"}), 401
        return f(*args, **kwargs)
    return wrapper


@app.route("/health")
def health():
    return jsonify({"status": "ok"})


@app.route("/api/parse-upload", methods=["POST"])
@require_api_key
def api_parse_upload():
    if "file" not in request.files:
        return jsonify({"ok": False, "error": "Field 'file' tidak ditemukan."}), 400
    f = request.files["file"]
    if not f.filename:
        return jsonify({"ok": False, "error": "Nama berkas kosong."}), 400
    result = parse_upload_bytes(f.read(), f.filename)
    return jsonify(result), (200 if result.get("ok") else 400)


@app.route("/api/preprocess", methods=["POST"])
@require_api_key
def api_preprocess():
    data = request.get_json(silent=True) or {}
    rows = data.get("rows", [])
    if not isinstance(rows, list):
        return jsonify({"ok": False, "error": "Field 'rows' harus array."}), 400
    result = preprocess_pipeline(rows)
    return jsonify(result), (200 if result.get("ok") else 400)


@app.route("/api/evaluate", methods=["POST"])
@require_api_key
def api_evaluate():
    data = request.get_json(silent=True) or {}
    rows = data.get("rows", [])
    if not isinstance(rows, list):
        return jsonify({"ok": False, "error": "Field 'rows' harus array."}), 400
    result = evaluate_pipeline(
        rows,
        skenario=data.get("skenario", "uji"),
        k=data.get("k"),
        produk=data.get("produk"),
    )
    return jsonify(result), (200 if result.get("ok") else 400)


@app.route("/api/summary", methods=["POST"])
@require_api_key
def api_summary():
    data = request.get_json(silent=True) or {}
    rows = data.get("rows", [])
    if not isinstance(rows, list):
        return jsonify({"ok": False, "error": "Field 'rows' harus array."}), 400
    result = summary_pipeline(rows)
    return jsonify(result), (200 if result.get("ok") else 400)


@app.route("/api/latest", methods=["POST"])
@require_api_key
def api_latest():
    data = request.get_json(silent=True) or {}
    rows = data.get("rows", [])
    if not isinstance(rows, list):
        return jsonify({"ok": False, "error": "Field 'rows' harus array."}), 400
    result = latest_pipeline(rows)
    return jsonify(result), (200 if result.get("ok") else 400)


if __name__ == "__main__":
    print(f"ML Service: http://127.0.0.1:{PORT}")
    app.run(host="127.0.0.1", port=PORT, debug=False)