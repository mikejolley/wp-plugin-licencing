<?php
/**
 * Lost licence form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<?php wc_print_notices(); ?>

<form method="post">

	<p class="form-row form-row-first">
		<label for="account_first_name"><?php _e( 'Activation email', 'wp-plugin-licencing' ); ?> <span class="required">*</span></label>
		<input type="text" class="input-text" name="activation_email" id="activation_email" value="" />
	</p>
	<div class="clear"></div>
	<p>
		<input type="submit" class="button" name="submit_lost_licence_form" value="<?php _e( 'Email my keys', 'wp-plugin-licencing' ); ?>" />
	</p>

</form>