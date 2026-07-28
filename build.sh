#!/bin/bash
set -euo pipefail

# ===== 8bit ビルド / デプロイ =====
# 参考: oxcel/build.sh + build-common version.txt 方式

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

APP_NAME="8bit"
COMMON="$SCRIPT_DIR/../build-common"

# shellcheck source=/dev/null
source "$COMMON/version.sh"
# shellcheck source=/dev/null
source "$COMMON/ftp-upload.sh"
# shellcheck source=/dev/null
source "$COMMON/git-commit.sh"

COMMIT_MSG=""
NO_VERUP=false
NO_UPLOAD=false
while [ $# -gt 0 ]; do
    case "$1" in
        -cm) shift; COMMIT_MSG="${1:-}" ;;
        -noverup) NO_VERUP=true ;;
        -noupload) NO_UPLOAD=true ;;
    esac
    shift || true
done

if [ -f "$HOME/.wp-env" ]; then
    # shellcheck source=/dev/null
    source "$HOME/.wp-env"
fi
if [ -f "$SCRIPT_DIR/.env" ]; then
    # shellcheck source=/dev/null
    source "$SCRIPT_DIR/.env"
fi

VERSION=$(version_read)
echo "📊 ${APP_NAME} v${VERSION} をデプロイ中..."

# index.html のバージョンコメントを同期（表示用メタ）
if [ -f index.html ]; then
    if grep -q 'meta name="app-version"' index.html; then
        python3 - << PY
from pathlib import Path
import re
version = "$VERSION"
p = Path("index.html")
t = p.read_text(encoding="utf-8")
t2, n = re.subn(
    r'<meta name="app-version" content="[^"]*">',
    f'<meta name="app-version" content="{version}">',
    t,
    count=1,
)
if n:
    p.write_text(t2, encoding="utf-8")
    print(f"  ✓ index.html の app-version を v{version} に更新")
PY
    else
        python3 - << PY
from pathlib import Path
version = "$VERSION"
p = Path("index.html")
t = p.read_text(encoding="utf-8")
needle = "<head>"
inject = f'<head>\n  <meta name="app-version" content="{version}">'
if needle in t:
    p.write_text(t.replace(needle, inject, 1), encoding="utf-8")
    print(f"  ✓ index.html に app-version v{version} を追加")
PY
    fi
fi

# 紹介ページ用 manifest.json（版表示）
python3 - << PY
import json
from pathlib import Path
version = "$VERSION"
path = Path("manifest.json")
data = {}
if path.is_file():
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        data = {}
if not isinstance(data, dict):
    data = {}
data["name"] = data.get("name") or "8BIT COIN HUNTER"
data["version"] = version
data["web_version"] = version
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(f"  ✓ manifest.json を v{version} に更新")
PY

if $NO_UPLOAD; then
    echo "  ℹ️  -noupload のため FTP をスキップ"
else
    # 公開物のみ（ユーザーDB・開発用は上げない）→ fuma.tomippe.jp
    ftp_upload_file "$SCRIPT_DIR/index.html" "8bit/index.html"
    ftp_upload_file "$SCRIPT_DIR/manifest.json" "8bit/manifest.json"
    for f in "$SCRIPT_DIR"/api/*.php; do
        [ -f "$f" ] || continue
        ftp_upload_file "$f" "8bit/api/$(basename "$f")"
    done
    if [ -f "$SCRIPT_DIR/data/.htaccess" ]; then
        ftp_upload_file "$SCRIPT_DIR/data/.htaccess" "8bit/data/.htaccess"
    fi

    # 紹介ページが読む manifest → apps.tomippe.jp/8bit/manifest.json
    APPS_SFTP="$(ftp_apps_sftp_json || true)"
    if [ -n "${APPS_SFTP:-}" ]; then
        echo ""
        echo "🌐 紹介ページ用 manifest を apps.tomippe.jp へ..."
        ftp_upload_file "$SCRIPT_DIR/manifest.json" "8bit/manifest.json" "$APPS_SFTP"
    else
        echo "  ⚠️  sftp-apps.json が無いため apps.tomippe.jp への manifest アップロードをスキップ"
    fi
fi

# 紹介ページのバージョン履歴
if [ -n "$COMMIT_MSG" ] || [ -n "${WP_APP_POST_ID:-}" ]; then
    echo ""
    echo "📝 WordPress app-versions を更新中..."
    python3 "$COMMON/scripts/wp-append-app-version.py" \
        --project-root "$SCRIPT_DIR" \
        --version "$VERSION" \
        --commit-msg "${COMMIT_MSG:-デプロイ}" \
        --skip-if-missing || true
fi

if ! $NO_VERUP; then
    echo ""
    echo "📝 次回用バージョンを更新しています..."
    version_save_next "$VERSION"
fi

git_commit_build "$VERSION" "$COMMIT_MSG"

echo ""
echo "🎉 ${APP_NAME} v${VERSION} — 完了"
echo "   公開: https://fuma.tomippe.jp/8bit/"
echo "   紹介: ${WP_APP_PAGE_URL:-https://apps.tomippe.jp/8bit/}"
echo "   manifest: https://apps.tomippe.jp/${APP_NAME}/manifest.json"
