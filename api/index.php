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

require 'Slim/Slim.php';
require 'lib.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

// Main program starts here
$pipe = dirname(__FILE__) . "/../fifo";
$sound_setting_file = dirname(__FILE__) . "/../sound_setting";
$basedir = "/media/usb1/videos";

$get_disk = function () {
	$response = [
		"method" => "disk",
		"response" => get_available_disk_space(),
		"unit" => "GB"
	];
    echo json_encode($response);
};

$get_movies = function () use ($basedir) {
    $response = [
        "method" => "movies",
        "response" => get_movie_list($basedir)
    ];
    echo json_encode($response);
};

$get_current_movie = function () {
    $response = [
        "method" => "current_movie",
        "response" => get_current_movie_name()
    ];
    echo json_encode($response);
};

$get_sound = function () use ($sound_setting_file) {
    $response = [
        "method" => "sound",
        "response" => load_sound_setting($sound_setting_file)
    ];
    echo json_encode($response);
};

$post_sound = function ($sound) use ($sound_setting_file) {
    if ($sound && ($sound === 'hdmi' || $sound === 'local')) {
        save_sound_setting($sound, $sound_setting_file);
        $response = [
            "method" => "set_sound",
            "response" => load_sound_setting($sound_setting_file)
        ];
    }
    else {
        $response = [
            "method" => "error",
            "response" => "Please specify 'local' or 'hdmi' as the 'value' parameter"
        ];
    }
    echo json_encode($response);
};

$post_play_movie = function ($movie) use ($basedir, $sound_setting_file, $pipe) {
    $movies = get_movie_list($basedir);
    if ($movie && in_array($movie, $movies)) {
        $sound = load_sound_setting($sound_setting_file);
        start_movie_playback($movie, $basedir, $pipe, $sound);
        $response = [
            "method" => "play",
            "response" => $movie,
            "sound" => $sound
        ];
    }
    else {
        $response = [
            "method" => "error",
            "response" => "Please specify a valid movie name as the 'value' parameter (received '$movie')"
        ];
    }
    echo json_encode($response);
};

$post_command_action = function ($action) use ($pipe, $sound_setting_file) {
    $pid = get_current_PID();
    if ($pid) {
        $chars = [
            "stop" =>       "q",
            "pause" =>      " ",
            "forward" =>    "$'\e'[C",
            "backward" =>   "$'\e'[D",
            "forward10" =>  "$'\e'[A",
            "backward10" => "$'\e'[B",
            "info" =>       "z",
            "faster" =>     "2",
            "slower" =>     "1",
            "volup" =>      "+",
            "voldown" =>    "-"
        ];
        if ($action && array_key_exists($action, $chars)) {
            $char = $chars[$action];
            pipe_char_to_fifo($char, $pipe);
            $sound = load_sound_setting($sound_setting_file);
            $response = [
                "method" => "command",
                "response" => $action,
                "sound" => $sound
            ];
        }
        else {
            $commands = implode(", ", array_keys($chars));
            $response = [
                "method" => "error",
                "response" => "Invalid command; try any of these: " . $commands
            ];
        }
    }
    else {
        $response = [
            "method" => "error",
            "response" => "No movie is playing"
        ];
    }
};

$app->get('/disk',             $get_disk);
$app->get('/movies',           $get_movies);
$app->get('/current_movie',    $get_current_movie);
$app->get('/sound',            $get_sound);
$app->post('/sound/:sound',    $post_sound);
$app->post('/play/:movie',     $post_play_movie);
$app->post('/command/:action', $post_command_action);
$app->run();

