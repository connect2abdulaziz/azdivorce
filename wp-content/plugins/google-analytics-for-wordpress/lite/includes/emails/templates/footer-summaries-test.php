<?php
/**
 * Email Footer Template
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
} ?>
						</td>
					</tr>
					<!-- FOOTER -->
					<tr>
						<td class="mset-footer-td" style="background-color: #F3F5F6; padding: 30px; text-align: center;">
							<!-- Footer Content -->
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
								<tr>
									<td align="center" style="padding-bottom: 15px;">
										<?php if ( isset( $logo_image ) && $logo_image ) : ?>
											<a href="<?php echo esc_url( $logo_link ); ?>" style="text-decoration: none;">
												<img src="<?php echo esc_url( $logo_image ); ?>"
													 alt="MonsterInsights"
													 width="60"
													 style="display: inline-block; width: 60px; height: auto; border: 0; margin-bottom: 20px;" />
											</a>
										<?php endif; ?>
									</td>
								</tr>
								<?php if ( isset( $settings_tab_url ) && $settings_tab_url ) : ?>
								<tr>
									<td align="center" style="color: #23262E; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 400; font-size: 12px; line-height: 20px; padding-bottom: 15px;">
										<?php
										$footer = sprintf(
											/* translators: Placeholders adds wrapping span tags and links to settings page. */
											esc_html__('%1$sThis email was auto-generated and sent from MonsterInsights.%2$s Learn how to %3$s disable it%4$s.', 'google-analytics-for-wordpress' ),
											'<span style="display: block;">',
											'</span><span>',
											'<a href="' . $settings_tab_url . '" target="_blank" style="color: #23262E; text-decoration: underline;">',
											'</a></span>'
										);

										echo apply_filters( 'mi_email_summaries_footer_text', $footer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the sprintf.
										?>
									</td>
								</tr>
								<?php endif; ?>
							</table>
							<!-- Footer Bar -->
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-top: 1px solid #EBEBEB;">
								<tr>
									<td style="padding: 15px 0;" valign="middle">
										<?php if ( isset( $left_image ) && $left_image ) : ?>
											<a href="<?php echo esc_url( $logo_link ); ?>" style="text-decoration: none;">
												<img src="<?php echo esc_url( $left_image ); ?>"
													 alt="MonsterInsights"
													 width="130"
													 style="display: inline-block; width: 130px; height: auto; border: 0;" />
											</a>
										<?php endif; ?>
									</td>
									<td style="padding: 15px 0; text-align: right;" valign="middle">
										<?php if ( isset( $facebook_url ) && $facebook_url ) : ?>
											<a href="<?php echo esc_url( $facebook_url ); ?>" target="_blank" style="color: #393F4C; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 400; font-size: 12px; margin: 0 5px;">Facebook</a>
										<?php endif;

										if ( isset( $linkedin_url ) && $linkedin_url ) : ?>
											<a href="<?php echo esc_url( $linkedin_url ); ?>" target="_blank" style="color: #393F4C; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 400; font-size: 12px; margin: 0 5px;">LinkedIn</a>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				<!--[if (gte mso 9)|(IE)]>
				</td></tr></table>
				<![endif]-->
			</td>
		</tr>
	</table>
</body>
</html>
