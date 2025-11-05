/**
 * Room Management for WP Gamify Bridge
 *
 * Handles real-time room updates and player presence.
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
         * WebSocket connection (if available).
         */
        socket: null,

        /**
         * Initialize room manager.
         */
        init: function() {
            const $roomElement = $('.wp-gamify-room');

            if (!$roomElement.length) {
                return;
            }

            this.roomId = $roomElement.data('room-id');

            if (this.config.debug) {
                console.log('[WP Gamify Room] Initialized for room:', this.roomId);
            }

            // Initialize UI
            this.initUI();

            // Join room
            this.joinRoom();

            // Setup event listeners
            this.setupEventListeners();

            // Try to connect WebSocket if configured
            this.connectWebSocket();
        },

        /**
         * Initialize UI elements.
         */
        initUI: function() {
            // UI initialization if needed
            // Styles are now loaded via wp_enqueue_style in class-script-enqueuer.php

            if (this.config.debug) {
                console.log('[WP Gamify Room] UI initialized');
            }
        },

        /**
         * Join the room.
         */
        joinRoom: function() {
            if (this.config.debug) {
                console.log('[WP Gamify Room] Joining room:', this.roomId);
            }

            // Add current user to players list
            this.addPlayer(this.config.userName, this.config.userId);

            // Broadcast join event (would be handled by WebSocket in production)
            this.broadcastMessage(this.config.userName + ' joined the room');
        },

        /**
         * Add player to room.
         *
         * @param {string} username - Player username
         * @param {int} userId - Player user ID
         */
        addPlayer: function(username, userId) {
            const player = {
                username: username,
                userId: userId,
                joinedAt: Date.now()
            };

            this.players.push(player);
            this.updatePlayerCount();

            if (this.config.debug) {
                console.log('[WP Gamify Room] Player added:', player);
            }
        },

        /**
         * Remove player from room.
         *
         * @param {int} userId - Player user ID
         */
        removePlayer: function(userId) {
            this.players = this.players.filter(p => p.userId !== userId);
            this.updatePlayerCount();
        },

        /**
         * Update player count display.
         */
        updatePlayerCount: function() {
            $('.room-status .player-count').text(this.players.length + '/10');
        },

        /**
         * Broadcast message to room.
         *
         * @param {string} message - Message to broadcast
         */
        broadcastMessage: function(message) {
            const $chat = $('.room-chat');
            const $message = $('<div>')
                .addClass('room-chat-message')
                .text(message);

            $chat.append($message);
            $chat.scrollTop($chat[0].scrollHeight);

            if (this.config.debug) {
                console.log('[WP Gamify Room] Broadcast:', message);
            }
        },

        /**
         * Setup event listeners.
         */
        setupEventListeners: function() {
            const self = this;

            // Listen for gamification events
            $(document).on('wp_gamify_event', function(e, data) {
                self.handleGamifyEvent(data);
            });

            // Handle page unload (leave room)
            $(window).on('beforeunload', function() {
                self.leaveRoom();
            });
        },

        /**
         * Handle gamification event.
         *
         * @param {object} data - Event data
         */
        handleGamifyEvent: function(data) {
            let message = '';

            switch(data.event) {
                case 'level_complete':
                    message = `${data.player} completed level ${data.data.level}!`;
                    break;
                case 'game_over':
                    message = `${data.player} game over - Score: ${data.score}`;
                    break;
                case 'score_milestone':
                    message = `${data.player} reached ${data.score} points!`;
                    break;
                default:
                    message = `${data.player} triggered ${data.event}`;
            }

            this.broadcastMessage(message);
        },

        /**
         * Connect to WebSocket server (if configured).
         */
        connectWebSocket: function() {
            // Placeholder for WebSocket connection
            // This would connect to Supabase, Pusher, or custom WebSocket server

            if (this.config.debug) {
                console.log('[WP Gamify Room] WebSocket connection would be initialized here');
            }

            // Example structure:
            // this.socket = new WebSocket('wss://your-server.com');
            // this.socket.onmessage = (event) => {
            //     this.handleSocketMessage(JSON.parse(event.data));
            // };
        },

        /**
         * Leave the room.
         */
        leaveRoom: function() {
            if (this.config.debug) {
                console.log('[WP Gamify Room] Leaving room');
            }

            // Disconnect WebSocket if connected
            if (this.socket) {
                this.socket.close();
            }

            // Remove player from list
            this.removePlayer(this.config.userId);
        }
    };

    /**
     * Initialize when document is ready.
     */
    $(document).ready(function() {
        WPGamifyRoom.init();
    });

})(jQuery);
