#!/bin/bash
# 8bit 紹介ページを WordPress（jonorotz）で作成・更新
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1090
source "$HOME/.wp-env"

if [ -z "${WP_APP_PASSWORD:-}" ]; then
  echo "❌ ~/.wp-env の WP_APP_PASSWORD が空です。記入してから再実行してください。"
  exit 1
fi

SLUG="8bit"
TITLE="8BIT COIN HUNTER"
APP_URL="https://fuma.tomippe.jp/8bit/"
PAGE_URL="https://apps.tomippe.jp/8bit/"

if [ -f .env ] && grep -q '^WP_APP_POST_ID=' .env 2>/dev/null; then
  # shellcheck disable=SC1091
  source .env
  POST_ID="$WP_APP_POST_ID"
  echo "📝 既存ページを更新 ID=$POST_ID"
else
  echo "📝 新規 app 投稿を作成..."
  python3 - <<PY
import json
open("/tmp/8bit-wp-create.json","w",encoding="utf-8").write(json.dumps({
    "title": "$TITLE",
    "slug": "$SLUG",
    "content": "<!-- setup -->",
    "status": "publish",
}, ensure_ascii=False))
PY
  CREATE=$(curl -sS -X POST -u "$WP_USER:$WP_APP_PASSWORD" \
    -H "Content-Type: application/json; charset=utf-8" \
    --data-binary @/tmp/8bit-wp-create.json \
    "$WP_SITE_URL/wp-json/wp/v2/${WP_APP_POST_TYPE:-app}")
  POST_ID=$(python3 -c "import json,sys; d=json.load(sys.stdin); assert 'id' in d, d; print(d['id'])" <<< "$CREATE")
  cat > .env << ENV
# 8bit 固有情報（WordPress 連携）
WP_APP_POST_ID=$POST_ID
WP_APP_PAGE_URL=$PAGE_URL
ENV
  echo "✅ .env 作成 POST_ID=$POST_ID"
fi

# shellcheck disable=SC1091
source .env

ICON="$ROOT/marketing/icon.png"
KV="$ROOT/marketing/kv-bg.png"
ICON_ID=""
KV_ID=""

if [ -f "$ICON" ]; then
  echo "📤 アイコンをアップロード..."
  ICON_JSON=$(curl -sS -X POST -u "$WP_USER:$WP_APP_PASSWORD" \
    -F "file=@$ICON" -F "title=$TITLE icon" \
    "$WP_SITE_URL/wp-json/wp/v2/media")
  ICON_ID=$(python3 -c "import json,sys; print(json.load(sys.stdin)['id'])" <<< "$ICON_JSON")
fi

if [ -f "$KV" ]; then
  echo "📤 KV背景をアップロード..."
  KV_JSON=$(curl -sS -X POST -u "$WP_USER:$WP_APP_PASSWORD" \
    -F "file=@$KV" -F "title=$TITLE KV" \
    "$WP_SITE_URL/wp-json/wp/v2/media")
  KV_ID=$(python3 -c "import json,sys; print(json.load(sys.stdin)['id'])" <<< "$KV_JSON")
elif [ -n "$ICON_ID" ]; then
  KV_ID="$ICON_ID"
fi

TODAY=$(date +%Y.%m.%d)
python3 << PY
import json
acf = {
    "app-cp": "レトロな画面でコインを集めろ\n8ビットのシンプルアクション",
    "platform": ["web"],
    "app-weburl": "$APP_URL",
    "app-webdesc": "Web<br>日本語",
    "app-keycolor": "#39ff14",
    "app-kvbgaddcss": "background-repeat: no-repeat;\nbackground-position: center;\nbackground-size: cover;\nbackground-blend-mode: screen;",
    "app-versions": "<table>\n<tbody>\n<tr><td>$TODAY</td><td>v1.0.0</td><td>紹介ページ作成</td></tr>\n</tbody>\n</table>",
}
icon = "$ICON_ID".strip()
kv = "$KV_ID".strip()
if icon:
    acf["app-icon"] = int(icon)
if kv:
    acf["app-kvbg"] = int(kv)
payload = {
    "content": "<p>キーボードで操作する、レトロ風のコイン集めアクションゲームです。敵を避けながらコインを集め、ハイスコアを狙いましょう。アカウントを作ればスコアをサーバーに保存できます。</p>\n\n<p>ブラウザですぐ遊べます。インストール不要。</p>",
    "acf": acf,
}
open("/tmp/8bit-wp.json", "w", encoding="utf-8").write(json.dumps(payload, ensure_ascii=False))
PY

curl -sS -X POST -u "$WP_USER:$WP_APP_PASSWORD" \
  -H "Content-Type: application/json; charset=utf-8" \
  --data-binary @/tmp/8bit-wp.json \
  "$WP_SITE_URL/wp-json/wp/v2/${WP_APP_POST_TYPE:-app}/$WP_APP_POST_ID" \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print('✅', d.get('link') or d)"

echo "URL: $WP_APP_PAGE_URL"
echo "app-weburl: $APP_URL"
