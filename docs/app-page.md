# 8BIT COIN HUNTER 紹介ページ設定

## 公開ステータス

**公開済み**（`status: publish`）。投稿者: **jonorotz**

## URL

- 紹介ページ: https://apps.tomippe.jp/8bit/
- アプリ本体（Web）: https://fuma.tomippe.jp/8bit/（`index.html`）

## WordPress 投稿 ID

| 用途 | ID |
|------|-----|
| 紹介ページ（app） | **2427** |

## キャッチフレーズ（app-cp）

レトロな画面でコインを集めろ
8ビットのシンプルアクション

## プラットフォーム

- **platform**: `["web"]`
- **app-weburl**: `https://fuma.tomippe.jp/8bit/`
- **app-webdesc**: `Web<br>日本語`

## アイコン・SS・KV

- **app-icon**: `marketing/icon.png`
- **app-ss01**: `marketing/ss01.png`（ゲーム canvas・900×780 相当ウィンドウで撮影）ID **2431**
- **app-ss01width**: `640` / **app-ss01radius**: true
- **app-kvbg**: `marketing/kv-bg.png`（スクショをリフトして暗潰れ防止）ID **2434**
- **app-keycolor**: `#245438`
- **app-kvbgaddcss**: `multiply`

## 版番号

- `version.txt`（いまのビルド対象版）。`./build.sh` 成功後にパッチ +1
- `manifest.json`（`version` / `web_version`）→ 紹介ページの版表示用
  - https://apps.tomippe.jp/8bit/manifest.json
  - https://fuma.tomippe.jp/8bit/manifest.json
- 履歴は ACF `app-versions`（`build-common/scripts/wp-append-app-version.py`）

## ローカル連携

- `~/.wp-env`（共通・jonorotz）
- プロジェクト `.env`: `WP_APP_POST_ID`, `WP_APP_PAGE_URL`
- デプロイ: `./build.sh -cm "変更内容"`
- 紹介再作成: `bash scripts/setup-intro-page.sh`
