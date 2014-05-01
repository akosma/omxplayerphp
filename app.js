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

// Main namespace object of this application
var MoviePlayer = function () {

    // This variable stores the value of the movie
    // that has been selected for deletion by the user
    var movieToDelete = null;

    // This function is used for read-only operations
    var readApi = function (method, callback) {
        $.ajax('api/index.php/' + method, {
            dataType: 'json',
            success: function (obj) {
                callback(obj.response);
            }
        });
    };

    // Function used for read-write operations
    var writeApi = function (method, param) {
        $.ajax('api/index.php/' + method + '/' + escape(param), {
            type: 'POST',
            dataType: 'json'
        });
    };

    // Public interface
    return {
        playMovie: function (movieName) {
            writeApi('play', movieName);
        },

        sendCommand: function (command) {
            writeApi('command', command);
        },

        setSound: function (sound) {
            writeApi('sound', sound);
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
        },

        setMovieToDelete: function (movie) {
            if (movie) {
                movieToDelete = movie;
                $.mobile.changePage('#confirmDeletion', { role: 'dialog' });
            }
        },

        getMovieToDelete: function () {
            return movieToDelete;
        },

        deleteSelectedMovie: function () {
            if (movieToDelete) {
                writeApi('delete', movieToDelete);
                movieToDelete = null;
                $.mobile.navigate('#main');
            }
        }
    };
} ();

// pageinit event handlers, where we set
// event handlers for all the buttons in the UI
$(document).on('pageinit', '#main', function() {
    $('input[name="sound"]').change(function (event) {
        var value = $(this).val();
        MoviePlayer.setSound(value);
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

$(document).on('pageinit', '#confirm', function () {
    $('#stopButton').click(function (event) {
        MoviePlayer.sendCommand('stop');
        $.mobile.navigate('#main');
    });
    $('#cancelStopButton').click(function (event) {
        $.mobile.navigate('#detail');
    });
});

$(document).on('pageinit', '#confirmDeletion', function () {
    $('#deleteButton').click(function (event) {
        MoviePlayer.deleteSelectedMovie();
        $.mobile.navigate('#main');
    });
    $('#cancelDeleteButton').click(function (event) {
        $.mobile.navigate('#main');
    });
});

$(document).on('pagebeforeshow', '#confirmDeletion', function () {
    var movie = MoviePlayer.getMovieToDelete();
    $('#movieToDelete').text(movie);
});

$(document).on('pagebeforeshow', '#detail', function () {
    MoviePlayer.getAvailableDiskSpace(function (disk) {
        $('#diskSpaceLabelAgain').html('Available disk space: ' + disk + ' GB');
    });
});

// pagebeforeshow events, where we update the UI depending
// on the state of the backend API
$(document).on('pagebeforeshow', '#main', function() {

    // We check to see whether a movie is playing. This is
    // required since the application might be closed by the user,
    // and when relaunched, we want to display the controls for the
    // movie instead of the movie list.
    setTimeout(function () {
        MoviePlayer.getCurrentMovie(function (movieName) {
            if (movieName === '') {
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
                    $('#diskSpaceLabel').html('Available disk space: ' + disk + ' GB');
                });

                MoviePlayer.getMovieList(function (movies) {
                    var createTapHandler = function(movie) {
                        return function (event, data) {
                            MoviePlayer.playMovie(movie);
                        };
                    };
                    var createAccessoryHandler = function(movie) {
                        return function (event, data) {
                            MoviePlayer.setMovieToDelete(movie);
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

                        var deleteLink = $('<a>');
                        deleteLink.bind('tap', createAccessoryHandler(movie));

                        var newLi = $('<li>');
                        newLi.append(playLink);
                        newLi.append(deleteLink);
                        list.append(newLi);
                    }
                    list.listview('refresh');
                });
            }
            else {
                $.mobile.navigate('#detail');
            }
        });
    }, 500);
});

$(document).on('pagebeforeshow', '#detail', function() {
    // Here we timeout because the name of the movie
    // might be available a little while later
    setTimeout(function () {
        MoviePlayer.getCurrentMovie(function (movieName) {
            if (movieName === '') {
                $.mobile.navigate('#main');
            }
            else {
                $('#movieName').html(movieName);
            }
        });
    }, 500);
});

