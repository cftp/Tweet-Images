<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); ?>

<h3><?php _e( 'Tweet Image', 'wpti' ); ?></h3>

<p><?php _e( 'These are the settings to enable hosting images from your Twitter app.', 'wpti' ); ?></p>

<?php wp_nonce_field( 'wpti_update', '_wpti_nonce' ); ?>

<table class="form-table">
	<tbody>	
		<tr>
			<th scope="row"><?php _e( 'Post notifications', 'wpti' ); ?></th>
			<td>
				<p>
					<label for="wpti_allow_tweet_image">
						<input 
							type="checkbox" 
							name="wpti_allow_tweet_image" 
							id="wpti_allow_tweet_image"
							<?php checked( $allow_tweet_image ); ?> />
							<?php if ( defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE ) : ?>
								<?php _e( 'Allow new Twitter images to be posted from my account', 'wpti' ); ?>
							<?php else :  ?>
								<?php _e( 'Allow new Twitter images to be posted by this user', 'wpti' ); ?>
							<?php endif; ?>
					</label>
				</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpti_api_endpoint"><?php _e( 'API Endpoint URL', 'wpti' ); ?></label></th>
				<td>
				<p>
					<input type="text" name="wpti_api_endpoint" id="wpti_api_endpoint" value="<?php echo esc_attr( $api_endpoint ); ?>" class="regular-text" />
					<input type="submit" class="button" name="wpti_regenerate_secret" value="<?php esc_attr_e( 'Regenerate Secret Code', 'wpti' ) ?>" />
				</p>
				<p class="description"><?php _e( 'Paste the contents of the text field above into your Twitter app settings to send your photos to this website.', 'wpti' ); ?></p>
				<p class="description"><?php _e( 'For reasons of security, the Tweet Image plugin does not require your Twitter or WordPress password when sending an image for hosting. Instead of a password, we provide a Secret Code (which is included in the "API Endpoint URL" above). Use the "Regenerate Secret Code" button if you need to change it. Happy tweeting!', 'wpti' ); ?></p>
				</td>
			</tr>
	</tbody>
</table>
