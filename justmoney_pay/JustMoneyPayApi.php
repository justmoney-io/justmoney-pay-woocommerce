<?php

/**
 * makes API calls with JM platform
 * Class JustMoneyPayApi
 * @package JustMoneyPay
 */
class JustMoneyPay_Api {

	const   API_URL = 'https://api-pay.just.money/v1/checkout';


	/**
	 *  sets the header with the auth has and params
	 * @return array
	 * @throws Exception
	 */
	private function get_headers() {

		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		return $headers;
	}

	/**
	 * @param string $endpoint
	 * @param array $params
	 * @param string $method
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function call( string $endpoint, array $params, $method = 'POST' ) {
		// if endpoint does not starts or end with a '/' we add it, as the API needs it
		if ( $endpoint[0] !== '/' ) {
			$endpoint = '/' . $endpoint;
		}
		if ( $endpoint[ - 1 ] !== '/' ) {
			$endpoint = $endpoint . '/';
		}

		try {
			$url = self::API_URL . $endpoint;
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->get_headers() );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			if ( $method === 'POST' ) {
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params, JSON_UNESCAPED_UNICODE ) );
			}
			$response = curl_exec( $ch );

			if ( $response === false ) {
				exit( curl_error( $ch ) );
			}
			curl_close( $ch );

			return json_decode( $response, true );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
}
