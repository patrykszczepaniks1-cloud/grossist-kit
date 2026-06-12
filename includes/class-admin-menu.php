<?php
/**
 * GrossistKit — Admin Menu & Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Admin_Menu {

    private string $current_tab = 'overview';

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_gk_create_customer',  [ $this, 'handle_create_customer' ] );
        add_action( 'admin_post_gk_create_order',     [ $this, 'handle_create_order' ] );
        add_action( 'admin_post_gk_edit_customer',    [ $this, 'handle_edit_customer' ] );
    }

    public function register_menu(): void {
        $pending = GK_Signups::get_pending_count();
        $badge   = $pending > 0 ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';
        add_menu_page(
            'GrossistKit', 'GrossistKit' . $badge, 'administrator',
            'grossist-kit', [ $this, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>' ),
            2
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_grossist-kit' !== $hook ) return;

        // Material Icons — loaded as a proper link tag, not @import
        wp_enqueue_style(
            'material-icons-round',
            'https://fonts.googleapis.com/icon?family=Material+Icons+Round',
            [],
            null
        );

        wp_enqueue_style(
            'grossist-kit-dashboard',
            GK_PLUGIN_URL . 'assets/dashboard.css',
            [ 'material-icons-round' ],
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
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'gk_ajax' ),
            'editNonce' => wp_create_nonce( 'gk_edit_customer' ),
            'adminPost' => admin_url( 'admin-post.php' ),
        ] );
    }

    // ─── Dashboard shell ─────────────────────────────────────────────────────

    public function render_dashboard(): void {
        $this->current_tab = sanitize_key( $_GET['tab'] ?? 'overview' );
        $pending   = GK_Signups::get_pending_count();
        $site_name = get_bloginfo( 'name' );

        $nav = [
            'overview'  => [ 'icon' => 'dashboard',       'label' => 'Översikt' ],
            'signups'   => [ 'icon' => 'assignment_ind',   'label' => 'Ansökningar', 'count' => $pending ],
            'customers' => [ 'icon' => 'people',           'label' => 'Kunder' ],
            'orders'    => [ 'icon' => 'shopping_cart',    'label' => 'Ny order' ],
            'groups'    => [ 'icon' => 'layers',           'label' => 'Kundgrupper' ],
        ];

        $page_titles = [
            'overview'  => 'Översikt',
            'signups'   => 'B2B Ansökningar',
            'customers' => 'Kunder',
            'orders'    => 'Skapa order',
            'groups'    => 'Kundgrupper',
        ];
        $page_subs = [
            'overview'  => 'Välkommen tillbaka — här är en snabb bild av butiken',
            'signups'   => 'Granska och godkänn inkommande B2B-ansökningar',
            'customers' => 'Hantera och redigera kundkonton',
            'orders'    => 'Lägg en order manuellt för en befintlig kund',
            'groups'    => 'Kundgrupper och prisnivåer',
        ];
        ?>
        <div class="gk-root">

            <!-- SIDEBAR -->
            <aside class="gk-sidebar">
                <div class="gk-sidebar-brand">
                    <div class="gk-brand-icon">
                        <span class="material-icons-round">storefront</span>
                    </div>
                    <div class="gk-brand-text">
                        <div class="gk-brand-name">GrossistKit</div>
                        <div class="gk-brand-version">v<?php echo GK_VERSION; ?></div>
                    </div>
                </div>

                <nav class="gk-nav">
                    <div class="gk-nav-label">Meny</div>
                    <?php foreach ( $nav as $slug => $item ) :
                        $active = $this->current_tab === $slug ? 'active' : '';
                        $url    = add_query_arg( [ 'page' => 'grossist-kit', 'tab' => $slug ], admin_url( 'admin.php' ) );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="gk-nav-item <?php echo $active; ?>">
                            <span class="material-icons-round"><?php echo esc_html( $item['icon'] ); ?></span>
                            <span><?php echo esc_html( $item['label'] ); ?></span>
                            <?php if ( ! empty( $item['count'] ) && $item['count'] > 0 ) : ?>
                                <span class="gk-nav-badge"><?php echo (int) $item['count']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="gk-nav-label" style="margin-top:8px;">WooCommerce</div>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="gk-nav-item">
                        <span class="material-icons-round">inventory_2</span>
                        <span>Produkter</span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="gk-nav-item">
                        <span class="material-icons-round">receipt_long</span>
                        <span>Alla ordrar</span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="gk-nav-item">
                        <span class="material-icons-round">manage_accounts</span>
                        <span>Alla användare</span>
                    </a>
                </nav>

                <div class="gk-sidebar-footer">
                    <div class="gk-site-chip">
                        <span class="material-icons-round">language</span>
                        <span><?php echo esc_html( $site_name ); ?></span>
                    </div>
                </div>
            </aside>

            <!-- MAIN -->
            <div class="gk-main">
                <div class="gk-topbar">
                    <div class="gk-topbar-title"><?php echo esc_html( $page_titles[ $this->current_tab ] ?? 'GrossistKit' ); ?></div>
                    <div class="gk-topbar-actions">
                        <?php if ( $pending > 0 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-btn gk-btn--ghost" style="font-size:12px;padding:6px 12px;">
                                <span class="material-icons-round" style="font-size:14px;color:var(--c-amber);">notifications_active</span>
                                <?php echo $pending; ?> väntande
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="gk-page">
                    <?php $this->render_notices(); ?>

                    <div class="gk-page-heading">
                        <h1><?php echo esc_html( $page_titles[ $this->current_tab ] ?? '' ); ?></h1>
                        <p><?php echo esc_html( $page_subs[ $this->current_tab ] ?? '' ); ?></p>
                    </div>

                    <?php
                    switch ( $this->current_tab ) {
                        case 'signups':   $this->render_signups_tab();   break;
                        case 'customers': $this->render_customers_tab(); break;
                        case 'orders':    $this->render_orders_tab();    break;
                        case 'groups':    $this->render_groups_tab();    break;
                        default:          $this->render_overview_tab();  break;
                    }
                    ?>

                    <div class="gk-footer">GrossistKit <?php echo GK_VERSION; ?> · <?php echo esc_html( $site_name ); ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Notices ─────────────────────────────────────────────────────────────

    private function render_notices(): void {
        $notice = sanitize_key( $_GET['gk_notice'] ?? '' );
        if ( ! $notice ) return;
        $messages = [
            'approved'       => [ 'success', 'check_circle',  'Kund godkänd och konto skapat. Välkomstmail skickat.' ],
            'rejected'       => [ 'info',    'info',          'Ansökan avvisad och borttagen.' ],
            'already_exists' => [ 'warning', 'warning',       'E-postadressen är redan registrerad.' ],
            'create_failed'  => [ 'error',   'error',         'Kunde inte skapa kontot. Försök igen.' ],
            'customer_saved' => [ 'success', 'check_circle',  'Kund skapad.' ],
            'customer_updated'=> [ 'success','check_circle',  'Kund uppdaterad.' ],
            'order_created'  => [ 'success', 'check_circle',  'Order skapad.' ],
            'missing_fields' => [ 'error',   'error',         'Fyll i alla obligatoriska fält.' ],
        ];
        if ( isset( $messages[ $notice ] ) ) {
            [ $type, $icon, $msg ] = $messages[ $notice ];
            echo "<div class='gk-notice gk-notice-{$type}'><span class='material-icons-round'>{$icon}</span>{$msg}</div>";
        }
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
        ?>
        <div class="gk-kpi-grid">
            <div class="gk-kpi">
                <div class="gk-kpi-header">
                    <span class="gk-kpi-label">Kunder totalt</span>
                    <div class="gk-kpi-icon"><span class="material-icons-round">people</span></div>
                </div>
                <div class="gk-kpi-value"><?php echo $customer_count; ?></div>
            </div>
            <div class="gk-kpi">
                <div class="gk-kpi-header">
                    <span class="gk-kpi-label">Aktiva ordrar</span>
                    <div class="gk-kpi-icon"><span class="material-icons-round">shopping_bag</span></div>
                </div>
                <div class="gk-kpi-value"><?php echo $order_count; ?></div>
            </div>
            <div class="gk-kpi <?php echo $pending_count > 0 ? 'gk-kpi--alert' : ''; ?>">
                <div class="gk-kpi-header">
                    <span class="gk-kpi-label">Väntande ansökningar</span>
                    <div class="gk-kpi-icon"><span class="material-icons-round">assignment_ind</span></div>
                </div>
                <div class="gk-kpi-value"><?php echo $pending_count; ?></div>
                <?php if ( $pending_count > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-kpi-cta">
                        Granska nu <span class="material-icons-round">arrow_forward</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="gk-grid-2">
            <div class="gk-card">
                <div class="gk-card-header">
                    <span class="material-icons-round">layers</span>
                    <h3>Kunder per grupp</h3>
                </div>
                <div class="gk-card-body">
                    <div class="gk-group-list">
                        <?php foreach ( $groups as $slug => $label ) : ?>
                            <div class="gk-group-row">
                                <div class="gk-group-row-left">
                                    <div class="gk-group-dot gk-dot-<?php echo esc_attr( $slug ); ?>"></div>
                                    <span class="gk-group-row-name"><?php echo esc_html( $label ); ?></span>
                                </div>
                                <span class="gk-group-row-count"><?php echo $group_counts[ $slug ]; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="gk-card">
                <div class="gk-card-header">
                    <span class="material-icons-round">bolt</span>
                    <h3>Snabbåtgärder</h3>
                </div>
                <div class="gk-card-body">
                    <div class="gk-quick-grid">
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-quick-btn gk-quick-btn--accent">
                            <span class="material-icons-round">person_add</span> Ny kund
                        </a>
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'orders' ], admin_url( 'admin.php' ) ) ); ?>" class="gk-quick-btn gk-quick-btn--accent">
                            <span class="material-icons-round">add_shopping_cart</span> Ny order
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="gk-quick-btn">
                            <span class="material-icons-round">inventory_2</span> Produkter
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="gk-quick-btn">
                            <span class="material-icons-round">receipt_long</span> Alla ordrar
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Signups ─────────────────────────────────────────────────────────────

    private function render_signups_tab(): void {
        $signups = GK_Signups::get_signups( 'pending' );
        $groups  = GK_Customer_Groups::get_groups();

        if ( empty( $signups ) ) {
            echo '<div class="gk-panel"><div class="gk-empty-state">
                <span class="material-icons-round">check_circle</span>
                <div class="gk-empty-title">Inga väntande ansökningar</div>
                <div class="gk-empty-sub">Nya ansökningar visas här direkt när de skickas in.</div>
            </div></div>';
            return;
        }

        echo '<div class="gk-signup-list">';
        foreach ( $signups as $signup ) {
            $company   = get_post_meta( $signup->ID, 'gk_company',      true );
            $contact   = get_post_meta( $signup->ID, 'gk_contact_name', true );
            $email     = get_post_meta( $signup->ID, 'gk_email',        true );
            $phone     = get_post_meta( $signup->ID, 'gk_phone',        true );
            $org_num   = get_post_meta( $signup->ID, 'gk_org_number',   true );
            $city      = get_post_meta( $signup->ID, 'gk_city',         true );
            $message   = get_post_meta( $signup->ID, 'gk_message',      true );
            $submitted = get_post_meta( $signup->ID, 'gk_submitted_at', true );
            $uid       = 'gk-s' . $signup->ID;
            ?>
            <div class="gk-signup-card">
                <div class="gk-signup-card-head">
                    <div class="gk-signup-company">
                        <span class="material-icons-round">business</span>
                        <?php echo esc_html( $company ); ?>
                    </div>
                    <div class="gk-signup-meta-row">
                        <?php if ( $org_num ) : ?><span class="gk-chip"><?php echo esc_html( $org_num ); ?></span><?php endif; ?>
                        <span class="gk-signup-time"><?php echo esc_html( $submitted ? date( 'd M Y H:i', strtotime( $submitted ) ) : '' ); ?></span>
                    </div>
                </div>

                <div class="gk-signup-card-body">
                    <div class="gk-detail-grid">
                        <div class="gk-detail-item">
                            <span class="gk-detail-label">Kontaktperson</span>
                            <span class="gk-detail-value"><?php echo esc_html( $contact ); ?></span>
                        </div>
                        <div class="gk-detail-item">
                            <span class="gk-detail-label">E-post</span>
                            <span class="gk-detail-value"><?php echo esc_html( $email ); ?></span>
                        </div>
                        <div class="gk-detail-item">
                            <span class="gk-detail-label">Telefon</span>
                            <span class="gk-detail-value"><?php echo esc_html( $phone ?: '–' ); ?></span>
                        </div>
                        <?php if ( $city ) : ?>
                        <div class="gk-detail-item">
                            <span class="gk-detail-label">Stad</span>
                            <span class="gk-detail-value"><?php echo esc_html( $city ); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="gk-expand-row">
                        <button type="button" class="gk-expand-trigger" data-target="<?php echo esc_attr( $uid . '-full' ); ?>">
                            <span class="material-icons-round">expand_more</span> Visa hela ansökan
                        </button>
                        <?php if ( $message ) : ?>
                        <button type="button" class="gk-expand-trigger" data-target="<?php echo esc_attr( $uid . '-msg' ); ?>">
                            <span class="material-icons-round">chat_bubble_outline</span> Meddelande
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="gk-full-form" id="<?php echo esc_attr( $uid . '-full' ); ?>" style="display:none;">
                        <?php
                        $rows = [
                            'Företagsnamn'       => $company,
                            'Organisationsnummer' => $org_num ?: '–',
                            'Kontaktperson'      => $contact,
                            'E-postadress'       => $email,
                            'Telefonnummer'      => $phone ?: '–',
                            'Stad'               => $city ?: '–',
                            'Meddelande'         => $message ?: '–',
                            'Skickad'            => $submitted ? date( 'd M Y H:i', strtotime( $submitted ) ) : '–',
                        ];
                        foreach ( $rows as $label => $value ) :
                        ?>
                            <dl class="gk-full-form-row">
                                <dt><?php echo esc_html( $label ); ?></dt>
                                <dd><?php echo esc_html( $value ); ?></dd>
                            </dl>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( $message ) : ?>
                    <div class="gk-full-form" id="<?php echo esc_attr( $uid . '-msg' ); ?>" style="display:none;">
                        <dl class="gk-full-form-row"><dt>Meddelande</dt><dd><?php echo esc_html( $message ); ?></dd></dl>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="gk-signup-card-actions">
                    <span class="gk-actions-label">Godkänn som:</span>
                    <div class="gk-approve-set">
                        <?php foreach ( $groups as $slug => $label ) :
                            $url = wp_nonce_url( add_query_arg( [ 'action' => 'gk_approve', 'id' => $signup->ID, 'group' => $slug ], admin_url( 'admin-post.php' ) ), 'gk_approve_' . $signup->ID );
                        ?>
                            <a href="<?php echo esc_url( $url ); ?>" class="gk-approve-btn gk-approve-btn--<?php echo esc_attr( $slug ); ?>">
                                <span class="material-icons-round">check</span> <?php echo esc_html( $label ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'gk_reject', 'id' => $signup->ID ], admin_url( 'admin-post.php' ) ), 'gk_reject_' . $signup->ID ) ); ?>"
                       class="gk-reject-btn"
                       onclick="return confirm('Avvisa ansökan från <?php echo esc_js( $company ); ?>?');">
                        <span class="material-icons-round">close</span> Avvisa
                    </a>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }

    // ─── Customers ────────────────────────────────────────────────────────────

    private function render_customers_tab(): void {
        $groups    = GK_Customer_Groups::get_groups();
        $customers = get_users( [ 'role' => 'customer', 'orderby' => 'display_name', 'number' => 200 ] );
        ?>
        <div style="display:grid;grid-template-columns:400px 1fr;gap:20px;align-items:start;">

            <!-- Create customer form -->
            <div class="gk-panel">
                <div class="gk-panel-head">
                    <div>
                        <h2>Ny kund</h2>
                        <p>Skapa ett B2B-konto manuellt</p>
                    </div>
                </div>
                <div class="gk-panel-body">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'gk_create_customer', 'gk_nonce' ); ?>
                        <input type="hidden" name="action" value="gk_create_customer">
                        <div class="gk-form-grid" style="grid-template-columns:1fr;">
                            <div class="gk-field">
                                <label>Företagsnamn <span class="req">*</span></label>
                                <input type="text" name="gk_company" required placeholder="Företaget AB">
                            </div>
                            <div class="gk-field">
                                <label>Organisationsnummer</label>
                                <input type="text" name="gk_org_number" placeholder="556000-0000" class="gk-org-input">
                            </div>
                            <div class="gk-field">
                                <label>Kontaktperson <span class="req">*</span></label>
                                <input type="text" name="gk_first_name" required placeholder="Förnamn Efternamn">
                            </div>
                            <div class="gk-field">
                                <label>E-postadress <span class="req">*</span></label>
                                <input type="email" name="gk_email" required placeholder="info@foretaget.se">
                            </div>
                            <div class="gk-field">
                                <label>Telefonnummer</label>
                                <input type="tel" name="gk_phone" placeholder="+46 70 000 00 00">
                            </div>
                            <div class="gk-field">
                                <label>Stad</label>
                                <input type="text" name="gk_city" placeholder="Stockholm">
                            </div>
                            <div class="gk-field">
                                <label>Kundgrupp</label>
                                <select name="gk_group">
                                    <?php foreach ( $groups as $slug => $label ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?><?php echo 'bas' === $slug ? ' — standardpris' : ''; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="gk-field">
                                <label>Välkomstmail</label>
                                <label class="gk-toggle-wrap">
                                    <input type="checkbox" name="gk_send_welcome" value="1" checked>
                                    <span class="gk-toggle-track"><span class="gk-toggle-thumb"></span></span>
                                    Skicka mail med inloggningsuppgifter
                                </label>
                            </div>
                        </div>
                        <div class="gk-form-actions">
                            <button type="submit" class="gk-btn gk-btn--primary">
                                <span class="material-icons-round">person_add</span> Skapa kund
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customer list -->
            <div class="gk-panel">
                <div class="gk-panel-head">
                    <div>
                        <h2>Alla kunder</h2>
                        <p><?php echo count( $customers ); ?> registrerade kunder</p>
                    </div>
                </div>
                <div style="padding:14px 16px;border-bottom:1px solid var(--c-border);">
                    <div class="gk-customer-search-wrap">
                        <span class="material-icons-round">search</span>
                        <input type="text" class="gk-customer-search" id="gk-customer-filter" placeholder="Sök på namn, e-post eller företag…">
                    </div>
                </div>
                <?php if ( empty( $customers ) ) : ?>
                    <div class="gk-empty-state">
                        <span class="material-icons-round">people_outline</span>
                        <div class="gk-empty-title">Inga kunder än</div>
                        <div class="gk-empty-sub">Skapa din första kund med formuläret till vänster.</div>
                    </div>
                <?php else : ?>
                    <div style="overflow-x:auto;">
                        <table class="gk-customer-table" id="gk-customer-list">
                            <thead>
                                <tr>
                                    <th>Kund</th>
                                    <th>Org.nr</th>
                                    <th>Grupp</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $customers as $c ) :
                                    $company = get_user_meta( $c->ID, 'billing_company',             true );
                                    $org     = get_user_meta( $c->ID, 'billing_organisation_number', true );
                                    $group   = get_user_meta( $c->ID, GK_USER_META_KEY,              true ) ?: 'bas';
                                    $phone   = get_user_meta( $c->ID, 'billing_phone',               true );
                                    $city    = get_user_meta( $c->ID, 'billing_city',                true );
                                    $fname   = get_user_meta( $c->ID, 'first_name',                  true );
                                    $lname   = get_user_meta( $c->ID, 'last_name',                   true );
                                    $g_label = $groups[ $group ] ?? 'Bas';
                                ?>
                                    <tr class="gk-customer-tr" data-search="<?php echo esc_attr( strtolower( $company . ' ' . $c->user_email . ' ' . $fname . ' ' . $lname ) ); ?>">
                                        <td>
                                            <div class="gk-customer-name"><?php echo esc_html( $company ?: $c->display_name ); ?></div>
                                            <div class="gk-customer-email"><?php echo esc_html( $c->user_email ); ?></div>
                                        </td>
                                        <td><span class="gk-chip"><?php echo esc_html( $org ?: '–' ); ?></span></td>
                                        <td><span class="gk-group-badge gk-group-badge--<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $g_label ); ?></span></td>
                                        <td>
                                            <button type="button" class="gk-table-action gk-open-edit"
                                                data-id="<?php echo esc_attr( $c->ID ); ?>"
                                                data-company="<?php echo esc_attr( $company ); ?>"
                                                data-firstname="<?php echo esc_attr( $fname ); ?>"
                                                data-lastname="<?php echo esc_attr( $lname ); ?>"
                                                data-email="<?php echo esc_attr( $c->user_email ); ?>"
                                                data-phone="<?php echo esc_attr( $phone ); ?>"
                                                data-city="<?php echo esc_attr( $city ); ?>"
                                                data-org="<?php echo esc_attr( $org ); ?>"
                                                data-group="<?php echo esc_attr( $group ); ?>">
                                                <span class="material-icons-round">edit</span> Redigera
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit customer modal (hidden) -->
        <div class="gk-modal-backdrop" id="gk-edit-modal" style="display:none;">
            <div class="gk-modal">
                <div class="gk-modal-header">
                    <span class="material-icons-round" style="color:var(--c-accent);">edit</span>
                    <h3>Redigera kund</h3>
                    <button type="button" class="gk-modal-close" id="gk-modal-close">
                        <span class="material-icons-round">close</span>
                    </button>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'gk_edit_customer', 'gk_nonce' ); ?>
                    <input type="hidden" name="action" value="gk_edit_customer">
                    <input type="hidden" name="gk_user_id" id="gk-edit-user-id">
                    <div class="gk-modal-body">
                        <div class="gk-form-grid">
                            <div class="gk-field gk-field--full">
                                <label>Företagsnamn</label>
                                <input type="text" name="gk_company" id="gk-edit-company" placeholder="Företaget AB">
                            </div>
                            <div class="gk-field">
                                <label>Förnamn</label>
                                <input type="text" name="gk_first_name" id="gk-edit-firstname">
                            </div>
                            <div class="gk-field">
                                <label>Efternamn</label>
                                <input type="text" name="gk_last_name" id="gk-edit-lastname">
                            </div>
                            <div class="gk-field">
                                <label>E-postadress</label>
                                <input type="email" name="gk_email" id="gk-edit-email">
                            </div>
                            <div class="gk-field">
                                <label>Telefonnummer</label>
                                <input type="tel" name="gk_phone" id="gk-edit-phone">
                            </div>
                            <div class="gk-field">
                                <label>Stad</label>
                                <input type="text" name="gk_city" id="gk-edit-city">
                            </div>
                            <div class="gk-field">
                                <label>Organisationsnummer</label>
                                <input type="text" name="gk_org_number" id="gk-edit-org" class="gk-org-input" placeholder="556000-0000">
                            </div>
                            <div class="gk-field">
                                <label>Kundgrupp</label>
                                <select name="gk_group" id="gk-edit-group">
                                    <?php foreach ( $groups as $slug => $label ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="gk-modal-footer">
                        <button type="button" class="gk-btn gk-btn--ghost" id="gk-modal-cancel">Avbryt</button>
                        <button type="submit" class="gk-btn gk-btn--primary">
                            <span class="material-icons-round">save</span> Spara ändringar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_create_customer(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_POST['gk_nonce'] ?? '', 'gk_create_customer' ) ) wp_die( 'Invalid nonce' );

        $email   = sanitize_email( $_POST['gk_email'] ?? '' );
        $fname   = sanitize_text_field( $_POST['gk_first_name'] ?? '' );
        $company = sanitize_text_field( $_POST['gk_company'] ?? '' );

        if ( ! $email || ! $fname || ! $company ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'missing_fields' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $user_id = wp_create_user( $email, wp_generate_password( 12, true ), $email );
        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'create_failed' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        ( new WP_User( $user_id ) )->set_role( 'customer' );
        update_user_meta( $user_id, 'first_name',                  $fname );
        update_user_meta( $user_id, 'billing_company',             $company );
        update_user_meta( $user_id, 'billing_email',               $email );
        update_user_meta( $user_id, 'billing_phone',               sanitize_text_field( $_POST['gk_phone'] ?? '' ) );
        update_user_meta( $user_id, 'billing_city',                sanitize_text_field( $_POST['gk_city'] ?? '' ) );
        update_user_meta( $user_id, 'billing_organisation_number', sanitize_text_field( $_POST['gk_org_number'] ?? '' ) );
        update_user_meta( $user_id, GK_USER_META_KEY,              sanitize_key( $_POST['gk_group'] ?? 'bas' ) );

        if ( ! empty( $_POST['gk_send_welcome'] ) ) wp_new_user_notification( $user_id, null, 'both' );

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'customer_saved' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_edit_customer(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_POST['gk_nonce'] ?? '', 'gk_edit_customer' ) ) wp_die( 'Invalid nonce' );

        $user_id = (int) ( $_POST['gk_user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'missing_fields' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        update_user_meta( $user_id, 'billing_company',             sanitize_text_field( $_POST['gk_company'] ?? '' ) );
        update_user_meta( $user_id, 'first_name',                  sanitize_text_field( $_POST['gk_first_name'] ?? '' ) );
        update_user_meta( $user_id, 'last_name',                   sanitize_text_field( $_POST['gk_last_name'] ?? '' ) );
        update_user_meta( $user_id, 'billing_email',               sanitize_email( $_POST['gk_email'] ?? '' ) );
        update_user_meta( $user_id, 'billing_phone',               sanitize_text_field( $_POST['gk_phone'] ?? '' ) );
        update_user_meta( $user_id, 'billing_city',                sanitize_text_field( $_POST['gk_city'] ?? '' ) );
        update_user_meta( $user_id, 'billing_organisation_number', sanitize_text_field( $_POST['gk_org_number'] ?? '' ) );
        update_user_meta( $user_id, GK_USER_META_KEY,              sanitize_key( $_POST['gk_group'] ?? 'bas' ) );

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'customers', 'gk_notice' => 'customer_updated' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    private function render_orders_tab(): void {
        $customers = get_users( [ 'role' => 'customer', 'orderby' => 'display_name', 'number' => 200 ] );
        $products  = wc_get_products( [ 'status' => 'publish', 'limit' => 200, 'orderby' => 'title', 'order' => 'ASC' ] );
        $groups    = GK_Customer_Groups::get_groups();

        $product_data = [];
        foreach ( $products as $p ) {
            $prices = [ 'bas' => $p->get_price() ];
            foreach ( GK_Customer_Groups::get_priced_groups() as $slug => $lbl ) {
                $meta = get_post_meta( $p->get_id(), GK_PRICE_META_PREFIX . $slug, true );
                $prices[ $slug ] = ( $meta !== '' && is_numeric( $meta ) ) ? (float) $meta : (float) $p->get_price();
            }
            $product_data[ $p->get_id() ] = [
                'name'  => $p->get_name(),
                'sku'   => $p->get_sku() ?: '–',
                'stock' => $p->get_stock_quantity(),
                'price' => $prices,
            ];
        }
        wp_localize_script( 'grossist-kit-dashboard', 'gkProducts', $product_data );
        ?>
        <div class="gk-panel">
            <div class="gk-panel-head">
                <div><h2>Skapa order</h2><p>Lägg en order manuellt för en befintlig kund</p></div>
            </div>
            <div class="gk-panel-body">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gk-order-form">
                    <?php wp_nonce_field( 'gk_create_order', 'gk_nonce' ); ?>
                    <input type="hidden" name="action" value="gk_create_order">

                    <div class="gk-form-grid">
                        <div class="gk-field gk-field--full">
                            <label>Kund <span class="req">*</span></label>
                            <select name="gk_customer_id" id="gk-customer-select" required>
                                <option value="">– Välj kund –</option>
                                <?php foreach ( $customers as $c ) :
                                    $co  = get_user_meta( $c->ID, 'billing_company', true );
                                    $grp = get_user_meta( $c->ID, GK_USER_META_KEY,  true ) ?: 'bas';
                                    $lbl = $co ? $co . ' — ' . $c->user_email : $c->display_name . ' — ' . $c->user_email;
                                ?>
                                    <option value="<?php echo esc_attr( $c->ID ); ?>" data-group="<?php echo esc_attr( $grp ); ?>">
                                        <?php echo esc_html( $lbl ); ?> [<?php echo esc_html( strtoupper( $grp ) ); ?>]
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
                            <label>Intern anteckning</label>
                            <textarea name="gk_order_note" rows="2" placeholder="T.ex. leveransinstruktioner, referens…"></textarea>
                        </div>
                    </div>

                    <div class="gk-product-table-wrap">
                        <div class="gk-product-table-header">
                            Produkter
                            <button type="button" id="gk-add-product" class="gk-add-row-btn">
                                <span class="material-icons-round">add</span> Lägg till rad
                            </button>
                        </div>
                        <div class="gk-product-cols">
                            <span>Produkt</span>
                            <span>SKU</span>
                            <span>Á-pris</span>
                            <span>Lager</span>
                            <span>Antal</span>
                            <span></span>
                        </div>
                        <div id="gk-product-rows">
                            <div class="gk-product-row">
                                <select name="gk_products[0][id]" class="gk-product-select">
                                    <option value="">– Välj produkt –</option>
                                    <?php foreach ( $products as $p ) : ?>
                                        <option value="<?php echo esc_attr( $p->get_id() ); ?>"><?php echo esc_html( $p->get_name() ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="gk-sku-cell">–</span>
                                <span class="gk-price-cell">–</span>
                                <span class="gk-stock-cell">–</span>
                                <input type="number" name="gk_products[0][qty]" value="1" min="1" class="gk-qty-input">
                                <button type="button" class="gk-remove-row"><span class="material-icons-round">close</span></button>
                            </div>
                        </div>
                    </div>

                    <div class="gk-form-actions">
                        <button type="submit" class="gk-btn gk-btn--primary">
                            <span class="material-icons-round">shopping_cart</span> Skapa order
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=shop_order' ) ); ?>" class="gk-btn gk-btn--ghost">
                            Öppna WC-formulär
                        </a>
                    </div>
                </form>
            </div>
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

        $group = get_user_meta( $customer_id, GK_USER_META_KEY, true ) ?: 'bas';
        $order = wc_create_order( [ 'customer_id' => $customer_id ] );

        foreach ( $products as $item ) {
            $pid = (int) ( $item['id'] ?? 0 );
            $qty = max( 1, (int) ( $item['qty'] ?? 1 ) );
            if ( ! $pid ) continue;
            $product = wc_get_product( $pid );
            if ( ! $product ) continue;

            if ( 'bas' !== $group ) {
                $group_price = get_post_meta( $pid, GK_PRICE_META_PREFIX . $group, true );
                if ( $group_price !== '' && is_numeric( $group_price ) ) {
                    $item_id = $order->add_product( $product, $qty );
                    if ( $item_id ) {
                        $line = $order->get_item( $item_id );
                        $line->set_subtotal( $group_price * $qty );
                        $line->set_total( $group_price * $qty );
                        $line->save();
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
            <div class="gk-panel-head"><div><h2>Kundgrupper</h2><p>Prisnivåer och antal kunder per grupp</p></div></div>
            <div class="gk-groups-grid">
                <?php foreach ( $groups as $slug => $label ) :
                    $count = count( get_users( [ 'meta_key' => GK_USER_META_KEY, 'meta_value' => $slug, 'fields' => 'ID' ] ) );
                ?>
                    <div class="gk-group-card gk-group-card--<?php echo esc_attr( $slug ); ?>">
                        <div class="gk-group-card-top">
                            <span class="gk-group-card-name"><?php echo esc_html( $label ); ?></span>
                            <span class="gk-group-card-count"><?php echo $count; ?> kunder</span>
                        </div>
                        <p class="gk-group-card-desc">
                            <?php echo 'bas' === $slug ? 'Ser alltid WooCommerce standardpris.' : 'Fast pris per produkt. Faller tillbaka på standardpris om inget pris är satt.'; ?>
                        </p>
                        <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="gk-group-card-link">Visa kunder →</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="gk-info-strip">
                <div class="gk-info-block">
                    <strong>Prissättning per produkt</strong>
                    Produktredigering → <em>Priser per kundgrupp</em>. Ange fast pris för Silver, Guld, VIP. Tomt = standardpris.
                </div>
                <div class="gk-info-block">
                    <strong>Tilldela grupp</strong>
                    Redigera kund under fliken Kunder, eller via massåtgärder i WordPress användarlista.
                </div>
            </div>
        </div>
        <?php
    }
}
