<?php
/**
 * Plugin Name: Fluent Support Customer Merge
 * Description: Administrator-reviewed customer record merges inside Fluent Support.
 * Plugin URI: https://github.com/stingray82/fluent-support-merge
 * Version: 1.1.0
 * Requires PHP: 7.4
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

final class FSCM_Admin {
    const PAGE = 'fscm-merge';

    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu'], 30);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_footer', [__CLASS__, 'customer_link']);
        add_action('wp_ajax_fscm_search', [__CLASS__, 'search']);
        add_action('admin_post_fscm_merge', [__CLASS__, 'submit']);
    }

    private static function ready() {
        return class_exists('FluentSupport\\App\\Models\\Customer')
            && class_exists('FluentSupport\\App\\Models\\Ticket')
            && class_exists('FluentSupport\\App\\Services\\EmailClaimService');
    }

    public static function menu() {
        add_submenu_page('fluent-support', 'Merge customer records', 'Merge customers', 'manage_options', self::PAGE, [__CLASS__, 'render']);
    }

    public static function assets() {
        if (!current_user_can('manage_options') || ($_GET['page'] ?? '') !== self::PAGE) { return; }
        wp_enqueue_style('fscm-admin', plugins_url('assets/admin.css', __FILE__), [], '1.1.0');
        wp_enqueue_script('fscm-admin', plugins_url('assets/admin.js', __FILE__), [], '1.1.0', true);
        wp_add_inline_script('fscm-admin', 'window.fscmAdmin=' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fscm_search'),
        ]) . ';', 'before');
    }

    /** Place the action on Fluent Support's own customer profile. */
    public static function customer_link() {
        if (!current_user_can('manage_options') || ($_GET['page'] ?? '') !== 'fluent-support' || !self::ready()) { return; }
        $base = admin_url('admin.php?page=' . self::PAGE . '&target=');
        echo '<script>(function(){const base=' . wp_json_encode($base) . ';let last="";function update(){const match=location.hash.match(/^#\\/customers\\/(\\d+)(?:\\/|$)/);const box=document.querySelector(".fs_customer_page .fs_box_actions");if(!match||!box)return;const id=match[1];let link=box.querySelector(".fscm-profile-link");if(!link){link=document.createElement("a");link.className="button fscm-profile-link";link.textContent="Merge customer records";box.appendChild(link)}if(last!==id){link.href=base+encodeURIComponent(id);last=id}}new MutationObserver(update).observe(document.body,{childList:true,subtree:true});addEventListener("hashchange",update);update()})();</script>';
    }

    private static function customer($id) {
        if (!$id || !self::ready()) { return null; }
        return \FluentSupport\App\Models\Customer::find($id);
    }

    /** Recheck ownership and addresses immediately before the merge. */
    private static function eligible($source, $target) {
        if (!$source || !$target || (int)$source->id === (int)$target->id) { return 'Choose two different customer records.'; }
        if (!is_email($source->email) || strcasecmp((string)$source->email, (string)$target->email) !== 0) { return 'Customer records must have the same email address.'; }
        $target_user = (int)$target->user_id ? get_user_by('id', (int)$target->user_id) : false;
        if (!$target_user || strcasecmp((string)$target_user->user_email, (string)$target->email) !== 0) { return 'The customer record to keep must be linked to an existing WordPress user with this email address.'; }
        $source_owner = (int)$source->user_id;
        if ($source_owner && $source_owner !== (int)$target_user->ID && get_user_by('id', $source_owner)) { return 'The source record belongs to another existing WordPress user. Resolve that account first.'; }
        return '';
    }

    private static function page_url($args = []) {
        return add_query_arg(array_merge(['page' => self::PAGE], $args), admin_url('admin.php'));
    }

    private static function summary($customer) {
        if (!$customer) { return null; }
        $name = trim((string)$customer->first_name . ' ' . (string)$customer->last_name);
        $owner = (int)$customer->user_id ? get_user_by('id', (int)$customer->user_id) : false;
        $status = !$customer->user_id ? 'No WordPress account linked' : ($owner ? 'Linked to WordPress account #' . $owner->ID : 'Linked account no longer exists');
        return [
            'id' => (int)$customer->id,
            'name' => $name ?: 'Unnamed customer',
            'email' => (string)$customer->email,
            'status' => $status,
            'tickets' => (int)\FluentSupport\App\Models\Ticket::where('customer_id', (int)$customer->id)->count(),
        ];
    }

    public static function search() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Forbidden'], 403); }
        check_ajax_referer('fscm_search', 'nonce');
        if (!self::ready()) { wp_send_json_error(['message' => 'Fluent Support is unavailable'], 503); }
        $term = trim(sanitize_text_field(wp_unslash($_GET['term'] ?? '')));
        if (strlen($term) < 2 && !ctype_digit($term)) { wp_send_json_success([]); }
        global $wpdb;
        $table = $wpdb->prefix . 'fs_persons';
        $like = '%' . $wpdb->esc_like($term) . '%';
        $id = ctype_digit($term) ? (int)$term : 0;
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE person_type = 'customer' AND (id = %d OR email LIKE %s OR first_name LIKE %s OR last_name LIKE %s) ORDER BY id DESC LIMIT 20",
            $id, $like, $like, $like
        ));
        $results = [];
        foreach ($rows as $row_id) {
            $customer = self::customer((int)$row_id);
            if ($customer) { $results[] = self::summary($customer); }
        }
        wp_send_json_success($results);
    }

    public static function render() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', ['response' => 403]); }
        $ready = self::ready();
        $target_id = absint($_GET['target'] ?? 0);
        $source_id = absint($_GET['source'] ?? 0);
        $target = self::customer($target_id);
        $source = self::customer($source_id);
        $selected = [self::summary($target), self::summary($source)];
        $page_url = self::page_url();
        $reason = $target && $source ? self::eligible($source, $target) : '';
        $source_tickets = $target && $source && !$reason ? \FluentSupport\App\Models\Ticket::where('customer_id', $source_id)->orderBy('id', 'ASC')->get() : [];
        require __DIR__ . '/includes/admin-page.php';
    }

    public static function submit() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden', '', ['response' => 403]); }
        $source_id = absint($_POST['source'] ?? 0);
        $target_id = absint($_POST['target'] ?? 0);
        check_admin_referer('fscm_merge_' . $source_id . '_' . $target_id);
        if (($_POST['confirm'] ?? '') !== '1') { wp_die('Confirm the merge first.', '', ['response' => 400]); }
        $source = self::customer($source_id);
        $target = self::customer($target_id);
        $reason = self::eligible($source, $target);
        if ($reason) { wp_die(esc_html($reason), '', ['response' => 400]); }
        require_once __DIR__ . '/includes/core-merge.php';
        $moved = FSCM_Core_Merge::absorb_records($source, $target);
        wp_safe_redirect(self::page_url(['target' => $target_id, 'merged' => $moved]));
        exit;
    }
}

FSCM_Admin::boot();
