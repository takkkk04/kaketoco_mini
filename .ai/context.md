# Kaketoco Mini – AI Context

# IMPORTANT
If you are an AI agent (ChatGPT, Codex, Copilot, Claude):
Read this file before making any code suggestions.

このプロジェクトは農薬検索サービス「カケトコ」のMVPです。

## 技術スタック

- PHP (vanilla)
- MySQL
- jQuery
- CSS
- Shopify Buy Button

フレームワークは使用していません。

---

# フォルダ構造

project root

public/
フロントエンドと画面

- index.php
  検索ページ
- mypage.php
  マイページ
- login.php
  ログイン画面
- favorite_toggle.php
  お気に入り登録API
- js/
  JavaScript
- css/
  CSS
src/backend/
- db.php
  PDO接続
- auth.php
  認証処理

---

# DB構造

## pesticides_base

農薬の基本情報

- registration_number
- name
- category
- registered_on
- rac_code
- quickly
- systemic
- translaminar
- toxicity

## pesticides_rules

農薬適用ルール

- registration_number
- crop
- target
- magnification
- timing
- times
- method

## favorites

ユーザーお気に入り

- user_id
- registration_number

---

# 設計ルール

このプロジェクトはMVPのため

- シンプルなPHP構成
- フレームワークは使わない
- 機能は小さく追加する
- 既存構造を壊さない

---

# コーディング方針

AIは次のルールに従う

- 変更箇所だけ提示する
- ファイル全体を書き換えない
- 既存コードを尊重する
- 小さな変更を段階的に行う