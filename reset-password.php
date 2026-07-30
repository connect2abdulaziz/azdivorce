<?php
/**
 * Simple password reset — enter username (or email) and new password.
 * Delete this file after use.
 */

$wp_load = dirname( __FILE__ ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	die( 'WordPress not found. Place this file in the site root.' );
}
require_once $wp_load;

// Clear maintenance mode if stuck (e.g. after failed update)
$maintenance_file = dirname( __FILE__ ) . '/.maintenance';
if ( file_exists( $maintenance_file ) ) {
	@unlink( $maintenance_file );
}

$message = '';
$error   = '';

if ( isset( $_POST['reset_password'] ) ) {
	$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
	$new_pass = isset( $_POST['new_password'] ) ? $_POST['new_password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( empty( $username ) ) {
		$error = 'Please enter a username or email.';
	} elseif ( strlen( $new_pass ) < 6 ) {
		$error = 'Password must be at least 6 characters.';
	} else {
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}
		if ( ! $user ) {
			$error = 'No user found with that username or email.';
		} else {
			wp_set_password( $new_pass, $user->ID );
			$message = 'Password updated for ' . esc_html( $user->user_login ) . '. You can log in now.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reset Password</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 420px; margin: 40px auto; padding: 20px; }
		h1 { font-size: 1.25rem; margin-bottom: 1rem; }
		.form-group { margin-bottom: 1rem; }
		label { display: block; margin-bottom: 0.25rem; font-weight: 600; }
		input[type="text"], input[type="password"] { width: 100%; padding: 8px 10px; font-size: 1rem; box-sizing: border-box; }
		button { padding: 10px 16px; font-size: 1rem; cursor: pointer; background: #2271b1; color: #fff; border: none; border-radius: 4px; }
		button:hover { background: #135e96; }
		.msg { padding: 10px; margin-bottom: 1rem; border-radius: 4px; }
		.msg.success { background: #d4edda; color: #155724; }
		.msg.error { background: #f8d7da; color: #721c24; }
	</style>
</head>
<body>
	<h1>Reset Password</h1>
	<p>Enter username or email and a new password.</p>

	<?php if ( $message ) : ?>
		<div class="msg success"><?php echo wp_kses_post( $message ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="msg error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<form method="post" action="">
		<div class="form-group">
			<label for="username">Username or email</label>
			<input type="text" id="username" name="username" value="<?php echo esc_attr( isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '' ); ?>" placeholder="admin" required autocomplete="username">
		</div>
		<div class="form-group">
			<label for="new_password">New password</label>
			<input type="password" id="new_password" name="new_password" placeholder="Min 6 characters" required minlength="6" autocomplete="new-password">
		</div>
		<button type="submit" name="reset_password" value="1">Reset password</button>
	</form>
</body>
</html>
