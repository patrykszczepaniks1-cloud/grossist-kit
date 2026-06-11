<?php
/**
 * GrossistKit — Admin Menu & Dashboard
 * Top-level admin menu with tabbed dashboard. Admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Admin_Menu {

    private string $current_tab = 'overview';

    public function __construct() {
        add_action( 'admin_menu',         [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    // ─── Menu registration ────────────────────────────────────────────────────

    public function register_menu(): void {
        $pending = GK_Signups::get_pending_count();
        $badge   = $pending > 0 ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';

        add_menu_page(
            'GrossistKit',
            'GrossistKit' . $badge,
            'administrator',
            'grossist-kit',
            [ $this, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#fff"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm0 6a1 1 0 011-1h6a1 1 0 010 2H4a1 1 0 01-1-1z"/></svg>' ),
            2
        );
    }

    // ─── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_grossist-kit' !== $hook ) return;
        wp_enqueue_style(
            'grossist-kit-dashboard',
            GK_PLUGIN_URL . 'assets/dashboard.css',
            [],
            GK_VERSION
        );
        wp_enqueue_script(
            'grossist-kit-dashboard',
            GK_PLUGIN_URL . 'assets/dashboard.js',
            [ 'jquery' ],
            GK_VERSION,
            true
        );
        wp_localize_script( 'grossist-kit-dashboard', 'gkData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gk_ajax' ),
        ] );
    }

    // ─── Dashboard router ─────────────────────────────────────────────────────

    public function render_dashboard(): void {
        $this->current_tab = sanitize_key( $_GET['tab'] ?? 'overview' );

        $this->render_notices();
        $this->render_header();
        $this->render_tabs();

        echo '<div class="gk-tab-content">';
        switch ( $this->current_tab ) {
            case 'signups':    $this->render_signups_tab();    break;
            case 'customers':  $this->render_customers_tab();  break;
            case 'orders':     $this->render_orders_tab();     break;
            case 'groups':     $this->render_groups_tab();     break;
            default:           $this->render_overview_tab();   break;
        }
        echo '</div>';

        $this->render_footer();
    }

    // ─── Notices ──────────────────────────────────────────────────────────────

    private function render_notices(): void {
        $notice = sanitize_key( $_GET['gk_notice'] ?? '' );
        if ( ! $notice ) return;

        $messages = [
            'approved'      => [ 'success', 'Kund godkänd och konto skapat. Välkomstmail skickat.' ],
            'rejected'      => [ 'info',    'Ansökan avvisad och borttagen.' ],
            'already_exists'=> [ 'warning', 'E-postadressen är redan registrerad.' ],
            'create_failed' => [ 'error',   'Kunde inte skapa kontot. Försök igen.' ],
            'customer_saved'=> [ 'success', 'Kund skapad.' ],
            'order_created' => [ 'success', 'Order skapad.' ],
        ];

        if ( isset( $messages[ $notice ] ) ) {
            [ $type, $msg ] = $messages[ $notice ];
            echo "<div class='gk-notice gk-notice-{$type}'>{$msg}</div>";
        }
    }

    // ─── Header ───────────────────────────────────────────────────────────────

    private function render_header(): void {
        $pending = GK_Signups::get_pending_count();
        ?>
        <div class="gk-header">
            <div class="gk-header-left">
                <div class="gk-logo">GrossistKit</div>
                <div class="gk-version">v<?php echo GK_VERSION; ?></div>
            </div>
            <div class="gk-header-right">
                <?php if ( $pending > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-header-badge">
                        <?php echo $pending; ?> väntande ansökan<?php echo $pending > 1 ? 'ar' : ''; ?>
                    </a>
                <?php endif; ?>
                <span class="gk-header-store"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
            </div>
        </div>
        <?php
    }

    // ─── Tabs ─────────────────────────────────────────────────────────────────

    private function render_tabs(): void {
        $pending = GK_Signups::get_pending_count();

        $tabs = [
            'overview'  => [ 'label' => 'Översikt',      'icon' => '◈' ],
            'signups'   => [ 'label' => 'Ansökningar',   'icon' => '◉', 'count' => $pending ],
            'customers' => [ 'label' => 'Skapa kund',    'icon' => '◎' ],
            'orders'    => [ 'label' => 'Skapa order',   'icon' => '◇' ],
            'groups'    => [ 'label' => 'Kundgrupper',   'icon' => '◆' ],
        ];

        echo '<div class="gk-tabs">';
        foreach ( $tabs as $slug => $tab ) {
            $active  = $this->current_tab === $slug ? 'gk-tab--active' : '';
            $url     = add_query_arg( [ 'page' => 'grossist-kit', 'tab' => $slug ], admin_url( 'admin.php' ) );
            $count   = isset( $tab['count'] ) && $tab['count'] > 0 ? '<span class="gk-tab-count">' . $tab['count'] . '</span>' : '';
            echo "<a href='" . esc_url( $url ) . "' class='gk-tab {$active}'>{$tab['icon']} {$tab['label']}{$count}</a>";
        }
        echo '</div>';
    }

    // ─── Overview tab ─────────────────────────────────────────────────────────

    private function render_overview_tab(): void {
        // Stats
        $customer_count = count( get_users( [ 'role' => 'customer', 'fields' => 'ID' ] ) );
        $order_count    = wc_orders_count( 'processing' ) + wc_orders_count( 'on-hold' );
        $pending_count  = GK_Signups::get_pending_count();

        $groups       = GK_Customer_Groups::get_groups();
        $group_counts = [];
        foreach ( $groups as $slug => $label ) {
            $group_counts[ $slug ] = count( get_users( [
                'meta_key'   => GK_USER_META_KEY,
                'meta_value' => $slug,
                'fields'     => 'ID',
            ] ) );
        }
        ?>
        <div class="gk-overview-grid">

            <div class="gk-stat-card">
                <div class="gk-stat-icon">👥</div>
                <div class="gk-stat-value"><?php echo $customer_count; ?></div>
                <div class="gk-stat-label">Kunder totalt</div>
            </div>

            <div class="gk-stat-card">
                <div class="gk-stat-icon">📦</div>
                <div class="gk-stat-value"><?php echo $order_count; ?></div>
                <div class="gk-stat-label">Aktiva ordrar</div>
            </div>

            <div class="gk-stat-card <?php echo $pending_count > 0 ? 'gk-stat-card--alert' : ''; ?>">
                <div class="gk-stat-icon">📋</div>
                <div class="gk-stat-value"><?php echo $pending_count; ?></div>
                <div class="gk-stat-label">Väntande ansökningar</div>
                <?php if ( $pending_count > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-stat-link">Granska →</a>
                <?php endif; ?>
            </div>

        </div>

        <div class="gk-section-title">Kunder per grupp</div>
        <div class="gk-group-grid">
            <?php foreach ( $groups as $slug => $label ) : ?>
                <div class="gk-group-card gk-group-card--<?php echo esc_attr( $slug ); ?>">
                    <div class="gk-group-label"><?php echo esc_html( $label ); ?></div>
                    <div class="gk-group-count"><?php echo $group_counts[ $slug ]; ?></div>
                    <div class="gk-group-sub">kunder</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="gk-section-title">Snabbåtgärder</div>
        <div class="gk-quick-actions">
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-action-btn">+ Skapa kund</a>
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'orders' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-action-btn">+ Skapa order</a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="gk-action-btn gk-action-btn--secondary">Produkter</a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="gk-action-btn gk-action-btn--secondary">Alla ordrar</a>
            <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="gk-action-btn gk-action-btn--secondary">Alla kunder</a>
        </div>
        <?php
    }

    // ─── Signups tab ──────────────────────────────────────────────────────────

    private function render_signups_tab(): void {
        $signups = GK_Signups::get_signups( 'pending' );
        $groups  = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <h2>B2B Ansökningar</h2>
                <span class="gk-panel-sub">Granska och godkänn nya kundansökningar</span>
            </div>

            <?php if ( empty( $signups ) ) : ?>
                <div class="gk-empty-state">
                    <div class="gk-empty-icon">✓</div>
                    <div class="gk-empty-text">Inga väntande ansökningar</div>
                    <div class="gk-empty-sub">Nya ansökningar dyker upp här direkt när de skickas in.</div>
                </div>
            <?php else : ?>
                <div class="gk-signup-list">
                    <?php foreach ( $signups as $signup ) :
                        $company = get_post_meta( $signup->ID, 'gk_company',      true );
                        $contact = get_post_meta( $signup->ID, 'gk_contact_name', true );
                        $email   = get_post_meta( $signup->ID, 'gk_email',        true );
                        $phone   = get_post_meta( $signup->ID, 'gk_phone',        true );
                        $org_num = get_post_meta( $signup->ID, 'gk_org_number',   true );
                        $address = get_post_meta( $signup->ID, 'gk_address',      true );
                        $message = get_post_meta( $signup->ID, 'gk_message',      true );
                        $submitted = get_post_meta( $signup->ID, 'gk_submitted_at', true );
                    ?>
                        <div class="gk-signup-card">
                            <div class="gk-signup-top">
                                <div class="gk-signup-company"><?php echo esc_html( $company ); ?></div>
                                <div class="gk-signup-date"><?php echo esc_html( $submitted ? date( 'd M Y, H:i', strtotime( $submitted ) ) : '–' ); ?></div>
                            </div>
                            <div class="gk-signup-meta">
                                <span><strong>Kontakt:</strong> <?php echo esc_html( $contact ); ?></span>
                                <span><strong>E-post:</strong> <?php echo esc_html( $email ); ?></span>
                                <?php if ( $phone ) : ?><span><strong>Tel:</strong> <?php echo esc_html( $phone ); ?></span><?php endif; ?>
                                <?php if ( $org_num ) : ?><span><strong>Org.nr:</strong> <?php echo esc_html( $org_num ); ?></span><?php endif; ?>
                                <?php if ( $address ) : ?><span><strong>Adress:</strong> <?php echo esc_html( $address ); ?></span><?php endif; ?>
                            </div>
                            <?php if ( $message ) : ?>
                                <div class="gk-signup-message"><?php echo esc_html( $message ); ?></div>
                            <?php endif; ?>

                            <div class="gk-signup-actions">
                                <span class="gk-group-select-label">Tilldela grupp vid godkännande:</span>
                                <div class="gk-approve-group-btns">
                                    <?php foreach ( $groups as $slug => $label ) :
                                        $approve_url = wp_nonce_url(
                                            add_query_arg( [ 'action' => 'gk_approve', 'id' => $signup->ID, 'group' => $slug ], admin_url( 'admin-post.php' ) ),
                                            'gk_approve_' . $signup->ID
                                        );
                                    ?>
                                        <a href="<?php echo esc_url( $approve_url ); ?>" class="gk-approve-btn gk-approve-btn--<?php echo esc_attr( $slug ); ?>">
                                            Godkänn som <?php echo esc_html( $label ); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'gk_reject', 'id' => $signup->ID ], admin_url( 'admin-post.php' ) ), 'gk_reject_' . $signup->ID ) ); ?>"
                                   class="gk-reject-btn"
                                   onclick="return confirm('Avvisa ansökan från <?php echo esc_js( $company ); ?>?');">
                                    Avvisa
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── Customers tab ────────────────────────────────────────────────────────

    private function render_customers_tab(): void {
        $groups = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <h2>Skapa kund</h2>
                <span class="gk-panel-sub">Lägg till en B2B-kund manuellt utan att gå via ansökningsflödet</span>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gk-form">
                <?php wp_nonce_field( 'gk_create_customer', 'gk_nonce' ); ?>
                <input type="hidden" name="action" value="gk_create_customer">

                <div class="gk-form-grid">
                    <div class="gk-form-group">
                        <label>Företagsnamn *</label>
                        <input type="text" name="gk_company" required>
                    </div>
                    <div class="gk-form-group">
                        <label>Organisationsnummer</label>
                        <input type="text" name="gk_org_number" placeholder="556000-0000">
                    </div>
                    <div class="gk-form-group">
                        <label>Kontaktperson *</label>
                        <input type="text" name="gk_first_name" required>
                    </div>
                    <div class="gk-form-group">
                        <label>Efternamn</label>
                        <input type="text" name="gk_last_name">
                    </div>
                    <div class="gk-form-group">
                        <label>E-postadress *</label>
                        <input type="email" name="gk_email" required>
                    </div>
                    <div class="gk-form-group">
                        <label>Telefonnummer</label>
                        <input type="tel" name="gk_phone">
                    </div>
                    <div class="gk-form-group gk-form-group--full">
                        <label>Leveransadress</label>
                        <input type="text" name="gk_address">
                    </div>
                    <div class="gk-form-group">
                        <label>Stad</label>
                        <input type="text" name="gk_city">
                    </div>
                    <div class="gk-form-group">
                        <label>Postnummer</label>
                        <input type="text" name="gk_postcode">
                    </div>
                    <div class="gk-form-group">
                        <label>Kundgrupp</label>
                        <select name="gk_group">
                            <?php foreach ( $groups as $slug => $label ) :
                                $note = 'bas' === $slug ? ' (standardpris)' : '';
                            ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label . $note ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gk-form-group">
                        <label>Skicka välkomstmail</label>
                        <label class="gk-toggle">
                            <input type="checkbox" name="gk_send_welcome" value="1" checked>
                            <span class="gk-toggle-slider"></span>
                            <span class="gk-toggle-label">Ja, skicka mail</span>
                        </label>
                    </div>
                </div>

                <div class="gk-form-actions">
                    <button type="submit" class="gk-btn gk-btn--primary">Skapa kund</button>
                </div>
            </form>
        </div>
        <?php

        // Register the admin-post handler inline
        add_action( 'admin_post_gk_create_customer', [ $this, 'handle_create_customer' ] );
    }

    public function handle_create_customer(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_POST['gk_nonce'] ?? '', 'gk_create_customer' ) ) wp_die( 'Invalid nonce' );

        $email      = sanitize_email( $_POST['gk_email'] ?? '' );
        $first_name = sanitize_text_field( $_POST['gk_first_name'] ?? '' );
        $company    = sanitize_text_field( $_POST['gk_company'] ?? '' );

        if ( ! $email || ! $first_name || ! $company ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'missing_fields' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $password = wp_generate_password( 12, true );
        $user_id  = wp_create_user( $email, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'create_failed' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );

        update_user_meta( $user_id, 'first_name',        $first_name );
        update_user_meta( $user_id, 'last_name',         sanitize_text_field( $_POST['gk_last_name'] ?? '' ) );
        update_user_meta( $user_id, 'billing_company',   $company );
        update_user_meta( $user_id, 'billing_email',     $email );
        update_user_meta( $user_id, 'billing_phone',     sanitize_text_field( $_POST['gk_phone'] ?? '' ) );
        update_user_meta( $user_id, 'billing_address_1', sanitize_text_field( $_POST['gk_address'] ?? '' ) );
        update_user_meta( $user_id, 'billing_city',      sanitize_text_field( $_POST['gk_city'] ?? '' ) );
        update_user_meta( $user_id, 'billing_postcode',  sanitize_text_field( $_POST['gk_postcode'] ?? '' ) );
        update_user_meta( $user_id, GK_USER_META_KEY,    sanitize_key( $_POST['gk_group'] ?? 'bas' ) );

        if ( ! empty( $_POST['gk_send_welcome'] ) ) {
            wp_new_user_notification( $user_id, null, 'both' );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'customer_saved' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Orders tab ───────────────────────────────────────────────────────────

    private function render_orders_tab(): void {
        // Get customers for dropdown
        $customers = get_users( [ 'role' => 'customer', 'orderby' => 'display_name', 'number' => 200 ] );

        // Get products for quick add
        $products = wc_get_products( [ 'status' => 'publish', 'limit' => 200, 'orderby' => 'title', 'order' => 'ASC' ] );
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <h2>Skapa order</h2>
                <span class="gk-panel-sub">Lägg en order manuellt för en befintlig kund</span>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gk-form" id="gk-order-form">
                <?php wp_nonce_field( 'gk_create_order', 'gk_nonce' ); ?>
                <input type="hidden" name="action" value="gk_create_order">

                <div class="gk-form-grid">
                    <div class="gk-form-group gk-form-group--full">
                        <label>Kund *</label>
                        <select name="gk_customer_id" required>
                            <option value="">– Välj kund –</option>
                            <?php foreach ( $customers as $customer ) :
                                $company = get_user_meta( $customer->ID, 'billing_company', true );
                                $label   = $company ? $company . ' (' . $customer->user_email . ')' : $customer->display_name . ' (' . $customer->user_email . ')';
                            ?>
                                <option value="<?php echo esc_attr( $customer->ID ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gk-form-group">
                        <label>Betalningsmetod</label>
                        <select name="gk_payment_method">
                            <option value="bacs">Faktura (30 dagar)</option>
                            <option value="cod">Kontant vid leverans</option>
                        </select>
                    </div>

                    <div class="gk-form-group">
                        <label>Orderstatus</label>
                        <select name="gk_order_status">
                            <option value="processing">Under behandling</option>
                            <option value="on-hold">Väntar</option>
                            <option value="completed">Slutförd</option>
                        </select>
                    </div>

                    <div class="gk-form-group gk-form-group--full">
                        <label>Anteckning (visas internt)</label>
                        <textarea name="gk_order_note" rows="2" placeholder="T.ex. leveransinstruktioner..."></textarea>
                    </div>
                </div>

                <div class="gk-order-products">
                    <div class="gk-order-products-header">
                        <strong>Produkter</strong>
                        <button type="button" class="gk-add-product-btn" id="gk-add-product">+ Lägg till produkt</button>
                    </div>
                    <div id="gk-product-rows">
                        <div class="gk-product-row">
                            <select name="gk_products[0][id]" class="gk-product-select">
                                <option value="">– Välj produkt –</option>
                                <?php foreach ( $products as $product ) : ?>
                                    <option value="<?php echo esc_attr( $product->get_id() ); ?>" data-price="<?php echo esc_attr( $product->get_price() ); ?>">
                                        <?php echo esc_html( $product->get_name() ); ?> – <?php echo wc_price( $product->get_price() ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="gk_products[0][qty]" value="1" min="1" class="gk-qty-input" placeholder="Antal">
                            <button type="button" class="gk-remove-product-row">✕</button>
                        </div>
                    </div>
                </div>

                <div class="gk-form-actions">
                    <button type="submit" class="gk-btn gk-btn--primary">Skapa order</button>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=shop_order' ) ); ?>" class="gk-btn gk-btn--secondary">
                        Öppna WooCommerce orderformulär
                    </a>
                </div>
            </form>
        </div>
        <?php

        add_action( 'admin_post_gk_create_order', [ $this, 'handle_create_order' ] );
    }

    public function handle_create_order(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_POST['gk_nonce'] ?? '', 'gk_create_order' ) ) wp_die( 'Invalid nonce' );

        $customer_id = (int) ( $_POST['gk_customer_id'] ?? 0 );
        $products    = $_POST['gk_products'] ?? [];

        if ( ! $customer_id || empty( $products ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'orders', 'gk_notice' => 'missing_fields' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $order = wc_create_order( [ 'customer_id' => $customer_id ] );

        foreach ( $products as $item ) {
            $product_id = (int) ( $item['id'] ?? 0 );
            $qty        = max( 1, (int) ( $item['qty'] ?? 1 ) );
            if ( $product_id ) {
                $order->add_product( wc_get_product( $product_id ), $qty );
            }
        }

        $order->set_payment_method( sanitize_key( $_POST['gk_payment_method'] ?? 'bacs' ) );
        $order->set_payment_method_title( 'Faktura' );
        $order->set_status( sanitize_key( $_POST['gk_order_status'] ?? 'processing' ) );

        $note = sanitize_textarea_field( $_POST['gk_order_note'] ?? '' );
        if ( $note ) $order->add_order_note( $note );

        // Copy billing address from customer
        $address = [
            'first_name' => get_user_meta( $customer_id, 'first_name',        true ),
            'last_name'  => get_user_meta( $customer_id, 'last_name',         true ),
            'company'    => get_user_meta( $customer_id, 'billing_company',   true ),
            'email'      => get_user_meta( $customer_id, 'billing_email',     true ),
            'phone'      => get_user_meta( $customer_id, 'billing_phone',     true ),
            'address_1'  => get_user_meta( $customer_id, 'billing_address_1', true ),
            'city'       => get_user_meta( $customer_id, 'billing_city',      true ),
            'postcode'   => get_user_meta( $customer_id, 'billing_postcode',  true ),
            'country'    => 'SE',
        ];
        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );

        $order->calculate_totals();
        $order->save();

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'orders', 'gk_notice' => 'order_created' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Groups tab ───────────────────────────────────────────────────────────

    private function render_groups_tab(): void {
        $groups = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <h2>Kundgrupper</h2>
                <span class="gk-panel-sub">Nuvarande grupper och hur de fungerar</span>
            </div>

            <div class="gk-groups-info-grid">
                <?php foreach ( $groups as $slug => $label ) :
                    $count    = count( get_users( [ 'meta_key' => GK_USER_META_KEY, 'meta_value' => $slug, 'fields' => 'ID' ] ) );
                    $is_base  = 'bas' === $slug;
                ?>
                    <div class="gk-group-info-card gk-group-card--<?php echo esc_attr( $slug ); ?>">
                        <div class="gk-group-info-top">
                            <div class="gk-group-info-name"><?php echo esc_html( $label ); ?></div>
                            <div class="gk-group-info-count"><?php echo $count; ?> kunder</div>
                        </div>
                        <div class="gk-group-info-desc">
                            <?php if ( $is_base ) : ?>
                                Ser alltid WooCommerce standardpris. Inget eget prisfält på produkten.
                            <?php else : ?>
                                Ser fast pris satt per produkt under fliken <em>Priser per kundgrupp</em>. Faller tillbaka på standardpris om inget pris är satt.
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url( add_query_arg( [ 'role' => 'customer', 'gk_group_filter' => $slug ], admin_url( 'users.php' ) ) ); ?>" class="gk-group-info-link">
                            Visa kunder →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gk-info-box">
                <strong>Prissättning per produkt</strong><br>
                Öppna en produkt i WooCommerce → fliken <em>Allmänt</em> → scrolla ned till <em>Priser per kundgrupp</em>. Ange ett fast pris för Silver, Guld och VIP. Lämna tomt om standardpriset ska gälla.
            </div>

            <div class="gk-info-box">
                <strong>Tilldela kundgrupp</strong><br>
                Gå till <em>Användare → Alla användare</em>, klicka på en kund och välj grupp längst ned på profilsidan. Du kan också använda massåtgärder i användarlistan.
            </div>
        </div>
        <?php
    }

    // ─── Footer ───────────────────────────────────────────────────────────────

    private function render_footer(): void {
        ?>
        <div class="gk-footer">
            GrossistKit <?php echo GK_VERSION; ?> &nbsp;·&nbsp; Linmad Gross
        </div>
        <?php
    }
}
