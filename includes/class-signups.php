<?php
/**
 * GrossistKit — Signups
 * Handles B2B signup applications: custom post type, shortcode form,
 * and approve/reject logic.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Signups {

    public function __construct() {
        add_action( 'init',                  [ __CLASS__, 'register_post_type' ] );
        add_shortcode( 'grossist_signup',    [ $this, 'render_signup_form' ] );
        add_action( 'init',                  [ $this, 'handle_signup_submission' ] );
        add_action( 'admin_post_gk_approve', [ $this, 'handle_approve' ] );
        add_action( 'admin_post_gk_reject',  [ $this, 'handle_reject' ] );
    }

    // ─── Post type ────────────────────────────────────────────────────────────

    public static function register_post_type(): void {
        register_post_type( GK_SIGNUP_POST_TYPE, [
            'label'               => 'B2B Ansökningar',
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'supports'            => [ 'title', 'custom-fields' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ] );
    }

    // ─── Shortcode form ───────────────────────────────────────────────────────

    public function render_signup_form(): string {
        if ( isset( $_GET['gk_signup'] ) ) {
            if ( 'sent' === $_GET['gk_signup'] ) {
                return '<div style="padding:1.5rem;background:#f0fdf4;border-left:4px solid #16a34a;border-radius:4px;">
                    <strong>Ansökan skickad!</strong> Vi granskar din ansökan och återkommer inom kort.
                </div>';
            }
            if ( 'error' === $_GET['gk_signup'] ) {
                return '<div style="padding:1.5rem;background:#fef2f2;border-left:4px solid #9A0002;border-radius:4px;">
                    <strong>Något gick fel.</strong> Fyll i alla obligatoriska fält och försök igen.
                </div>';
            }
            if ( 'exists' === $_GET['gk_signup'] ) {
                return '<div style="padding:1.5rem;background:#fff7ed;border-left:4px solid #ea580c;border-radius:4px;">
                    <strong>E-postadressen är redan registrerad.</strong> Kontakta oss om du behöver hjälp.
                </div>';
            }
        }

        ob_start();
        ?>
        <div class="gk-signup-form-wrap" style="max-width:540px;">
            <form method="post" action="">
                <?php wp_nonce_field( 'gk_signup_submit', 'gk_nonce' ); ?>
                <input type="hidden" name="gk_action" value="signup">

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Företagsnamn *</label>
                    <input type="text" name="gk_company" required style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Organisationsnummer *</label>
                    <input type="text" name="gk_org_number" required placeholder="556000-0000" style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Kontaktperson *</label>
                    <input type="text" name="gk_contact_name" required style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">E-postadress *</label>
                    <input type="email" name="gk_email" required style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Telefonnummer</label>
                    <input type="tel" name="gk_phone" style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Leveransadress</label>
                    <input type="text" name="gk_address" style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label style="display:block;font-weight:600;margin-bottom:.35rem;">Meddelande (valfritt)</label>
                    <textarea name="gk_message" rows="4" style="width:100%;padding:.6rem .8rem;border:1px solid #ddd;border-radius:4px;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="background:#9A0002;color:#fff;padding:.75rem 2rem;border:none;border-radius:4px;font-size:1rem;font-weight:600;cursor:pointer;">
                    Skicka ansökan
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─── Form submission ──────────────────────────────────────────────────────

    public function handle_signup_submission(): void {
        if ( ! isset( $_POST['gk_action'] ) || 'signup' !== $_POST['gk_action'] ) return;
        if ( ! wp_verify_nonce( $_POST['gk_nonce'] ?? '', 'gk_signup_submit' ) ) return;

        $email   = sanitize_email( $_POST['gk_email'] ?? '' );
        $company = sanitize_text_field( $_POST['gk_company'] ?? '' );
        $contact = sanitize_text_field( $_POST['gk_contact_name'] ?? '' );
        $org_num = sanitize_text_field( $_POST['gk_org_number'] ?? '' );

        if ( ! $email || ! $company || ! $contact || ! $org_num ) {
            wp_safe_redirect( add_query_arg( 'gk_signup', 'error', get_permalink() ) );
            exit;
        }

        if ( email_exists( $email ) ) {
            wp_safe_redirect( add_query_arg( 'gk_signup', 'exists', get_permalink() ) );
            exit;
        }

        $post_id = wp_insert_post( [
            'post_type'   => GK_SIGNUP_POST_TYPE,
            'post_title'  => sanitize_text_field( $company . ' – ' . $contact ),
            'post_status' => 'pending',
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'gk_signup', 'error', get_permalink() ) );
            exit;
        }

        update_post_meta( $post_id, 'gk_company',      $company );
        update_post_meta( $post_id, 'gk_org_number',   $org_num );
        update_post_meta( $post_id, 'gk_contact_name', $contact );
        update_post_meta( $post_id, 'gk_email',        $email );
        update_post_meta( $post_id, 'gk_phone',        sanitize_text_field( $_POST['gk_phone'] ?? '' ) );
        update_post_meta( $post_id, 'gk_address',      sanitize_text_field( $_POST['gk_address'] ?? '' ) );
        update_post_meta( $post_id, 'gk_message',      sanitize_textarea_field( $_POST['gk_message'] ?? '' ) );
        update_post_meta( $post_id, 'gk_submitted_at', current_time( 'mysql' ) );

        wp_safe_redirect( add_query_arg( 'gk_signup', 'sent', get_permalink() ) );
        exit;
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function handle_approve(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'gk_approve_' . (int) $_GET['id'] ) ) wp_die( 'Invalid nonce' );

        $post_id = (int) $_GET['id'];
        $group   = sanitize_key( $_GET['group'] ?? 'bas' );

        $email   = get_post_meta( $post_id, 'gk_email',        true );
        $contact = get_post_meta( $post_id, 'gk_contact_name', true );
        $company = get_post_meta( $post_id, 'gk_company',      true );
        $phone   = get_post_meta( $post_id, 'gk_phone',        true );
        $address = get_post_meta( $post_id, 'gk_address',      true );

        if ( email_exists( $email ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups', 'gk_notice' => 'already_exists' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $password = wp_generate_password( 12, true );
        $user_id  = wp_create_user( $email, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups', 'gk_notice' => 'create_failed' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Set WooCommerce customer role and meta
        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );

        update_user_meta( $user_id, 'first_name',        $contact );
        update_user_meta( $user_id, 'billing_company',   $company );
        update_user_meta( $user_id, 'billing_email',     $email );
        update_user_meta( $user_id, 'billing_phone',     $phone );
        update_user_meta( $user_id, 'billing_address_1', $address );
        update_user_meta( $user_id, GK_USER_META_KEY,    $group );

        // Update signup post status
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        update_post_meta( $post_id, 'gk_approved_at', current_time( 'mysql' ) );
        update_post_meta( $post_id, 'gk_user_id',     $user_id );
        update_post_meta( $post_id, 'gk_status',      'approved' );

        // Send welcome email
        wp_new_user_notification( $user_id, null, 'both' );

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups', 'gk_notice' => 'approved' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function handle_reject(): void {
        if ( ! current_user_can( 'administrator' ) ) wp_die( 'Unauthorized' );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'gk_reject_' . (int) $_GET['id'] ) ) wp_die( 'Invalid nonce' );

        $post_id = (int) $_GET['id'];

        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'trash' ] );
        update_post_meta( $post_id, 'gk_status',      'rejected' );
        update_post_meta( $post_id, 'gk_rejected_at', current_time( 'mysql' ) );

        wp_safe_redirect( add_query_arg( [ 'page' => 'grossist-kit', 'tab' => 'signups', 'gk_notice' => 'rejected' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── Helper: pending count ────────────────────────────────────────────────

    public static function get_pending_count(): int {
        $query = new WP_Query( [
            'post_type'      => GK_SIGNUP_POST_TYPE,
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        return (int) $query->found_posts;
    }

    // ─── Helper: fetch signups ────────────────────────────────────────────────

    public static function get_signups( string $status = 'pending' ): array {
        $query = new WP_Query( [
            'post_type'      => GK_SIGNUP_POST_TYPE,
            'post_status'    => $status,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        return $query->posts;
    }
}
