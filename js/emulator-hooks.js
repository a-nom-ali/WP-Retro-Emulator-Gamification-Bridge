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
         * Event queue for failed requests.
         */
        eventQueue: [],

        /**
         * Is request in progress?
         */
        isProcessing: false,

        /**
         * Retry configuration.
         */
        retryConfig: {
            maxRetries: 3,
            retryDelay: 1000, // milliseconds
            backoffMultiplier: 2
        },

        /**
         * Network status.
         */
        isOnline: navigator.onLine,

        /**
         * Statistics.
         */
        stats: {
            eventsSent: 0,
            eventsSuccess: 0,
            eventsFailed: 0,
            eventsRetried: 0
        },

        /**
         * Detected emulator type.
         */
        emulatorType: null,

        /**
         * Initialize the bridge.
         */
        init: function() {
            this.log('info', 'Initializing WP Gamify Bridge', this.config);

            // Setup network monitoring
            this.setupNetworkMonitoring();

            // Auto-detect emulator if available
            this.detectEmulator();

            // Process any queued events
            this.processQueue();

            // Setup periodic queue processing
            setInterval(this.processQueue.bind(this), 5000);

            this.log('success', 'WP Gamify Bridge initialized');
        },

        /**
         * Setup network monitoring.
         */
        setupNetworkMonitoring: function() {
            const self = this;

            window.addEventListener('online', function() {
                self.isOnline = true;
                self.log('info', 'Network connection restored');
                self.processQueue();
            });

            window.addEventListener('offline', function() {
                self.isOnline = false;
                self.log('warning', 'Network connection lost - events will be queued');
            });
        },

        /**
         * Detect and hook into common emulators.
         */
        detectEmulator: function() {
            const detectedEmulators = [];

            // Check for JSNES (NES emulator)
            if (typeof window.JSNES !== 'undefined') {
                detectedEmulators.push('JSNES');
                this.hookJSNES();
            }

            // Check for GBA.js
            if (typeof window.GBA !== 'undefined') {
                detectedEmulators.push('GBA');
                this.hookGBA();
            }

            // Check for RetroArch
            if (typeof window.Module !== 'undefined' && window.Module.canvas) {
                detectedEmulators.push('RetroArch');
                this.hookRetroArch();
            }

            // Check for EmulatorJS
            if (typeof window.EJS_player !== 'undefined') {
                detectedEmulators.push('EmulatorJS');
                this.hookEmulatorJS();
            }

            if (detectedEmulators.length > 0) {
                this.emulatorType = detectedEmulators[0];
                this.log('success', 'Emulators detected: ' + detectedEmulators.join(', '));
            } else {
                this.log('warning', 'No emulator detected - manual event triggering required');
            }
        },

        /**
         * Hook into JSNES emulator.
         */
        hookJSNES: function() {
            const self = this;

            this.log('info', 'Hooking into JSNES emulator');

            // Example: Hook into frame rendering to detect game state changes
            // This is a placeholder - actual implementation depends on emulator API
            if (window.JSNES && window.JSNES.prototype) {
                const originalFrame = window.JSNES.prototype.frame;

                window.JSNES.prototype.frame = function() {
                    const result = originalFrame.apply(this, arguments);

                    // Custom logic to detect game events
                    self.detectGameStateChanges(this);

                    return result;
                };
            }
        },

        /**
         * Hook into GBA emulator.
         */
        hookGBA: function() {
            this.log('info', 'Hooking into GBA emulator');
            // Placeholder for GBA-specific hooks
        },

        /**
         * Hook into RetroArch.
         */
        hookRetroArch: function() {
            this.log('info', 'Hooking into RetroArch');
            // Placeholder for RetroArch-specific hooks
        },

        /**
         * Hook into EmulatorJS.
         */
        hookEmulatorJS: function() {
            this.log('info', 'Hooking into EmulatorJS');
            // Placeholder for EmulatorJS-specific hooks
        },

        /**
         * Detect game state changes (placeholder).
         *
         * @param {object} emulator - Emulator instance
         */
        detectGameStateChanges: function(emulator) {
            // This would analyze emulator state to detect events
            // Implementation depends on specific emulator API
        },

        /**
         * Trigger a WordPress gamification event.
         *
         * @param {string} eventType - Type of event
         * @param {object} eventData - Additional event data
         * @param {object} options - Optional settings
         * @returns {Promise}
         */
        triggerEvent: function(eventType, eventData, options) {
            eventData = eventData || {};
            options = options || {};

            const payload = {
                event: eventType,
                player: this.config.userName,
                room_id: this.config.roomId,
                score: eventData.score || 0,
                data: eventData,
                _timestamp: Date.now(),
                _emulatorType: this.emulatorType
            };

            this.stats.eventsSent++;

            this.log('info', 'Triggering event: ' + eventType, payload);

            // If offline, queue immediately
            if (!this.isOnline && !options.skipQueue) {
                this.log('warning', 'Offline - queueing event: ' + eventType);
                return this.queueEvent(payload);
            }

            return this.sendEvent(payload, 0, options);
        },

        /**
         * Send event to WordPress REST API with retry logic.
         *
         * @param {object} payload - Event payload
         * @param {number} retryCount - Current retry attempt
         * @param {object} options - Optional settings
         * @returns {Promise}
         */
        sendEvent: function(payload, retryCount, options) {
            const self = this;
            retryCount = retryCount || 0;
            options = options || {};

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: self.config.apiUrl,
                    type: 'POST',
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    timeout: options.timeout || 10000,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
                    },
                    success: function(response) {
                        self.stats.eventsSuccess++;

                        self.log('success', 'Event sent successfully: ' + payload.event, response);

                        // Show notification if reward given
                        if (response.reward && !options.silent) {
                            self.showNotification(response.reward, 'success');
                        }

                        // Update rate limit info
                        if (response.rate_limit) {
                            self.updateRateLimitStatus(response.rate_limit);
                        }

                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        self.log('error', 'Event send failed: ' + payload.event, {
                            status: xhr.status,
                            error: error,
                            retry: retryCount
                        });

                        // Handle rate limiting
                        if (xhr.status === 429) {
                            self.handleRateLimitExceeded(xhr);
                            reject(new Error('Rate limit exceeded'));
                            return;
                        }

                        // Retry logic
                        if (retryCount < self.retryConfig.maxRetries && self.shouldRetry(xhr.status)) {
                            self.stats.eventsRetried++;

                            const delay = self.retryConfig.retryDelay * Math.pow(self.retryConfig.backoffMultiplier, retryCount);

                            self.log('warning', 'Retrying event in ' + delay + 'ms (attempt ' + (retryCount + 1) + ')');

                            setTimeout(function() {
                                self.sendEvent(payload, retryCount + 1, options)
                                    .then(resolve)
                                    .catch(reject);
                            }, delay);
                        } else {
                            // Max retries exceeded - queue for later
                            self.stats.eventsFailed++;

                            if (!options.skipQueue) {
                                self.queueEvent(payload);
                                self.log('warning', 'Event queued for retry: ' + payload.event);
                            }

                            reject(new Error('Event send failed after ' + retryCount + ' retries'));
                        }
                    }
                });
            });
        },

        /**
         * Determine if error should be retried.
         *
         * @param {number} statusCode - HTTP status code
         * @returns {boolean}
         */
        shouldRetry: function(statusCode) {
            // Retry on network errors and server errors, but not client errors
            return statusCode === 0 || statusCode >= 500;
        },

        /**
         * Queue event for later processing.
         *
         * @param {object} payload - Event payload
         * @returns {Promise}
         */
        queueEvent: function(payload) {
            this.eventQueue.push({
                payload: payload,
                timestamp: Date.now(),
                attempts: 0
            });

            // Persist queue to localStorage
            this.saveQueue();

            return Promise.resolve({
                success: true,
                queued: true,
                message: 'Event queued for later delivery'
            });
        },

        /**
         * Process queued events.
         */
        processQueue: function() {
            if (!this.isOnline || this.isProcessing || this.eventQueue.length === 0) {
                return;
            }

            this.isProcessing = true;
            const self = this;
            const event = this.eventQueue.shift();

            if (!event) {
                this.isProcessing = false;
                return;
            }

            this.log('info', 'Processing queued event', event);

            this.sendEvent(event.payload, 0, { skipQueue: true })
                .then(function() {
                    self.log('success', 'Queued event sent successfully');
                })
                .catch(function(error) {
                    self.log('error', 'Queued event failed', error);

                    // Re-queue if not too old (max 1 hour)
                    if (Date.now() - event.timestamp < 3600000) {
                        event.attempts++;
                        if (event.attempts < 5) {
                            self.eventQueue.push(event);
                        }
                    }
                })
                .finally(function() {
                    self.isProcessing = false;
                    self.saveQueue();

                    // Continue processing queue
                    if (self.eventQueue.length > 0) {
                        setTimeout(function() {
                            self.processQueue();
                        }, 1000);
                    }
                });
        },

        /**
         * Save queue to localStorage.
         */
        saveQueue: function() {
            try {
                localStorage.setItem('wpGamifyBridge_queue', JSON.stringify(this.eventQueue));
            } catch (e) {
                this.log('error', 'Failed to save queue to localStorage', e);
            }
        },

        /**
         * Load queue from localStorage.
         */
        loadQueue: function() {
            try {
                const saved = localStorage.getItem('wpGamifyBridge_queue');
                if (saved) {
                    this.eventQueue = JSON.parse(saved);
                    this.log('info', 'Loaded ' + this.eventQueue.length + ' queued events');
                }
            } catch (e) {
                this.log('error', 'Failed to load queue from localStorage', e);
            }
        },

        /**
         * Handle rate limit exceeded.
         *
         * @param {object} xhr - XMLHttpRequest object
         */
        handleRateLimitExceeded: function(xhr) {
            const retryAfter = xhr.getResponseHeader('Retry-After') || 60;

            this.showNotification(
                'Rate limit exceeded. Please wait ' + retryAfter + ' seconds.',
                'error'
            );

            this.log('warning', 'Rate limit exceeded, retry after: ' + retryAfter + 's');
        },

        /**
         * Update rate limit status.
         *
         * @param {object} rateLimitInfo - Rate limit information
         */
        updateRateLimitStatus: function(rateLimitInfo) {
            if (rateLimitInfo.remaining_minute < 10) {
                this.log('warning', 'Rate limit warning: ' + rateLimitInfo.remaining_minute + ' requests remaining this minute');
            }
        },

        /**
         * Show notification to user.
         *
         * @param {string} message - Notification message
         * @param {string} type - Notification type (success, error, warning, info)
         */
        showNotification: function(message, type) {
            type = type || 'success';

            const $notification = $('<div>')
                .addClass('wp-gamify-notification')
                .addClass(type)
                .html('<strong>' + this.getNotificationIcon(type) + '</strong> ' + message)
                .appendTo('body');

            // Auto-remove after duration based on type
            const duration = type === 'error' ? 5000 : 3000;

            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, duration);
        },

        /**
         * Get icon for notification type.
         *
         * @param {string} type - Notification type
         * @returns {string} Icon
         */
        getNotificationIcon: function(type) {
            const icons = {
                success: '✓',
                error: '✗',
                warning: '⚠',
                info: 'ℹ'
            };
            return icons[type] || icons.info;
        },

        /**
         * Log message with appropriate styling.
         *
         * @param {string} level - Log level (info, success, warning, error)
         * @param {string} message - Log message
         * @param {*} data - Optional data to log
         */
        log: function(level, message, data) {
            if (!this.config.debug) {
                return;
            }

            const styles = {
                info: 'color: #2196F3',
                success: 'color: #4CAF50',
                warning: 'color: #FF9800',
                error: 'color: #F44336'
            };

            const prefix = '[WP Gamify Bridge]';
            const style = styles[level] || styles.info;

            if (data) {
                console.log('%c' + prefix + ' ' + message, style, data);
            } else {
                console.log('%c' + prefix + ' ' + message, style);
            }
        },

        /**
         * Get current statistics.
         *
         * @returns {object} Statistics
         */
        getStats: function() {
            return Object.assign({}, this.stats, {
                queueLength: this.eventQueue.length,
                emulatorType: this.emulatorType,
                isOnline: this.isOnline
            });
        },

        /**
         * Reset statistics.
         */
        resetStats: function() {
            this.stats = {
                eventsSent: 0,
                eventsSuccess: 0,
                eventsFailed: 0,
                eventsRetried: 0
            };
            this.log('info', 'Statistics reset');
        },

        /**
         * Convenience methods for common events.
         */

        onGameLoad: function(gameName, gameData) {
            return this.triggerEvent('game_start', {
                game: gameName,
                gameData: gameData,
                timestamp: Date.now()
            });
        },

        onLevelComplete: function(level, score, time) {
            return this.triggerEvent('level_complete', {
                level: level,
                score: score,
                time: time,
                timestamp: Date.now()
            });
        },

        onGameOver: function(score, level, time) {
            return this.triggerEvent('game_over', {
                score: score,
                level: level,
                time: time,
                timestamp: Date.now()
            });
        },

        onScoreMilestone: function(score, milestone) {
            return this.triggerEvent('score_milestone', {
                score: score,
                milestone: milestone,
                timestamp: Date.now()
            });
        },

        onDeath: function(lives, level, cause) {
            return this.triggerEvent('death', {
                lives: lives,
                level: level,
                cause: cause,
                timestamp: Date.now()
            });
        },

        onGameStart: function(game, difficulty) {
            return this.triggerEvent('game_start', {
                game: game,
                difficulty: difficulty,
                timestamp: Date.now()
            });
        },

        onAchievementUnlock: function(achievement, description) {
            return this.triggerEvent('achievement_unlock', {
                achievement: achievement,
                description: description,
                timestamp: Date.now()
            });
        }
    };

    /**
     * Initialize when document is ready.
     */
    $(document).ready(function() {
        WPGamifyBridge.loadQueue();
        WPGamifyBridge.init();
    });

    /**
     * Export triggerWPEvent as global shorthand.
     */
    window.triggerWPEvent = function(eventType, eventData, options) {
        return WPGamifyBridge.triggerEvent(eventType, eventData, options);
    };

    /**
     * Export stats function for debugging.
     */
    window.wpGamifyStats = function() {
        const stats = WPGamifyBridge.getStats();
        console.table(stats);
        return stats;
    };

})(jQuery);
