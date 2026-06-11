<?php
/**
 * GrossistKit — Admin Menu & Dashboard
 * Top-level admin menu with tabbed dashboard. Admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Admin_Menu {

    private string $current_tab = 'overview';

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_gk_create_customer', [ $this, 'handle_create_customer' ] );
        add_action( 'admin_post_gk_create_order',    [ $this, 'handle_create_order' ] );
    }

    // ─── Menu ────────────────────────────────────────────────────────────────

    public function register_menu(): void {
        $pending = GK_Signups::get_pending_count();
        $badge   = $pending > 0 ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';
        add_menu_page(
            'GrossistKit', 'GrossistKit' . $badge, 'administrator',
            'grossist-kit', [ $this, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#fff"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm0 6a1 1 0 011-1h6a1 1 0 010 2H4a1 1 0 01-1-1z"/></svg>'),
            2
        );
    }

    // ─── Assets ──────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_grossist-kit' !== $hook ) return;
        wp_enqueue_style( 'grossist-kit-dashboard', GK_PLUGIN_URL . 'assets/dashboard.css', [], GK_VERSION );
        wp_enqueue_script( 'grossist-kit-dashboard', GK_PLUGIN_URL . 'assets/dashboard.js', [ 'jquery' ], GK_VERSION, true );
        wp_localize_script( 'grossist-kit-dashboard', 'gkData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gk_ajax' ),
        ] );
    }

    // ─── Router ───────────────────────────────────────────────────────────────

    public function render_dashboard(): void {
        $this->current_tab = sanitize_key( $_GET['tab'] ?? 'overview' );
        $this->render_notices();
        $this->render_header();
        $this->render_tabs();
        echo '<div class="gk-tab-content">';
        switch ( $this->current_tab ) {
            case 'signups':   $this->render_signups_tab();   break;
            case 'customers': $this->render_customers_tab(); break;
            case 'orders':    $this->render_orders_tab();    break;
            case 'groups':    $this->render_groups_tab();    break;
            default:          $this->render_overview_tab();  break;
        }
        echo '</div>';
        $this->render_footer();
    }

    // ─── Notices ─────────────────────────────────────────────────────────────

    private function render_notices(): void {
        $notice = sanitize_key( $_GET['gk_notice'] ?? '' );
        if ( ! $notice ) return;
        $messages = [
            'approved'       => [ 'success', 'Kund godkänd och konto skapat. Välkomstmail skickat.' ],
            'rejected'       => [ 'info',    'Ansökan avvisad och borttagen.' ],
            'already_exists' => [ 'warning', 'E-postadressen är redan registrerad.' ],
            'create_failed'  => [ 'error',   'Kunde inte skapa kontot. Försök igen.' ],
            'customer_saved' => [ 'success', 'Kund skapad.' ],
            'order_created'  => [ 'success', 'Order skapad.' ],
            'missing_fields' => [ 'error',   'Fyll i alla obligatoriska fält.' ],
        ];
        if ( isset( $messages[ $notice ] ) ) {
            [ $type, $msg ] = $messages[ $notice ];
            echo "<div class='gk-notice gk-notice-{$type}'><span class='gk-notice-icon'></span>{$msg}</div>";
        }
    }

    // ─── Header ──────────────────────────────────────────────────────────────

    private function render_header(): void {
        $pending   = GK_Signups::get_pending_count();
        $site_name = get_bloginfo( 'name' );
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
                <span class="gk-header-store"><?php echo esc_html( $site_name ); ?></span>
            </div>
        </div>
        <?php
    }

    // ─── Tabs ────────────────────────────────────────────────────────────────

    private function render_tabs(): void {
        $pending = GK_Signups::get_pending_count();
        $tabs = [
            'overview'  => [ 'label' => 'Översikt',    'icon' => '⊞' ],
            'signups'   => [ 'label' => 'Ansökningar', 'icon' => '⊡', 'count' => $pending ],
            'customers' => [ 'label' => 'Skapa kund',  'icon' => '⊕' ],
            'orders'    => [ 'label' => 'Skapa order', 'icon' => '⊟' ],
            'groups'    => [ 'label' => 'Kundgrupper', 'icon' => '⊠' ],
        ];
        echo '<div class="gk-tabs">';
        foreach ( $tabs as $slug => $tab ) {
            $active = $this->current_tab === $slug ? 'gk-tab--active' : '';
            $url    = add_query_arg( [ 'page' => 'grossist-kit', 'tab' => $slug ], admin_url( 'admin.php' ) );
            $count  = ( isset( $tab['count'] ) && $tab['count'] > 0 ) ? '<span class="gk-tab-count">' . $tab['count'] . '</span>' : '';
            echo "<a href='" . esc_url( $url ) . "' class='gk-tab {$active}'><span class='gk-tab-icon'>{$tab['icon']}</span>{$tab['label']}{$count}</a>";
        }
        echo '</div>';
    }

    // ─── Overview ────────────────────────────────────────────────────────────

    private function render_overview_tab(): void {
        $customer_count = count( get_users( [ 'role' => 'customer', 'fields' => 'ID' ] ) );
        $order_count    = wc_orders_count( 'processing' ) + wc_orders_count( 'on-hold' );
        $pending_count  = GK_Signups::get_pending_count();
        $groups         = GK_Customer_Groups::get_groups();
        $group_counts   = [];
        foreach ( $groups as $slug => $label ) {
            $group_counts[ $slug ] = count( get_users( [ 'meta_key' => GK_USER_META_KEY, 'meta_value' => $slug, 'fields' => 'ID' ] ) );
        }
        $site_name = get_bloginfo( 'name' );
        ?>
        <div class="gk-overview-welcome">
            <div class="gk-welcome-text">
                <div class="gk-welcome-title">Välkommen till GrossistKit</div>
                <div class="gk-welcome-sub"><?php echo esc_html( $site_name ); ?> · B2B hanteringspanel</div>
            </div>
        </div>

        <div class="gk-stat-row">
            <div class="gk-stat-card">
                <div class="gk-stat-top">
                    <span class="gk-stat-label">Kunder totalt</span>
                    <span class="gk-stat-icon-sm">👥</span>
                </div>
                <div class="gk-stat-value"><?php echo $customer_count; ?></div>
            </div>
            <div class="gk-stat-card">
                <div class="gk-stat-top">
                    <span class="gk-stat-label">Aktiva ordrar</span>
                    <span class="gk-stat-icon-sm">📦</span>
                </div>
                <div class="gk-stat-value"><?php echo $order_count; ?></div>
            </div>
            <div class="gk-stat-card <?php echo $pending_count > 0 ? 'gk-stat-card--alert' : ''; ?>">
                <div class="gk-stat-top">
                    <span class="gk-stat-label">Väntande ansökningar</span>
                    <span class="gk-stat-icon-sm">📋</span>
                </div>
                <div class="gk-stat-value"><?php echo $pending_count; ?></div>
                <?php if ( $pending_count > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-stat-link">Granska →</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="gk-two-col">
            <div>
                <div class="gk-section-label">Kunder per grupp</div>
                <div class="gk-group-row">
                    <?php foreach ( $groups as $slug => $label ) : ?>
                        <div class="gk-group-pill gk-group-pill--<?php echo esc_attr( $slug ); ?>">
                            <span class="gk-group-pill-name"><?php echo esc_html( $label ); ?></span>
                            <span class="gk-group-pill-count"><?php echo $group_counts[ $slug ]; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="gk-section-label">Snabbåtgärder</div>
                <div class="gk-quick-actions">
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-action-btn">+ Ny kund</a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'orders' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-action-btn">+ Ny order</a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="gk-action-btn gk-action-btn--ghost">Produkter</a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="gk-action-btn gk-action-btn--ghost">Alla ordrar</a>
                    <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="gk-action-btn gk-action-btn--ghost">Alla kunder</a>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Signups ─────────────────────────────────────────────────────────────

    private function render_signups_tab(): void {
        $signups = GK_Signups::get_signups( 'pending' );
        $groups  = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <div>
                    <h2>B2B Ansökningar</h2>
                    <p class="gk-panel-sub">Granska och godkänn nya kundansökningar</p>
                </div>
            </div>

            <?php if ( empty( $signups ) ) : ?>
                <div class="gk-empty-state">
                    <div class="gk-empty-icon">✓</div>
                    <div class="gk-empty-text">Inga väntande ansökningar</div>
                    <div class="gk-empty-sub">Nya ansökningar visas här direkt när de skickas in.</div>
                </div>
            <?php else : ?>
                <div class="gk-signup-list">
                    <?php foreach ( $signups as $signup ) :
                        $company   = get_post_meta( $signup->ID, 'gk_company',      true );
                        $contact   = get_post_meta( $signup->ID, 'gk_contact_name', true );
                        $email     = get_post_meta( $signup->ID, 'gk_email',        true );
                        $phone     = get_post_meta( $signup->ID, 'gk_phone',        true );
                        $org_num   = get_post_meta( $signup->ID, 'gk_org_number',   true );
                        $city      = get_post_meta( $signup->ID, 'gk_city',         true );
                        $message   = get_post_meta( $signup->ID, 'gk_message',      true );
                        $submitted = get_post_meta( $signup->ID, 'gk_submitted_at', true );
                        $card_id   = 'gk-signup-' . $signup->ID;
                    ?>
                        <div class="gk-signup-card" id="<?php echo esc_attr( $card_id ); ?>">
                            <div class="gk-signup-head">
                                <div class="gk-signup-company"><?php echo esc_html( $company ); ?></div>
                                <div class="gk-signup-meta-inline">
                                    <?php if ( $org_num ) : ?><span class="gk-badge"><?php echo esc_html( $org_num ); ?></span><?php endif; ?>
                                    <span class="gk-signup-date"><?php echo esc_html( $submitted ? date( 'd M Y H:i', strtotime( $submitted ) ) : '–' ); ?></span>
                                </div>
                            </div>

                            <div class="gk-signup-summary">
                                <span><strong>Kontakt:</strong> <?php echo esc_html( $contact ); ?></span>
                                <span><strong>E-post:</strong> <?php echo esc_html( $email ); ?></span>
                                <?php if ( $phone ) : ?><span><strong>Tel:</strong> <?php echo esc_html( $phone ); ?></span><?php endif; ?>
                                <?php if ( $city )  : ?><span><strong>Stad:</strong> <?php echo esc_html( $city ); ?></span><?php endif; ?>
                            </div>

                            <?php if ( $message ) : ?>
                                <button type="button" class="gk-expand-btn" data-target="<?php echo esc_attr( $card_id . '-msg' ); ?>">
                                    Visa meddelande ▾
                                </button>
                                <div class="gk-expandable" id="<?php echo esc_attr( $card_id . '-msg' ); ?>" style="display:none;">
                                    <div class="gk-signup-message"><?php echo esc_html( $message ); ?></div>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="gk-expand-btn" data-target="<?php echo esc_attr( $card_id . '-full' ); ?>">
                                Visa hela ansökan ▾
                            </button>
                            <div class="gk-expandable" id="<?php echo esc_attr( $card_id . '-full' ); ?>" style="display:none;">
                                <div class="gk-full-form">
                                    <div class="gk-full-form-row"><span>Företagsnamn</span><strong><?php echo esc_html( $company ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Organisationsnummer</span><strong><?php echo esc_html( $org_num ?: '–' ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Kontaktperson</span><strong><?php echo esc_html( $contact ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>E-postadress</span><strong><?php echo esc_html( $email ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Telefonnummer</span><strong><?php echo esc_html( $phone ?: '–' ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Stad</span><strong><?php echo esc_html( $city ?: '–' ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Meddelande</span><strong><?php echo esc_html( $message ?: '–' ); ?></strong></div>
                                    <div class="gk-full-form-row"><span>Skickad</span><strong><?php echo esc_html( $submitted ? date( 'd M Y H:i', strtotime( $submitted ) ) : '–' ); ?></strong></div>
                                </div>
                            </div>

                            <div class="gk-signup-actions">
                                <span class="gk-actions-label">Godkänn som:</span>
                                <div class="gk-approve-btns">
                                    <?php foreach ( $groups as $slug => $label ) :
                                        $url = wp_nonce_url(
                                            add_query_arg( [ 'action' => 'gk_approve', 'id' => $signup->ID, 'group' => $slug ], admin_url( 'admin-post.php' ) ),
                                            'gk_approve_' . $signup->ID
                                        );
                                    ?>
                                        <a href="<?php echo esc_url( $url ); ?>" class="gk-approve-btn gk-approve-btn--<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
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

    // ─── Create customer ──────────────────────────────────────────────────────

    private function render_customers_tab(): void {
        $groups = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <div>
                    <h2>Skapa kund</h2>
                    <p class="gk-panel-sub">Lägg till en B2B-kund manuellt</p>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gk-form">
                <?php wp_nonce_field( 'gk_create_customer', 'gk_nonce' ); ?>
                <input type="hidden" name="action" value="gk_create_customer">

                <div class="gk-form-grid">
                    <div class="gk-field">
                        <label>Företagsnamn *</label>
                        <input type="text" name="gk_company" required placeholder="Företaget AB">
                    </div>
                    <div class="gk-field">
                        <label>Organisationsnummer</label>
                        <input type="text" name="gk_org_number" placeholder="556000-0000">
                    </div>
                    <div class="gk-field">
                        <label>Kontaktperson *</label>
                        <input type="text" name="gk_first_name" required placeholder="Förnamn Efternamn">
                    </div>
                    <div class="gk-field">
                        <label>Efternamn</label>
                        <input type="text" name="gk_last_name" placeholder="Efternamn">
                    </div>
                    <div class="gk-field">
                        <label>E-postadress *</label>
                        <input type="email" name="gk_email" required placeholder="info@foretaget.se">
                    </div>
                    <div class="gk-field">
                        <label>Telefonnummer</label>
                        <input type="tel" name="gk_phone" placeholder="+46 70 000 00 00">
                    </div>
                    <div class="gk-field gk-field--full">
                        <label>Leveransadress</label>
                        <input type="text" name="gk_address" placeholder="Gatuadress">
                    </div>
                    <div class="gk-field">
                        <label>Stad</label>
                        <input type="text" name="gk_city" placeholder="Stockholm">
                    </div>
                    <div class="gk-field">
                        <label>Postnummer</label>
                        <input type="text" name="gk_postcode" placeholder="123 45">
                    </div>
                    <div class="gk-field">
                        <label>Kundgrupp</label>
                        <select name="gk_group">
                            <?php foreach ( $groups as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?><?php echo 'bas' === $slug ? ' (standardpris)' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gk-field">
                        <label>Välkomstmail</label>
                        <label class="gk-toggle">
                            <input type="checkbox" name="gk_send_welcome" value="1" checked>
                            <span class="gk-toggle-track"><span class="gk-toggle-thumb"></span></span>
                            <span>Skicka mail</span>
                        </label>
                    </div>
                </div>

                <div class="gk-form-footer">
                    <button type="submit" class="gk-btn gk-btn--primary">Skapa kund</button>
                </div>
            </form>
        </div>
        <?php
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

        update_user_meta( $user_id, 'first_name',                    $first_name );
        update_user_meta( $user_id, 'last_name',                     sanitize_text_field( $_POST['gk_last_name'] ?? '' ) );
        update_user_meta( $user_id, 'billing_company',               $company );
        update_user_meta( $user_id, 'billing_email',                 $email );
        update_user_meta( $user_id, 'billing_phone',                 sanitize_text_field( $_POST['gk_phone'] ?? '' ) );
        update_user_meta( $user_id, 'billing_address_1',             sanitize_text_field( $_POST['gk_address'] ?? '' ) );
        update_user_meta( $user_id, 'billing_city',                  sanitize_text_field( $_POST['gk_city'] ?? '' ) );
        update_user_meta( $user_id, 'billing_postcode',              sanitize_text_field( $_POST['gk_postcode'] ?? '' ) );
        update_user_meta( $user_id, 'billing_organisation_number',   sanitize_text_field( $_POST['gk_org_number'] ?? '' ) );
        update_user_meta( $user_id, GK_USER_META_KEY,                sanitize_key( $_POST['gk_group'] ?? 'bas' ) );

        if ( ! empty( $_POST['gk_send_welcome'] ) ) {
            wp_new_user_notification( $user_id, null, 'both' );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'customer_saved' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Create order ─────────────────────────────────────────────────────────

    private function render_orders_tab(): void {
        $customers = get_users( [ 'role' => 'customer', 'orderby' => 'display_name', 'number' => 200 ] );
        $products  = wc_get_products( [ 'status' => 'publish', 'limit' => 200, 'orderby' => 'title', 'order' => 'ASC' ] );
        $groups    = GK_Customer_Groups::get_groups();

        // Build product data with SKU for JS price lookup per group
        $product_data = [];
        foreach ( $products as $product ) {
            $prices = [ 'default' => $product->get_price() ];
            foreach ( GK_Customer_Groups::get_priced_groups() as $slug => $label ) {
                $meta = get_post_meta( $product->get_id(), GK_PRICE_META_PREFIX . $slug, true );
                $prices[ $slug ] = $meta !== '' ? $meta : $product->get_price();
            }
            $product_data[ $product->get_id() ] = [
                'price'  => $prices,
                'sku'    => $product->get_sku(),
            ];
        }
        wp_localize_script( 'grossist-kit-dashboard', 'gkProducts', $product_data );
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <div>
                    <h2>Skapa order</h2>
                    <p class="gk-panel-sub">Lägg en order manuellt för en befintlig kund</p>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gk-form" id="gk-order-form">
                <?php wp_nonce_field( 'gk_create_order', 'gk_nonce' ); ?>
                <input type="hidden" name="action" value="gk_create_order">

                <div class="gk-form-grid">
                    <div class="gk-field gk-field--full">
                        <label>Kund *</label>
                        <select name="gk_customer_id" id="gk-customer-select" required>
                            <option value="">– Välj kund –</option>
                            <?php foreach ( $customers as $c ) :
                                $co    = get_user_meta( $c->ID, 'billing_company',  true );
                                $grp   = get_user_meta( $c->ID, 'lg_kundgrupp',     true );
                                $lbl   = $co ? $co . ' — ' . $c->user_email : $c->display_name . ' — ' . $c->user_email;
                            ?>
                                <option value="<?php echo esc_attr( $c->ID ); ?>" data-group="<?php echo esc_attr( $grp ?: 'bas' ); ?>">
                                    <?php echo esc_html( $lbl ); ?><?php echo $grp ? ' [' . esc_html( strtoupper( $grp ) ) . ']' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gk-field">
                        <label>Betalningsmetod</label>
                        <select name="gk_payment_method">
                            <option value="bacs">Faktura (30 dagar)</option>
                            <option value="cod">Kontant vid leverans</option>
                        </select>
                    </div>
                    <div class="gk-field">
                        <label>Orderstatus</label>
                        <select name="gk_order_status">
                            <option value="processing">Under behandling</option>
                            <option value="on-hold">Väntar</option>
                            <option value="completed">Slutförd</option>
                        </select>
                    </div>
                    <div class="gk-field gk-field--full">
                        <label>Anteckning (intern)</label>
                        <textarea name="gk_order_note" rows="2" placeholder="T.ex. leveransinstruktioner..."></textarea>
                    </div>
                </div>

                <div class="gk-products-block">
                    <div class="gk-products-header">
                        <strong>Produkter</strong>
                        <button type="button" id="gk-add-product" class="gk-add-row-btn">+ Lägg till rad</button>
                    </div>
                    <div class="gk-products-table-head">
                        <span>Produkt</span>
                        <span>SKU</span>
                        <span>Á-pris</span>
                        <span>Antal</span>
                        <span></span>
                    </div>
                    <div id="gk-product-rows">
                        <div class="gk-product-row">
                            <select name="gk_products[0][id]" class="gk-product-select">
                                <option value="">– Välj produkt –</option>
                                <?php foreach ( $products as $p ) : ?>
                                    <option value="<?php echo esc_attr( $p->get_id() ); ?>">
                                        <?php echo esc_html( $p->get_name() ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="gk-sku-display">–</span>
                            <span class="gk-price-display">–</span>
                            <input type="number" name="gk_products[0][qty]" value="1" min="1" class="gk-qty-input">
                            <button type="button" class="gk-remove-row">✕</button>
                        </div>
                    </div>
                </div>

                <div class="gk-form-footer">
                    <button type="submit" class="gk-btn gk-btn--primary">Skapa order</button>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=shop_order' ) ); ?>" class="gk-btn gk-btn--ghost">Öppna WC-formulär</a>
                </div>
            </form>
        </div>
        <?php
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

        // Get customer group so correct prices are applied
        $group = get_user_meta( $customer_id, GK_USER_META_KEY, true ) ?: 'bas';

        $order = wc_create_order( [ 'customer_id' => $customer_id ] );

        foreach ( $products as $item ) {
            $product_id = (int) ( $item['id'] ?? 0 );
            $qty        = max( 1, (int) ( $item['qty'] ?? 1 ) );
            if ( ! $product_id ) continue;

            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;

            // Apply group price if applicable
            if ( 'bas' !== $group ) {
                $group_price = get_post_meta( $product_id, GK_PRICE_META_PREFIX . $group, true );
                if ( $group_price !== '' && is_numeric( $group_price ) ) {
                    $item_id = $order->add_product( $product, $qty );
                    // Override line item price with group price
                    if ( $item_id ) {
                        $line_item = $order->get_item( $item_id );
                        $line_item->set_subtotal( $group_price * $qty );
                        $line_item->set_total( $group_price * $qty );
                        $line_item->save();
                    }
                    continue;
                }
            }
            $order->add_product( $product, $qty );
        }

        $order->set_payment_method( sanitize_key( $_POST['gk_payment_method'] ?? 'bacs' ) );
        $order->set_payment_method_title( 'Faktura' );
        $order->set_status( sanitize_key( $_POST['gk_order_status'] ?? 'processing' ) );
        $order->update_meta_data( '_gk_group', $group );

        $note = sanitize_textarea_field( $_POST['gk_order_note'] ?? '' );
        if ( $note ) $order->add_order_note( $note );

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

    // ─── Groups ───────────────────────────────────────────────────────────────

    private function render_groups_tab(): void {
        $groups = GK_Customer_Groups::get_groups();
        ?>
        <div class="gk-panel">
            <div class="gk-panel-header">
                <div>
                    <h2>Kundgrupper</h2>
                    <p class="gk-panel-sub">Hur grupperna fungerar och antal kunder per grupp</p>
                </div>
            </div>
            <div class="gk-groups-grid">
                <?php foreach ( $groups as $slug => $label ) :
                    $count   = count( get_users( [ 'meta_key' => GK_USER_META_KEY, 'meta_value' => $slug, 'fields' => 'ID' ] ) );
                    $is_base = 'bas' === $slug;
                ?>
                    <div class="gk-group-card gk-group-card--<?php echo esc_attr( $slug ); ?>">
                        <div class="gk-group-card-top">
                            <span class="gk-group-card-name"><?php echo esc_html( $label ); ?></span>
                            <span class="gk-group-card-count"><?php echo $count; ?> kunder</span>
                        </div>
                        <p class="gk-group-card-desc">
                            <?php if ( $is_base ) : ?>
                                Ser alltid WooCommerce standardpris.
                            <?php else : ?>
                                Fast pris per produkt. Faller tillbaka på standardpris om inget pris är satt.
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="gk-group-card-link">Visa kunder →</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gk-info-strip">
                <div class="gk-info-item">
                    <strong>Prissättning</strong>
                    Produktredigering → <em>Priser per kundgrupp</em>. Ange fast pris för Silver, Guld, VIP. Tomt = standardpris.
                </div>
                <div class="gk-info-item">
                    <strong>Tilldela grupp</strong>
                    Användarprofil i admin eller via massåtgärder i användarlistan.
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Footer ───────────────────────────────────────────────────────────────

    private function render_footer(): void {
        ?>
        <div class="gk-footer">
            GrossistKit <?php echo GK_VERSION; ?> &nbsp;·&nbsp; <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
        </div>
        <?php
    }
}
