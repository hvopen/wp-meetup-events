<?php
/**
  * @package WP_Meetup_Events
  * @version 0.1
  */

/*
  Plugin Name: Meetup Events
  Plugin URI: http://github.com/sdague/wp-meetup-events
  Description: This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation summed up in two words sung most famously by Louis Armstrong: Hello, Dolly. When activated you will randomly see a lyric from <cite>Hello, Dolly</cite> in the upper right of your admin screen on every page.
  Author: Sean Dague
  Version: 0.1
  Author URI: http://dague.net
*/

add_action('save_post', 'wp_meetup_events_sync');
add_action('admin_init', 'wp_meetup_events_register_settings');

  /**
     Guess the venue from the name. Meetup API provides an interface
     that returns that most active 10 venues, so this can be used to
     fuzzy match our names.
   */
function wp_meetup_events_guess_venue($post_id) {
    $params = array(
                  "key" => get_option("wp_meetup_apikey"),
                  "sign" => "true"
                  );
    $payload = http_build_query($params, '', '&');
    $url = "https://api.meetup.com/mhvlug/venues?" . $payload;
    error_log("URL: $url");
    $venue = tribe_get_venue($post_id);
    error_log("Venue: $venue");

    $data = array(
	'method' => 'GET',
	'timeout' => 45,
	'redirection' => 5,
	'blocking' => true,
        'headers' => array(
                           'Content-Type' => 'application/x-www-form-urlencoded',
                           'Accept-Charset' => 'utf-8'),
        'cookies' => array()
                  );

    $response = wp_remote_get( $url, $data);

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
    } else {
        $json = json_decode($response['body']);
        foreach ($json as $v) {
            error_log("Venue Name: " . $v->name);
            if ($venue == $v->name) {
                return $v->id;
            }
        }

        error_log("No suitable venue found...");
    }
}

function wp_meetup_events_create($post_id) {

    $MEETUP_API = "https://api.meetup.com/2/event";

    $meetup_id = get_post_meta($post_id, '_MeetupID', true);
    if ($meetup_id) {
        $MEETUP_API .= "/$meetup_id";
    }
    error_log("Meetup ID: $meetup_id");

    $key = get_option("wp_meetup_apikey");
    $group_id = get_option("wp_meetup_id");
    $post = get_post($post_id);
    $title = $post->post_title;
    $desc = $post->post_content;
    $tz_string = get_post_meta( $post_id, '_EventTimezone', true );
    # this converts us over to UTC time, which is what meetup needs
    $start = Tribe__Events__Timezones::to_utc( tribe_get_start_date( $post_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT ), $tz_string, 'c' );
    $start = new DateTime($start);

    $end = Tribe__Events__Timezones::to_utc( tribe_get_end_date( $post_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT ), $tz_string, 'c' );
    $end = new DateTime($end);
    # $timezone = Tribe__Events__Timezones::get_event_timezone_string( $post_id );
    # error_log("Start: $start");

    # return;
    # error_log($start);
    # return;

    # $start = new DateTime($start); # get_post_meta($post_id, '_EventStartDate', true));

    $data = array(
	'method' => 'POST',
	'timeout' => 45,
	'redirection' => 5,
	'blocking' => true,
        'headers' => array(
                           'Content-Type' => 'application/x-www-form-urlencoded',
                           'Accept-Charset' => 'utf-8'),
        'body' => array(
                        // remove the TEST bit once we get to production
                        'name' => "TEST: $title",
                        'key' => $key,
                        'group_id' => $group_id,
                        'time' => $start->getTimestamp() * 1000,
                        'description' => $desc,
                        'duration' => ($end->getTimestamp() - $start->getTimestamp()) * 1000,
                        ),
        'cookies' => array()
                  );
    error_log("API: $MEETUP_API");
    $response = wp_remote_post( $MEETUP_API, $data);

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
        return;
    }

    $json = json_decode($response['body']);

    if (isset($json->code) && $json->code == "not_found") {
        error_log("Code was not found, deleting the meetup id");
        delete_post_meta($post_id, '_MeetupID');
        return;
    }

    $new_meetup_id = $json->id;
    error_log(print_r($json, true));
    error_log("Meetup ID: $new_meetup_id");
    if ($meetup_id) {
        error_log("Trying to update");
        update_post_meta($post_id, '_MeetupID', $new_meetup_id);
    } else {
        error_log("Trying to add");
        add_post_meta($post_id, '_MeetupID', $new_meetup_id, true);
        update_post_meta($post_id, '_MeetupID', $new_meetup_id);
    }
    error_log(get_post_meta($post_id, "_MeetupID", true));
  # error_log(print_r($response, true));

    // We have to set the venue as an update, because the API expects
    // a flow that looks like the manual flow.
    $venue_id = wp_meetup_events_guess_venue($post_id);

    if (! $venue_id ) {
        error_log("No venue id found, not setting in meetup");
        return;
    }

    error_log("Setting venue_id: " . $venue_id);
    $data = array(
                  'method' => 'POST',
                  'timeout' => 45,
                  'redirection' => 5,
                  'blocking' => true,
                  'headers' => array(
                                     'Content-Type' => 'application/x-www-form-urlencoded',
                                     'Accept-Charset' => 'utf-8'),
                  'body' => array(
                                  'venue_id' => $venue_id,
                                  ),
                  'cookies' => array()
                  );
    $response = wp_remote_post( "https://api.meetup.com/2/event/$new_meetup_id", $data);
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
        return;
    }


    #);
    # error_log(print_r($data, true));
}

function wp_meetup_events_sync($post_id){

    $post_type = get_post_type($post_id);

    if ($post_type == 'tribe_events'){

        $event_datee = get_post_meta($post_id, '_EventStartDate', true);
        $month = date("m",strtotime($event_datee));
        wp_meetup_events_create($post_id);
        error_log("Found an event!" . $post_id);
        # update_post_meta($post_id, 'event_month', $month);
    }

}

function wp_meetup_events_section($args) {
    echo "<p>Settings for Wordpress to Meetup Synchronization</p>";
}


function wp_meetup_events_register_settings() {
    register_setting( 'writing', 'wp_meetup_id' );
    register_setting( 'writing', 'wp_meetup_apikey' );

    add_settings_section( "wp_meetup",
                          "Meetup Events Sync Options",
                          "wp_meetup_events_section",
                          "writing");

    add_settings_field(
                       "wp_meetup_events_meetup_apikey",
                       "Meetup API Key",
                       "wp_meetup_events_meetup_apikey_cb",
                       "writing",
                       "wp_meetup");
    add_settings_field(
                       "wp_meetup_events_meetup_id",
                       "Meetup ID",
                       "wp_meetup_events_meetup_id_cb",
                       "writing",
                       "wp_meetup");
}

function wp_meetup_events_meetup_id_cb($args) {
    // get the value of the setting we've registered with register_setting()
    $setting = get_option('wp_meetup_id');
    // output the field
    ?>
    <input type="text" name="wp_meetup_id" value="<?= isset($setting) ? esc_attr($setting) : ''; ?>">
    <?php
 }

function wp_meetup_events_meetup_apikey_cb($args) {
    // get the value of the setting we've registered with register_setting()
    $setting = get_option('wp_meetup_apikey');
    // output the field
    ?>
    <input type="text" name="wp_meetup_apikey" value="<?= isset($setting) ? esc_attr($setting) : ''; ?>">
    <?php
 }
