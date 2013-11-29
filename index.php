<?php
//
// Copyright (c) 2013, Adrian Kosmaczewski
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are met:
//
// Redistributions of source code must retain the above copyright notice, this
// list of conditions and the following disclaimer.
// Redistributions in binary form must reproduce the above copyright notice,
// this list of conditions and the following disclaimer in the documentation
// and/or other materials provided with the distribution.
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
// AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
// ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
// LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
// CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
// SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
// INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
// CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
// ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
// POSSIBILITY OF SUCH DAMAGE.
//
// This script allows to start and stop the playback of a list of movies in a
// Raspberry Pi computer, using lighttpd as a web server and the PHP package.
//
// It requires the "www-data" user to be part of the "video" and "audio" groups,
// as defined in "/etc/group".
//
// The pipe "fifo" is located in the same folder as this script; it was created
// using the commands:
//      $ mkfifo fifo
//      $ chmod 777 fifo
//
// This pipe is used to send commands to the running instance of omxplayer, just
// as if the user was typing those commands on the command line.
//

function redirect() {
    header("Location: " . basename(__FILE__));
    exit();
}

function get_available_disk_space() {
    return intval(`df -h | grep rootfs | awk '{print \$4}'`);
}

function get_current_PID() {
    return `ps -eaf | grep /usr/bin/omxplayer | grep -v grep | grep -v bash | awk '{print $2}'`;
}

function get_movie_list($basedir) {
    $movies = array();
    $d = dir($basedir);
    while (false !== ($entry = $d->read())) {
        if ($entry !== "." && $entry !== "..") {
            $movies[] = $entry;
        }
    }
    $d->close();
    sort($movies);
    return $movies;
}

function get_current_movie_name() {
    return basename(`ps -eaf | grep /usr/bin/omxplayer | grep -v grep | grep -v bash | awk '{print $11}'`);
}

function start_movie_playback($movie, $basedir, $pipe, $sound) {
    $output = "omxplayer -o $sound $basedir/$movie <$pipe &";

    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w"),  // stderr
    );

    $process = proc_open($output, $descriptorspec, $pipes, dirname(__FILE__), null);

    // Trigger the playback now (this is weird)
    $out = `echo -n "." > $pipe`;
}

function pipe_char_to_fifo($char, $pipe) {
    // Inspired from
    // http://www.raspberrypi.org/phpBB3/viewtopic.php?t=33117
    $out = `echo -n "$char" > $pipe`;
}

function checked_if($sound, $audio) {
    if ($sound === $audio) {
        return 'checked="true"';
    }
    return "";
}

function save_sound_setting($sound, $sound_setting_file) {
    // Of course, this requires "write" permissions on the current folder!
    $out = `echo '$sound' > $sound_setting_file`;
}

function load_sound_setting($sound_setting_file) {
    return trim(`cat $sound_setting_file`);
}

// Main program starts here
$pipe = dirname(__FILE__) . "/fifo";
$sound_setting_file = dirname(__FILE__) . "/sound_setting";
$basedir = dirname(__FILE__) . "/../../Videos";
$disk = get_available_disk_space();

// Let's see if the player is running already
$pid = get_current_PID();
$action = $_GET["action"];
$movie = $_GET["play"];
$sound = $_GET["sound"];
if (!$sound) {
    $sound = load_sound_setting($sound_setting_file);
    if (!$sound) {
        $sound = "local";
    }
}
else {
    save_sound_setting($sound, $sound_setting_file);
}
$movies = get_movie_list($basedir);

// Variable checks. If some parameters are passed on the URL,
// we make the corresponding action and then redirect to this same script
// without any variables.
if ($pid) {
    $movie = get_current_movie_name();
    if ($action) {
        if ($action === "stop") {
            pipe_char_to_fifo("q", $pipe);
            redirect();
        }
        else if ($action === "pause") {
            pipe_char_to_fifo(" ", $pipe);
            redirect();
        }
        else if ($action === "forward") {
            pipe_char_to_fifo("$'\e'[C", $pipe);
            redirect();
        }
        else if ($action === "backward") {
            pipe_char_to_fifo("$'\e'[D", $pipe);
            redirect();
        }
        else if ($action === "forward10") {
            pipe_char_to_fifo("$'\e'[A", $pipe);
            redirect();
        }
        else if ($action === "backward10") {
            pipe_char_to_fifo("$'\e'[B", $pipe);
            redirect();
        }
        else if ($action === "info") {
            pipe_char_to_fifo("z", $pipe);
            redirect();
        }
        else if ($action === "faster") {
            pipe_char_to_fifo("2", $pipe);
            redirect();
        }
        else if ($action === "slower") {
            pipe_char_to_fifo("1", $pipe);
            redirect();
        }
        else if ($action === "volup") {
            pipe_char_to_fifo("+", $pipe);
            redirect();
        }
        else if ($action === "voldown") {
            pipe_char_to_fifo("-", $pipe);
            redirect();
        }
    }
}
else if ($movie) {
    start_movie_playback($movie, $basedir, $pipe, $sound);
    redirect();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-title" content="Movies">

    <title>Movies</title>
    <link rel="apple-touch-icon" href="movies.png">
    <link rel="stylesheet" href="http://code.jquery.com/mobile/1.3.2/jquery.mobile-1.3.2.min.css">
    <script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
    <script src="http://code.jquery.com/mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>
<script>
$(document).on('pageinit', '#main', function() {
    $('input[name="sound"]').change(function (event) {
        var value = $(this).val();
        location.replace('<?= basename(__FILE__) ?>?sound=' + value);
    });
});
</script>
</head>

<body>

<?php
if ($pid) {
?>

    <div data-role="page" data-theme="b" id="detail">
        <div data-role="header" data-position="fixed">
            <h1><?php echo $movie ?>
            </h1>
        </div>

        <div data-role="content">
            <p>Control</p>

            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="refresh" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=pause">Pause / Resume</a>
                <a data-role="button" data-icon="delete" 
                data-rel="dialog" href="#confirm">Stop</a>
            </div>
            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="plus" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=volup">Vol UP</a>
                <a data-role="button" data-icon="minus" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=voldown">Vol DOWN</a>
            </div>

            <p>Skip</p>

            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="arrow-l" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=backward">Back 30s</a> 
                <a data-role="button" data-icon="arrow-r" data-iconpos="right" 
                data-ajax="false" href="<?= basename(__FILE__) ?>?action=forward">Forw 30s</a>
            </div>
            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="arrow-l" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=backward10">Back 10m</a> 
                <a data-role="button" data-icon="arrow-r" data-iconpos="right" 
                data-ajax="false" href="<?= basename(__FILE__) ?>?action=forward10">Forw 10m</a>
            </div>
            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="arrow-d" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=slower">Slower</a>
                <a data-role="button" data-icon="arrow-u" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=faster">Faster</a> 
            </div>

            <p>More</p>

            <div data-role="controlgroup" data-type="horizontal">
                <a data-role="button" data-icon="info" data-ajax="false" 
                href="<?= basename(__FILE__) ?>?action=info">Toggle Info</a>
            </div>
        </div>
    </div>

    <div data-role="page" id="confirm">
        <div data-role="header">
            <h1>Are you sure?</h1>
        </div>

        <div data-role="content">
            <a data-role="button" data-rel="back" href="#main">No</a> 
            <a data-role="button" data-ajax="false" 
            href="<?= basename(__FILE__) ?>?action=stop">Yes</a>
        </div>
    </div>

<?php
}
else {
?>
    <div data-role="page" data-theme="b" id="main">
        <div data-role="header" data-position="fixed">
            <h1>Available Movies</h1>
        </div>

        <div data-role="content">
            <ul data-role="listview">
<?php
$format = '<li><a data-ajax="false" href="%s?play=%s">%s</a></li>';
foreach ($movies as $entry) {
    $str = sprintf($format, basename(__FILE__), $entry, $entry);
    echo($str);
}
?>
            </ul>
            <h4 align="center"><?php echo $disk ?> GB available</h4>
        </div>

        <div data-role="footer" data-position="fixed" class="ui-bar">
            <div data-role="controlgroup" data-type="horizontal" data-mini="true">
                <label><input id="hdmiaudio" type="radio" name="sound" 
                value="hdmi"<?= checked_if($sound, "hdmi") ?>>Audio on TV</label>
                <label><input id="localaudio" type="radio" name="sound"
                value="local"<?= checked_if($sound, "local")?>>Speakers</label>
            </div>
        </div>
    </div>

<?php
}
?>

</body>
</html>
