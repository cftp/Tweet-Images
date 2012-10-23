<?php

/*  Copyright 2010 Simon Wheatley

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
* 
*/
class WPTI_Bitly {
	
	/**
	 * The version of the Bit.ly API that we're aiming at
	 *
	 * @var int
	 **/
	protected $version;
	
	/**
	 * A login for the Bit.ly API
	 *
	 * @var string
	 **/
	protected $login;
	
	/**
	 * An API key associated with the login for the Bit.ly API
	 *
	 * @var string
	 **/
	protected $api_key;
	
	/**
	 * The URL to next be used for a request
	 *
	 * @var string
	 **/
	protected $url;
	
	/**
	 * An array of arguments to pass with the request
	 *
	 * @var array
	 **/
	protected $args;
	
	/**
	 * undocumented function
	 *
	 * @param string $login The login for Bit.ly 
	 * @param string $api_key The Bit.ly API key for this user
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function __construct( $login, $api_key ) {
		$this->version = 3;
		$this->login = $login;
		$this->api_key = $api_key;
	}
	
	/**
	 * Shorten a URL using the Bit.ly API
	 *
	 * @param string $url The URL to shorten
	 * @return string|object The shortened URL if it succeeds, or a WP_Error if there was a problem with WP HTTP API
	 * @author Simon Wheatley
	 **/
	public function shorten( $long_url ) {
		$this->set_base_url( 'shorten' );
		$this->set_args( array( 'longUrl' => $long_url ) );
		$this->construct_url();
		$response = wp_remote_get( $this->url, array() );
		// Some kind of HTTP error
		if ( is_wp_error( $response ) )
			return $response;
		$body = json_decode( $response[ 'body' ] );
		// Some kind of API error
		if ( $body->status_code != 200 )
			return new WP_Error( 'bitly_api_error', $body->status_txt );
		return $body->data->url;
	}
	
	/**
	 * Set the "url" property, which is the base URL we will be addressing
	 * with our request.
	 *
	 * @param string A valid action string in the Bit.ly API 
	 * @param string A valid format supported by the Bit.ly API 
	 * @return string The constructed URL
	 * @author Simon Wheatley
	 **/
	protected function set_base_url( $action, $format = 'json' ) {
		// http://api.bit.ly/v3/shorten?login=bitlyapidemo&apiKey=R_0da49e0a9118ff35f52f629d2d71bf07&longUrl=http%3A%2F%2Fbetaworks.com%2F&format=json
		$this->url = "http://api.bit.ly/v{$this->version}/{$action}";
	}
	
	/**
	 * Set the arguments for the request, pass the specific arguments and 
	 * they will be merged with the generally required API arguments. Expects
	 * everything un-urlencoded as it will urlencode itself.
	 *
	 * @param array $args An array of arguments, each value UN-urlencoded (we encode them here)
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function set_args( $args = array() ) {
		$this->args = $args + array( 'apiKey' => $this->api_key, 'format' => 'json', 'login' => $this->login );
		$this->args = urlencode_deep( $this->args );
	}
	
	/**
	 * Assemble the URL with all the GET params in place
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function construct_url() {
		$this->url = add_query_arg( $this->args, $this->url );
	}

}



?>