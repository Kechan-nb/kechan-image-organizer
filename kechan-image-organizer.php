<?php
/**
 * Plugin Name: Kechan Image Organizer & Restore
 * Description: 既存記事の本文画像を仮想フォルダで管理し、除去した画像を元の位置へ復元します。
 * Version: 1.1.0
 * Author: Kechan
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: kechan-image-organizer
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Kechan_Image_Organizer {
    const TAXONOMY = 'kechan_image_folder';
    const META_KEY = '_kechan_removed_images_v1';
    const MARKER_PREFIX = '<!-- kechan-image-restore:';
    const GITHUB_REPO = 'Kechan-nb/kechan-image-organizer';
    const RELEASE_ASSET = 'kechan-image-organizer.zip';
    const UPDATE_CACHE_KEY = 'kechan_image_organizer_github_release_v1';

    public function __construct() {
        add_action('init', [$this, 'register_taxonomy']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_kechan_image_process', [$this, 'handle_process']);
        add_filter('manage_upload_columns', [$this, 'add_media_column']);
        add_action('manage_media_custom_column', [$this, 'render_media_column'], 10, 2);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
    }

    public function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, ['attachment'], [
            'labels' => [
                'name' => '画像フォルダ',
                'singular_name' => '画像フォルダ',
                'search_items' => '画像フォルダを検索',
                'all_items' => '画像フォルダ',
                'edit_item' => '画像フォルダを編集',
                'update_item' => '画像フォルダを更新',
                'add_new_item' => '画像フォルダを追加',
                'new_item_name' => '新しい画像フォルダ名',
                'menu_name' => '画像フォルダ',
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
            'hierarchical' => true,
            'rewrite' => false,
        ]);
    }

    public function admin_menu() {
        add_management_page(
            'Kechan画像整理',
            'Kechan画像整理',
            'manage_options',
            'kechan-image-organizer',
            [$this, 'render_page']
        );
    }

    private function get_posts() {
        return get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ]);
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('このページを表示する権限がありません。');
        }

        $notice = isset($_GET['kechan_notice']) ? sanitize_key(wp_unslash($_GET['kechan_notice'])) : '';
        $counts = isset($_GET['kechan_count']) ? absint($_GET['kechan_count']) : 0;
        if ($notice === 'removed') {
            echo '<div class="notice notice-success is-dismissible"><p>画像の除去処理が完了しました。対象：' . esc_html((string) $counts) . '件。復元用データを保存しています。</p></div>';
        } elseif ($notice === 'restored') {
            echo '<div class="notice notice-success is-dismissible"><p>画像の復元処理が完了しました。対象：' . esc_html((string) $counts) . '件。</p></div>';
        } elseif ($notice === 'error') {
            echo '<div class="notice notice-error"><p>処理できませんでした。記事を選択しているか、権限をご確認ください。</p></div>';
        }

        $posts = $this->get_posts();
        ?>
        <div class="wrap">
            <h1>Kechan画像整理・復元</h1>
            <p>記事本文内の通常の画像（メディアライブラリに登録済みの画像）を対象にします。SNS埋め込みの投稿カード等は画像として処理しません。</p>
            <div class="notice notice-warning inline">
                <p><strong>安全のため、最初はテスト記事1件でお試しください。</strong>実行前にWordPressとデータベースのバックアップを取ってください。画像ファイル自体は移動・削除しません。記事本文から画像HTMLを取り除き、復元用コメントと画像データを保存します。</p>
            </div>
            <h2>画像を除去する</h2>
            <p>選択した記事、または全記事の本文から通常の画像を除去し、メディアライブラリの「記事画像」フォルダに分類します。復元すると元の位置へ戻ります。</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kechan_image_process'); ?>
                <input type="hidden" name="action" value="kechan_image_process">
                <input type="hidden" name="operation" value="remove">
                <p>
                    <label><input type="radio" name="scope" value="selected" checked> 選択した記事のみ</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="scope" value="all"> 全記事・固定ページ</label>
                </p>
                <div style="max-width:850px;max-height:320px;overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:12px;margin:12px 0;">
                    <?php foreach ($posts as $post) : ?>
                        <label style="display:block;margin:5px 0;">
                            <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr((string) $post->ID); ?>">
                            <?php echo esc_html(get_the_title($post) ?: '(タイトルなし)'); ?>
                            <span style="color:#646970;">（ID: <?php echo esc_html((string) $post->ID); ?> / <?php echo esc_html($post->post_type); ?>）</span>
                            <?php if (get_post_meta($post->ID, self::META_KEY, true)) : ?>
                                <strong style="color:#b32d2e;">復元データあり</strong>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p><label>フォルダ名：
                    <input type="text" name="folder_name" value="記事画像" maxlength="100">
                </label></p>
                <?php submit_button('選択した記事の画像を除去', 'primary', 'submit', true, ['onclick' => "return confirm('選択範囲の記事本文から画像を除去します。事前にバックアップを取りましたか？');"]); ?>
            </form>

            <hr>
            <h2>画像を元の位置へ復元する</h2>
            <p>保存した復元データを使い、画像を除去した記事の同じ位置に画像HTMLを戻します。除去後に記事本文を編集している場合、復元マーカーが残っている箇所だけを復元します。</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kechan_image_process'); ?>
                <input type="hidden" name="action" value="kechan_image_process">
                <input type="hidden" name="operation" value="restore">
                <p>
                    <label><input type="radio" name="scope" value="selected" checked> 選択した記事のみ</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="scope" value="all"> 復元データがある全記事</label>
                </p>
                <div style="max-width:850px;max-height:240px;overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:12px;margin:12px 0;">
                    <?php foreach ($posts as $post) :
                        $saved = get_post_meta($post->ID, self::META_KEY, true);
                        if (!$saved) continue;
                    ?>
                        <label style="display:block;margin:5px 0;">
                            <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr((string) $post->ID); ?>">
                            <?php echo esc_html(get_the_title($post) ?: '(タイトルなし)'); ?>
                            <span style="color:#646970;">（ID: <?php echo esc_html((string) $post->ID); ?>）</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php submit_button('選択した記事の画像を復元', 'secondary', 'submit', true, ['onclick' => "return confirm('保存された画像を元の位置へ復元しますか？');"]); ?>
            </form>
            <hr>
            <p><strong>注意：</strong>この初版では、画像の分類はWordPressの添付ファイルに付ける「画像フォルダ」分類で実現します。既存の画像ファイルやURLは変更しません。本文の画像タグを除去する操作なので、必ずバックアップ後に実行してください。</p>
        </div>
        <?php
    }

    public function handle_process() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        check_admin_referer('kechan_image_process');

        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'selected';
        $ids = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['post_ids'])))))
            : [];

        if ($scope === 'all') {
            $ids = [];
            foreach ($this->get_posts() as $post) {
                if ($operation === 'remove' || get_post_meta($post->ID, self::META_KEY, true)) {
                    $ids[] = (int) $post->ID;
                }
            }
        }

        if (!$ids || !in_array($operation, ['remove', 'restore'], true)) {
            $this->redirect('error', 0);
        }

        $count = 0;
        foreach ($ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || !in_array($post->post_type, ['post', 'page'], true) || !current_user_can('edit_post', $post_id)) {
                continue;
            }
            if ($operation === 'remove') {
                $folder_name = isset($_POST['folder_name']) ? sanitize_text_field(wp_unslash($_POST['folder_name'])) : '記事画像';
                $folder_name = trim(wp_html_excerpt($folder_name, 100, ''));
                if ($folder_name === '') { $folder_name = '記事画像'; }
                if ($this->remove_images($post_id, $folder_name)) {
                    $count++;
                }
            } else {
                if ($this->restore_images($post_id)) {
                    $count++;
                }
            }
        }

        $this->redirect($operation === 'remove' ? 'removed' : 'restored', $count);
    }

    private function remove_images($post_id, $folder_name = '記事画像') {
        $post = get_post($post_id);
        if (!$post) return false;

        $content = $post->post_content;
        if (strpos($content, self::MARKER_PREFIX) !== false || get_post_meta($post_id, self::META_KEY, true)) {
            return false; // すでに処理済み、または復元データがある場合は上書きしない。
        }

        $pattern = '/<img\b[^>]*>/i';
        if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $items = [];
        $new_content = '';
        $last = 0;
        $index = 0;

        foreach ($matches[0] as $match) {
            $img_html = $match[0];
            $offset = $match[1];

            // メディアライブラリに存在する添付ファイルの画像だけ対象。
            $attachment_id = $this->attachment_id_from_img($img_html);
            if (!$attachment_id) {
                continue;
            }

            $new_content .= substr($content, $last, $offset - $last);
            $token = wp_generate_password(12, false, false) . '-' . $index;
            $marker = self::MARKER_PREFIX . esc_attr($token) . '-->';
            $new_content .= $marker;

            $items[$token] = [
                'html' => $img_html,
                'attachment_id' => $attachment_id,
            ];
            $last = $offset + strlen($img_html);
            $index++;

            $this->assign_to_folder($attachment_id, $folder_name);
        }

        if (!$items) return false;
        $new_content .= substr($content, $last);

        // 先に復元情報を保存し、本文更新が失敗した場合は復元情報を取り消す。
        $meta_saved = update_post_meta($post_id, self::META_KEY, $items);
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content,
        ], true);

        if (is_wp_error($result)) {
            delete_post_meta($post_id, self::META_KEY);
            return false;
        }
        return true;
    }

    private function attachment_id_from_img($img_html) {
        if (preg_match('/\bwp-image-(\d+)\b/i', $img_html, $m)) {
            $id = absint($m[1]);
            if ($id && wp_attachment_is_image($id)) return $id;
        }

        if (preg_match('/\bsrc=["\']([^"\']+)["\']/i', $img_html, $m)) {
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $id = attachment_url_to_postid($url);
            if ($id && wp_attachment_is_image($id)) return $id;

            // WordPressが生成する -scaled / -300x200 等のURLも可能な範囲で照合。
            $clean = preg_replace('/-\d+x\d+(?=\.[a-z0-9]+(?:\?|$))/i', '', $url);
            $clean = preg_replace('/-scaled(?=\.[a-z0-9]+(?:\?|$))/i', '', $clean);
            $id = attachment_url_to_postid($clean);
            if ($id && wp_attachment_is_image($id)) return $id;
        }
        return 0;
    }

    private function assign_to_folder($attachment_id, $folder_name) {
        $folder_name = sanitize_text_field($folder_name ?: '記事画像');
        $term = get_term_by('name', $folder_name, self::TAXONOMY);
        if (!$term) {
            $created = wp_insert_term($folder_name, self::TAXONOMY);
            if (is_wp_error($created)) {
                $term = get_term_by('name', '記事画像', self::TAXONOMY);
                if (!$term) {
                    $created = wp_insert_term('記事画像', self::TAXONOMY);
                    if (is_wp_error($created)) return;
                    $term_id = (int) $created['term_id'];
                } else {
                    $term_id = (int) $term->term_id;
                }
            } else {
                $term_id = (int) $created['term_id'];
            }
        } else {
            $term_id = (int) $term->term_id;
        }
        wp_set_object_terms($attachment_id, [$term_id], self::TAXONOMY, true);
    }

    private function restore_images($post_id) {
        $saved = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($saved) || !$saved) return false;

        $post = get_post($post_id);
        if (!$post) return false;

        $content = $post->post_content;
        $restored_any = false;

        foreach ($saved as $token => $item) {
            if (!isset($item['html'])) continue;
            $marker = self::MARKER_PREFIX . esc_attr($token) . '-->';
            if (strpos($content, $marker) !== false) {
                $content = str_replace($marker, $item['html'], $content);
                $restored_any = true;
            }
        }

        if (!$restored_any) {
            // マーカーが失われた場合に本文全体を上書きするのは危険なので自動復元しない。
            return false;
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ], true);

        if (is_wp_error($result)) return false;

        // 全マーカーが復元されたらバックアップを削除。残った分があれば保持。
        $remaining = [];
        foreach ($saved as $token => $item) {
            $marker = self::MARKER_PREFIX . esc_attr($token) . '-->';
            if (strpos($content, $marker) !== false) {
                $remaining[$token] = $item;
            }
        }
        if ($remaining) {
            update_post_meta($post_id, self::META_KEY, $remaining);
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
        return true;
    }

    public function add_media_column($columns) {
        $columns['kechan_image_folder'] = '画像フォルダ';
        return $columns;
    }

    public function render_media_column($column, $post_id) {
        if ($column !== 'kechan_image_folder') return;
        $terms = get_the_terms($post_id, self::TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            echo '—';
            return;
        }
        echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
    }

    /** Retrieve the latest stable GitHub release and cache it for six hours. */
    private function get_github_release() {
        $cached = get_transient(self::UPDATE_CACHE_KEY);
        if (is_array($cached) && !empty($cached['tag_name'])) {
            return $cached;
        }

        $response = wp_remote_get('https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest', [
            'timeout' => 8,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Kechan-Image-Organizer-WordPress-Updater',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
        ]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || empty($release['tag_name']) || !empty($release['draft']) || !empty($release['prerelease'])) {
            return false;
        }

        $asset_url = '';
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (!empty($asset['name']) && self::RELEASE_ASSET === $asset['name'] && !empty($asset['browser_download_url'])) {
                    $asset_url = esc_url_raw($asset['browser_download_url']);
                    break;
                }
            }
        }
        if (!$asset_url || 0 !== strpos($asset_url, 'https://github.com/')) {
            return false;
        }

        $data = [
            'tag_name' => sanitize_text_field($release['tag_name']),
            'name' => isset($release['name']) ? sanitize_text_field($release['name']) : '',
            'html_url' => isset($release['html_url']) ? esc_url_raw($release['html_url']) : 'https://github.com/' . self::GITHUB_REPO . '/releases',
            'body' => isset($release['body']) ? wp_kses_post($release['body']) : '',
            'package' => $asset_url,
        ];
        set_transient(self::UPDATE_CACHE_KEY, $data, 6 * HOUR_IN_SECONDS);
        return $data;
    }

    public function inject_update($transient) {
        if (!is_object($transient) || empty($transient->checked) || !is_admin()) {
            return $transient;
        }
        $plugin_file = plugin_basename(__FILE__);
        if (!isset($transient->checked[$plugin_file])) {
            return $transient;
        }
        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }
        $version = ltrim($release['tag_name'], "vV ");
        if (!preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            return $transient;
        }
        if (version_compare($version, $transient->checked[$plugin_file], '>')) {
            $transient->response[$plugin_file] = (object) [
                'slug' => 'kechan-image-organizer',
                'plugin' => $plugin_file,
                'new_version' => $version,
                'url' => $release['html_url'],
                'package' => $release['package'],
                'tested' => '6.8',
                'requires_php' => '7.4',
            ];
        }
        return $transient;
    }

    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || 'kechan-image-organizer' !== $args->slug) {
            return $result;
        }
        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }
        $version = ltrim($release['tag_name'], "vV ");
        return (object) [
            'name' => 'Kechan画像整理プラグイン',
            'slug' => 'kechan-image-organizer',
            'version' => $version,
            'author' => '<a href="https://github.com/' . esc_attr(self::GITHUB_REPO) . '">Kechan</a>',
            'homepage' => $release['html_url'],
            'sections' => [
                'description' => '記事本文内のメディアライブラリ画像を分類し、保存した復元情報を使って元の位置へ戻します。',
                'changelog' => $release['body'],
            ],
            'download_link' => $release['package'],
            'requires' => '6.0',
            'tested' => '6.8',
            'requires_php' => '7.4',
        ];
    }

    private function redirect($notice, $count) {
        $url = add_query_arg([
            'page' => 'kechan-image-organizer',
            'kechan_notice' => $notice,
            'kechan_count' => absint($count),
        ], admin_url('tools.php'));
        wp_safe_redirect($url);
        exit;
    }
}

new Kechan_Image_Organizer();
