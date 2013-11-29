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

var MoviePlayer = function () {

    var readApi = function (method, callback) {
        $.ajax('api.php?method=' + method, {
            dataType: "json",
            success: function (obj) {
                callback(obj.response);
            }
        });
    };

    var writeApi = function (method, param) {
        $.ajax('api.php?method=' + method + '&value=' + param, {
            dataType: "json"
        });
    };

    return {
        playMovie: function (movieName) {
            writeApi('play', movieName);
        },
        
        sendCommand: function (command) {
            writeApi('command', command);
        },
        
        setSound: function (sound) {
            writeApi('set_sound', sound);
        },
        
        getMovieList: function (callback) {
            readApi("movies", callback);
        },
        
        getCurrentMovie: function (callback) {
            readApi("current_movie", callback);
        },
        
        getAvailableDiskSpace: function (callback) {
            readApi("disk", callback);
        },
        
        getCurrentSound: function (callback) {
            readApi("sound", callback);
        }
    };
} ();

$(document).on('pageinit', '#main', function() {
    $('input[name="sound"]').change(function (event) {
        var value = $(this).val();
        MoviePlayer.setSound(value);
    });
});

$(document).on('pagebeforeshow', '#main', function() {
    MoviePlayer.getCurrentSound(function (sound) {
        if (sound === 'local') {
            $('#localaudio').attr('checked', 'checked');
            $('#hdmiaudio').removeAttr('checked');
        }
        else if (sound === 'hdmi') {
            $('#localaudio').removeAttr('checked');
            $('#hdmiaudio').attr('checked', 'checked');
        }
        $('#localaudio').checkboxradio("refresh");
        $('#hdmiaudio').checkboxradio("refresh");
    });
    
    MoviePlayer.getAvailableDiskSpace(function (disk) {
        $('#diskSpaceLabel').html('Available ' + disk + 'GB');
    });
    
    MoviePlayer.getMovieList(function (movies) {
        var createTapHandler = function(movie) {
            return function (event, data) {
                MoviePlayer.playMovie(movie);
            };
        };
        var list = $('#movieList');
        list.empty();
        for (var index = 0, length = movies.length; index < length; ++index) {
            var movie = movies[index];
            
            var playLink = $('<a>');
            playLink.attr('href', '#detail');
            playLink.attr('data-transition', 'slide');
            playLink.bind('tap', createTapHandler(movie));
            playLink.append(movie);

            var newLi = $('<li>');
            newLi.append(playLink);
            list.append(newLi);
        }
        list.listview('refresh');
    });
});

$(document).on('pageinit', '#detail', function () {
    var commands = ['pause', 'volup', 'voldown', 'backward', 'forward', 
                    'backward10', 'forward10', 'slower', 'faster', 'info'];
    var createCommandHandler = function(command) {
        return function (event) {
            MoviePlayer.sendCommand(command);
        };
    };

    for (var index = 0, len = commands.length; index < len; ++index) {
        var item = commands[index];
        $('#' + item + 'Button').click(createCommandHandler(item));
    }
});

$(document).on('pagebeforeshow', '#detail', function() {
    setTimeout(function () {
        MoviePlayer.getCurrentMovie(function (movieName) {
            $('#movieName').html(movieName);
        });
    }, 500);
});

$(document).on('pageinit', '#confirm', function () {
    $('#stopButton').click(function (event) {
        MoviePlayer.sendCommand('stop');
        $.mobile.navigate('#main');
    });
});
