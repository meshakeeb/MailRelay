<?php
/**
 * Super-simple, minimum abstraction MailRelay API v2 wrapper
 * MailRelay API v2: https://mailrelay.com/en/api-documentation
 *
 * @author Shakeeb Ahmed <me@shakeebahmed.com>
 * @version 1.0
 */

if( ! class_exists('MailRelay') ):

class MailRelay {

	private $api_key;
    private $api_endpoint = 'https://<dc>.ip-zone.com/ccm/admin/api/version/2/&type=json';

    /*  SSL Verification
        Read before disabling:
        http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
    */
    public $verify_ssl = false;

    private $request_successful = false;
    private $last_error         = '';
    private $last_response      = array();
    private $last_request       = array();

    /**
     * Create a new instance
     * @param string $api_key Your MailRelay API key
     * @throws \Exception
     */
    public function __construct( $api_key, $username ) {

		$this->api_key       = $api_key;
        $this->api_endpoint  = str_replace('<dc>', $this->clean_username( $username ), $this->api_endpoint);
        $this->last_response = array('headers' => null, 'body' => null);
    }

	public function __call( $name, $arguments ) {

		// If Allowed
		$allowed_funcs = array(

			// General Functions
			'doAuthentication',
			'sendMail',
			'setReturnType',

			// SMTP
			'getSmtpTags',

			// Log Functions
			'getSends',
			'getDeliveryErrors',
			'getDayLog',
			'getMailRcptNumber',
			'getMailRcptInfo',
			'getPackages',

			// Campaigns
			'getCampaigns',
			'addCampaign',
			'updateCampaign',
			'deleteCampaign',
			'sendCampaign',
			'sendCampaignTest',

			// Campaign Folders
			'getCampaignFolders',
			'addCampaignFolder',
			'updateCampaignFolder',
			'deleteCampaignFolder',

			// Mailing lists
			'getMailingLists',
			'cancelMailingList',
			'pauseMailingList',
			'resumeMailingList',

			// Groups
			'getGroups',
			'addGroup',
			'updateGroup',
			'deleteGroup',

			// Subscribers
			'getSubscribers',
			'addSubscriber',
			'updateSubscriber',
			'updateSubscribers',
			'deleteSubscriber',
			'assignSubscribersToGroups',
			'unsubscribe',

			// Import
			'import',
			'getImports',
			'getImportData',

			// Mailboxes
			'getMailboxes',
			'addMailbox',
			'updateMailbox',
			'deleteMailbox',
			'sendMailboxConfirmationEmail',

			// Custom Fields
			'getCustomFields',
			'addCustomField',
			'updateCustomField',
			'deleteCustomField',

			// Statistics
			'getStats',
			'getClicksInfo',
			'getUniqueClicksInfo',
			'getImpressionsInfo',
			'getUniqueImpressionsInfo',

			// Spam Reports
			'getSpamReports'
		);

		if( in_array( $name, $allowed_funcs ) ) {
			$arguments = !empty( $arguments ) && isset( $arguments[0] ) ? $arguments[0] : $arguments;
			return $this->makeRequest( $name, $arguments );
		}
		if( method_exists( $this, $name ) ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}

		trigger_error( "Call to undefined method '{$method}'" );
	}

	// ------------------- HELPER --------------------------

	private function clean_username( $username ) {
		$replace = array( 'http://', 'https://', '.ip-zone.com', '/' );
		return str_replace( $replace, '', $username );
	}

    /**
     * Was the last request successful?
     * @return bool  True for success, false for failure
     */
    public function success() {
        return $this->request_successful;
    }

    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     * @return  array|false  describing the error
     */
    public function getLastError() {
        return $this->last_error ?: false;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API response.
     * @return array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse() {
        return $this->last_response;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API request.
     * @return array  Assoc array
     */
    public function getLastRequest() {
        return $this->last_request;
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param  string $method The API method to be called
     * @param  array $args Assoc array of parameters to be passed
     * @param int $timeout
     * @return array|false Assoc array of decoded result
     * @throws \Exception
     */
    private function makeRequest( $method, $args = array(), $timeout = 10 ) {

		if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $this->last_error         = '';
        $this->request_successful = false;
        $response                 = array('headers' => null, 'body' => null);
        $this->last_response      = $response;

        $this->last_request = array(
            'body'    => '',
            'timeout' => $timeout,
        );

		$args['apiKey'] = $this->api_key;
		$args['function'] = $method;

        $ch = curl_init( $this->api_endpoint );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		$this->attachRequestPayload($ch, $args);

        $response['body']    = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);

        if (isset($response['headers']['request_header'])) {
            $this->last_request['headers'] = $response['headers']['request_header'];
        }

        if ($response['body'] === false) {
            $this->last_error = curl_error($ch);
        }

        curl_close($ch);

        $formattedResponse = $this->formatResponse($response);

        $this->determineSuccess($response, $formattedResponse);

        return $formattedResponse;
    }

    /**
     * Encode the data and attach it to the request
     * @param   resource $ch cURL session handle, used by reference
     * @param   array $data Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data) {
        $this->last_request['body'] = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    /**
     * Decode the response and format any error messages for debugging
     * @param array $response The response from the curl request
     * @return array|false    The JSON decoded into an array
     */
    private function formatResponse($response) {
        $this->last_response = $response;

        if (!empty($response['body'])) {
            return json_decode($response['body'], true);
        }

        return false;
    }

    /**
     * Check if the response was successful or a failure. If it failed, store the error.
     * @param array $response The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     * @return bool     If the request was successful
     */
    private function determineSuccess($response, $formattedResponse) {
        $status = $this->findHTTPStatus($response, $formattedResponse);

        if ($status >= 200 && $status <= 299) {
            $this->request_successful = true;
            return true;
        }

        if (isset($formattedResponse['detail'])) {
            $this->last_error = sprintf('%d: %s', $formattedResponse['status'], $formattedResponse['detail']);
            return false;
        }

        $this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
        return false;
    }

    /**
     * Find the HTTP status code from the headers or API response body
     * @param array $response The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     * @return int  HTTP status code
     */
    private function findHTTPStatus($response, $formattedResponse) {
        if (!empty($response['headers']) && isset($response['headers']['http_code'])) {
            return (int) $response['headers']['http_code'];
        }

        if (!empty($response['body']) && isset($formattedResponse['status'])) {
            return (int) $formattedResponse['status'];
        }

        return 418;
    }
}
endif;
