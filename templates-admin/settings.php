<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); ?>
<?php require_once( $this->dir( '/category-walker.php' ) ); ?>

<div class="wrap">

	<div id="icon-options-general" class="icon32"><br></div>
	<h2><?php _e( 'Tweet Image Settings', 'wpti' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( $this->name ); ?>
		
		<h3><?php _e( 'Category', 'wpti' ); ?></h3>

		<p><?php _e( "By default, Tweet Images posts in your default category (natch), change this below.", 'wpti' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><?php _e( 'Category for images', 'wpti' ); ?></th>
					<td class="wpti-category-checklist">
						<ul><?php
							ti_terms_checklist( array(
								'input_name' => $this->name.'[categories]',
								'selected_cats' => (array) @ $options[ 'categories' ], 
							) );
						?></ul>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h3><?php _e( 'Bit.ly Link Shortening', 'wpti' ); ?></h3>

		<p><?php _e( "These Bit.ly Link Shortening options are optional and if you are happy with your link shortening then there is no need to fill them in. These options allow you to specify a separate link shortening account for your Tweet Images, for example if you want to use http://mylin.ks for your links and http://mypi.cs for your images.", 'wpti' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="bitly_login"><?php _e( 'Bit.ly Login', 'wpti' ); ?></label></th>
					<td><input type="text" name="<?php echo esc_attr( $this->name ); ?>[bitly_login]" id="bitly_login" value="<?php echo esc_attr( $options[ 'bitly_login' ] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="api_key"><?php _e( 'Bit.ly API Key', 'wpti' ); ?></label></th>
					<td><input type="text" name="<?php echo esc_attr( $this->name ); ?>[bitly_api_key]" id="bitly_api_key" value="<?php echo esc_attr( $options[ 'bitly_api_key' ] ); ?>" class="regular-text" /></td>
				</tr>
			</tbody>
		</table>

		<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wpti' ); ?>"></p>

	</form>
	
	<?php printf( __( "Test script location: %s", 'wpti' ), "<code>" . $this->url( '/test.php' ) . "</code>" ); ?>

</div>