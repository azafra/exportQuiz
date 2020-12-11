// This file is part of mod_exportquiz for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


M.mod_exportquiz = M.mod_exportquiz || {};

M.mod_exportquiz.init_attempt_form = function(Y) {
    M.core_question_engine.init_form(Y, '#responseform');
    Y.on('submit', M.mod_exportquiz.timer.stop, '#responseform');
    M.core_formchangechecker.init({formid: 'responseform'});
};

M.mod_exportquiz.init_review_form = function(Y) {
    M.core_question_engine.init_form(Y, '.questionflagsaveform');
    Y.on('submit', function(e) { e.halt(); }, '.questionflagsaveform');
};

M.mod_exportquiz.init_comment_popup = function(Y) {
    // Add a close button to the window.
    var closebutton = Y.Node.create('<input type="button" />');
    closebutton.set('value', M.util.get_string('cancel', 'moodle'));
    Y.one('#id_submitbutton').ancestor().append(closebutton);
    Y.on('click', function() { window.close() }, closebutton);
}

// Code for updating the countdown timer that is used on timed exportquizzes.
M.mod_exportquiz.timer = {
    // YUI object.
    Y: null,

    // Timestamp at which time runs out, according to the student's computer's clock.
    endtime: 0,

    // Is this a exportquiz preview?
    preview: 0,

    // This records the id of the timeout that updates the clock periodically,
    // so we can cancel.
    timeoutid: null,

    /**
     * @param Y the YUI object
     * @param start, the timer starting time, in seconds.
     * @param preview, is this a exportquiz preview?
     */
    init: function(Y, start, preview) {
        M.mod_exportquiz.timer.Y = Y;
        M.mod_exportquiz.timer.endtime = new Date().getTime() + start * 1000;
        M.mod_exportquiz.timer.preview = preview;
        M.mod_exportquiz.timer.update();
        Y.one('#exportquiz-timer').setStyle('display', 'block');
    },

    /**
     * Stop the timer, if it is running.
     */
    stop: function(e) {
        if (M.mod_exportquiz.timer.timeoutid) {
            clearTimeout(M.mod_exportquiz.timer.timeoutid);
        }
    },

    /**
     * Function to convert a number between 0 and 99 to a two-digit string.
     */
    two_digit: function(num) {
        if (num < 10) {
            return '0' + num;
        } else {
            return num;
        }
    },

    // Function to update the clock with the current time left, and submit the exportquiz if necessary.
    update: function() {
        var Y = M.mod_exportquiz.timer.Y;
        var secondsleft = Math.floor((M.mod_exportquiz.timer.endtime - new Date().getTime()) / 1000);
        // If this is a preview and time expired, display timeleft 0 and don't renew the timer.
        if (M.mod_exportquiz.timer.preview && secondsleft < 0) {
            Y.one('#exportquiz-time-left').setContent('0:00:00');
            return;
        }

        // If time has expired, Set the hidden form field that says time has expired.
        if (secondsleft < 0) {
            M.mod_exportquiz.timer.stop(null);
            Y.one('#exportquiz-time-left').setContent(M.str.exportquiz.timesup);
            var input = Y.one('input[name=timeup]');
            input.set('value', 1);
            var form = input.ancestor('form');
            if (form.one('input[name=finishattempt]')) {
                form.one('input[name=finishattempt]').set('value', 0);
            }
            M.core_formchangechecker.set_form_submitted();
            form.submit();
            return;
        }

        // If time has nearly expired, change the colour.
        if (secondsleft < 100) {
            Y.one('#exportquiz-timer').removeClass('timeleft' + (secondsleft + 2))
                    .removeClass('timeleft' + (secondsleft + 1))
                    .addClass('timeleft' + secondsleft);
        }

        // Update the time display.
        var hours = Math.floor(secondsleft / 3600);
        secondsleft -= hours * 3600;
        var minutes = Math.floor(secondsleft / 60);
        secondsleft -= minutes * 60;
        var seconds = secondsleft;
        Y.one('#exportquiz-time-left').setContent(hours + ':' +
                M.mod_exportquiz.timer.two_digit(minutes) + ':' +
                M.mod_exportquiz.timer.two_digit(seconds));

        // Arrange for this method to be called again soon.
        M.mod_exportquiz.timer.timeoutid = setTimeout(M.mod_exportquiz.timer.update, 100);
    }
};

M.mod_exportquiz.nav = M.mod_exportquiz.nav || {};

M.mod_exportquiz.nav.update_flag_state = function(attemptid, questionid, newstate) {
    var Y = M.mod_exportquiz.nav.Y;
    var navlink = Y.one('#exportquiznavbutton' + questionid);
    navlink.removeClass('flagged');
    if (newstate == 1) {
        navlink.addClass('flagged');
        navlink.one('.accesshide .flagstate').setContent(M.str.question.flagged);
    } else {
        navlink.one('.accesshide .flagstate').setContent('');
    }
};

M.mod_exportquiz.nav.init = function(Y) {
    M.mod_exportquiz.nav.Y = Y;

    Y.all('#exportquiznojswarning').remove();

    var form = Y.one('#responseform');
    if (form) {
        function find_enabled_submit() {
            // This is rather inelegant, but the CSS3 selector
            //     return form.one('input[type=submit]:enabled');
            // does not work in IE7, 8 or 9 for me.
            var enabledsubmit = null;
            form.all('input[type=submit]').each(function(submit) {
                if (!enabledsubmit && !submit.get('disabled')) {
                    enabledsubmit = submit;
                }
            });
            return enabledsubmit;
        }

        function nav_to_page(pageno) {
            Y.one('#followingpage').set('value', pageno);

            // Automatically submit the form. We do it this strange way because just
            // calling form.submit() does not run the form's submit event handlers.
            var submit = find_enabled_submit();
            submit.set('name', '');
            submit.getDOMNode().click();
        };

        Y.delegate('click', function(e) {
            if (this.hasClass('thispage')) {
                return;
            }

            e.preventDefault();

            var pageidmatch = this.get('href').match(/page=(\d+)/);
            var pageno;
            if (pageidmatch) {
                pageno = pageidmatch[1];
            } else {
                pageno = 0;
            }

            var questionidmatch = this.get('href').match(/#q(\d+)/);
            if (questionidmatch) {
                form.set('action', form.get('action') + '#q' + questionidmatch[1]);
            }

            nav_to_page(pageno);
        }, document.body, '.qnbutton');
    }

    if (Y.one('a.endtestlink')) {
        Y.on('click', function(e) {
            e.preventDefault();
            nav_to_page(-1);
        }, 'a.endtestlink');
    }

    if (M.core_question_flags) {
        M.core_question_flags.add_listener(M.mod_exportquiz.nav.update_flag_state);
    }
};

M.mod_exportquiz.secure_window = {
    init: function(Y) {
        if (window.location.href.substring(0, 4) == 'file') {
            window.location = 'about:blank';
        }
        Y.delegate('contextmenu', M.mod_exportquiz.secure_window.prevent, document, '*');
        Y.delegate('mousedown',   M.mod_exportquiz.secure_window.prevent_mouse, document, '*');
        Y.delegate('mouseup',     M.mod_exportquiz.secure_window.prevent_mouse, document, '*');
        Y.delegate('dragstart',   M.mod_exportquiz.secure_window.prevent, document, '*');
        Y.delegate('selectstart', M.mod_exportquiz.secure_window.prevent, document, '*');
        Y.delegate('cut',         M.mod_exportquiz.secure_window.prevent, document, '*');
        Y.delegate('copy',        M.mod_exportquiz.secure_window.prevent, document, '*');
        Y.delegate('paste',       M.mod_exportquiz.secure_window.prevent, document, '*');
        M.mod_exportquiz.secure_window.clear_status;
        Y.on('beforeprint', function() {
            Y.one(document.body).setStyle('display', 'none');
        }, window);
        Y.on('afterprint', function() {
            Y.one(document.body).setStyle('display', 'block');
        }, window);
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'press:67,86,88+ctrl');
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'up:67,86,88+ctrl');
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'down:67,86,88+ctrl');
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'press:67,86,88+meta');
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'up:67,86,88+meta');
        Y.on('key', M.mod_exportquiz.secure_window.prevent, '*', 'down:67,86,88+meta');
    },

    clear_status: function() {
        window.status = '';
        setTimeout(M.mod_exportquiz.secure_window.clear_status, 10);
    },

    prevent: function(e) {
        alert(M.str.exportquiz.functiondisabledbysecuremode);
        e.halt();
    },

    prevent_mouse: function(e) {
        if (e.button == 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
            // Left click on a button or similar. No worries.
            return;
        }
        e.halt();
    },

    /**
     * Event handler for the exportquiz start attempt button.
     */
    start_attempt_action: function(e, args) {
        if (args.startattemptwarning == '') {
            openpopup(e, args);
        } else {
            M.util.show_confirm_dialog(e, {
                message: args.startattemptwarning,
                callback: function() {
                    openpopup(e, args);
                },
                continuelabel: M.util.get_string('startattempt', 'exportquiz')
            });
        }
    },

    init_close_button: function(Y, url) {
        Y.on('click', function(e) {
            M.mod_exportquiz.secure_window.close(url, 0)
        }, '#secureclosebutton');
    },

    close: function(Y, url, delay) {
        setTimeout(function() {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else {
                window.location.href = url;
            }
        }, delay * 1000);
    }
};
