<?php
/**
 * GrossistKit — Customer Groups
 * Full replacement for linmad-kundgrupper. Reads the same meta keys so no
 * data migration is needed when switching from that plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Customer_Groups {

    public function __construct() {
        // Product price fields
        add_action( 'woocommerce_product_options_pricing',  [ $this, 'render_product_price_fields' ] );
        add_action( 'woocommerce_process_product_meta',     [ $this, 'save_product_price_fields' ] );
        add_action( 'save_post_product',                    [ $this, 'clear_variation_price_cache' ] );

        // Price filters
        add_filter( 'woocommerce_product_get_price',                   [ $this, 'apply_group_price' ], 20, 2 );
        add_filter( 'woocommerce_product_get_regular_price',           [ $this, 'apply_group_price' ], 20, 2 );
        add_filter( 'woocommerce_product_variation_get_price',         [ $this, 'apply_group_price' ], 20, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price', [ $this, 'apply_group_price' ], 20, 2 );
        add_filter( 'woocommerce_variation_prices_price',              [ $this, 'apply_group_price' ], 20, 2 );
        add_filter( 'woocommerce_variation_prices_regular_price',      [ $this, 'apply_group_price' ], 20, 2 );

        // User profile fields
        add_action( 'show_user_profile',        [ $this, 'render_user_group_field' ] );
        add_action( 'edit_user_profile',        [ $this, 'render_user_group_field' ] );
        add_action( 'personal_options_update',  [ $this, 'save_user_group_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_group_field' ] );

        // User list column + bulk actions
        add_filter( 'manage_users_columns',       [ $this, 'add_user_column' ] );
        add_filter( 'manage_users_custom_column', [ $this, 'render_user_column' ], 10, 3 );
        add_filter( 'bulk_actions-users',         [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-users',  [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_action( 'admin_notices',              [ $this, 'bulk_action_notice' ] );

        // Order meta
        add_action( 'woocommerce_checkout_create_order',                  [ $this, 'save_group_to_order' ], 10, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'show_group_in_order' ] );

        // My Account badge
        add_action( 'woocommerce_after_account_navigation', [ $this, 'render_my_account_badge' ] );
    }

    // ─── Groups config ───────────────────────────────────────────────────────

    public static function get_groups(): array {
        return [
            'bas'    => 'Bas',
            'silver' => 'Silver',
            'guld'   => 'Guld',
            'vip'    => 'VIP',
        ];
    }

    public static function get_priced_groups(): array {
        $all = self::get_groups();
        unset( $all['bas'] );
        return $all;
    }

    // ─── Product price fields ─────────────────────────────────────────────────

    public function render_product_price_fields(): void {
        global $post;

        echo '<div class="options_group gk-group-prices">';
        echo '<p class="form-field"><strong style="padding-left:12px;">Priser per kundgrupp</strong></p>';
        echo '<p class="form-field" style="padding-left:12px;color:#888;font-size:.9em;margin-top:-8px;">Bas-kunder ser alltid standardpriset. Lämna tomt för att använda standardpriset.</p>';

        foreach ( self::get_priced_groups() as $slug => $label ) {
            $meta_key = GK_PRICE_META_PREFIX . $slug;
            $value    = get_post_meta( $post->ID, $meta_key, true );

            woocommerce_wp_text_input( [
                'id'          => $meta_key,
                'name'        => $meta_key,
                'label'       => $label . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => 'Tomt = standardpris',
                'value'       => $value,
                'data_type'   => 'price',
                'desc_tip'    => true,
                'description' => "Fast pris för kunder i gruppen {$label}. Lämna tomt om standardpriset ska gälla.",
            ] );
        }

        echo '</div>';
    }

    public function save_product_price_fields( int $post_id ): void {
        foreach ( self::get_priced_groups() as $slug => $label ) {
            $meta_key = GK_PRICE_META_PREFIX . $slug;

            if ( isset( $_POST[ $meta_key ] ) && '' !== $_POST[ $meta_key ] ) {
                $price = wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) );
                update_post_meta( $post_id, $meta_key, $price );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }
    }

    public function clear_variation_price_cache( int $post_id ): void {
        $product = wc_get_product( $post_id );
        if ( $product && $product->is_type( 'variable' ) ) {
            WC_Cache_Helper::get_transient_version( 'product', true );
        }
    }

    // ─── Price filter ─────────────────────────────────────────────────────────

    public function apply_group_price( $price, $product ) {
        if ( is_admin() && ! wp_doing_ajax() ) return $price;
        if ( ! is_user_logged_in() ) return $price;

        $group = self::get_current_user_group();
        if ( ! $group || 'bas' === $group ) return $price;

        $meta_key    = GK_PRICE_META_PREFIX . $group;
        $group_price = get_post_meta( $product->get_id(), $meta_key, true );

        return ( '' !== $group_price && is_numeric( $group_price ) ) ? $group_price : $price;
    }

    // ─── User profile field ───────────────────────────────────────────────────

    public function render_user_group_field( WP_User $user ): void {
        $current = get_user_meta( $user->ID, GK_USER_META_KEY, true );
        $groups  = self::get_groups();
        ?>
        <h3>Linmad Kundgrupp</h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="<?php echo esc_attr( GK_USER_META_KEY ); ?>">Kundgrupp</label></th>
                <td>
                    <select name="<?php echo esc_attr( GK_USER_META_KEY ); ?>" id="<?php echo esc_attr( GK_USER_META_KEY ); ?>">
                        <option value="">– Ingen grupp –</option>
                        <?php foreach ( $groups as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
                                <?php echo esc_html( $label ); ?>
                                <?php echo 'bas' === $slug ? ' (standardpris)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Bas-kunder ser alltid WooCommerce standardpris. Silver/Guld/VIP ser priser satta per produkt.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_group_field( int $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;

        $groups = self::get_groups();
        $value  = isset( $_POST[ GK_USER_META_KEY ] ) ? sanitize_key( $_POST[ GK_USER_META_KEY ] ) : '';

        if ( $value !== '' && ! isset( $groups[ $value ] ) ) return;

        update_user_meta( $user_id, GK_USER_META_KEY, $value );
    }

    // ─── User list column ─────────────────────────────────────────────────────

    public function add_user_column( array $columns ): array {
        $columns['gk_group'] = 'Kundgrupp';
        return $columns;
    }

    public function render_user_column( string $output, string $column_name, int $user_id ): string {
        return 'gk_group' === $column_name ? self::get_user_group_label( $user_id ) : $output;
    }

    public function add_bulk_actions( array $actions ): array {
        foreach ( self::get_groups() as $slug => $label ) {
            $actions[ 'gk_set_' . $slug ] = "Sätt kundgrupp: {$label}";
        }
        $actions['gk_clear_group'] = 'Ta bort kundgrupp';
        return $actions;
    }

    public function handle_bulk_actions( string $redirect_to, string $action, array $user_ids ): string {
        foreach ( self::get_groups() as $slug => $label ) {
            if ( 'gk_set_' . $slug === $action ) {
                foreach ( $user_ids as $uid ) {
                    update_user_meta( (int) $uid, GK_USER_META_KEY, $slug );
                }
                return add_query_arg( [ 'gk_updated' => count( $user_ids ), 'gk_group' => $slug ], $redirect_to );
            }
        }

        if ( 'gk_clear_group' === $action ) {
            foreach ( $user_ids as $uid ) {
                delete_user_meta( (int) $uid, GK_USER_META_KEY );
            }
            return add_query_arg( [ 'gk_updated' => count( $user_ids ), 'gk_group' => 'removed' ], $redirect_to );
        }

        return $redirect_to;
    }

    public function bulk_action_notice(): void {
        if ( empty( $_REQUEST['gk_updated'] ) ) return;

        $count  = (int) $_REQUEST['gk_updated'];
        $group  = sanitize_key( $_REQUEST['gk_group'] ?? '' );
        $groups = self::get_groups();

        if ( 'removed' === $group ) {
            $msg = "Kundgrupp borttagen för {$count} användare.";
        } else {
            $label = isset( $groups[ $group ] ) ? $groups[ $group ] : $group;
            $msg   = "{$count} användare uppdaterade till <strong>" . esc_html( $label ) . "</strong>.";
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>';
    }

    // ─── Order meta ───────────────────────────────────────────────────────────

    public function save_group_to_order( WC_Order $order, array $data ): void {
        $group = self::get_current_user_group();
        if ( $group ) {
            $order->update_meta_data( '_gk_group', $group );
        }
    }

    public function show_group_in_order( WC_Order $order ): void {
        $group  = $order->get_meta( '_gk_group' );
        $groups = self::get_groups();

        if ( ! $group || ! isset( $groups[ $group ] ) ) return;
        echo '<p><strong>Kundgrupp:</strong> ' . esc_html( $groups[ $group ] ) . '</p>';
    }

    // ─── My Account badge ─────────────────────────────────────────────────────

    public function render_my_account_badge(): void {
        if ( ! is_user_logged_in() ) return;

        $group  = self::get_current_user_group();
        $groups = self::get_groups();

        if ( ! $group ) return;
        ?>
        <div style="margin-top:1rem;padding:.75rem 1rem;background:#f9f9f9;border-left:4px solid #9A0002;font-size:.9em;">
            <strong>Din kundgrupp:</strong> <?php echo esc_html( $groups[ $group ] ); ?>
        </div>
        <?php
    }

    // ─── Helpers (public so admin menu can use them) ──────────────────────────

    public static function get_current_user_group(): string {
        if ( ! is_user_logged_in() ) return '';

        $group  = get_user_meta( get_current_user_id(), GK_USER_META_KEY, true );
        $groups = self::get_groups();

        return ( $group && isset( $groups[ $group ] ) ) ? $group : '';
    }

    public static function get_user_group_label( int $user_id ): string {
        $group  = get_user_meta( $user_id, GK_USER_META_KEY, true );
        $groups = self::get_groups();

        return ( $group && isset( $groups[ $group ] ) ) ? esc_html( $groups[ $group ] ) : '–';
    }
}
