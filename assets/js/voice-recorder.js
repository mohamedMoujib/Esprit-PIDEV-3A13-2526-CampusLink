/**
 * Dictée vocale (Web Speech API) — append au textarea cible.
 * Usage : window.VoiceRecorder
 */
window.VoiceRecorder = class VoiceRecorder {
    constructor(targetSelector, options = {}) {
        this.target = document.querySelector(targetSelector);
        this.lang = options.lang || 'fr-FR';
        this.onTranscriptFinal = options.onTranscriptFinal || null;
        this.autoTrigger = !!options.autoTrigger;
        this.silenceTimeout = options.silenceTimeout ?? 30000;
        this._recognition = null;
        this._listening = false;
        this._silenceTimer = null;
        this._interimEl = null;
        this._finalEl = null;
        this._btn = null;
        this.buttonLabel = options.buttonLabel || '🎤 Dicter';
    }

    _clearSilenceTimer() {
        if (this._silenceTimer) {
            clearTimeout(this._silenceTimer);
            this._silenceTimer = null;
        }
    }

    _armSilenceTimer() {
        const self = this;
        this._clearSilenceTimer();
        this._silenceTimer = setTimeout(function () {
            self.stop();
        }, this.silenceTimeout);
    }

    _speechSupported() {
        return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
    }

    _setBtnState(state) {
        if (!this._btn) return;
        this._btn.classList.remove('listening', 'processing');
        if (state === 'listening') {
            this._btn.classList.add('listening');
            this._btn.textContent = '⏹ Arrêter';
        } else if (state === 'processing') {
            this._btn.classList.add('processing');
            this._btn.textContent = '…';
        } else {
            this._btn.textContent = this._btn.dataset.idleLabel || this.buttonLabel;
        }
    }

    _showMsg(text, isError) {
        if (!this._btn || !this._btn.parentElement) return;
        var el = this._btn.parentElement.querySelector('.voice-recorder-msg');
        if (!el) {
            el = document.createElement('div');
            el.className = 'voice-recorder-msg';
            el.style.cssText = 'font-size:12px;margin-top:6px;color:var(--danger);';
            this._btn.parentElement.appendChild(el);
        }
        el.style.color = isError ? 'var(--danger)' : 'var(--text-muted)';
        el.textContent = text;
    }

    start() {
        if (!this.target) return;
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) {
            this._showMsg('Votre navigateur ne supporte pas la dictée vocale', true);
            return;
        }
        if (this._listening) return;

        this._recognition = new SR();
        this._recognition.lang = this.lang;
        this._recognition.continuous = true;
        this._recognition.interimResults = true;

        const self = this;
        this._recognition.onstart = function () {
            self._listening = true;
            self._setBtnState('listening');
            self._showMsg('');
        };

        this._recognition.onerror = function (ev) {
            if (ev.error === 'not-allowed' || ev.error === 'service-not-allowed') {
                self._showMsg('Accès au microphone refusé', true);
            }
            self.stop();
        };

        this._recognition.onend = function () {
            if (!self._listening) {
                return;
            }
            try {
                self._recognition.start();
            } catch (e) {
                self._listening = false;
                self._setBtnState('idle');
            }
        };

        this._recognition.onresult = function (event) {
            self._armSilenceTimer();
            var interim = '';
            var finalTxt = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                var piece = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTxt += piece;
                } else {
                    interim += piece;
                }
            }
            if (self._interimEl) self._interimEl.textContent = interim;
            if (finalTxt) {
                var cur = self.target.value;
                var add = (cur && !cur.endsWith(' ') ? ' ' : '') + finalTxt.trim();
                self.target.value = cur + add;
                self._lastInterim = '';
                if (self._finalEl) {
                    self._finalEl.textContent = (self._finalEl.textContent ? self._finalEl.textContent + ' ' : '') + finalTxt.trim();
                }
                if (self.onTranscriptFinal) {
                    self.onTranscriptFinal(finalTxt.trim());
                }
            }
        };

        try {
            this._recognition.start();
            this._armSilenceTimer();
        } catch (e) {
            this._showMsg('Impossible de démarrer la dictée', true);
        }
    }

    stop() {
        this._clearSilenceTimer();
        this._listening = false;
        if (this._recognition) {
            try {
                this._recognition.stop();
            } catch (e) {}
            this._recognition.onend = null;
            this._recognition = null;
        }
        this._setBtnState('idle');
        if (this._interimEl) this._interimEl.textContent = '';
    }

    toggle() {
        if (this._listening) this.stop();
        else this.start();
    }

    renderButton(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container || !this.target) return;

        const wrap = document.createElement('div');
        wrap.className = 'voice-recorder-wrap';
        wrap.style.marginTop = '8px';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-ghost btn-sm voice-btn';
        btn.textContent = this.buttonLabel;
        btn.dataset.idleLabel = this.buttonLabel;
        btn.style.borderColor = 'var(--border)';
        this._btn = btn;

        const self = this;
        btn.addEventListener('click', function () {
            self.toggle();
        });

        const interim = document.createElement('div');
        interim.className = 'voice-transcript-interim';
        this._interimEl = interim;

        const fin = document.createElement('div');
        fin.className = 'voice-transcript-final';
        this._finalEl = fin;

        wrap.appendChild(btn);
        wrap.appendChild(interim);
        wrap.appendChild(fin);
        container.appendChild(wrap);

        if (!this._speechSupported()) {
            btn.disabled = true;
            this._showMsg('Votre navigateur ne supporte pas la dictée vocale', true);
        }
    }
};
