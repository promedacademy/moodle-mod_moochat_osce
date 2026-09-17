// This file is part of Moodle - http://moodle.org/
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

/**
 * MooChat JavaScript module
 *
 * @module     mod_moochat/chat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    return {
        /**
         * Initialise the MooChat interface.
         * @param {int} moochatid The moochat instance ID
         */
        init: function(moochatid) {

            var conversationHistory = [];
            var remainingQuestions  = -1; // -1 = unlimited
            var hasChattedOnce      = false;
            var strings             = [];
            var sessionId           = generateSessionId(); // New UUID per page load

            // ------------------------------------------------------------------
            // Generate a simple session UUID (alphanumeric only for PARAM_ALPHANUMEXT).
            // ------------------------------------------------------------------
            /**
             * Generate a random session ID string.
             * @returns {string} A random alphanumeric session ID
             */
            function generateSessionId() {
                var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                var id = 's';
                for (var i = 0; i < 31; i++) {
                    id += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return id;
            }

            // Load language strings.
            Str.get_strings([
                {key: 'questionsremaining_js',  component: 'mod_moochat'}, // 0
                {key: 'thinking_js',             component: 'mod_moochat'}, // 1
                {key: 'ratelimitreached_title',  component: 'mod_moochat'}, // 2
                {key: 'error_title',             component: 'mod_moochat'}, // 3
                {key: 'connectionerror',         component: 'mod_moochat'}, // 4
                {key: 'chatcleared',             component: 'mod_moochat'}, // 5
                {key: 'confirmclear',            component: 'mod_moochat'}, // 6
                {key: 'chattingwith',            component: 'mod_moochat'}, // 7
                {key: 'objectiveschecking',      component: 'mod_moochat'}, // 8
                {key: 'objectivescheckingdone',  component: 'mod_moochat'}, // 9
                {key: 'objectiveunlocked',       component: 'mod_moochat'}, // 10
                {key: 'scorelabel',              component: 'mod_moochat'}, // 11
                {key: 'bestscorelabel',          component: 'mod_moochat'}  // 12
            ]).done(function(s) {
                strings = s;
            });

            var messagesDiv   = $('#moochat-messages-'   + moochatid);
            var inputField    = $('#moochat-input-'      + moochatid);
            var sendButton    = $('#moochat-send-'       + moochatid);
            var clearButton   = $('#moochat-clear-'      + moochatid);
            var remainingDiv  = $('#moochat-remaining-'  + moochatid);
            var objectivesDiv = $('#moochat-objectives-' + moochatid);
            var interfaceDiv  = $('#moochat-' + moochatid);
            osceMode          =  interfaceDiv.data('osce-mode') == 1;
            sessionTimeLimit  =  parseInt(interfaceDiv.data('session-time-limit'),10) || 0;
            var timerDiv      =  $('#moochat-osce-timer-' + moochatid);
    
            // ------------------------------------------------------------------
            // Update the remaining-questions display.
            // ------------------------------------------------------------------
            var updateRemaining = function(remaining) {
                if (remaining >= 0) {
                    var msg = strings[0] + ': ' + remaining;
                    remainingDiv.html('<div class="alert alert-info">' + msg + '</div>');
                    remainingDiv.show();
                } else {
                    remainingDiv.hide();
                }
            };

            // ------------------------------------------------------------------
            // Update the objectives panel.
            // Only show objectives that have been met — hidden until earned.
            // Newly-met objectives appear with a highlight animation.
            // ------------------------------------------------------------------
            var updateObjectivesPanel = function(data, newlyMetIndices) {
                if (!objectivesDiv.length || !data) {
                    return;
                }

                // Filter to only met objectives.
                var metResults = data.results ? data.results.filter(function(r) { return r.met; }) : [];

                var html = '<div class="moochat-objectives-panel">';

                // Score display.
                if (data.grademax > 0) {
                    html += '<div class="moochat-score-display">';
                    html += '<span class="moochat-score-label">' + (strings[11] || 'Score') + ':</span> ';
                    html += '<strong class="moochat-score-value">' + data.rawgrade + ' / ' + data.grademax + '</strong>';
                    if (data.bestscore !== undefined && data.bestscore > data.rawgrade) {
                        html += '<br><span class="moochat-bestscore small text-muted">';
                        html += (strings[12] || 'Best') + ': ' + data.bestscore + ' / ' + data.grademax;
                        html += '</span>';
                    }
                    html += '</div>';
                }

                // Progress bar (always visible so student knows there are objectives).
                var pct = (data.totalcount > 0) ? Math.round((data.metcount / data.totalcount) * 100) : 0;
                html += '<div class="moochat-objectives-progress mb-2">';
                html += '<div class="moochat-progress-label">';
                html += '<span class="badge badge-secondary">' + data.metcount + ' / ' + data.totalcount + '</span>';
                html += '</div>';
                html += '<div class="progress moochat-progress mt-1" title="' + pct + '% complete">';
                html += '<div class="progress-bar bg-success" role="progressbar" ';
                html += 'style="width:' + pct + '%" aria-valuenow="' + pct + '" ';
                html += 'aria-valuemin="0" aria-valuemax="100"></div>';
                html += '</div>';
                html += '</div>';

                // Show met objectives (revealed one at a time).
                if (metResults.length > 0) {
                    html += '<ul class="moochat-objectives-list list-unstyled mb-0">';
                    for (var i = 0; i < metResults.length; i++) {
                        var obj = metResults[i];
                        var isNew = newlyMetIndices && newlyMetIndices.indexOf(obj.index) >= 0;
                        var cls = 'moochat-objective-item moochat-obj-met' + (isNew ? ' moochat-obj-newlymet' : '');
                        html += '<li class="' + cls + '">';
                        html += '<span class="moochat-obj-icon">&#10003;</span> ';
                        html += '<span class="moochat-obj-text">' + escapeHtml(obj.objective) + '</span>';
                        if (isNew) {
                            html += ' <span class="badge badge-success moochat-new-badge">+pts</span>';
                        }
                        html += '</li>';
                    }
                    html += '</ul>';
                } else {
                    html += '<p class="moochat-objectives-hint small text-muted">' +
                            (strings[10] || 'Objectives will appear here as you discover them!') +
                            '</p>';
                }

                html += '</div>';
                objectivesDiv.html(html);

                // Remove the highlight class after animation completes.
                if (newlyMetIndices && newlyMetIndices.length > 0) {
                    setTimeout(function() {
                        objectivesDiv.find('.moochat-obj-newlymet').removeClass('moochat-obj-newlymet');
                    }, 3000);
                }
            };

            // ------------------------------------------------------------------
            // Check objectives via AJAX after each reply.
            // ------------------------------------------------------------------
            var checkObjectives = function() {
                if (!objectivesDiv.length) {
                    return;
                }

                Ajax.call([{
                    methodname: 'mod_moochat_check_objectives',
                    args: {
                        moochatid: moochatid,
                        history:   JSON.stringify(conversationHistory),
                        sessionid: sessionId
                    }
                }])[0].then(function(response) {
                    if (response.success) {
                        // Determine which were newly met by comparing to previous state.
                        var prevMet = getStoredMet();
                        var newlyMet = [];
                        if (response.results) {
                            response.results.forEach(function(r) {
                                if (r.met && prevMet.indexOf(r.index) < 0) {
                                    newlyMet.push(r.index);
                                }
                            });
                        }
                        storeMet(response.results);
                        updateObjectivesPanel(response, newlyMet);

                        // If any newly met, show a brief notification in chat.
                        if (newlyMet.length > 0) {
                            newlyMet.forEach(function(idx) {
                                var obj = response.results.find(function(r) { return r.index === idx; });
                                if (obj) {
                                    showObjectiveToast(obj.objective, response.rawgrade, response.grademax);
                                }
                            });
                        }
                    }
                }).catch(function() {
                    // Silently ignore objective-check failures.
                });
            };

            // Track which objectives are currently known to be met.
            var storedMet = [];
            var getStoredMet = function() { return storedMet.slice(); };
            var storeMet    = function(results) {
                storedMet = [];
                if (results) {
                    results.forEach(function(r) { if (r.met) { storedMet.push(r.index); } });
                }
            };

            // Show an in-chat toast when an objective is newly earned.
            var showObjectiveToast = function(objectiveText, rawgrade, grademax) {
                var toast = $('<div class="moochat-objective-toast">' +
                    '<strong>&#127919; Objective unlocked!</strong><br>' +
                    '<span>' + escapeHtml(objectiveText) + '</span>' +
                    (grademax > 0 ? '<br><small>Score: ' + rawgrade + ' / ' + grademax + '</small>' : '') +
                    '</div>');
                messagesDiv.append(toast);
                scrollToBottom();
                setTimeout(function() { toast.addClass('moochat-toast-fadein'); }, 50);
                setTimeout(function() { toast.addClass('moochat-toast-fadeout'); }, 4000);
                setTimeout(function() { toast.remove(); }, 5000);
            };

            // ------------------------------------------------------------------
            // Send message.
            // ------------------------------------------------------------------
            var sendMessage = function() {
                var message = inputField.val().trim();
                if (message === '') {
                    return;
                }

                inputField.prop('disabled', true);
                sendButton.prop('disabled', true);

                addMessage('user', message);
                conversationHistory.push({role: 'user', content: message});
                inputField.val('');

                var thinkingId = 'thinking-' + Date.now();
                messagesDiv.append(
                    '<div class="moochat-message moochat-assistant" id="' + thinkingId + '">' +
                    '<em>' + (strings[1] || 'Thinking...') + '</em></div>'
                );
                scrollToBottom();

                Ajax.call([{
                    methodname: 'mod_moochat_send_message',
                    args: {
                        moochatid: moochatid,
                        message:   message,
                        history:   JSON.stringify(conversationHistory)
                    }
                }])[0].then(function(response) {
                    $('#' + thinkingId).remove();

                    if (response.error || !response.success) {
                        if (response.remaining !== undefined && response.remaining === 0) {
                            Notification.alert(strings[2] || 'Rate Limit', response.error, 'OK');
                            inputField.prop('disabled', true);
                            sendButton.prop('disabled', true);
                            updateRemaining(0);
                        } else {
                            Notification.alert(strings[3] || 'Error', response.error, 'OK');
                        }
                    } else if (response.success && response.reply) {
                        addMessage('assistant', response.reply);
                        conversationHistory.push({role: 'assistant', content: response.reply});

                        if (!hasChattedOnce) {
                            hasChattedOnce = true;
                            messagesDiv.find('p.moochat-welcome').text(strings[7] || '');
                        }

                        // Save conversation to DB (with sessionId).
                        Ajax.call([{
                            methodname: 'mod_moochat_save_conversation',
                            args: {
                                moochatid:        moochatid,
                                usermessage:      message,
                                assistantmessage: response.reply,
                                sessionid:        sessionId
                            },
                            done: function() {},
                            fail: function() {}
                        }]);

                        // Update remaining questions.
                        if (response.remaining !== undefined) {
                            remainingQuestions = response.remaining;
                            updateRemaining(remainingQuestions);
                            if (remainingQuestions === 0) {
                                inputField.prop('disabled', true);
                                sendButton.prop('disabled', true);
                            }
                        }

                        // Check objectives (async, updates sidebar).
                        checkObjectives();
                    }

                    if (remainingQuestions !== 0) {
                        inputField.prop('disabled', false);
                        sendButton.prop('disabled', false);
                        inputField.focus();
                    }

                }).catch(function() {
                    $('#' + thinkingId).remove();
                    Notification.alert(strings[3] || 'Error', strings[4] || 'Connection error', 'OK');
                    inputField.prop('disabled', false);
                    sendButton.prop('disabled', false);
                });
            };

            // ------------------------------------------------------------------
            // Add a message bubble to the chat window.
            // ------------------------------------------------------------------
            var addMessage = function(role, content) {
                var messageClass   = role === 'user' ? 'moochat-user' : 'moochat-assistant';
                var formattedContent = formatMessage(content);
                messagesDiv.append(
                    '<div class="moochat-message ' + messageClass + '">' + formattedContent + '</div>'
                );
                scrollToBottom();
            };

            var formatMessage = function(text) {
                var escaped      = escapeHtml(text);
                var sentenceCount = (text.match(/[.!?]+/g) || []).length;

                if (text.length < 100 && sentenceCount <= 3) {
                    return escaped;
                }

                escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/__([^_]+)__/g,    '<strong>$1</strong>');
                escaped = escaped.replace(/\*([^*\n]+)\*/g,  '<em>$1</em>');
                escaped = escaped.replace(/\n\n+/g,          '</p><p>');
                escaped = escaped.replace(/\n/g,             '<br>');
                escaped = '<p>' + escaped + '</p>';
                escaped = escaped.replace(/(\d+)\.\s/g,      '<br><strong>$1.</strong> ');
                escaped = escaped.replace(/<br>[-*]\s+/g,    '<br>• ');
                escaped = escaped.replace(/<p>[-*]\s+/g,     '<p>• ');

                return escaped;
            };

            var scrollToBottom = function() {
                messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
            };

            var escapeHtml = function(text) {
                var div      = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };

            // ------------------------------------------------------------------
            // Clear chat — starts a NEW session (new UUID, fresh grade tracking).
            // ------------------------------------------------------------------
            var clearChat = function() {
                conversationHistory = [];
                hasChattedOnce      = false;
                storedMet           = [];
                sessionId           = generateSessionId(); // New session!
                messagesDiv.html('<p class="moochat-welcome">' + (strings[5] || '') + '</p>');
                inputField.val('').focus();

                // Reset objectives panel to empty (fresh session).
                if (objectivesDiv.length) {
                    updateObjectivesPanel({
                        results: [], rawgrade: 0, grademax: 0,
                        metcount: 0, totalcount: 0, bestscore: 0
                    }, []);
                }
            };

            // ------------------------------------------------------------------
            // Event handlers.
            // ------------------------------------------------------------------
            sendButton.on('click', sendMessage);

            inputField.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            clearButton.on('click', function() {
                if (confirm(strings[6] || 'Clear all messages?')) {
                    clearChat();
                }
            });

            // On page load, show initial panel (empty — no prior session to load).
            if (objectivesDiv.length) {
                // Load best-ever score for display even before chatting.
                Ajax.call([{
                    methodname: 'mod_moochat_check_objectives',
                    args: {
                        moochatid: moochatid,
                        history:   JSON.stringify([]),
                        sessionid: sessionId
                    }
                }])[0].then(function(response) {
                    if (response.success) {
                        // Show empty panel with best score hint.
                        updateObjectivesPanel(response, []);
                    }
                }).catch(function() {});
            }

            inputField.focus();
        }
    };
});
