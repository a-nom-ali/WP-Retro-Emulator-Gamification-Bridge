/**
 * Room Management for WP Gamify Bridge
 *
 * Handles real-time room updates, player presence, and notifications via polling.
 * Designed with graceful degradation and WebSocket upgrade path.
 *
 * @package WP_Gamify_Bridge
 */

(function($) {
    'use strict';

    /**
     * Room Manager object.
     */
    window.WPGamifyRoom = {

        /**
         * Configuration from WordPress.
         */
        config: window.wpGamifyRoom || {},

        /**
         * Current room ID.
         */
        roomId: null,

        /**
         * Players in room.
         */
        players: [],

        /**
         * Last seen event ID (for incremental updates).
         */
        lastEventId: 0,

        /**
         * Polling interval ID.
         */
        pollingInterval: null,

        /**
         * Presence interval ID.
         */
        presenceInterval: null,

        /**
         * Polling frequency in milliseconds.
         */
        pollingFrequency: 3000, // 3 seconds

        /**
         * Presence update frequency in milliseconds.
         */
        presenceFrequency: 30000, // 30 seconds

        /**
         * WebSocket connection (for future upgrade).
         */
        socket: null,

        /**
         * Room data cache.
         */
        roomData: null,

        /**
         * Is polling active?
         */
        isPolling: false,

        /**
         * Notification queue.
         */
        notifications: [],

        /**
         * Initialize room manager.
         */
        init: function() {
            const $roomElement = $('.wp-gamify-room');

            if (!$roomElement.length) {
                return;
            }

            this.roomId = $roomElement.data('room-id');

            if (!this.roomId) {
                console.error('[WP Gamify Room] No room ID found');
                return;
            }

            // Use config values if available.
            if (this.config.roomId) {
                this.roomId = this.config.roomId;
            }

            if (this.config.presenceInterval) {
                this.presenceFrequency = this.config.presenceInterval;
            }

            this.log('info', 'Initializing room: ' + this.roomId);

            // Initialize UI
            this.initUI();

            // Load initial room data
            this.loadRoomData();

            // Setup event listeners
            this.setupEventListeners();

            // Start polling for updates
            this.startPolling();

            // Start presence updates
            this.startPresenceUpdates();

            // Try WebSocket connection (placeholder for future)
            this.connectWebSocket();
        },

        /**
         * Initialize UI elements.
         */
        initUI: function() {
            const self = this;

            // Add notification container if not exists
            if (!$('.room-notifications .notification-list').length) {
                $('.wp-gamify-room').append(
                    '<div class="room-notifications">' +
                    '<h3>Room Activity</h3>' +
                    '<div class="notification-list"></div>' +
                    '</div>'
                );
            }

            // Make player status indicators pulsate
            this.updatePresenceIndicators();

            this.log('info', 'UI initialized');
        },

        /**
         * Load room data from API.
         */
        loadRoomData: function() {
            const self = this;

            $.ajax({
                url: this.config.roomUrl + '/' + this.roomId,
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function(response) {
                    if (response.success && response.room) {
                        self.roomData = response.room;
                        self.players = response.room.players || [];
                        self.updatePlayerList();
                        self.updatePlayerCount();
                        self.log('success', 'Room data loaded', response.room);
                    }
                },
                error: function(xhr, status, error) {
                    self.log('error', 'Failed to load room data', error);
                }
            });
        },

        /**
         * Start polling for room updates.
         */
        startPolling: function() {
            if (this.isPolling) {
                return;
            }

            this.isPolling = true;
            const self = this;

            // Initial poll
            this.pollRoomUpdates();

            // Setup interval
            this.pollingInterval = setInterval(function() {
                self.pollRoomUpdates();
            }, this.pollingFrequency);

            this.log('info', 'Polling started (every ' + this.pollingFrequency + 'ms)');
        },

        /**
         * Stop polling.
         */
        stopPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
                this.isPolling = false;
                this.log('info', 'Polling stopped');
            }
        },

        /**
         * Poll for room updates.
         */
        pollRoomUpdates: function() {
            const self = this;

            // Get players
            $.ajax({
                url: this.config.roomUrl + '/' + this.roomId + '/players',
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function(response) {
                    if (response.success && response.players) {
                        self.updatePlayers(response.players);
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 401 || xhr.status === 403) {
                        self.stopPolling();
                        self.log('error', 'Authentication failed - polling stopped');
                    }
                }
            });

            // Get room stats for event updates
            $.ajax({
                url: this.config.roomUrl + '/' + this.roomId + '/stats',
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function(response) {
                    if (response.success && response.stats) {
                        self.processRoomStats(response.stats);
                    }
                }
            });
        },

        /**
         * Process room statistics.
         *
         * @param {object} stats - Room stats
         */
        processRoomStats: function(stats) {
            // Update event count if changed
            if (this.roomData && stats.event_count > this.roomData.event_count) {
                const newEvents = stats.event_count - this.roomData.event_count;
                this.showNotification('info', newEvents + ' new event(s) in room');
            }

            // Store updated stats
            if (this.roomData) {
                this.roomData.event_count = stats.event_count;
            }
        },

        /**
         * Update players list.
         *
         * @param {array} newPlayers - New players array
         */
        updatePlayers: function(newPlayers) {
            const oldPlayerIds = this.players.map(p => p.user_id);
            const newPlayerIds = newPlayers.map(p => p.user_id);

            // Check for new players
            newPlayers.forEach(player => {
                if (!oldPlayerIds.includes(player.user_id)) {
                    this.showNotification('join', player.user_name + ' joined the room');
                }
            });

            // Check for left players
            this.players.forEach(player => {
                if (!newPlayerIds.includes(player.user_id)) {
                    this.showNotification('leave', player.user_name + ' left the room');
                }
            });

            // Update players
            this.players = newPlayers;
            this.updatePlayerList();
            this.updatePlayerCount();
            this.updatePresenceIndicators();
        },

        /**
         * Update player list in UI.
         */
        updatePlayerList: function() {
            const $playerList = $('.room-players .player-list');

            if (!$playerList.length) {
                return;
            }

            $playerList.empty();

            this.players.forEach(player => {
                const $playerItem = $('<li>')
                    .addClass('player-item')
                    .attr('data-user-id', player.user_id);

                const $playerName = $('<span>')
                    .addClass('player-name')
                    .text(player.user_name);

                const $playerStatus = $('<span>')
                    .addClass('player-status online')
                    .attr('title', 'Online');

                $playerItem.append($playerName, $playerStatus);
                $playerList.append($playerItem);
            });

            this.log('info', 'Player list updated (' + this.players.length + ' players)');
        },

        /**
         * Update player count display.
         */
        updatePlayerCount: function() {
            const $playerCount = $('.room-status .player-count');
            const maxPlayers = $playerCount.data('max') || 10;
            const currentCount = this.players.length;

            $playerCount.text(currentCount + '/' + maxPlayers);
            $playerCount.attr('data-current', currentCount);

            // Update color based on capacity
            $playerCount.removeClass('low medium high');
            if (currentCount / maxPlayers < 0.5) {
                $playerCount.addClass('low');
            } else if (currentCount / maxPlayers < 0.8) {
                $playerCount.addClass('medium');
            } else {
                $playerCount.addClass('high');
            }
        },

        /**
         * Update presence indicators.
         */
        updatePresenceIndicators: function() {
            $('.player-status.online').each(function() {
                $(this).addClass('pulse');
            });

            // Remove pulse after animation
            setTimeout(function() {
                $('.player-status').removeClass('pulse');
            }, 2000);
        },

        /**
         * Start presence updates.
         */
        startPresenceUpdates: function() {
            const self = this;

            // Initial presence update
            this.updatePresence();

            // Setup interval
            this.presenceInterval = setInterval(function() {
                self.updatePresence();
            }, this.presenceFrequency);

            this.log('info', 'Presence updates started (every ' + this.presenceFrequency + 'ms)');
        },

        /**
         * Stop presence updates.
         */
        stopPresenceUpdates: function() {
            if (this.presenceInterval) {
                clearInterval(this.presenceInterval);
                this.presenceInterval = null;
                this.log('info', 'Presence updates stopped');
            }
        },

        /**
         * Update user presence.
         */
        updatePresence: function() {
            const self = this;

            $.ajax({
                url: this.config.roomUrl + '/' + this.roomId + '/presence',
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        self.log('info', 'Presence updated');
                    }
                },
                error: function(xhr, status, error) {
                    self.log('error', 'Presence update failed', error);
                }
            });
        },

        /**
         * Show notification.
         *
         * @param {string} type - Notification type (info, join, leave, event, success, error)
         * @param {string} message - Notification message
         */
        showNotification: function(type, message) {
            const $notificationList = $('.room-notifications .notification-list');

            if (!$notificationList.length) {
                return;
            }

            const timestamp = new Date().toLocaleTimeString();

            const $notification = $('<div>')
                .addClass('notification')
                .addClass('notification-' + type)
                .html(
                    '<span class="notification-icon">' + this.getNotificationIcon(type) + '</span>' +
                    '<span class="notification-message">' + message + '</span>' +
                    '<span class="notification-time">' + timestamp + '</span>'
                );

            // Add with animation
            $notification.hide().prependTo($notificationList).slideDown(300);

            // Limit notifications (keep last 20)
            const $notifications = $notificationList.find('.notification');
            if ($notifications.length > 20) {
                $notifications.slice(20).remove();
            }

            // Trigger custom event
            $(document).trigger('wp_gamify_room_notification', {
                type: type,
                message: message,
                timestamp: timestamp
            });

            this.log('info', 'Notification: ' + message);
        },

        /**
         * Get notification icon.
         *
         * @param {string} type - Notification type
         * @returns {string} Icon HTML
         */
        getNotificationIcon: function(type) {
            const icons = {
                info: 'ℹ',
                join: '➕',
                leave: '➖',
                event: '🎮',
                success: '✓',
                error: '✗'
            };
            return icons[type] || icons.info;
        },

        /**
         * Setup event listeners.
         */
        setupEventListeners: function() {
            const self = this;

            // Listen for gamification events from emulator
            $(document).on('wp_gamify_event', function(e, data) {
                self.handleGamifyEvent(data);
            });

            // Listen for broadcast events (from server)
            $(document).on('wp_gamify_broadcast', function(e, data) {
                self.handleBroadcastEvent(data);
            });

            // Handle page visibility changes
            $(document).on('visibilitychange', function() {
                if (document.hidden) {
                    self.log('info', 'Page hidden - reducing polling');
                    // Could reduce polling frequency here
                } else {
                    self.log('info', 'Page visible - resuming normal polling');
                    self.pollRoomUpdates(); // Immediate poll on return
                }
            });

            // Handle page unload (leave room)
            $(window).on('beforeunload', function() {
                self.leaveRoom();
            });

            // Handle network status
            $(window).on('online', function() {
                self.log('success', 'Network connection restored');
                self.showNotification('success', 'Connection restored');
                self.startPolling();
            });

            $(window).on('offline', function() {
                self.log('error', 'Network connection lost');
                self.showNotification('error', 'Connection lost - updates paused');
                self.stopPolling();
            });
        },

        /**
         * Handle gamification event from emulator.
         *
         * @param {object} data - Event data
         */
        handleGamifyEvent: function(data) {
            let message = '';
            let playerName = data.player || this.config.userName;

            switch(data.event) {
                case 'level_complete':
                    message = playerName + ' completed level ' + (data.data.level || '?') + '!';
                    break;
                case 'game_over':
                    message = playerName + ' game over - Score: ' + (data.score || 0);
                    break;
                case 'score_milestone':
                    message = playerName + ' reached ' + (data.score || 0) + ' points!';
                    break;
                case 'death':
                    message = playerName + ' died';
                    break;
                case 'game_start':
                    message = playerName + ' started playing';
                    break;
                default:
                    message = playerName + ' triggered ' + data.event;
            }

            this.showNotification('event', message);
        },

        /**
         * Handle broadcast event from server.
         *
         * @param {object} data - Broadcast data
         */
        handleBroadcastEvent: function(data) {
            this.log('info', 'Broadcast received', data);

            if (data.event_type) {
                this.handleGamifyEvent({
                    event: data.event_type,
                    player: data.user_name || 'Player',
                    score: data.score || 0,
                    data: data.data || {}
                });
            }
        },

        /**
         * Connect to WebSocket server (placeholder for future upgrade).
         */
        connectWebSocket: function() {
            // Placeholder for WebSocket implementation
            // This would connect to Supabase Realtime, Pusher, or custom WebSocket server

            this.log('info', 'WebSocket: Not configured (using polling)');

            // Future implementation:
            /*
            if (this.config.websocketUrl) {
                this.socket = new WebSocket(this.config.websocketUrl);

                this.socket.onopen = () => {
                    this.log('success', 'WebSocket connected');
                    this.stopPolling(); // Switch from polling to WebSocket
                    this.socket.send(JSON.stringify({
                        action: 'join',
                        room_id: this.roomId,
                        user_id: this.config.userId
                    }));
                };

                this.socket.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleBroadcastEvent(data);
                };

                this.socket.onerror = (error) => {
                    this.log('error', 'WebSocket error', error);
                    this.startPolling(); // Fallback to polling
                };

                this.socket.onclose = () => {
                    this.log('info', 'WebSocket disconnected');
                    this.startPolling(); // Fallback to polling
                };
            }
            */
        },

        /**
         * Leave the room.
         */
        leaveRoom: function() {
            this.log('info', 'Leaving room');

            // Stop polling and presence updates
            this.stopPolling();
            this.stopPresenceUpdates();

            // Disconnect WebSocket if connected
            if (this.socket) {
                this.socket.close();
            }

            // Send leave request to server
            const self = this;
            $.ajax({
                url: this.config.roomUrl + '/' + this.roomId + '/leave',
                type: 'POST',
                async: false, // Synchronous for beforeunload
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                }
            });
        },

        /**
         * Log message with styling.
         *
         * @param {string} level - Log level (info, success, error)
         * @param {string} message - Log message
         * @param {*} data - Optional data
         */
        log: function(level, message, data) {
            if (!this.config.debug) {
                return;
            }

            const styles = {
                info: 'color: #2196F3',
                success: 'color: #4CAF50',
                error: 'color: #F44336'
            };

            const prefix = '[WP Gamify Room]';
            const style = styles[level] || styles.info;

            if (data) {
                console.log('%c' + prefix + ' ' + message, style, data);
            } else {
                console.log('%c' + prefix + ' ' + message, style);
            }
        },

        /**
         * Get room statistics.
         *
         * @returns {object} Room stats
         */
        getStats: function() {
            return {
                roomId: this.roomId,
                playerCount: this.players.length,
                isPolling: this.isPolling,
                pollingFrequency: this.pollingFrequency,
                hasWebSocket: this.socket !== null,
                notificationCount: $('.notification-list .notification').length
            };
        }
    };

    /**
     * Initialize when document is ready.
     */
    $(document).ready(function() {
        WPGamifyRoom.init();
    });

    /**
     * Export debug function.
     */
    window.wpGamifyRoomStats = function() {
        const stats = WPGamifyRoom.getStats();
        console.table(stats);
        return stats;
    };

})(jQuery);
