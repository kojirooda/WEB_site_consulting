# インフラ監査レポート — menskajiyaru.com

調査日: 2026-03-13
担当: インフラエンジニア

---

## 1. ホスティング環境

### 現状
| 項目 | 状態 | 詳細 |
|------|------|------|
| CMS | WordPress | `/wp/` サブディレクトリ構成 |
| HTTPS | ✅ 有効 | SSL証明書設定済み |
| HTTP→HTTPSリダイレクト | ✅ 有効 | 301リダイレクト確認 |
| CDN | ❓ 不明 | Cloudflare等の利用状況不明 |
| サーバー種別 | 不明 | レンタルサーバー（Apache/Nginx） |

### 問題点
- CDNの利用状況が確認できない。静的アセット（画像・CSS・JS）の配信速度に影響。

---

## 2. セキュリティヘッダー

### 現状
| ヘッダー | 状態 | 備考 |
|----------|------|------|
| HSTS | ❓ 未確認 | — |
| X-Content-Type-Options | ❓ 未確認 | — |
| X-Frame-Options | ❓ 未確認 | クリックジャッキング対策 |
| Content-Security-Policy | ❓ 未確認 | — |
| Referrer-Policy | ❓ 未確認 | — |

### 問題点
- WordPressサイトはデフォルトでセキュリティヘッダーが設定されないケースが多い。
- `.htaccess` またはサーバー設定でのヘッダー追加を要確認。

---

## 3. WordPress構成

### 現状
| 項目 | 状態 | 詳細 |
|------|------|------|
| WPインストールパス | `/wp/` | サブディレクトリ構成 |
| wp-login.phpアクセス | ❓ 確認必要 | ブルートフォース対策の有無 |
| XMLRPCの状態 | ❓ 確認必要 | 攻撃対象になりやすい |
| WordPress管理者URL | `/wp/wp-admin/` | デフォルトパス（変更推奨） |
| プラグイン数 | 多数 | Autoptimize, Yoast SEO, Site Kit等を確認 |

### 問題点
- **wp-login.phpのデフォルトパス**: 自動化されたブルートフォース攻撃の標的になりやすい。
- **XMLRPC**: WordPress標準機能だが、攻撃ベクターになるため不使用なら無効化推奨。
- **プラグインの過多**: 未使用プラグインがあれば削除。各プラグインの更新状況も要確認。

---

## 4. パフォーマンス関連インフラ

### 現状
| 項目 | 状態 | 詳細 |
|------|------|------|
| ページキャッシュ | ❓ 不明 | WP Super Cache / W3 Total Cache等の有無 |
| オブジェクトキャッシュ | ❓ 不明 | Redis / Memcached の有無 |
| gzip/Brotli圧縮 | ❓ 不明 | サーバー設定依存 |
| 画像CDN | ❌ なし | Cloudinary等の利用なし |
| Autoptimize | ✅ 導入済み | CSS/JS結合・最小化（効果は限定的） |

### 問題点
- **ページキャッシュ未設定の可能性**: WordPressはデフォルトで動的生成のため、毎リクエストでDBアクセスが発生。キャッシュプラグインなしでは遅延が大きい。
- **Autoptimizeのみでは不十分**: CSS/JSファイル数（16+43）から判断して、結合・遅延読み込みが機能していない可能性が高い。

---

## 5. バックアップ体制

### 現状
- バックアップ方法: **不明**（ホスティング会社の自動バックアップに依存している可能性）
- Git等でのバージョン管理: **確認できず**

### 問題点
- **バックアップ設定が不明**: WordPressのDB・ファイルのバックアップが取れていないと、障害時にサイトが消失するリスクがある。
- 推奨: `UpdraftPlus` プラグインで自動バックアップ設定（週次・Google Drive/S3連携）

---

## 6. URL・ドメイン設定

### 現状
| 項目 | 状態 | 詳細 |
|------|------|------|
| カスタムドメイン | ✅ 設定済み | menskajiyaru.com |
| www→non-wwwリダイレクト | ✅ 統一 | non-wwwに統一 |
| サイトURL設定 | `https://menskajiyaru.com/wp/` 構成 | WordPressをサブディレクトリに配置、ルートに転送 |

---

## 7. robots.txt / sitemap

### 現状
| 項目 | 状態 | 詳細 |
|------|------|------|
| robots.txt | ✅ 適切 | Yoast SEO管理、wp-admin/等をDisallow |
| sitemap_index.xml | ✅ 存在 | Yoast SEO自動生成 |
| sitemap登録（GSC） | ❓ 不明 | Google Search Console登録状況不明 |

---

## 8. 具体的改善施策

### P1（高優先度）
- **ページキャッシュプラグイン導入**: `WP Super Cache` または `W3 Total Cache` を導入。動的ページのキャッシュ生成でサーバー負荷・応答速度を改善。
- **Autoptimize設定見直し**: CSS・JSの結合と遅延読み込み設定を正しく有効化

### P2（中優先度）
- **セキュリティヘッダー設定**: `.htaccess` に `X-Frame-Options`、`X-Content-Type-Options`、`Referrer-Policy` を追加
- **wp-login.php保護**: `Limit Login Attempts Reloaded` などでブルートフォース対策
- **バックアップ設定**: `UpdraftPlus` で週次自動バックアップを設定（保存先: Google Drive）
- **Cloudflare導入**: 無料プランでCDN・DDoS対策・キャッシュ・HTTPS証明書管理を一元化

### P3（低優先度）
- **XMLRPC無効化**: 使用していない場合は `.htaccess` でアクセスブロック
- **Google Search Console**: sitemap.xmlを登録済みか確認
- **サーバー gzip/Brotli圧縮**: ホスティング設定を確認・有効化

---

## 総合評価

| 領域 | スコア | 最優先課題 |
|------|--------|-----------|
| HTTPS / ドメイン設定 | 8/10 | 問題なし |
| セキュリティヘッダー | 3/10 | ヘッダー追加が必要 |
| キャッシュ・CDN | 2/10 | ページキャッシュ導入 |
| バックアップ | 2/10 | UpdraftPlus設定 |
| WordPress保護 | 4/10 | ログイン保護・XMLRPC無効化 |
