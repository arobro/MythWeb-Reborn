(function() {
    function ensureDebugModalDOM() {
        if (document.getElementById('epg_debug_modal')) return;

        const modal = document.createElement('div');
        modal.id = 'epg_debug_modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:10000; display:none; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,monospace,sans-serif;';

        modal.innerHTML = `
            <div style="background:#181818; color:#e0e0e0; border:1px solid #333; border-radius:8px; padding:20px; width:600px; max-width:92vw; max-height:85vh; overflow-y:auto; box-shadow:0 10px 30px rgba(0,0,0,0.7);">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; padding-bottom:10px; margin-bottom:15px;">
                    <h3 style="margin:0; font-size:16px; font-weight:600; color:#4caf50; letter-spacing:0.5px;">EPG Cache Debug Matrix (&plusmn;16 Hours)</h3>
                    <span id="epg_debug_close_btn" style="cursor:pointer; font-size:22px; font-weight:bold; color:#888; line-height:1;">&times;</span>
                </div>
                <div id="epg_debug_body"></div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('epg_debug_close_btn').addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    function renderDebugTable() {
        const modalBody = document.getElementById('epg_debug_body');
        const container = document.getElementById('scrollContainer');

        if (!modalBody) return;

        if (!window.EPG || typeof window.EPG.getStore !== 'function') {
            modalBody.innerHTML = '<div style="color:#ff5252; padding:12px;">' +
                '<strong>Debug Error:</strong> <code>window.EPG.getStore</code> is not exposed in <code>epg.js</code>.' +
                '</div>';
            return;
        }

        const EpgStore = window.EPG.getStore();
        const getWindowSeconds = typeof window.EPG.getWindowSeconds === 'function' ? window.EPG.getWindowSeconds() : 14400;
        const currentTs = (container && typeof window.EPG.pixelsToTime === 'function')
            ? window.EPG.pixelsToTime(container.scrollLeft)
            : EpgStore.minLoadedTime;

        const currentHourTs = Math.floor(currentTs / 3600) * 3600;

        let html = '<table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px; font-family:monospace;">';
        html += '<thead><tr>' +
                    '<th style="padding:8px 12px; border:1px solid #2a2a2a; background:#222; color:#999; font-weight:600; text-transform:uppercase; font-size:11px;">Offset</th>' +
                    '<th style="padding:8px 12px; border:1px solid #2a2a2a; background:#222; color:#999; font-weight:600; text-transform:uppercase; font-size:11px;">Time Slot</th>' +
                    '<th style="padding:8px 12px; border:1px solid #2a2a2a; background:#222; color:#999; font-weight:600; text-transform:uppercase; font-size:11px;">Ingested</th>' +
                    '<th style="padding:8px 12px; border:1px solid #2a2a2a; background:#222; color:#999; font-weight:600; text-transform:uppercase; font-size:11px;">Cached</th>' +
                    '<th style="padding:8px 12px; border:1px solid #2a2a2a; background:#222; color:#999; font-weight:600; text-transform:uppercase; font-size:11px;">Status</th>' +
                '</tr></thead><tbody>';

        for (let offset = -16; offset <= 16; offset++) {
            const slotStart = currentHourTs + (offset * 3600);
            const slotEnd = slotStart + 3600;
            const isCurrent = offset === 0;

            const isWindowLoaded = Object.keys(EpgStore.loadedWindows || {}).some(function(winTs) {
                const wStart = parseInt(winTs, 10);
                const wEnd = wStart + getWindowSeconds;
                return slotStart >= wStart && slotStart < wEnd;
            });

            let programCount = 0;
            Object.keys(EpgStore.channels || {}).forEach(function(chanId) {
                const progs = EpgStore.channels[chanId].programmes || {};
                Object.keys(progs).forEach(function(pKey) {
                    const prog = progs[pKey];
                    if (prog.start >= slotStart && prog.start < slotEnd) {
                        programCount++;
                    }
                });
            });

            const slotDate = new Date(slotStart * 1000);
            const timeStr = slotDate.toLocaleDateString([], { month: 'numeric', day: 'numeric' }) + ' ' +
                            slotDate.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });

            const isCached = isWindowLoaded || programCount > 0;
            const statusLabel = isCached ? 'CACHED' : 'MISSING';
            const pillStyle = isCached
                ? 'background:#1b5e20; color:#a5d6a7; border:1px solid #2e7d32;'
                : 'background:#b71c1c; color:#ef9a9a; border:1px solid #c62828;';

            const rowStyle = isCurrent
                ? 'background:rgba(76, 175, 80, 0.15); font-weight:bold; color:#fff;'
                : '';

            html += `<tr style="${rowStyle}">` +
                        `<td style="padding:8px 12px; border:1px solid #2a2a2a;">${offset > 0 ? '+' + offset : offset}h${isCurrent ? ' (Now)' : ''}</td>` +
                        `<td style="padding:8px 12px; border:1px solid #2a2a2a;">${timeStr}</td>` +
                        `<td style="padding:8px 12px; border:1px solid #2a2a2a;">${isWindowLoaded ? 'Yes' : 'No'}</td>` +
                        `<td style="padding:8px 12px; border:1px solid #2a2a2a;">${programCount} progs</td>` +
                        `<td style="padding:8px 12px; border:1px solid #2a2a2a;"><span style="display:inline-block; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:11px; ${pillStyle}">${statusLabel}</span></td>` +
                    '</tr>';
        }

        html += '</tbody></table>';
        modalBody.innerHTML = html;
    }

    function toggleDebugModal() {
        ensureDebugModalDOM();
        const modal = document.getElementById('epg_debug_modal');
        if (!modal) return;

        if (modal.style.display === 'none' || modal.style.display === '') {
            renderDebugTable();
            modal.style.display = 'flex';
        } else {
            modal.style.display = 'none';
        }
    }

    window.addEventListener('keydown', function(e) {
        const isCtrlD = (e.ctrlKey || e.metaKey) && (e.key === 'd' || e.key === 'D' || e.code === 'KeyD' || e.keyCode === 68);
        if (!isCtrlD) return;

        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        if (activeTag === 'input' || activeTag === 'select' || activeTag === 'textarea' || document.activeElement.isContentEditable) {
            return;
        }

        e.preventDefault();
        toggleDebugModal();
    }, true);

    window.toggleEPGDebug = toggleDebugModal;
})();