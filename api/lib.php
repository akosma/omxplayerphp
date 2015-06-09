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

function ends_with($haystack, $needle) {
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function get_movie_list($basedir) {
    $movies = array();
    $d = dir($basedir);
    while (false !== ($entry = $d->read())) {
        if ($entry !== "."
            && $entry !== ".."
            && $entry !== ".DS_Store"
            && !ends_with($entry, ".srt")) {
                $movies[] = $entry;
        }
    }
    $d->close();
    sort($movies, SORT_FLAG_CASE | SORT_NATURAL);
    return $movies;
}

function get_available_disk_space() {
    return intval(`df -h | grep /dev/root | awk '{print \$4}'`);
}

function get_current_movie_name() {
    return basename(`ps -eo args | grep /usr/bin/omxplayer | grep -v grep | grep -v bash`);
}

function get_current_PID() {
    return `ps -eaf | grep /usr/bin/omxplayer | grep -v grep | grep -v bash | awk '{print $2}'`;
}

function save_sound_setting($sound, $sound_setting_file) {
    // Of course, this requires "write" permissions on the current folder!
    $out = `echo '$sound' > $sound_setting_file`;
}

function load_sound_setting($sound_setting_file) {
    $sound = trim(`cat $sound_setting_file`);
    if (!$sound) {
        $sound = "local";
        save_sound_setting($sound, $sound_setting_file);
    }
    return $sound;
}

function start_movie_playback($movie, $basedir, $pipe, $sound) {
    $command = "omxplayer -o $sound \"$basedir/$movie\" <$pipe &";

    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w"),  // stderr
    );

    $process = proc_open($command, $descriptorspec, $pipes, dirname(__FILE__), null);

    // Trigger the playback now (this is weird)
    $out = `echo -n "." > $pipe`;

    // At the time of this fix, the current version of omxplayer
    // has a weird bug; it starts videos at "Playspeed:2.000" but
    // this can be solved by hitting play, and then requesting a
    // slower speed; these two commands are executed below, in sequence.
    // See the comment by "cookdoeyl" at the end of this page:
    // https://www.raspberrypi.org/forums/viewtopic.php?f=35&t=15947&p=767551
    $out = `echo -n "p" > $pipe`;
    $out = `echo -n "1" > $pipe`;
    return $command;
}

function pipe_char_to_fifo($char, $pipe) {
    // Inspired from
    // http://www.raspberrypi.org/phpBB3/viewtopic.php?t=33117
    $out = `echo -n "$char" > $pipe`;
}

