(function() {
	// ==================================================================
	// 1. Constants & State Management
	// ==================================================================
	const SECONDS_PER_SLOT = 15 * 60; // 900s per slot
	const VISIBLE_SLOTS_IN_VIEW = 12; // 3 hours visible by default at full scale
	const MAX_CACHE_SIZE = 150;

	const EpgStore = {
		channels: {},
		channelOrder: [],
		loadedWindows: {},
		minLoadedTime: GRID_ORIGIN_TIME,
		maxLoadedTime: GRID_ORIGIN_TIME + getWindowSeconds()
	};

	const UIState = {
		slotWidthPx: 0,
		totalWidthPx: 0,
		isFetching: false,
		isDragging: false,
		isProgrammaticScroll: false,
		suppressScrollEvents: false,
		activeHoverCardId: null,
		hoverDelayTimer: null,
		detailsCache: {},
		currentMousePos: {
			clientX: 0,
			clientY: 0
		},
		scrollDebounceTimer: null,
		isUpdatingEllipsis: false,
		activeAjaxRequests: 0,
		lastKnownScrollLeft: 0,
		jumpRetryCount: 0
	};

	console.log('[EPG Init] GRID_ORIGIN_TIME:', GRID_ORIGIN_TIME, '(' + new Date(GRID_ORIGIN_TIME * 1000).toLocaleString() + ')');

	// ==================================================================
	// 2. Pure Utilities & Helper Functions
	// ==================================================================
	function getWindowSeconds() {
		return VISIBLE_SLOTS_IN_VIEW * SECONDS_PER_SLOT;
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.innerText = text || '';
		return div.innerHTML;
	}

	function parseHashTimestamp() {
		const hash = window.location.hash.replace(/^#/, '');
		if (!hash) return null;

		const match = hash.match(/(?:^|&)(?:time|ts)=(\d+)/);
		return match ? parseInt(match[1], 10) : null;
	}

	function getServerDateParts(timestampInSec) {
		const date = new Date(timestampInSec * 1000);
		const formatter = new Intl.DateTimeFormat('en-US', {
			timeZone: SERVER_TIMEZONE,
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
			hourCycle: 'h23'
		});
		const parts = {};
		formatter.formatToParts(date).forEach(function(p) {
			if (p.type !== 'literal') parts[p.type] = parseInt(p.value, 10);
		});
		return {
			year: parts.year,
			month: parts.month - 1,
			day: parts.day,
			hour: parts.hour === 24 ? 0 : parts.hour,
			minute: parts.minute
		};
	}

function getTimestampForServerTime(year, month, day, hour, minute) {
	// Target ISO string representation in local server time format (YYYY-MM-DD HH:mm)
	const pad = (n) => String(n).padStart(2, '0');
	const targetStr = `${year}-${pad(month + 1)}-${pad(day)} ${pad(hour || 0)}:${pad(minute || 0)}`;

	// Start with a UTC estimate
	let ts = Date.UTC(year, month, day, hour || 0, minute || 0, 0) / 1000;

	for (let i = 0; i < 5; i++) {
		const parts = getServerDateParts(ts);
		const currentStr = `${parts.year}-${pad(parts.month + 1)}-${pad(parts.day)} ${pad(parts.hour)}:${pad(parts.minute)}`;

		if (currentStr === targetStr) break;

		// Calculate actual time difference using Date UTC timestamps of the formatted parts
		const targetUtc = Date.UTC(year, month, day, hour || 0, minute || 0);
		const currentUtc = Date.UTC(parts.year, parts.month, parts.day, parts.hour, parts.minute);

		const diffMs = targetUtc - currentUtc;
		if (diffMs === 0) break;

		ts += (diffMs / 1000);
	}

	return ts;
}

	function parseHourFromOption(opt) {
		const val = parseInt(opt.value, 10);
		if (!isNaN(val) && val >= 0 && val <= 23) return val;

		const text = opt.text.trim();
		const match12 = text.match(/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/i);
		if (match12) {
			let h = parseInt(match12[1], 10);
			const ampm = match12[3].toUpperCase();
			if (ampm === 'PM' && h < 12) h += 12;
			if (ampm === 'AM' && h === 12) h = 0;
			return h;
		}

		const match24 = text.match(/^(\d{1,2}):?(\d{2})?$/);
		if (match24) return parseInt(match24[1], 10);

		return NaN;
	}

	function getSelectedDateAndHour() {
		const dateElem = document.getElementById('date_select');
		const hourElem = document.getElementById('hour_select');
		if (!dateElem || !hourElem) return null;

		const dateStr = String(dateElem.value);
		const selectedOption = hourElem.options[hourElem.selectedIndex];
		if (!selectedOption) return null;

		let selectedHour = parseHourFromOption(selectedOption);
		if (isNaN(selectedHour)) selectedHour = 0;

		let year, month, day;
		if (/^\d{8}$/.test(dateStr)) {
			year = parseInt(dateStr.substring(0, 4), 10);
			month = parseInt(dateStr.substring(4, 6), 10) - 1;
			day = parseInt(dateStr.substring(6, 8), 10);
		} else if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
			const dParts = dateStr.split('-');
			year = parseInt(dParts[0], 10);
			month = parseInt(dParts[1], 10) - 1;
			day = parseInt(dParts[2], 10);
		} else {
			const ts = parseInt(dateStr, 10);
			if (!isNaN(ts)) {
				const oParts = getServerDateParts(ts);
				year = oParts.year;
				month = oParts.month;
				day = oParts.day;
			}
		}

		if (year === undefined) return null;

		return {
			year: year,
			month: month,
			day: day,
			hour: selectedHour
		};
	}

	function clearActiveTimers() {
		if (UIState.hoverDelayTimer) {
			clearTimeout(UIState.hoverDelayTimer);
			UIState.hoverDelayTimer = null;
		}
		if (UIState.scrollDebounceTimer) {
			clearTimeout(UIState.scrollDebounceTimer);
			UIState.scrollDebounceTimer = null;
		}
	}

	function updateAjaxStatus(delta) {
		UIState.activeAjaxRequests = Math.max(0, UIState.activeAjaxRequests + delta);
		const workingElem = document.getElementById('ajax_working');
		const countElem = document.getElementById('ajax_num_requests');
		if (countElem) countElem.textContent = UIState.activeAjaxRequests;
		if (workingElem) {
			if (UIState.activeAjaxRequests > 0) workingElem.classList.remove('hidden');
			else workingElem.classList.add('hidden');
		}
	}

	// ==================================================================
	// 3. Coordinate Conversions & Dynamic Width Engine
	// ==================================================================
	function updateLayoutDimensions() {
		const container = document.getElementById('scrollContainer');
		const gridBody = document.getElementById('gridBody');
		const timelineHeader = document.getElementById('timelineHeader');

		if (!container || !gridBody || !timelineHeader) return;

		const viewportWidth = container.clientWidth;
		if (viewportWidth <= 0) return;

		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		const totalSlots = totalSeconds / SECONDS_PER_SLOT;

		UIState.slotWidthPx = viewportWidth / VISIBLE_SLOTS_IN_VIEW;
		UIState.totalWidthPx = totalSlots * UIState.slotWidthPx;

		gridBody.style.width = UIState.totalWidthPx + 'px';
		timelineHeader.style.width = UIState.totalWidthPx + 'px';

		const timeCells = timelineHeader.querySelectorAll('.time-cell');
		timeCells.forEach(function(cell) {
			cell.style.width = UIState.slotWidthPx + 'px';
			cell.style.flex = '0 0 ' + UIState.slotWidthPx + 'px';
		});

		console.log('[EPG Layout] Viewport Width:', viewportWidth, 'px | Slot Width:', UIState.slotWidthPx, 'px | Total Width:', UIState.totalWidthPx,
			'px');
	}

	function timeToPercent(timestamp) {
		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		if (totalSeconds <= 0) return 0;
		const offsetSeconds = timestamp - EpgStore.minLoadedTime;
		return (offsetSeconds / totalSeconds) * 100;
	}

	function durationToPercent(durationSec) {
		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		if (totalSeconds <= 0) return 0;
		return (durationSec / totalSeconds) * 100;
	}

	function pixelsToTime(pixelX) {
		if (!UIState.totalWidthPx) return EpgStore.minLoadedTime;
		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		const ratio = pixelX / UIState.totalWidthPx;
		return Math.floor(EpgStore.minLoadedTime + (ratio * totalSeconds));
	}

	function calculateEpgTimeBounds(scrollLeft, viewportWidth, totalWidthPx) {
		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		return {
			visibleMinTime: EpgStore.minLoadedTime + Math.floor((scrollLeft / totalWidthPx) * totalSeconds),
			visibleMaxTime: EpgStore.minLoadedTime + Math.floor(((scrollLeft + viewportWidth) / totalWidthPx) * totalSeconds)
		};
	}

	// ==================================================================
	// 4. Data Store Ingestion & Caching
	// ==================================================================
	function ingestEpgData(channelsData, windowStart) {
		console.group('[EPG Ingest] Window Start:', windowStart, '(' + new Date(windowStart * 1000).toLocaleString() + ')');

		const windowEnd = windowStart + getWindowSeconds();
		if (Object.keys(EpgStore.loadedWindows).length === 0) {
			EpgStore.minLoadedTime = windowStart;
			EpgStore.maxLoadedTime = windowEnd;
		} else {
			EpgStore.minLoadedTime = Math.min(EpgStore.minLoadedTime, windowStart);
			EpgStore.maxLoadedTime = Math.max(EpgStore.maxLoadedTime, windowEnd);
		}

		EpgStore.loadedWindows[windowStart] = true;

		if (!Array.isArray(channelsData)) {
			console.warn('[EPG Ingest] Data is not an array:', channelsData);
			console.groupEnd();
			return;
		}

		let progCount = 0;
		channelsData.forEach(function(chan) {
			const chanId = String(chan.id || chan.chanid);
			if (!EpgStore.channels[chanId]) {
				EpgStore.channels[chanId] = {
					id: chanId,
					name: chan.name,
					callsign: chan.callsign,
					channum: chan.channum || '',
					icon: chan.icon || '',
					programmes: {}
				};
				if (EpgStore.channelOrder.indexOf(chanId) === -1) {
					EpgStore.channelOrder.push(chanId);
				}
			}

			if (Array.isArray(chan.programmes)) {
				chan.programmes.forEach(function(prog) {
					if (!prog || !prog.start) return;
					const progKey = chanId + '_' + prog.start;
					if (!EpgStore.channels[chanId].programmes[progKey]) {
						progCount++;
						EpgStore.channels[chanId].programmes[progKey] = prog;
					}
				});
			}
		});

		console.log('Ingested', progCount, 'new programs.');
		console.groupEnd();
	}

	function cacheShowDetails(cardId, data) {
		const keys = Object.keys(UIState.detailsCache);
		if (keys.length >= MAX_CACHE_SIZE) {
			delete UIState.detailsCache[keys[0]];
		}
		UIState.detailsCache[cardId] = data;
	}

	// ==================================================================
	// 5. Layout & Render Pipeline
	// ==================================================================
	function renderTimeHeader() {
		const timelineHeader = document.getElementById('timelineHeader');
		const leftNav = document.getElementById('timelineLeftNav');
		const rightNav = document.getElementById('timelineRightNav');

		if (!timelineHeader) return;

		if (leftNav) {
			leftNav.innerHTML = '';
			const aLeft = document.createElement('a');
			aLeft.className = 'link';
			aLeft.onclick = function() {
				window.EPG.shiftTimeHours(-3);
			};
			aLeft.innerHTML = '<img src="' + SKIN_URL + 'img/left.gif" alt="left">';
			leftNav.appendChild(aLeft);
		}
		if (rightNav) {
			rightNav.innerHTML = '';
			const aRight = document.createElement('a');
			aRight.className = 'link';
			aRight.onclick = function() {
				window.EPG.shiftTimeHours(3);
			};
			aRight.innerHTML = '<img src="' + SKIN_URL + 'img/right.gif" alt="right">';
			rightNav.appendChild(aRight);
		}

		timelineHeader.innerHTML = '';
		const totalSlots = (EpgStore.maxLoadedTime - EpgStore.minLoadedTime) / SECONDS_PER_SLOT;

		for (let i = 0; i < totalSlots; i++) {
			const slotTime = EpgStore.minLoadedTime + (i * SECONDS_PER_SLOT);
			const date = new Date(slotTime * 1000);
			const sParts = getServerDateParts(slotTime);
			const minutes = sParts.minute;

			const fullTimeStr = date.toLocaleTimeString([], {
				hour: 'numeric',
				minute: '2-digit',
				hour12: true,
				timeZone: SERVER_TIMEZONE
			}).toUpperCase();

			const hourOnlyStr = (minutes === 0) ?
				date.toLocaleTimeString([], {
					hour: 'numeric',
					hour12: true,
					timeZone: SERVER_TIMEZONE
				}).toUpperCase() :
				'&nbsp;';

			const cell = document.createElement('div');
			cell.className = 'menu nowrap time-cell';
			cell.style.width = UIState.slotWidthPx + 'px';
			cell.style.flex = '0 0 ' + UIState.slotWidthPx + 'px';

			const cellLink = document.createElement('a');
			cellLink.className = 'link';
			cellLink.addEventListener('click', (function(time) {
				return function() {
					window.EPG.scrollToTimestamp(time);
				};
			})(slotTime));

			cellLink.innerHTML = '<span class="time-full">' + fullTimeStr + '</span>' +
				'<span class="time-hour-only">' + hourOnlyStr + '</span>';

			cell.appendChild(cellLink);
			timelineHeader.appendChild(cell);
		}
	}

function renderChannelGrid() {
		const channelLabels = document.getElementById('channelLabels');
		const gridBody = document.getElementById('gridBody');
		if (!channelLabels || !gridBody) return;

		channelLabels.innerHTML = '';
		gridBody.innerHTML = '';

		const labelsFragment = document.createDocumentFragment();
		const gridFragment = document.createDocumentFragment();

		EpgStore.channelOrder.forEach(function(chanId) {
			const chan = EpgStore.channels[chanId];

			// 1. Build channel label
			const lbl = document.createElement('div');
			lbl.className = 'x-channel';
			let chanHtml = '';

			if (chan.channum) {
				chanHtml += '<div class="chan-num">' + escapeHtml(chan.channum) + '</div>';
			}
			if (chan.icon) {
				chanHtml += '<div class="chan-icon"><img src="' + escapeHtml(chan.icon) + '"></div>';
			}
			const chanUrl = ROOT_URL + 'tv/channel/' + chanId;
			chanHtml += '<div class="chan-name"><a href="' + chanUrl + '">(' + escapeHtml(chan.name) + ')</a></div>';

			lbl.innerHTML = chanHtml;
			labelsFragment.appendChild(lbl);

			// 2. Build channel row & cards
			const row = document.createElement('div');
			row.className = 'channel-row';
			row.id = 'channel-row-' + chanId;

			Object.keys(chan.programmes).forEach(function(progKey) {
				const prog = chan.programmes[progKey];
				const startPct = timeToPercent(prog.start);
				const durationPct = durationToPercent(prog.duration);

				const card = createProgramCard(prog, chanId, startPct, durationPct);
				row.appendChild(card);
			});

			gridFragment.appendChild(row);
		});

		channelLabels.appendChild(labelsFragment);
		gridBody.appendChild(gridFragment);
	}

function createProgramCard(prog, chanId, startPct, durationPct) {
		const card = document.createElement('div');
		card.className = 'programme-card' + (prog.css_class ? ' ' + prog.css_class : '');

		const cardId = 'program-' + chanId + '-' + prog.start;
		card.id = cardId;
		card.style.left = startPct + '%';
		card.style.width = durationPct + '%';

		card.setAttribute('data-card-id', cardId);
		card.setAttribute('data-chan-id', chanId);
		card.setAttribute('data-start', prog.start);
		card.setAttribute('data-stop', prog.stop);
		card.setAttribute('data-duration', prog.duration);

		let fullTitle = prog.title || '';
		if (prog.movie_info) fullTitle += ' ' + prog.movie_info;

		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = fullTitle;
		const decodedTitle = tempDiv.textContent || tempDiv.innerText || fullTitle;

		const titleDiv = document.createElement('div');
		titleDiv.className = 'prog-title';

		const titleSpan = document.createElement('span');
		titleSpan.className = 'title-text';
		titleSpan.textContent = decodedTitle;
		titleSpan.setAttribute('data-original-text', decodedTitle);

		titleDiv.appendChild(titleSpan);
		card.appendChild(titleDiv);

		if (prog.subTitle) {
			const subDiv = document.createElement('div');
			subDiv.className = 'prog-subtitle';
			subDiv.textContent = prog.subTitle;
			card.appendChild(subDiv);
		}

		const extraDetails = [];
		if (prog.hdtv) extraDetails.push('<span class="hdtv_icon2">HD</span>');
		if (prog.previouslyshown) extraDetails.push('<span class="prog-tag tag-repeat"><em>(Rerun)</em></span>');

		if (extraDetails.length > 0) {
			const extraDiv = document.createElement('div');
			extraDiv.className = 'prog-extra-info';
			extraDiv.innerHTML = extraDetails.join(' ');
			card.appendChild(extraDiv);
		}

		return card;
	}

function initGridBodyDelegation() {
		const gridBody = document.getElementById('gridBody');
		if (!gridBody || gridBody.getAttribute('data-delegation-init') === 'true') return;
		gridBody.setAttribute('data-delegation-init', 'true');

		gridBody.addEventListener('click', function(e) {
			if (UIState.isDragging || UIState.justDragged) return;

			const card = e.target.closest('.programme-card');
			if (!card || card.classList.contains('no-data-card')) return;

			const chanId = card.getAttribute('data-chan-id');
			const start = card.getAttribute('data-start');
			if (!chanId || !start) return;

			const container = document.getElementById('scrollContainer');
			const currentEpgTime = container ? pixelsToTime(container.scrollLeft) : EpgStore.minLoadedTime;

			window.location.href = ROOT_URL + 'tv/detail/' + chanId + '/' + start + '?epg_time=' + currentEpgTime;
		});

		function isTextTarget(target) {
			return target.closest('.prog-title, .prog-subtitle') !== null;
		}

		gridBody.addEventListener('mouseover', function(e) {
			const card = e.target.closest('.programme-card');
			if (!card || card.classList.contains('no-data-card') || !isTextTarget(e.target)) return;

			const cardId = card.getAttribute('data-card-id');
			if (UIState.activeHoverCardId === cardId) return;

			UIState.activeHoverCardId = cardId;
			UIState.currentMousePos = { clientX: e.clientX, clientY: e.clientY };
			card.classList.add('text-hovered');

			if (UIState.hoverDelayTimer) clearTimeout(UIState.hoverDelayTimer);

			const chanId = card.getAttribute('data-chan-id');
			const start = card.getAttribute('data-start');
			const mousePos = { clientX: e.clientX, clientY: e.clientY };

			UIState.hoverDelayTimer = setTimeout(function() {
				fetchShowDetails(cardId, chanId, start, mousePos);
			}, 200);
		});

		gridBody.addEventListener('mousemove', function(e) {
			if (!UIState.activeHoverCardId || !isTextTarget(e.target)) return;

			UIState.currentMousePos = { clientX: e.clientX, clientY: e.clientY };
			const tooltip = document.getElementById('modern_epg_tooltip');
			if (tooltip && tooltip.style.display === 'block') {
				positionPopupRelativeToMouse(e);
			}
		});

		gridBody.addEventListener('mouseout', function(e) {
			const card = e.target.closest('.programme-card');
			if (!card) return;

			const related = e.relatedTarget;
			if (related && card.contains(related) && isTextTarget(related)) return;

			if (UIState.activeHoverCardId === card.getAttribute('data-card-id')) {
				UIState.activeHoverCardId = null;
				card.classList.remove('text-hovered');
				if (UIState.hoverDelayTimer) clearTimeout(UIState.hoverDelayTimer);
				hidePopup();
			}
		});

		gridBody.addEventListener('mouseleave', function() {
			if (UIState.activeHoverCardId) {
				const activeCard = gridBody.querySelector('.programme-card[data-card-id="' + UIState.activeHoverCardId + '"]');
				if (activeCard) activeCard.classList.remove('text-hovered');
				UIState.activeHoverCardId = null;
				if (UIState.hoverDelayTimer) clearTimeout(UIState.hoverDelayTimer);
				hidePopup();
			}
		});
	}

	// ==================================================================
	// 6. Tooltip & Popup Controllers
	// ==================================================================
	function fetchShowDetails(cardId, chanId, startTime, mouseEvent) {
		if (mouseEvent) {
			UIState.currentMousePos = {
				clientX: mouseEvent.clientX,
				clientY: mouseEvent.clientY
			};
		}

		if (UIState.detailsCache[cardId]) {
			renderPopupContent(cardId, UIState.detailsCache[cardId]);
			return;
		}

		const url = ROOT_URL + 'tv/get_show_details?chanid=' + chanId + '&starttime=' + startTime + '&ajax=true';
		updateAjaxStatus(1);

		fetch(url)
			.then(function(res) {
				return res.json();
			})
			.then(function(data) {
				if (data && data.info) {
					cacheShowDetails(cardId, data.info);
					renderPopupContent(cardId, data.info);
				}
			})
			.catch(function(err) {
				console.error('[ToolTip Error]', err);
			})
			.finally(function() {
				updateAjaxStatus(-1);
			});
	}

	function renderPopupContent(cardId, htmlInfo) {
		if (cardId === UIState.activeHoverCardId) {
			const tooltip = document.getElementById('modern_epg_tooltip');
			if (tooltip) {
				if (typeof DOMPurify !== 'undefined') {
					tooltip.innerHTML = DOMPurify.sanitize(htmlInfo);
				} else {
					tooltip.innerHTML = htmlInfo.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
				}
				tooltip.style.display = 'block';
				positionPopupRelativeToMouse(UIState.currentMousePos);
			}
		}
	}

	function positionPopupRelativeToMouse(e) {
		const tooltip = document.getElementById('modern_epg_tooltip');
		if (!tooltip || !e) return;

		const offset = 20;
		let left = e.clientX + offset;
		let top = e.clientY + offset;

		if (left + tooltip.offsetWidth > window.innerWidth - 10) {
			left = e.clientX - tooltip.offsetWidth - offset;
		}
		if (top + tooltip.offsetHeight > window.innerHeight - 10) {
			top = e.clientY - tooltip.offsetHeight - offset;
		}

		tooltip.style.left = Math.max(10, left) + 'px';
		tooltip.style.top = Math.max(10, top) + 'px';
	}

	function hidePopup() {
		clearActiveTimers();
		const tooltip = document.getElementById('modern_epg_tooltip');
		if (tooltip) tooltip.style.display = 'none';
	}

	// ==================================================================
	// 7. Visual Overlay & Truncation Engines
	// ==================================================================
	function collectCardMetrics(cards) {
		const cardData = [];
		for (let i = 0; i < cards.length; i++) {
			const card = cards[i];
			cardData.push({
				card: card,
				cardLeft: card.offsetLeft,
				cardWidth: card.offsetWidth,
				progStart: parseInt(card.getAttribute('data-start'), 10),
				progStop: parseInt(card.getAttribute('data-stop'), 10),
				titleDiv: card.querySelector('.prog-title'),
				textSpan: card.querySelector('.title-text'),
				subDiv: card.querySelector('.prog-subtitle'),
				extraDiv: card.querySelector('.prog-extra-info')
			});
		}
		return cardData;
	}

	function updateSlidingBorder(item, borderType, offsetPx, shouldShow) {
		const isLeft = borderType === 'left';
		const className = isLeft ? 'sliding-left-border' : 'sliding-right-border';
		let borderElem = item.card.querySelector('.' + className);

		if (!borderElem) {
			borderElem = document.createElement('div');
			borderElem.className = className;
			borderElem.style.position = 'absolute';
			borderElem.style.top = '0';
			borderElem.style.bottom = '0';
			borderElem.style.zIndex = '5';

			if (!item.cachedStyle) {
				item.cachedStyle = window.getComputedStyle(item.card);
			}

			if (isLeft) {
				borderElem.style.borderLeftWidth = item.cachedStyle.borderLeftWidth;
				borderElem.style.borderLeftStyle = item.cachedStyle.borderLeftStyle;
				borderElem.style.borderLeftColor = item.cachedStyle.borderLeftColor;
			} else {
				borderElem.style.borderRightWidth = item.cachedStyle.borderRightWidth;
				borderElem.style.borderRightStyle = item.cachedStyle.borderRightStyle;
				borderElem.style.borderRightColor = item.cachedStyle.borderRightColor;
			}

			borderElem.style.display = 'none';
			item.card.appendChild(borderElem);
		}

		if (shouldShow) {
			borderElem.style[isLeft ? 'left' : 'right'] = offsetPx + 'px';
			borderElem.style.display = 'block';
		} else {
			borderElem.style.display = 'none';
		}
	}

	function updateSlidingBorders(item, scrollLeft, viewportWidth) {
		const visibleScreenRight = scrollLeft + viewportWidth;
		const overlap = scrollLeft - item.cardLeft;
		const cardRightEdge = item.cardLeft + item.cardWidth;
		const rightOverlap = cardRightEdge - visibleScreenRight;

		const showLeft = overlap >= 0 && overlap < item.cardWidth;
		updateSlidingBorder(item, 'left', overlap, showLeft);

		const showRight = rightOverlap > 0 && item.cardLeft < visibleScreenRight;
		updateSlidingBorder(item, 'right', rightOverlap, showRight);
	}

	function updateOverflowArrows(item, bounds, scrollLeft, viewportWidth) {
		const card = item.card;
		const titleDiv = item.titleDiv;
		const textSpan = item.textSpan;
		const visibleScreenRight = scrollLeft + viewportWidth;

		const startsBefore = item.progStart < bounds.visibleMinTime;
		const endsAfter = item.progStop > bounds.visibleMaxTime;

		const existingLeftImg = titleDiv.querySelector('.left_arrow');
		let existingRightImg = card.querySelector('.right_arrow');

		if (startsBefore && !existingLeftImg) {
			const imgL = document.createElement('img');
			imgL.src = SKIN_URL + 'img/left_sm.png';
			imgL.className = 'left_arrow';
			imgL.alt = 'left';
			titleDiv.insertBefore(imgL, textSpan);
		} else if (!startsBefore && existingLeftImg) {
			existingLeftImg.remove();
		}

		if (endsAfter && !existingRightImg) {
			const imgR = document.createElement('img');
			imgR.src = SKIN_URL + 'img/right_sm.png';
			imgR.className = 'right_arrow';
			imgR.alt = 'right';
			card.appendChild(imgR);
			existingRightImg = imgR;
		} else if (!endsAfter && existingRightImg) {
			existingRightImg.remove();
		}

		if (existingRightImg) {
			const cardRightEdge = item.cardLeft + item.cardWidth;
			existingRightImg.style.right = (cardRightEdge > visibleScreenRight) ? ((cardRightEdge - visibleScreenRight) + 6) + 'px' : '4px';
		}
	}

	function updateCardTextTruncation(item, scrollLeft) {
		const titleDiv = item.titleDiv;
		const textSpan = item.textSpan;
		const subDiv = item.subDiv;
		const extraDiv = item.extraDiv;

		if (!textSpan.getAttribute('data-original-text')) {
			textSpan.setAttribute('data-original-text', textSpan.textContent);
		}
		const originalText = textSpan.getAttribute('data-original-text');

		const overlap = scrollLeft - item.cardLeft;

		if (overlap > 0 && overlap < item.cardWidth) {
			textSpan.textContent = originalText;
			const visibleWidth = item.cardWidth - overlap - 12;

			if (visibleWidth > 20) {
				titleDiv.style.marginLeft = overlap + 'px';
				titleDiv.style.width = visibleWidth + 'px';

				if (visibleWidth < 45) {
					textSpan.textContent = '...';
				}

				if (subDiv) {
					subDiv.style.marginLeft = overlap + 'px';
					subDiv.style.width = visibleWidth + 'px';
				}
				if (extraDiv) {
					extraDiv.style.marginLeft = overlap + 'px';
				}
			} else {
				titleDiv.style.marginLeft = '0px';
				textSpan.textContent = '...';
				if (subDiv) subDiv.style.display = 'none';
				if (extraDiv) extraDiv.style.display = 'none';
			}
		} else {
			titleDiv.style.marginLeft = '0px';
			titleDiv.style.width = '';
			textSpan.textContent = originalText;

			if (subDiv) {
				subDiv.style.marginLeft = '0px';
				subDiv.style.width = '';
				subDiv.style.display = 'block';
			}
			if (extraDiv) {
				extraDiv.style.marginLeft = '0px';
				extraDiv.style.display = 'block';
			}
		}
	}

	function updateSidebarOverlapTruncation() {
		const container = document.getElementById('scrollContainer');
		const gridBody = document.getElementById('gridBody');
		if (!container || !gridBody) return;

		const scrollLeft = container.scrollLeft;
		const viewportWidth = container.clientWidth;
		const totalWidthPx = parseFloat(gridBody.style.width) || container.scrollWidth;

		const timeBounds = calculateEpgTimeBounds(scrollLeft, viewportWidth, totalWidthPx);
		const cards = document.querySelectorAll('.programme-card');

		const cardMetrics = collectCardMetrics(cards);

		for (let i = 0; i < cardMetrics.length; i++) {
			const item = cardMetrics[i];
			if (!item.titleDiv || !item.textSpan) continue;

			updateSlidingBorders(item, scrollLeft, viewportWidth);
			updateOverflowArrows(item, timeBounds, scrollLeft, viewportWidth);
			updateCardTextTruncation(item, scrollLeft);
		}
	}

	function requestEllipsisUpdate() {
		if (!UIState.isUpdatingEllipsis) {
			UIState.isUpdatingEllipsis = true;
			window.requestAnimationFrame(function() {
				updateSidebarOverlapTruncation();
				UIState.isUpdatingEllipsis = false;
			});
		}
	}

	// ==================================================================
	// 8. Navigation & Scroll Mechanics
	// ==================================================================
	function syncTimelinePosition(scrollLeft) {
		const container = document.getElementById('scrollContainer');
		const timelineWrapper = document.getElementById('timelineWrapper');
		const timelineHeader = document.getElementById('timelineHeader');

		if (!container || !timelineHeader) return;

		let px = (typeof scrollLeft === 'number') ? scrollLeft : container.scrollLeft;
		px = Math.max(0, px);

		if (timelineWrapper) timelineWrapper.scrollLeft = 0;

		timelineHeader.style.transform = 'translate3d(-' + px + 'px, 0, 0)';
		timelineHeader.style.willChange = 'transform';
	}

	function syncHeaderAndDropdowns(timestamp) {
		const dateObj = new Date(timestamp * 1000);
		const sParts = getServerDateParts(timestamp);

		const browsingElem = document.getElementById('x_current_time');
		if (browsingElem) {
			const formatted = new Intl.DateTimeFormat('en-US', {
				weekday: 'short',
				month: 'short',
				day: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
				hour12: true,
				timeZone: SERVER_TIMEZONE
			}).format(dateObj);
			browsingElem.innerText = 'Currently Browsing: ' + formatted;
		}

		const hourSelect = document.getElementById('hour_select');
		if (hourSelect) {
			Array.from(hourSelect.options).some(function(opt) {
				const parsedHour = parseHourFromOption(opt);
				if (parsedHour === sParts.hour) {
					hourSelect.value = opt.value;
					return true;
				}
			});
		}

		const dateSelect = document.getElementById('date_select');
		if (dateSelect) {
			const targetYmd = String(sParts.year) +
				String(sParts.month + 1).padStart(2, '0') +
				String(sParts.day).padStart(2, '0');

			Array.from(dateSelect.options).some(function(opt) {
				const v = String(opt.value);
				let ymd = '';
				if (/^\d{8}$/.test(v)) {
					ymd = v;
				} else if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
					ymd = v.replace(/-/g, '');
				} else if (!isNaN(parseInt(v, 10)) && parseInt(v, 10) > 100000) {
					const oParts = getServerDateParts(parseInt(v, 10));
					ymd = String(oParts.year) +
						String(oParts.month + 1).padStart(2, '0') +
						String(oParts.day).padStart(2, '0');
				}
				if (ymd === targetYmd) {
					dateSelect.value = opt.value;
					return true;
				}
			});
		}
	}

	function snapToNearestSlot() {
		const container = document.getElementById('scrollContainer');
		if (!container) return;

		if (UIState.isFetching) {
			console.warn('[EPG Snap Aborted] Fetching in progress; snap deferred.');
			return;
		}

		const scrollLeft = container.scrollLeft;

		if (scrollLeft === 0 && UIState.lastKnownScrollLeft > 100) {
			console.warn('[EPG Snap Aborted] Rejecting scrollLeft=0 snap attempt. Last known scroll position was:', UIState.lastKnownScrollLeft);
			return;
		}

		const currentTs = pixelsToTime(scrollLeft);
		let snappedTs = Math.round(currentTs / SECONDS_PER_SLOT) * SECONDS_PER_SLOT;
		snappedTs = Math.max(EpgStore.minLoadedTime, Math.min(EpgStore.maxLoadedTime, snappedTs));

		console.log('[EPG Snap Engine] Snapping scrollLeft:', scrollLeft, 'px -> Timestamp:', snappedTs, '(' + new Date(snappedTs * 1000)
			.toLocaleTimeString() + ')');

		syncHeaderAndDropdowns(snappedTs);
		scrollToTimestamp(snappedTs);
	}

	function scrollToTimestamp(targetTimestamp, skipHistory) {
		const container = document.getElementById('scrollContainer');
		if (!container) return;

		const MAX_RETRIES = 2;

		if (targetTimestamp < EpgStore.minLoadedTime) {
			if (UIState.jumpRetryCount >= MAX_RETRIES) {
				console.error('[EPG Error] Max retries reached while scrolling left. Aborting to prevent recursion.');
				UIState.jumpRetryCount = 0;
				return;
			}
			UIState.jumpRetryCount++;
			fetchWindow(targetTimestamp, 'left', function() {
				scrollToTimestamp(targetTimestamp, skipHistory);
			});
			return;
		} else if (targetTimestamp >= EpgStore.maxLoadedTime) {
			if (UIState.jumpRetryCount >= MAX_RETRIES) {
				console.error('[EPG Error] Max retries reached while scrolling right. Aborting to prevent recursion.');
				UIState.jumpRetryCount = 0;
				return;
			}
			UIState.jumpRetryCount++;
			fetchWindow(targetTimestamp, 'right', function() {
				scrollToTimestamp(targetTimestamp, skipHistory);
			});
			return;
		}

		UIState.jumpRetryCount = 0;

		const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		const offsetSeconds = targetTimestamp - EpgStore.minLoadedTime;
		const targetScrollLeft = Math.max(0, (offsetSeconds / totalSeconds) * UIState.totalWidthPx);

		UIState.suppressScrollEvents = true;
		container.scrollLeft = targetScrollLeft;
		syncTimelinePosition(targetScrollLeft);
		syncHeaderAndDropdowns(targetTimestamp);
		requestEllipsisUpdate();

		setTimeout(function() {
			UIState.suppressScrollEvents = false;
			if (!skipHistory && container) {
				container.dispatchEvent(new Event('scroll'));
			}
		}, 300);
	}

	function resetGridForJump(targetTimestamp) {
		EpgStore.loadedWindows = {};
		if (EpgStore.channelOrder) {
			EpgStore.channelOrder.forEach(function(chanId) {
				if (EpgStore.channels[chanId]) {
					EpgStore.channels[chanId].programmes = {};
				}
			});
		}

		const container = document.getElementById('scrollContainer');
		if (container) container.scrollLeft = 0;
		UIState.lastKnownScrollLeft = 0;

		const gridBody = document.getElementById('gridBody');
		if (gridBody) gridBody.innerHTML = '';
		const timelineHeader = document.getElementById('timelineHeader');
		if (timelineHeader) timelineHeader.innerHTML = '';
	}

	function navigateToTime(targetTimestamp, opts) {
		opts = opts || {};
		const isPopstate = !!opts.isPopstate;
		const replace = !!opts.replace;
		const isScrollEnd = !!opts.isScrollEnd;

		if (!targetTimestamp) return;

		// History & Hash Management
		if (!isPopstate) {
			const hash = '#time=' + targetTimestamp;
			const targetUrl = ROOT_URL + 'tv/list' + hash;
			if (window.location.hash !== hash) {
				if (replace) {
					window.history.replaceState({
						epgTime: targetTimestamp
					}, '', targetUrl);
				} else {
					window.history.pushState({
						epgTime: targetTimestamp
					}, '', targetUrl);
				}
			}
		}

		syncHeaderAndDropdowns(targetTimestamp);

		if (isScrollEnd) return;

		const FAR_THRESHOLD = 6 * 3600;
		const isOutOfBounds = targetTimestamp < (EpgStore.minLoadedTime - FAR_THRESHOLD) ||
			targetTimestamp >= (EpgStore.maxLoadedTime + FAR_THRESHOLD);

		if (isOutOfBounds) {
			UIState.suppressScrollEvents = true;
			resetGridForJump(targetTimestamp);

			fetchWindow(targetTimestamp, 'jump', function() {
				const container = document.getElementById('scrollContainer');
				if (container) container.scrollLeft = 0;
				syncTimelinePosition(0);
				syncHeaderAndDropdowns(targetTimestamp);
				prefetchAdjacentWindows(targetTimestamp);
				setTimeout(function() {
					UIState.suppressScrollEvents = false;
				}, 300);
			});
		} else {
			scrollToTimestamp(targetTimestamp, true);
		}
	}

	function jumpToCombinedDateTime(e) {
		if (e && e.preventDefault) e.preventDefault();

		const selection = getSelectedDateAndHour();
		if (!selection) return;

		const targetTimestamp = getTimestampForServerTime(
			selection.year, selection.month, selection.day, selection.hour, 0
		);
		navigateToTime(targetTimestamp);
	}

	function jumpToRelativeDay(offsetDays) {
		const selection = getSelectedDateAndHour();
		if (!selection) return;

		const currentDate = new Date(Date.UTC(selection.year, selection.month, selection.day));
		currentDate.setUTCDate(currentDate.getUTCDate() + offsetDays);

		const targetTimestamp = getTimestampForServerTime(
			currentDate.getUTCFullYear(),
			currentDate.getUTCMonth(),
			currentDate.getUTCDate(),
			selection.hour,
			0
		);

		navigateToTime(targetTimestamp);
	}

	// ==================================================================
	// 9. Network Engine
	// ==================================================================
	function fetchWindow(windowStart, direction, callback) {
		const windowKey = String(windowStart);
		if (EpgStore.loadedWindows[windowKey] || UIState.isFetching) {
			console.log('[EPG Fetch Bypassed] Window already loaded or fetch in progress:', windowStart,
				'| loaded:', !!EpgStore.loadedWindows[windowKey],
				'| isFetching:', UIState.isFetching);
			if (typeof callback === 'function') callback();
			return;
		}

		UIState.isFetching = true;
		UIState.suppressScrollEvents = true;

		const container = document.getElementById('scrollContainer');
		if (container) container.style.pointerEvents = 'none';

		updateAjaxStatus(1);

		const gridBody = document.getElementById('gridBody');
		const oldScrollLeft = container ? container.scrollLeft : 0;
		const oldMinTime = EpgStore.minLoadedTime;
		const oldTotalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
		const currentTargetTime = oldMinTime + Math.floor((oldScrollLeft / (UIState.totalWidthPx || 1)) * oldTotalSeconds);

		fetch(ROOT_URL + 'tv/list?ajax=1&start=' + encodeURIComponent(windowStart))
			.then(function(res) {
				return res.json();
			})
			.then(function(data) {
				ingestEpgData(data, windowStart);

				updateLayoutDimensions();
				renderTimeHeader();
				renderChannelGrid();

				const newTotalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;

				if (direction === 'left' && container && gridBody) {
					const addedTime = oldMinTime - EpgStore.minLoadedTime;
					const addedPx = (addedTime / newTotalSeconds) * UIState.totalWidthPx;
					const targetScrollLeft = oldScrollLeft + addedPx;

					if (window.EPG_AdjustDragAnchor) {
						window.EPG_AdjustDragAnchor(addedPx);
					}

					container.scrollLeft = targetScrollLeft;
					syncTimelinePosition(targetScrollLeft);
					UIState.lastKnownScrollLeft = targetScrollLeft;
				} else if (direction === 'right' && container && gridBody) {
					const offsetSeconds = currentTargetTime - EpgStore.minLoadedTime;
					const targetScrollLeft = Math.max(0, (offsetSeconds / newTotalSeconds) * UIState.totalWidthPx);

					container.scrollLeft = targetScrollLeft;
					syncTimelinePosition(targetScrollLeft);
					UIState.lastKnownScrollLeft = targetScrollLeft;
				}

				if (container) container.style.pointerEvents = '';
				requestEllipsisUpdate();
			})
			.catch(function(err) {
				console.error('[EPG Fetch Error]', err);
				if (container) container.style.pointerEvents = '';
			})
			.finally(function() {
				UIState.isFetching = false;
				updateAjaxStatus(-1);

				if (direction !== 'jump') {
					window.requestAnimationFrame(function() {
						window.requestAnimationFrame(function() {
							UIState.suppressScrollEvents = false;
						});
					});
				}

				if (typeof callback === 'function') {
					callback();
				}
			});
	}

	function prefetchAdjacentWindows(centerStartTime) {
		const SECONDS_PER_HOUR = 3600;
		const prevStart = EpgStore.minLoadedTime - (2 * SECONDS_PER_HOUR);
		const nextStart = EpgStore.maxLoadedTime;

		function fetchForward() {
			if (!EpgStore.loadedWindows[String(nextStart)]) {
				console.log('[EPG Cache] Pre-fetching forward window starting at:', nextStart);
				fetchWindow(nextStart, 'right');
			}
		}

		if (!EpgStore.loadedWindows[String(prevStart)]) {
			console.log('[EPG Cache] Pre-fetching 2 hours BEFORE:', prevStart);
			fetchWindow(prevStart, 'left', function() {
				setTimeout(fetchForward, 0);
			});
		} else {
			fetchForward();
		}
	}

	// ==================================================================
	// 10. Event Listeners & Drag Engine
	// ==================================================================
	function updateDragBrowsingLabel(scrollLeftPx) {
		const dragElem = document.getElementById('x_current_time_drag');
		if (!dragElem) return;

		const currentTs = pixelsToTime(scrollLeftPx);
		const dateObj = new Date(currentTs * 1000);

		const formatted = new Intl.DateTimeFormat('en-US', {
			weekday: 'short',
			month: 'short',
			day: '2-digit',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
			hour12: true,
			timeZone: SERVER_TIMEZONE
		}).format(dateObj);

		dragElem.innerText = 'Currently Browsing: ' + formatted;
	}

	function initDragScroll() {
		const container = document.getElementById('scrollContainer');
		const statusWrapper = document.getElementById('epg_time_status');
		if (!container || container.getAttribute('data-drag-init') === 'true') return;
		container.setAttribute('data-drag-init', 'true');

		let startX, scrollLeft;

		window.EPG_AdjustDragAnchor = function(addedPixels) {
			if (UIState.isDragging || startX !== undefined) {
				scrollLeft += addedPixels;
				console.log('[EPG Drag Engine] Drag anchor adjusted by +', addedPixels, 'px');
			}
		};

		container.addEventListener('mousedown', function(e) {
			UIState.isDragging = false;
			startX = e.pageX - container.offsetLeft;
			scrollLeft = container.scrollLeft;

			function onMouseMove(moveEvent) {
				if (UIState.suppressScrollEvents) return;

				const x = moveEvent.pageX - container.offsetLeft;
				const walk = (x - startX);
				if (Math.abs(walk) > 3) {
					if (!UIState.isDragging) {
						UIState.isDragging = true;
						// Add CSS class to toggle visibility
						if (statusWrapper) statusWrapper.classList.add('is-dragging');
					}

					container.scrollLeft = scrollLeft - walk;
					syncTimelinePosition(container.scrollLeft);
					hidePopup();

					updateDragBrowsingLabel(container.scrollLeft);
				}
			}

			function onMouseUp() {
				window.removeEventListener('mousemove', onMouseMove);
				window.removeEventListener('mouseup', onMouseUp);

				if (statusWrapper) statusWrapper.classList.remove('is-dragging');

				if (UIState.isDragging) {
					if (!UIState.isFetching && !UIState.suppressScrollEvents) {
						snapToNearestSlot();
					} else {
						console.warn('[EPG Drag Engine] Snap-to-slot bypassed due to active background fetch/scroll lock.');
					}

					UIState.justDragged = true;
					setTimeout(function() {
						UIState.justDragged = false;
						UIState.isDragging = false;
					}, 100);
				} else {
					UIState.isDragging = false;
				}
			}

			window.addEventListener('mousemove', onMouseMove);
			window.addEventListener('mouseup', onMouseUp);
		});
	}

	function initScrollListeners() {
		const container = document.getElementById('scrollContainer');
		if (!container) return;

		container.addEventListener('scroll', function() {
			if (UIState.suppressScrollEvents) return;

			hidePopup();
			syncTimelinePosition(container.scrollLeft);

			if (container.scrollLeft > 0) {
				UIState.lastKnownScrollLeft = container.scrollLeft;
			}

			requestEllipsisUpdate();

			if (UIState.scrollDebounceTimer) clearTimeout(UIState.scrollDebounceTimer);

			UIState.scrollDebounceTimer = setTimeout(function() {
				if (UIState.suppressScrollEvents) return;

				const scrollLeft = container.scrollLeft;
				const viewportWidth = container.clientWidth;
				const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
				const visibleStartTime = EpgStore.minLoadedTime + Math.floor((scrollLeft / UIState.totalWidthPx) * totalSeconds);
				const visibleEndTime = visibleStartTime + Math.floor((viewportWidth / UIState.totalWidthPx) * totalSeconds);

				const snappedTime = Math.round(visibleStartTime / SECONDS_PER_SLOT) * SECONDS_PER_SLOT;
				navigateToTime(snappedTime, {
					isScrollEnd: true
				});

				const needLeft = scrollLeft > 10 && visibleStartTime <= EpgStore.minLoadedTime + (4 * 3600);
				const needRight = visibleEndTime >= EpgStore.maxLoadedTime - (4 * 3600);

				if (needLeft && needRight) {
					fetchWindow(EpgStore.minLoadedTime - getWindowSeconds(), 'left', function() {
						fetchWindow(EpgStore.maxLoadedTime, 'right');
					});
				} else if (needLeft) {
					fetchWindow(EpgStore.minLoadedTime - getWindowSeconds(), 'left');
				} else if (needRight) {
					fetchWindow(EpgStore.maxLoadedTime, 'right');
				}
			}, 100);
		}, {
			passive: true
		});
	}

	// ==================================================================
	// 11. Public API & Initialization
	// ==================================================================
	window.EPG = {
		scrollToTimestamp: function(ts) {
			navigateToTime(ts);
		},
		shiftTimeHours: function(hours) {
			const container = document.getElementById('scrollContainer');
			if (!container) return;
			const currentTs = pixelsToTime(container.scrollLeft);
			navigateToTime(currentTs + (hours * 3600));
		}
	};

	document.addEventListener("DOMContentLoaded", function() {
		ingestEpgData(INITIAL_EPG_DATA, GRID_ORIGIN_TIME);

		updateLayoutDimensions();
		renderTimeHeader();
		renderChannelGrid();
		syncTimelinePosition(0);
		initScrollListeners();
		initDragScroll();
		initGridBodyDelegation();

		prefetchAdjacentWindows(GRID_ORIGIN_TIME);
		requestEllipsisUpdate();

		const hourSelect = document.getElementById('hour_select');
		const dateSelect = document.getElementById('date_select');
		const btnJumpTo = document.getElementById('btn_jumpto');
		const btnPrevDay = document.getElementById('btn_prev_day');
		const btnNextDay = document.getElementById('btn_next_day');

		if (hourSelect) hourSelect.addEventListener('change', jumpToCombinedDateTime);
		if (dateSelect) dateSelect.addEventListener('change', jumpToCombinedDateTime);
		if (btnJumpTo) btnJumpTo.addEventListener('click', jumpToCombinedDateTime);

		if (btnPrevDay) {
			btnPrevDay.addEventListener('click', function() {
				jumpToRelativeDay(-1);
			});
		}
		if (btnNextDay) {
			btnNextDay.addEventListener('click', function() {
				jumpToRelativeDay(1);
			});
		}

		window.addEventListener('popstate', function(e) {
			UIState.jumpRetryCount = 0;
			const targetTimestamp = (e.state && e.state.epgTime) || parseHashTimestamp() || GRID_ORIGIN_TIME;
			navigateToTime(targetTimestamp, {
				isPopstate: true
			});
		});

		const initialHashTime = parseHashTimestamp();
		if (initialHashTime) {
			navigateToTime(initialHashTime, {
				replace: true
			});
		} else {
			window.history.replaceState({
				epgTime: GRID_ORIGIN_TIME
			}, '', window.location.href);
			syncHeaderAndDropdowns(GRID_ORIGIN_TIME);
		}

		let resizeDebounceTimer = null;
		let isActivelyResizing = false;

		window.addEventListener('resize', function() {
			if (!isActivelyResizing) {
				isActivelyResizing = true;
				UIState.suppressScrollEvents = true;
			}

			if (resizeDebounceTimer) clearTimeout(resizeDebounceTimer);

			resizeDebounceTimer = setTimeout(function() {
				isActivelyResizing = false;

				window.requestAnimationFrame(function() {
					const container = document.getElementById('scrollContainer');
					if (!container) return;

					const currentHashTime = parseHashTimestamp();
					const targetTime = currentHashTime ? currentHashTime : pixelsToTime(container.scrollLeft);

					updateLayoutDimensions();
					renderTimeHeader();
					renderChannelGrid();

					const totalSeconds = EpgStore.maxLoadedTime - EpgStore.minLoadedTime;
					const offsetSeconds = targetTime - EpgStore.minLoadedTime;
					const targetPx = (offsetSeconds / totalSeconds) * UIState.totalWidthPx;

					container.scrollLeft = targetPx;
					syncTimelinePosition(targetPx);
					requestEllipsisUpdate();

					setTimeout(function() {
						UIState.suppressScrollEvents = false;
					}, 100);
				});
			}, 200);
		});
	});

})();