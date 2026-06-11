<?php
/**
 * GrossistKit — Org Number
 * Handles organisationsnummer field across checkout, orders, user profiles.
 * Migrated from functions.php — keep functions.php clean.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GK_Org_Number {

    public function __construct() {
        // Checkout field
        add_filter( 'woocommerce_billing_fields',          [ $this, 'add_checkout_field' ] );
        add_action( 'woocommerce_checkout_process',        [ $this, 'validate_checkout_field' ] );
        add_action( 'woocommerce_checkout_create_order',   [ $this, 'save_to_order' ], 10, 2 );
        add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'save_to_user' ], 10, 2 );
        add_filter( 'woocommerce_checkout_get_value',      [ $this, 'prefill_from_user' ], 10, 2 );

        // Admin order view
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'show_in_order' ] );

        // User profile
        add_action( 'show_user_profile',        [ $this, 'render_user_field' ] );
        add_action( 'edit_user_profile',        [ $this, 'render_user_field' ] );
        add_action( 'personal_options_update',  [ $this, 'save_user_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_field' ] );
    }

    // ─── Checkout field ───────────────────────────────────────────────────────

    public function add_checkout_field( array $fields ): array {
        $fields['billing_organisation_number'] = [
            'label'       => 'Organisationsnummer',
            'placeholder' => '556XXX-XXXX',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'priority'    => 25,
        ];
        return $fields;
    }

    public function validate_checkout_field(): void {
        if ( empty( $_POST['billing_organisation_number'] ) ) {
            wc_add_notice( 'Organisationsnummer är obligatoriskt.', 'error' );
            return;
        }
        $org = sanitize_text_field( $_POST['billing_organisation_number'] );
        if ( ! preg_match( '/^\d{6}-\d{4}$/', $org ) ) {
            wc_add_notice( 'Organisationsnummer har fel format. Använd formatet XXXXXX-XXXX (t.ex. 556123-4567)', 'error' );
        }
    }

    public function save_to_order( WC_Order $order, array $data ): void {
        $org = isset( $data['billing_organisation_number'] )
            ? sanitize_text_field( $data['billing_organisation_number'] )
            : '';
        if ( empty( $org ) && ! empty( $_POST['billing_organisation_number'] ) ) {
            $org = sanitize_text_field( $_POST['billing_organisation_number'] );
        }
        if ( ! empty( $org ) ) {
            $order->update_meta_data( '_organisation_number',        $org );
            $order->update_meta_data( 'billing_organisation_number', $org );
        }
    }

    public function save_to_user( int $customer_id, array $data ): void {
        if ( ! empty( $data['billing_organisation_number'] ) ) {
            update_user_meta( $customer_id, 'billing_organisation_number',
                sanitize_text_field( $data['billing_organisation_number'] ) );
        }
    }

    public function prefill_from_user( $value, string $input ) {
        if ( 'billing_organisation_number' === $input && is_user_logged_in() ) {
            $saved = get_user_meta( get_current_user_id(), 'billing_organisation_number', true );
            if ( $saved ) return $saved;
        }
        return $value;
    }

    // ─── Admin order display ──────────────────────────────────────────────────

    public function show_in_order( WC_Order $order ): void {
        $org = $order->get_meta( '_organisation_number' );
        if ( $org ) {
            echo '<p><strong>Organisationsnummer:</strong> ' . esc_html( $org ) . '</p>';
        }
    }

    // ─── User profile ─────────────────────────────────────────────────────────

    public function render_user_field( WP_User $user ): void {
        $org = get_user_meta( $user->ID, 'billing_organisation_number', true );
        ?>
        <h3>Företagsinformation</h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="billing_organisation_number">Organisationsnummer</label></th>
                <td>
                    <input type="text"
                        name="billing_organisation_number"
                        id="billing_organisation_number"
                        value="<?php echo esc_attr( $org ); ?>"
                        class="regular-text"
                        placeholder="556000-0000">
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_field( int $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        if ( isset( $_POST['billing_organisation_number'] ) ) {
            update_user_meta( $user_id, 'billing_organisation_number',
                sanitize_text_field( $_POST['billing_organisation_number'] ) );
        }
    }

    // ─── Static helper ────────────────────────────────────────────────────────

    public static function get_for_user( int $user_id ): string {
        return (string) get_user_meta( $user_id, 'billing_organisation_number', true );
    }
}
