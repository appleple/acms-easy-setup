# a-blog cms 簡単セットアップ / 簡単アップデート

## 簡単セットアップ
より簡単に、ご利用の環境に合わせて a-blog cms をインストールできるパッケージです。

- [簡単セットアップ 3.0.x](https://github.com/appleple/acms-easy-setup/releases/latest/download/install.zip)

## 簡単アップデート
より簡単に、ご利用の環境に合わせて a-blog cms をアップデートできるパッケージです。

- [簡単アップデート 3.0.x](https://github.com/appleple/acms-easy-setup/releases/latest/download/update.zip)

## コントリビュート

### 開発環境のセットアップ

Node.js のバージョン管理に `.node-version` を使用しています。[nodenv](https://github.com/nodenv/nodenv) などを利用してバージョンを合わせてください。

```bash
git clone https://github.com/appleple/acms-easy-setup.git
cd acms-easy-setup
npm install
```

### ディレクトリ構成

```
acms-easy-setup/
├── packages/
│   ├── install/   # 簡単セットアップのソースファイル
│   └── update/    # 簡単アップデートのソースファイル
└── tools/
    └── build.js   # zip ビルドスクリプト
```

### ローカルビルド

```bash
npm run build
# build/install.zip, build/update.zip が生成されます
```

### リリース

バージョンを上げてタグをpushすると、GitHub Actions が自動的にzipをビルドして GitHub Release を作成します。

```bash
npm run patch   # パッチバージョンアップ (例: 2.0.x → 2.0.x+1)
npm run minor   # マイナーバージョンアップ (例: 2.x.0 → 2.x+1.0)
npm run major   # メジャーバージョンアップ (例: x.0.0 → x+1.0.0)
```