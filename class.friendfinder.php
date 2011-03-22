<?php

ini_set('memory_limit', '128M');

class FriendFinder {

	var $initial_profile_url; 
	var $valid_friend_urls = array();

	/**
	 * Mega Regex of services that you want to crawl
	 * @var Private $valid_service_regex
	 **/
	var $valid_service_regex = '/http:\/\/(github\.com\/.*)|http:\/\/www.flickr.com\/photos\/*|http:\/\/twitter.com\/*|http:\/\/quora.com\/*|http:\/\/linkedin.com\/*/i';
	
	/**
	 * Regex for the profile you are looking for
	 * @var Private $valid_profile_regex
	 **/
	 var $valid_profile_regex = '/http:\/\/www.flickr.com\/photos\/*/i';
	
	/**
	 * Endpoint for the Social Graph API
	 * @var Private $endpoint
	 **/
	var $endpoint = 'http://socialgraph.apis.google.com/lookup';
	
	/**
	 * Query parameters to send to API
	 * edo: Return edges out from returned nodes
	 * edi: Return edges in to returned nodes
	 * fme: Follow 'me' links, also returning reachable nodes
	 * @var Private $query_params
	 **/
	var $query_params = array(
		'edo' => 1,
		'edi' => 1,
		'fme' => 1
	);

	/**
	 * Constructor method for FriendFinder
	 * @param String $initial_profile_url User Profile to Crawl From
	 **/
	function __construct($initial_profile_url) {
		$this->initial_profile_url = $initial_profile_url;
		$this->find_friends();
		$this->display_friends();
	}

	/**
	 * Do the huge crawl to find friend profiles
	 **/
	function find_friends() {
		$initial_json = $this->make_request($this->initial_profile_url);
		$user_info = json_decode($initial_json, true);
		$node_keys = array_keys($user_info['nodes']);

		foreach($node_keys as $node_key => $node_value) {

			$all_profiles = array_merge(
				$user_info['nodes']["$node_value"]['claimed_nodes'],
				$user_info['nodes']["$node_value"]['unverified_claiming_nodes']
			);

			foreach($all_profiles as $p => $profile) {

				if(preg_match_all($this->valid_service_regex, $profile, $arr, PREG_PATTERN_ORDER) === 1) {

					$second_json = $this->make_request($profile);
					$second_array = json_decode($second_json, true);
					$contacts = $second_array['nodes'][$profile]['nodes_referenced'];

					foreach($contacts as $contact_url => $contact) {

						if(!in_array('me', $contact['types'])) {

							$friend_request = $this->make_request($contact_url);
							$friend_array = json_decode($friend_request, true);
							$friend_profile_urls = array_keys($friend_array['nodes']);

							foreach($friend_profile_urls as $k => $friend_profile_url) {

								$claimed_profile_url = preg_grep($this->valid_profile_regex, $friend_array['nodes']["$friend_profile_url"]['claimed_nodes']);
								$unclaimed_profile_url = preg_grep($this->valid_profile_regex, $friend_array['nodes']["$friend_profile_url"]['unverified_claiming_nodes']);

								if(count($claimed_profile_url) != 0 && count($claimed_profile_url) <= 2) {

									$this->valid_friend_urls = array_merge($this->valid_friend_urls, $claimed_profile_url);

								} elseif(count($unclaimed_profile_url) != 0 && count($unclaimed_profile_url) <= 2) {

									$this->valid_friend_urls = array_merge($this->valid_friend_urls, $unclaimed_profile_url);

								}	
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Just show the results, for now
	 **/
	function display_friends() {

		print_r(array_unique($this->valid_friend_urls));

	}

	/**
	 * Make a request to the Social Graph API	 
	 * @param String $profile_url 
	 * @return String $payload
	 **/
	function make_request($profile_url) {

		$this->query_params['q'] = $profile_url;
		$final_url = $this->endpoint.'?'.http_build_query($this->query_params);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $final_url);
		$payload = curl_exec($ch);
		curl_close($ch);

		return $payload;

	}

}

new FriendFinder('http://www.yelp.com/user_details?userid=59SbbCoprJ1kSELJxafIGA');

?>