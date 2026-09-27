/**
 * MythWeb LightTooltip library.
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/
 
(function(global) {
    'use me strict';

    class LightTooltip {
        constructor(options = {}) {
            this.options = Object.assign({
                tooltipId: 'light_tooltip',
                delay: 200,
                offset: 20,
                maxCacheSize: 150,
                fetchData: null, // Function(element, callback) => Promise or data
                targetSelector: '[data-tooltip]', // Delegated selector
                container: document.body
            }, options);

            this.cache = new Map();
            this.activeTarget = null;
            this.hoverTimer = null;
            this.mousePos = { clientX: 0, clientY: 0 };
            this.tooltipElem = null;

            this._initDOM();
            this._bindEvents();
        }

        _initDOM() {
            let elem = document.getElementById(this.options.tooltipId);
            if (!elem) {
                elem = document.createElement('div');
                elem.id = this.options.tooltipId;
                elem.style.position = 'fixed';
                elem.style.display = 'none';
                elem.style.zIndex = '9999';
                elem.style.pointerEvents = 'none'; // Avoid interfering with mouse events
                document.body.appendChild(elem);
            }
            this.tooltipElem = elem;
        }

        _bindEvents() {
            const container = typeof this.options.container === 'string'
                ? document.querySelector(this.options.container)
                : this.options.container;

            if (!container) return;

            container.addEventListener('mouseover', (e) => {
                const target = e.target.closest(this.options.targetSelector);
                if (!target || this.activeTarget === target) return;

                this.activeTarget = target;
                this.mousePos = { clientX: e.clientX, clientY: e.clientY };

                this._clearTimer();
                this.hoverTimer = setTimeout(() => {
                    this._loadAndShow(target);
                }, this.options.delay);
            });

            container.addEventListener('mousemove', (e) => {
                if (!this.activeTarget) return;
                this.mousePos = { clientX: e.clientX, clientY: e.clientY };
                if (this.tooltipElem.style.display === 'block') {
                    this._positionTooltip();
                }
            });

            container.addEventListener('mouseout', (e) => {
                const target = e.target.closest(this.options.targetSelector);
                if (!target) return;

                const related = e.relatedTarget;
                if (related && target.contains(related)) return;

                this.hide();
            });
        }

        _loadAndShow(target) {
            const key = target.getAttribute('data-tooltip-id') || target.dataset.cardId || target.id;

            // 1. Return cached content if available
            if (key && this.cache.has(key)) {
                this.show(this.cache.get(key));
                return;
            }

            // 2. Fetch or compute content dynamically
            if (typeof this.options.fetchData === 'function') {
                Promise.resolve(this.options.fetchData(target))
                    .then((htmlContent) => {
                        if (htmlContent) {
                            if (key) this._setCache(key, htmlContent);
                            if (this.activeTarget === target) {
                                this.show(htmlContent);
                            }
                        }
                    })
                    .catch((err) => console.error('[LightTooltip Error]', err));
            } else {
                // Default fallback to data attribute
                const content = target.getAttribute('data-tooltip') || '';
                this.show(content);
            }
        }

        _setCache(key, content) {
            if (this.cache.size >= this.options.maxCacheSize) {
                const firstKey = this.cache.keys().next().value;
                this.cache.delete(firstKey);
            }
            this.cache.set(key, content);
        }

        _positionTooltip() {
            if (!this.tooltipElem || this.tooltipElem.style.display === 'none') return;

            const offset = this.options.offset;
            let left = this.mousePos.clientX + offset;
            let top = this.mousePos.clientY + offset;

            // Viewport edge collision detection
            if (left + this.tooltipElem.offsetWidth > window.innerWidth - 10) {
                left = this.mousePos.clientX - this.tooltipElem.offsetWidth - offset;
            }
            if (top + this.tooltipElem.offsetHeight > window.innerHeight - 10) {
                top = this.mousePos.clientY - this.tooltipElem.offsetHeight - offset;
            }

            this.tooltipElem.style.left = Math.max(10, left) + 'px';
            this.tooltipElem.style.top = Math.max(10, top) + 'px';
        }

        show(content) {
            if (!content || !this.tooltipElem) return;

            // Sanitize input if DOMPurify exists
            if (typeof DOMPurify !== 'undefined') {
                this.tooltipElem.innerHTML = DOMPurify.sanitize(content);
            } else {
                this.tooltipElem.innerHTML = content.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            }

            this.tooltipElem.style.display = 'block';
            this._positionTooltip();
        }

        hide() {
            this._clearTimer();
            this.activeTarget = null;
            if (this.tooltipElem) {
                this.tooltipElem.style.display = 'none';
            }
        }

        _clearTimer() {
            if (this.hoverTimer) {
                clearTimeout(this.hoverTimer);
                this.hoverTimer = null;
            }
        }
    }

    // Expose to Global / Window scope
    global.LightTooltip = LightTooltip;
})(typeof window !== 'undefined' ? window : this);