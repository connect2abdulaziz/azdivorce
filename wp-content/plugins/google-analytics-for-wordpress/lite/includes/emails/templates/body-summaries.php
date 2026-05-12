<?php
/**
 * Email Body Template
 *
 * Email-client compatible template using table-based layout and inline styles.
 * Works with Gmail, Outlook, Apple Mail, Yahoo Mail and other major clients.
 * CSS classes are prefixed with 'mset-' (MonsterInsights Summary Email Template)
 * to avoid conflicts with email client styles.
 *
 * @since 8.19.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

if ( isset( $update_available ) && $update_available ) : ?>
	<!-- Update Notice -->
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 30px;">
		<tr>
			<td style="background-color: #FDFBEC; border: 2px solid #D68936; border-radius: 4px; padding: 20px; text-align: center;">
				<p style="color: #393E4B; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 500; line-height: 26px; margin: 0;">
					<?php esc_html_e('An update is available for MonsterInsights.', 'google-analytics-for-wordpress'); ?>
				</p>
				<a href="<?php echo esc_url(admin_url('plugins.php')); ?>" style="display: inline-block; color: #338EEF; padding: 12px 24px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px;">
					<?php esc_html_e('Upgrade to the latest version', 'google-analytics-for-wordpress'); ?> &rarr;
				</a>
			</td>
		</tr>
	</table>
<?php endif; ?>

<!-- Analytics Report Section -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; margin-bottom: 30px; border-radius: 4px;">
	<?php if ( isset( $report_title ) && $report_title ) : ?>
	<tr>
		<td class="mset-section-header-td" style="padding: 20px 30px; border-bottom: 1px solid #EAEAEA;">
			<h2 style="color: #393E4B; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 600; line-height: 26px; margin: 0;">
				<?php echo esc_html( $report_title ); ?>
			</h2>
		</td>
	</tr>
	<?php endif; ?>
	<tr>
		<td class="mset-section-content-td" style="padding: 20px 30px;">
			<?php if ( isset( $report_image_src ) && $report_image_src ) : ?>
				<img src="<?php echo esc_url( $report_image_src ); ?>"
					alt="<?php esc_attr_e('MonsterInsights Dashboard', 'google-analytics-for-wordpress'); ?>"
					width="620"
					style="display: block; width: 100%; height: auto; border: 0;" />
			<?php endif;

			if ( ! empty( $report_description ) ) : ?>
				<p style="color: #393F4C; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; line-height: 24px; text-align: center; margin: 25px 0;">
					<?php echo wp_kses_post( $report_description ); ?>
				</p>
			<?php endif;

			if ( ! empty( $report_features ) ) : ?>
				<!-- Features Grid -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="max-width: 400px; margin: 20px auto 0 auto;">
					<tr>
						<?php
						$feature_count = 0;
						foreach ($report_features as $feature) :
							if ($feature_count > 0 && $feature_count % 2 === 0) : ?>
								</tr><tr>
							<?php endif; ?>
							<td class="mset-feature-cell" style="padding: 0 15px 15px 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 17px; color: #393F4C; vertical-align: top; width: 50%;">
								<span style="display: inline-block; font-size: 12px; line-height: 16px; padding: 3px 6px; border-radius: 32px; color: #46BF40; background-color: #EAFAEE;">&#10003;</span>
								<span><?php echo esc_html($feature); ?></span>
							</td>
						<?php
							$feature_count++;
						endforeach; ?>
					</tr>
				</table>
			<?php endif;

			if ( ! empty( $report_button_text ) && ! empty( $report_link ) ) : ?>
				<!-- CTA Button -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td align="center" style="padding: 10px 0;">
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #338EEF; border-radius: 4px; padding: 12px 24px;">
							<![endif]-->
							<a href="<?php echo esc_url( $report_link ); ?>" style="display: inline-block; background-color: #338EEF; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; text-align: center;">
								<?php echo esc_html( $report_button_text ); ?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
						</td>
					</tr>
				</table>
			<?php else : ?>
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td align="center" style="padding: 10px 0;">
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #338EEF; border-radius: 4px; padding: 12px 24px;">
							<![endif]-->
							<a href="<?php echo esc_url( monsterinsights_get_upgrade_link('lite-email-summaries') ); ?>" style="display: inline-block; background-color: #338EEF; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; text-align: center;">
								<?php esc_html_e('Upgrade and Unlock', 'google-analytics-for-wordpress'); ?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</td>
	</tr>
</table>

<!-- Analytics Stats Section -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; margin-bottom: 30px; border-radius: 4px;">
	<tr>
		<td class="mset-section-header-td" style="padding: 20px 30px; border-bottom: 1px solid #EAEAEA;">
			<h2 style="color: #393E4B; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 600; line-height: 26px; margin: 0;">
				&#128200; <?php esc_html_e('Analytics Stats', 'google-analytics-for-wordpress'); ?>
			</h2>
		</td>
	</tr>
	<tr>
		<td class="mset-section-content-td" style="padding: 20px 30px;">
			<?php if ( isset( $report_stats ) && ! empty( $report_stats ) ) : ?>
				<!-- Stats Grid -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 20px;">
					<?php
					$stat_index = 0;
					$total_stats = count( $report_stats );
					foreach ($report_stats as $stat) :
						if ($stat_index % 3 === 0) : ?>
							<tr>
						<?php endif; ?>
						<td class="mset-stat-cell" width="33%" style="padding: 5px; vertical-align: top;">
							<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #FBFDFF; border: 1px solid #E3F0FD;">
								<tr>
									<td style="text-align: center; padding: 15px 5px;">
										<!-- Icon circle -->
										<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
											<tr>
												<td style="width: 38px; height: 38px; background-color: #6F4BBB; border-radius: 50%; color: #ffffff; font-size: 16px; line-height: 38px; text-align: center; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
													<?php echo esc_html($stat['icon']); ?>
												</td>
											</tr>
										</table>
										<!-- Label -->
										<p style="color: #393F4C; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; margin: 10px 0;">
											<?php echo esc_html($stat['label']); ?>
										</p>
										<!-- Value -->
										<p style="color: #393F4C; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 600; margin: 0;">
											<?php echo esc_html($stat['value']); ?>
											<?php if (isset($stat['difference'])) : ?>
												<span style="font-size: 14px; <?php echo 'mset-text-increase' === $stat['trend_class'] ? 'color: #5CC0A5;' : 'color: #EB5757;'; ?>">
													<?php echo esc_html($stat['trend_icon']); ?> <?php echo esc_html($stat['difference']); ?>%
												</span>
											<?php endif; ?>
										</p>
									</td>
								</tr>
							</table>
						</td>
						<?php
						$stat_index++;
						if ($stat_index % 3 === 0 || $stat_index === $total_stats) : ?>
							</tr>
						<?php endif;
					endforeach; ?>
				</table>
			<?php endif; ?>

			<?php if ( isset( $reports_url ) ) : ?>
				<!-- CTA Button -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td align="center" style="padding: 10px 0;">
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #338EEF; border-radius: 4px; padding: 12px 24px;">
							<![endif]-->
							<a href="<?php echo esc_url( $reports_url ); ?>" style="display: inline-block; background-color: #338EEF; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; text-align: center;">
								<?php esc_html_e('See My Analytics', 'google-analytics-for-wordpress'); ?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php if (!empty($top_pages)) : ?>
<!-- Top 5 Pages Section -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; margin-bottom: 30px; border-radius: 4px;">
	<tr>
		<td class="mset-section-header-td" style="padding: 20px 30px; border-bottom: 1px solid #EAEAEA;">
			<h2 style="color: #393E4B; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 600; line-height: 26px; margin: 0;">
				&#127760; <?php esc_html_e('Your Top 5 Viewed Pages', 'google-analytics-for-wordpress'); ?>
			</h2>
		</td>
	</tr>
	<tr>
		<td class="mset-section-content-td" style="padding: 20px 30px;">
			<!-- Pages Table -->
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 20px;">
				<!-- Table Header -->
				<tr>
					<td style="background-color: #6F4BBB; color: #ffffff; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 600; font-size: 14px; padding: 10px 15px;">
						<?php esc_html_e('Page Title', 'google-analytics-for-wordpress'); ?>
					</td>
					<td style="background-color: #6F4BBB; color: #ffffff; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 600; font-size: 14px; padding: 10px 15px; text-align: right; width: 100px;">
						<?php esc_html_e('Page Views', 'google-analytics-for-wordpress'); ?>
					</td>
				</tr>
				<?php foreach ($top_pages as $i => $page) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive ?>
				<tr>
					<td style="padding: 10px 15px; border-bottom: 1px solid #E3F0FD; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px;">
						<a href="<?php echo esc_url($page['hostname'] . $page['url']); ?>" style="color: #23262E; text-decoration: none;">
							<?php echo esc_html((intval($i) + 1) . '. ' . monsterinsights_trim_text($page['title'], 2)); ?>
						</a>
					</td>
					<td style="padding: 10px 15px; border-bottom: 1px solid #E3F0FD; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #338EEF; text-align: right; width: 100px;">
						<?php echo esc_html(number_format_i18n($page['sessions'])); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<?php if ( isset( $reports_url ) ) : ?>
				<!-- CTA Button -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td align="center" style="padding: 10px 0;">
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #338EEF; border-radius: 4px; padding: 12px 24px;">
							<![endif]-->
							<a href="<?php echo esc_url( $reports_url ); ?>" style="display: inline-block; background-color: #338EEF; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; text-align: center;">
								<?php esc_html_e('View All Pages', 'google-analytics-for-wordpress'); ?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if ( ! empty( $blog_posts ) ) : ?>
<!-- Blog Posts Section -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; margin-bottom: 30px; border-radius: 4px;">
	<tr>
		<td class="mset-section-header-td" style="padding: 20px 30px; border-bottom: 1px solid #EAEAEA;">
			<h2 style="color: #393E4B; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 20px; font-weight: 600; line-height: 26px; margin: 0;">
				&#11088; <?php esc_html_e('What\'s New at MonsterInsights', 'google-analytics-for-wordpress'); ?>
			</h2>
		</td>
	</tr>
	<tr>
		<td class="mset-section-content-td" style="padding: 20px 30px;">
			<?php foreach ( $blog_posts as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive ?>
				<!-- Blog Post -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #E3F0FD;">
					<tr>
						<?php if ( ! empty( $post['featured_image'] ) ) : ?>
						<td class="mset-blog-image-cell" width="230" style="vertical-align: top; padding-right: 20px; width: 230px;">
							<img src="<?php echo esc_url( $post['featured_image'] ); ?>"
								 alt="<?php echo esc_attr( $post['title'] ); ?>"
								 width="230"
								 style="display: block; width: 230px; height: auto; border: 0; border-radius: 4px;" />
						</td>
						<?php endif; ?>
						<td class="mset-blog-content-cell" style="vertical-align: top; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
							<h4 style="font-weight: 700; font-size: 16px; line-height: 24px; color: #23262E; margin: 0 0 8px 0;">
								<?php echo esc_html( $post['title'] ); ?>
							</h4>
							<p style="font-weight: 400; font-size: 14px; line-height: 20px; color: #393F4C; margin: 0 0 8px 0;">
								<?php echo esc_html( $post['excerpt'] ); ?>
							</p>
							<a href="<?php echo esc_url( $post['link'] ); ?>" target="_blank" rel="noopener noreferrer" style="font-weight: 400; font-size: 14px; line-height: 20px; color: #338EEF; text-decoration: underline;">
								<?php esc_html_e('Continue Reading', 'google-analytics-for-wordpress'); ?>
							</a>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>

			<?php if ( isset( $blog_posts_url ) && $blog_posts_url ) : ?>
				<!-- CTA Button -->
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td align="center" style="padding: 10px 0;">
							<!--[if mso]>
							<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color: #338EEF; border-radius: 4px; padding: 12px 24px;">
							<![endif]-->
							<a href="<?php echo esc_url( $blog_posts_url ); ?>" style="display: inline-block; background-color: #338EEF; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-weight: 500; font-size: 16px; text-align: center;">
								<?php esc_html_e('See All Resources', 'google-analytics-for-wordpress'); ?>
							</a>
							<!--[if mso]>
							</td></tr></table>
							<![endif]-->
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</td>
	</tr>
</table>
<?php endif; ?>
