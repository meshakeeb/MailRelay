# Mailrelay

Mailrelay is a simple PHP wrapper for [Mailrelay.com][2] API. You can do a large number of operations like managing subscribers and campaigns, sending your newsletters, getting your stats data, etc.

# Installation

Simply download it and include it in your project:

    require_once 'MailRelay.php';
    
# Examples

You can check the [API documentation][1] to view a list of all available functions. All functions can be called with this wrapper.

If you want to create a subscriber, simply use:

    # Set your account hostname and api key here
    $hostname = 'your_account_hostname';
    $api_key = 'your_api_key';

    $api = new MailRelay( $api_key, $hostname );

    $api->addSubscriber(
    	array(
			'email' => 'email@host.com',
			'name' => 'Subscriber name',
			'groups' => array( 'group_id_1', 'group_id_2' )
		)
    );

[1]: http://mailrelay.com/en/api-documentation
[2]: http://mailrelay.com
