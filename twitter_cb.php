<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/action_base.php';
use Spatie\TwitterStreamingApi\PublicStream;

// twitter credentials
$user_auth = array(
                 "access_token"           => '837914132871438336-u0aAg6m4YigikjCTmL4zoclNs5ZHs7r',
                 "access_token_secret"    => 'NgJ7tRGPGUzg14wuxKLafw2sUGgvO2oYFSChhzIyz83Hl',
                 "consumer_key"           => 'S678Wjbn8FpNHCTtbmnq7iSzf',
                 "consumer_secret"        => 'fkdzepoKKe3W1a7lnlVv7IIXas2ZIHCBHuSUjvymWkKztFnEaQ'
             );


PublicStream::create(
    $user_auth['access_token'],
    $user_auth['access_token_secret'],
    $user_auth['consumer_key'],
    $user_auth['consumer_secret']
)->whenHears('@recog_bot', function(array $tweet) {
    syslog(LOG_INFO, "twitter_cb: We got mentioned by {$tweet['user']['screen_name']} who tweeted {$tweet['text']}");
    //
    global $user_auth;
    $ret_str = searchPersonTwitterLink($tweet, $user_auth);

})->startListening();
