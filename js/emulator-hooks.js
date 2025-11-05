/**
 * Emulator Event Hooks for WP Gamify Bridge
 *
 * This script provides hooks to connect JavaScript-based retro emulators
 * to WordPress gamification systems via REST API.
 *
 * @package WP_Gamify_Bridge
 */

(function($) {
    'use strict';

    /**
     * Main Gamify Bridge object.
     */
    window.WPGamifyBridge = {

        /**
         * Configuration from WordPress.
         */
        config: window.wpGamifyBridge || {},

        /**
         * Event queue for rate limiting.
         */
        eventQueue: [],

        /**
         * Is request in progress?
         */
        isProcessing: false,

        /**
         * Initialize the bridge.
         */
        init: function() {
            if (this.config.debug) {
                console.log('[WP Gamify Bridge] Initialized', this.config);
            }

            // Auto-detect emulator if available
            this.detectEmulator();
        },

        /**
         * Detect and hook into common emulators.
         */
        detectEmulator: function() {
            // Check for jNES
            if (typeof window.JSNES !== 'undefined') {
                this.hookJSNES();
            }

            // Check for other emulators
            if (typeof window.GBA !== 'undefined') {
                this.hookGBA();
            }

            // Log if no emulator detected
            if (this.config.debug) {
                console.log('[WP Gamify Bridge] Emulator detection complete');
            }
        },

        /**
         * Hook into JSNES emulator.
         */
        hookJSNES: function() {
            const self = this;

            if (this.config.debug) {
                console.log('[WP Gamify Bridge] Hooking into JSNES');
            }

            // Override or extend emulator methods if available
            // This is a placeholder - actual implementation depends on emulator API
        },

        /**
         * Hook into GBA emulator.
         */
        hookGBA: function() {
            if (this.config.debug) {
                console.log('[WP Gamify Bridge] Hooking into GBA');
            }
            // Placeholder for GBA-specific hooks
        },

        /**
         * Trigger a WordPress gamification event.
         *
         * @param {string} eventType - Type of event (level_complete, game_over, etc.)
         * @param {object} eventData - Additional event data
         * @returns {Promise}
         */
        triggerEvent: function(eventType, eventData) {
            eventData = eventData || {};

            const payload = {
                event: eventType,
                player: this.config.userName,
                room_id: this.config.roomId,
                score: eventData.score || 0,
                data: eventData
            };

            if (this.config.debug) {
                console.log('[WP Gamify Bridge] Triggering event:', payload);
            }

            return this.sendEvent(payload);
        },

        /**
         * Send event to WordPress REST API.
         *
         * @param {object} payload - Event payload
         * @returns {Promise}
         */
        sendEvent: function(payload) {
            const self = this;

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: self.config.apiUrl,
                    type: 'POST',
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                    },
                    success: function(response) {
                        if (self.config.debug) {
                            console.log('[WP Gamify Bridge] Event response:', response);
                        }

                        // Show notification if reward given
                        if (response.reward) {
                            self.showNotification(response.reward);
                        }

                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('[WP Gamify Bridge] Event error:', error);
                        reject(error);
                    }
                });
            });
        },

        /**
         * Show notification to user.
         *
         * @param {string} message - Notification message
         */
        showNotification: function(message) {
            // Create simple notification element
            const $notification = $('<div>')
                .addClass('wp-gamify-notification')
                .text(message)
                .appendTo('body');

            // Auto-remove after 3 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Convenience methods for common events.
         */

        onLevelComplete: function(level, score) {
            return this.triggerEvent('level_complete', {
                level: level,
                score: score
            });
        },

        onGameOver: function(score) {
            return this.triggerEvent('game_over', {
                score: score
            });
        },

        onScoreMilestone: function(score) {
            return this.triggerEvent('score_milestone', {
                score: score
            });
        },

        onDeath: function(lives) {
            return this.triggerEvent('death', {
                lives: lives
            });
        },

        onGameStart: function(game) {
            return this.triggerEvent('game_start', {
                game: game
            });
        },

        onAchievementUnlock: function(achievement) {
            return this.triggerEvent('achievement_unlock', {
                achievement: achievement
            });
        }
    };

    /**
     * Initialize when document is ready.
     */
    $(document).ready(function() {
        WPGamifyBridge.init();
    });

    /**
     * Export triggerWPEvent as global shorthand.
     */
    window.triggerWPEvent = function(eventType, eventData) {
        return WPGamifyBridge.triggerEvent(eventType, eventData);
    };

})(jQuery);
