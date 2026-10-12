=== Kechan Image Organizer & Restore ===
Contributors: kechan
Tags: media, images, restore, organizer
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

既存記事の本文内にあるメディアライブラリ登録済み画像を除去し、復元マーカーとHTMLを保存して元の位置へ復元できるプラグインです。画像ファイルそのものは移動・削除しません。

== 使い方 ==
1. 管理画面の「プラグイン」→「新規プラグインを追加」→「プラグインのアップロード」からZIPをアップロードします。
2. 有効化します。
3. 「ツール」→「Kechan画像整理」を開きます。
4. 最初はテスト記事を1件だけ選択して実行します。
5. 元に戻す場合は同じ画面の「画像を元の位置へ復元」を実行します。

== 注意 ==
- 実行前にファイルとデータベースのバックアップを取ってください。
- 本文の通常のimgタグが対象です。SNS埋め込みの投稿カードは処理しません。
- メディアライブラリ内の添付ファイルURLを照合できた画像だけが対象です。
- 画像ファイル自体は削除も移動もしません。
- この初版はクラシックエディター／ブロックエディターの保存本文中にあるimgタグを対象とします。画像ブロック等のHTMLコメントを含む記事でも、通常のimgタグは対象ですが、特殊なギャラリーやプラグイン独自の画像構造は別途検証が必要です。

== Version 1.1.0 ==
* Added update checks against the public GitHub Releases page for Kechan-nb/kechan-image-organizer.
* Improved capability checks and failed-save handling.
* Applied the folder name entered in the admin screen.
* Important: automatic updates only work after a matching GitHub Release is published with the asset named kechan-image-organizer.zip.
