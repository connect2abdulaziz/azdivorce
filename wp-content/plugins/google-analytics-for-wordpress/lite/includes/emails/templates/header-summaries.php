<?php
/**
 * Email Header Template
 *
 * Email-client compatible template using table-based layout and inline styles.
 * Works with Gmail, Outlook, Apple Mail, Yahoo Mail and other major clients.
 * CSS classes are prefixed with 'mset-' (MonsterInsights Summary Email Template)
 * to avoid conflicts with email client styles.
 *
 * @since 8.19.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$mail_text_direction = is_rtl() ? 'rtl' : 'ltr';
?>
<!doctype html>
<html dir="<?php echo esc_attr( $mail_text_direction ); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="x-apple-disable-message-reformatting">
	<meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:AllowPNG/>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<style type="text/css">
		body, table, td, th { font-family: Helvetica, Arial, sans-serif !important; }
		table { border-collapse: collapse; }
		td, th { mso-line-height-rule: exactly; }
	</style>
	<![endif]-->
	<style type="text/css">
		/* CSS Reset */
		body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
		body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

		/* Progressive enhancement - responsive */
		@media only screen and (max-width: 600px) {
			.mset-wrapper { width: 100% !important; }
			.mset-header-td { padding: 30px 20px !important; }
			.mset-header-title { font-size: 22px !important; line-height: 28px !important; }
			.mset-section-header-td { padding: 15px 20px !important; }
			.mset-section-content-td { padding: 15px 20px !important; }
			.mset-footer-td { padding: 20px !important; }
			.mset-stat-cell { display: block !important; width: 100% !important; padding-bottom: 10px !important; }
			.mset-blog-image-cell { display: block !important; width: 100% !important; padding-bottom: 10px !important; }
			.mset-blog-content-cell { display: block !important; width: 100% !important; }
			.mset-feature-cell { display: block !important; width: 100% !important; }
			.mset-header-bg { background-size: 30% !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f6f7f8; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
	<!-- Outer background table -->
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f6f7f8;">
		<tr>
			<td align="center" style="padding: 50px 0;">
				<!--[if (gte mso 9)|(IE)]>
				<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td>
				<![endif]-->
				<!-- Main container -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="mset-wrapper" style="width: 100%; max-width: 680px; margin: 0 auto;">
					<!-- HEADER -->
					<tr>
						<td class="mset-header-td mset-header-bg" style="background-color: #6F4BBB; <?php if ( isset( $assets_url ) && $assets_url ) : ?>background-image: url('<?php echo esc_url( $assets_url . '/assets/img/header-background-monsterinsights.png' ); ?>'); background-position: bottom right; background-repeat: no-repeat; background-size: contain; <?php endif; ?>padding: 40px 30px; color: #ffffff;">
							<?php if ( isset( $header_image ) && $header_image ) : ?>
							<a href="<?php echo esc_url( $logo_link ); ?>" style="text-decoration: none;">
								<img src="<?php echo esc_url( $header_image ); ?>"
									 alt="<?php echo esc_attr__( 'Monthly Traffic Summary', 'google-analytics-for-wordpress' ); ?>"
									 width="160"
									 style="display: block; width: 160px; height: auto; margin: 20px 0; border: 0;" />
							</a>
							<?php endif; ?>
							<h1 class="mset-header-title" style="max-width: 360px; margin: 20px 0; font-size: 26px; font-weight: 600; line-height: 32px; color: #ffffff; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
								<?php esc_html_e( 'It\'s your Monthly Website Analytics Summary', 'google-analytics-for-wordpress' ); ?>
							</h1>
							<?php if ( isset( $start_date ) && isset( $end_date ) ) : ?>
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #8E64E5; padding: 7px 15px; border-radius: 4px;">
							<![endif]-->
							<a href="<?php echo esc_url( $reports_url ); ?>" style="display: inline-block; background-color: #8E64E5; color: #ffffff; padding: 7px 15px; border-radius: 4px; text-decoration: none; font-size: 15px; font-weight: 700; margin: 20px 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
								<?php
								printf(
									'%s - %s',
									esc_html( $start_date ),
									esc_html( $end_date )
								);
								?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
							<?php endif; ?>
						</td>
					</tr>
					<!-- CONTENT -->
					<tr>
						<td style="padding: 30px 0;">
