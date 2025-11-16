(function(window, document) {
    'use strict';

    function RetroEmulator(element) {
        this.element = element;
        this.canvas = element.querySelector('.wp-gamify-emulator__canvas');
        this.statusEl = element.querySelector('.wp-gamify-emulator__status');
        this.romSelect = element.querySelector('.wp-gamify-emulator__rom-select');
        this.metaFields = {
            system: element.querySelector('[data-meta="system"]'),
            release: element.querySelector('[data-meta="release"]'),
            publisher: element.querySelector('[data-meta="publisher"]'),
            size: element.querySelector('[data-meta="size"]'),
            notes: element.querySelector('[data-meta="notes"]')
        };
        this.touchLayer = element.querySelector('.wp-gamify-emulator__touch');
        this.touchToggle = element.querySelector('.wp-gamify-emulator__touch-toggle');
        this.autoTouch = element.dataset.autoTouch || 'auto';

        this.ctx = null;
        this.imageData = null;
        this.frameU8 = null;
        this.frameU32 = null;
        this.currentRom = null;
        this.lastLoad = null;
        this.animationFrame = null;

        this.audioBuffering = 512;
        this.sampleCount = 4 * 1024;
        this.sampleMask = this.sampleCount - 1;
        this.audioSamplesL = new Float32Array(this.sampleCount);
        this.audioSamplesR = new Float32Array(this.sampleCount);
        this.audioWrite = 0;
        this.audioRead = 0;
        this.audioCtx = null;
        this.scriptNode = null;

        this.nes = null;

        this.init();
    }

    RetroEmulator.prototype.init = function() {
        if (!this.canvas) {
            return;
        }

        this.initCanvas();
        this.initAudio();
        this.initNes();
        this.bindUI();
        this.initTouchControls();

        // Handle Retro Emulator block (with ROM selector)
        if (this.romSelect && this.element.dataset.defaultRom) {
            this.romSelect.value = this.element.dataset.defaultRom;
        }

        // Load ROM if selector has value OR if there's a default ROM (ROM Player block)
        if ((this.romSelect && this.romSelect.value) || (!this.romSelect && this.element.dataset.defaultRom)) {
            this.loadSelectedRomWhenReady();
        }
    };

    RetroEmulator.prototype.initCanvas = function() {
        this.ctx = this.canvas.getContext('2d');
        this.imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        var buffer = new ArrayBuffer(this.imageData.data.length);
        this.frameU8 = new Uint8ClampedArray(buffer);
        this.frameU32 = new Uint32Array(buffer);
    };

    RetroEmulator.prototype.initAudio = function() {
        if (!window.AudioContext && !window.webkitAudioContext) {
            return;
        }
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        this.audioCtx = new AudioCtx();
        this.scriptNode = this.audioCtx.createScriptProcessor(this.audioBuffering, 0, 2);
        this.scriptNode.onaudioprocess = this.onAudioProcess.bind(this);
        this.scriptNode.connect(this.audioCtx.destination);
    };

    RetroEmulator.prototype.initNes = function() {
        var self = this;
        this.nes = new jsnes.NES({
            onFrame: function(framebuffer) {
                for (var i = 0; i < framebuffer.length; i++) {
                    self.frameU32[i] = 0xFF000000 | framebuffer[i];
                }
            },
            onAudioSample: function(l, r) {
                self.audioSamplesL[self.audioWrite] = l;
                self.audioSamplesR[self.audioWrite] = r;
                self.audioWrite = (self.audioWrite + 1) & self.sampleMask;
            }
        });
    };

    RetroEmulator.prototype.bindUI = function() {
        var self = this;
        if (this.romSelect) {
            this.romSelect.addEventListener('change', function() {
                self.loadSelectedRom();
            });
        }

        if (this.touchToggle) {
            this.touchToggle.addEventListener('click', function() {
                self.element.classList.toggle('touch-visible');
            });
        }
    };

    RetroEmulator.prototype.onAudioProcess = function(event) {
        if (!this.nes) {
            return;
        }

        var dst = event.outputBuffer;
        var len = dst.length;

        if (this.audioRemain() < this.audioBuffering) {
            this.nes.frame();
        }

        var dstL = dst.getChannelData(0);
        var dstR = dst.getChannelData(1);

        for (var i = 0; i < len; i++) {
            var idx = (this.audioRead + i) & this.sampleMask;
            dstL[i] = this.audioSamplesL[idx];
            dstR[i] = this.audioSamplesR[idx];
        }

        this.audioRead = (this.audioRead + len) & this.sampleMask;
    };

    RetroEmulator.prototype.audioRemain = function() {
        return (this.audioWrite - this.audioRead) & this.sampleMask;
    };

    RetroEmulator.prototype.loadSelectedRomWhenReady = function(attempt) {
        attempt = attempt || 0;
        var self = this;
        var maxAttempts = 10;

        // Get ROM ID from selector (Retro Emulator block) or data attribute (ROM Player block)
        var romId = 0;
        if (this.romSelect && this.romSelect.value) {
            romId = parseInt(this.romSelect.value, 10);
        } else if (this.element.dataset.defaultRom) {
            romId = parseInt(this.element.dataset.defaultRom, 10);
        }

        if (!romId) {
            return;
        }

        // Check if WPGamifyBridge is ready and has ROM data
        if (!window.WPGamifyBridge || !window.WPGamifyBridge.availableRoms || window.WPGamifyBridge.availableRoms.length === 0) {
            if (attempt < maxAttempts) {
                setTimeout(function() {
                    self.loadSelectedRomWhenReady(attempt + 1);
                }, 100);
            } else {
                this.updateStatus('ROM data not available');
            }
            return;
        }

        // Data is ready, load the ROM
        this.loadSelectedRom();
    };

    RetroEmulator.prototype.loadSelectedRom = function() {
        // Get ROM ID from selector (Retro Emulator block) or data attribute (ROM Player block)
        var romId = 0;
        if (this.romSelect && this.romSelect.value) {
            romId = parseInt(this.romSelect.value, 10);
        } else if (this.element.dataset.defaultRom) {
            romId = parseInt(this.element.dataset.defaultRom, 10);
        }

        if (!window.WPGamifyBridge || !romId) {
            return;
        }
        var rom = window.WPGamifyBridge.getRomById(romId);
        if (!rom || !rom.rom_url) {
            this.updateStatus('ROM unavailable');
            return;
        }

        if (this.currentRom && this.currentRom.id !== rom.id) {
            this.signalGameOver('rom_switch');
        }

        this.updateStatus('Loading ' + rom.title + '…');
        var self = this;
        fetch(rom.rom_url, { credentials: 'same-origin' })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.arrayBuffer();
            })
            .then(function(buffer) {
                var bytes = new Uint8Array(buffer);
                var binary = '';
                for (var i = 0; i < bytes.length; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                self.nes.loadROM(binary);
                self.startRenderLoop();
                if (self.audioCtx && self.audioCtx.state === 'suspended') {
                    self.audioCtx.resume();
                }
                self.setActiveRom(rom);
                self.signalGameStart(rom);
                RetroEmulator.activeInstance = self;
                self.updateMetadata(rom);
                self.updateStatus(rom.title + ' loaded. Press Start to play.');
            })
            .catch(function(error) {
                self.updateStatus('Error loading ROM: ' + error.message);
            });
    };

    RetroEmulator.prototype.updateStatus = function(message) {
        if (this.statusEl) {
            this.statusEl.textContent = message;
        }
    };

    RetroEmulator.prototype.startRenderLoop = function() {
        var self = this;
        function onFrame() {
            self.imageData.data.set(self.frameU8);
            self.ctx.putImageData(self.imageData, 0, 0);
            self.animationFrame = window.requestAnimationFrame(onFrame);
        }
        if (this.animationFrame) {
            window.cancelAnimationFrame(this.animationFrame);
        }
        this.animationFrame = window.requestAnimationFrame(onFrame);
    };

    RetroEmulator.prototype.setActiveRom = function(rom) {
        this.currentRom = rom;
        this.lastLoad = Date.now();
        if (!window.WPGamifyBridge) {
            return;
        }
        window.WPGamifyBridge.activeRom = rom;
        window.WPGamifyBridge.emulatorType = rom.adapter || 'jsnes';
        window.WPGamifyBridge.log && window.WPGamifyBridge.log('info', 'ROM ready', rom);
    };

    RetroEmulator.prototype.signalGameStart = function(rom) {
        this.dispatchLifecycleEvent('jsnes:gameLoad', {
            rom: rom,
            timestamp: Date.now()
        });
        this.dispatchLifecycleEvent('jsnes:gameStart', {
            rom: rom,
            timestamp: Date.now()
        });
        if (window.WPGamifyBridge) {
            var primarySystem = Array.isArray(rom.systems) && rom.systems.length ? rom.systems[0] : null;
            window.WPGamifyBridge.onGameLoad(rom.title, {
                emulator: rom.adapter,
                romId: rom.id,
                system: primarySystem
            });
            window.WPGamifyBridge.onGameStart(rom.title, primarySystem);
        }
    };

    RetroEmulator.prototype.signalGameOver = function(reason) {
        if (!this.currentRom) {
            return;
        }
        this.dispatchLifecycleEvent('jsnes:gameOver', {
            rom: this.currentRom,
            reason: reason || 'manual',
            timestamp: Date.now()
        });
        if (window.WPGamifyBridge) {
            window.WPGamifyBridge.onGameOver(0, null, null);
        }
        this.currentRom = null;
    };

    RetroEmulator.prototype.dispatchLifecycleEvent = function(name, detail) {
        if (typeof window.CustomEvent !== 'function') {
            function CustomEventPoly(event, params) {
                params = params || { bubbles: false, cancelable: false, detail: null };
                var evt = document.createEvent('CustomEvent');
                evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
                return evt;
            }
            window.CustomEvent = CustomEventPoly;
        }
        document.dispatchEvent(new CustomEvent(name, { detail: detail }));
    };

    RetroEmulator.prototype.updateMetadata = function(rom) {
        if (!this.metaFields.system) {
            return;
        }
        this.metaFields.system.textContent = rom.systems && rom.systems.length ? rom.systems.join(', ') : '—';
        this.metaFields.release.textContent = rom.release_year || '—';
        this.metaFields.publisher.textContent = rom.publisher || '—';
        this.metaFields.size.textContent = rom.file_size_human || '—';
        var notes = '';
        if (rom.notes) {
            notes = typeof rom.notes === 'string' ? rom.notes : JSON.stringify(rom.notes);
        }
        if (notes && this.metaFields.notes) {
            this.metaFields.notes.hidden = false;
            this.metaFields.notes.innerHTML = '<strong>Notes:</strong><p>' + window.escapeHtml(notes) + '</p>';
        } else if (this.metaFields.notes) {
            this.metaFields.notes.hidden = true;
            this.metaFields.notes.innerHTML = '';
        }
    };

    RetroEmulator.prototype.initTouchControls = function() {
        if (!this.touchLayer) {
            return;
        }
        var self = this;
        var coarse = window.matchMedia && window.matchMedia('(pointer:coarse)').matches;
        var prefersTouch = (this.autoTouch === 'always') || ((this.autoTouch === 'auto' || this.autoTouch === 'mobile') && coarse);
        if (prefersTouch) {
            this.element.classList.add('touch-visible');
        }

        var buttons = this.touchLayer.querySelectorAll('[data-button]');
        buttons.forEach(function(btn) {
            var action = btn.dataset.button;
            ['touchstart', 'mousedown'].forEach(function(evt) {
                btn.addEventListener(evt, function(e) {
                    e.preventDefault();
                    self.handleButton(action, true);
                    btn.classList.add('is-active');
                });
            });
            ['touchend', 'touchcancel', 'mouseup', 'mouseleave'].forEach(function(evt) {
                btn.addEventListener(evt, function(e) {
                    e.preventDefault();
                    self.handleButton(action, false);
                    btn.classList.remove('is-active');
                });
            });
        });
    };

    RetroEmulator.prototype.handleButton = function(action, isDown) {
        if (!this.nes) {
            return;
        }
        var button = null;
        switch (action) {
            case 'up': button = jsnes.Controller.BUTTON_UP; break;
            case 'down': button = jsnes.Controller.BUTTON_DOWN; break;
            case 'left': button = jsnes.Controller.BUTTON_LEFT; break;
            case 'right': button = jsnes.Controller.BUTTON_RIGHT; break;
            case 'a': button = jsnes.Controller.BUTTON_A; break;
            case 'b': button = jsnes.Controller.BUTTON_B; break;
            case 'start': button = jsnes.Controller.BUTTON_START; break;
            case 'select': button = jsnes.Controller.BUTTON_SELECT; break;
        }
        if (button !== null) {
            if (isDown) {
                this.nes.buttonDown(1, button);
            } else {
                this.nes.buttonUp(1, button);
            }
        }
    };

    RetroEmulator.activeInstance = null;

    document.addEventListener('keydown', function(event) {
        var instance = RetroEmulator.activeInstance;
        if (!instance || !instance.nes) {
            return;
        }
        switch (event.keyCode) {
            case 38: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_UP); break;
            case 40: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_DOWN); break;
            case 37: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_LEFT); break;
            case 39: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_RIGHT); break;
            case 65:
            case 90: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_A); break;
            case 83:
            case 88: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_B); break;
            case 13: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_START); break;
            case 9: instance.nes.buttonDown(1, jsnes.Controller.BUTTON_SELECT); break;
            default: return;
        }
        event.preventDefault();
    });

    document.addEventListener('keyup', function(event) {
        var instance = RetroEmulator.activeInstance;
        if (!instance || !instance.nes) {
            return;
        }
        switch (event.keyCode) {
            case 38: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_UP); break;
            case 40: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_DOWN); break;
            case 37: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_LEFT); break;
            case 39: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_RIGHT); break;
            case 65:
            case 90: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_A); break;
            case 83:
            case 88: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_B); break;
            case 13: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_START); break;
            case 9: instance.nes.buttonUp(1, jsnes.Controller.BUTTON_SELECT); break;
            default: return;
        }
        event.preventDefault();
    });

    window.addEventListener('click', function(event) {
        var target = event.target.closest('.wp-gamify-emulator');
        if (!target) {
            return;
        }
        var player = target.__retroPlayer;
        if (player) {
            RetroEmulator.activeInstance = player;
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var wrappers = document.querySelectorAll('.wp-gamify-emulator');
        wrappers.forEach(function(wrapper) {
            var player = new RetroEmulator(wrapper);
            wrapper.__retroPlayer = player;
            RetroEmulator.activeInstance = player;
        });
    });

    window.escapeHtml = window.escapeHtml || function(string) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(string));
        return div.innerHTML;
    };

})(window, document);
