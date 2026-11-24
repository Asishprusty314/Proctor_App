<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';
adminHeader('Live Monitor', 'monitor');
?>

<style>
.live-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.live-dot{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;color:var(--success,#059669)}
.live-dot::before{content:'';width:9px;height:9px;border-radius:50%;background:currentColor;animation:livePulse 2s ease infinite}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.live-count{font-size:13px;color:var(--text-muted,#8c8579);font-weight:500;margin-left:4px}
.refresh-badge{font-size:11px;padding:5px 12px;border-radius:20px;background:rgba(5,150,105,.08);color:var(--success,#059669);font-weight:600;display:inline-flex;align-items:center;gap:5px}
.refresh-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;animation:livePulse 1.5s ease infinite}

/* SURVEILLANCE GRID */
.surv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:24px}
.surv-card{background:var(--bg-white,#fff);border:1px solid var(--border,#e0ddd7);border-radius:14px;overflow:hidden;transition:all .3s}
.surv-card:hover{box-shadow:0 8px 28px rgba(0,0,0,.06)}
.surv-card.alert-card{border-color:rgba(225,29,72,.3)}

/* LIVE FEED */
.surv-feed{position:relative;background:#1a1714;aspect-ratio:16/10;overflow:hidden}
.surv-feed img{width:100%;height:100%;object-fit:cover;transition:opacity .3s}
.surv-feed .no-feed{display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:rgba(255,255,255,.2);font-size:14px;font-weight:700;letter-spacing:2px}
.feed-overlay{position:absolute;top:0;left:0;right:0;padding:8px 12px;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(180deg,rgba(0,0,0,.6),transparent);z-index:2}
.feed-live{font-size:9px;font-weight:700;color:#fff;background:rgba(225,29,72,.9);padding:2px 8px;border-radius:10px;display:flex;align-items:center;gap:4px;text-transform:uppercase;letter-spacing:.5px}
.feed-live::before{content:'';width:5px;height:5px;border-radius:50%;background:#fff;animation:livePulse 1s ease infinite}
.feed-time{font-size:10px;color:rgba(255,255,255,.7);font-family:'JetBrains Mono',monospace}
.feed-face{position:absolute;bottom:8px;left:8px;z-index:2}
.feed-face .face-badge{padding:3px 10px;border-radius:14px;font-size:10px;font-weight:700;backdrop-filter:blur(8px)}
.face-ok{background:rgba(5,150,105,.85);color:#fff}
.face-warn{background:rgba(180,83,9,.85);color:#fff}
.face-bad{background:rgba(225,29,72,.85);color:#fff}

/* STUDENT INFO */
.surv-info{padding:14px 16px}
.surv-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.surv-name{font-size:14px;font-weight:700}
.surv-exam{font-size:11px;color:var(--text-muted,#8c8579);margin-bottom:10px}
.surv-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.surv-stat{text-align:center;padding:8px;background:var(--bg-input,#f5f7fa);border-radius:8px}
.surv-stat-val{font-size:16px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-1px}
.surv-stat-lbl{font-size:9px;color:var(--text-muted,#8c8579);font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:1px}
.surv-bar{margin-top:10px;display:flex;align-items:center;gap:8px}
.surv-bar-track{flex:1;height:5px;border-radius:3px;background:var(--bg-input,#ece9e3)}
.surv-bar-fill{height:100%;border-radius:3px;transition:width .5s}
.surv-bar-lbl{font-size:10px;font-weight:700;min-width:28px;text-align:right}
.surv-footer{padding:10px 16px;border-top:1px solid var(--border,#e0ddd7);display:flex;justify-content:space-between;align-items:center}
.surv-hb{font-size:10px;display:flex;align-items:center;gap:4px}
.surv-hb-dot{width:6px;height:6px;border-radius:50%}
.surv-ip{font-size:10px;color:var(--text-muted,#8c8579);font-family:'JetBrains Mono',monospace}

/* VIOLATION FEED */
.viol-feed{background:var(--bg-white,#fff);border:1px solid var(--border,#e0ddd7);border-radius:14px;overflow:hidden;max-height:340px}
.viol-feed-header{padding:14px 18px;border-bottom:1px solid var(--border,#e0ddd7);display:flex;justify-content:space-between;align-items:center}
.viol-feed-header h2{font-size:14px;font-weight:800}
.viol-list{overflow-y:auto;max-height:280px}
.viol-item{display:flex;gap:10px;padding:10px 18px;border-bottom:1px solid rgba(0,0,0,.03);transition:background .12s;font-size:12px}
.viol-item:hover{background:var(--bg-input,#faf9f6)}
.viol-item:last-child{border-bottom:none}
.viol-time{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted,#8c8579);min-width:55px;flex-shrink:0}
.viol-name{font-weight:700;min-width:80px;flex-shrink:0}
.viol-msg{color:var(--text-muted,#8c8579);flex:1}
.no-sessions{text-align:center;padding:60px 20px;color:var(--text-muted,#8c8579)}
.no-sessions .big-icon{font-size:48px;margin-bottom:12px;opacity:.4}
.no-sessions h3{font-size:18px;color:var(--text-secondary,#5c564d);margin-bottom:6px}
</style>

<div class="live-header">
    <div>
        <span class="live-dot" id="live-status">0 active sessions</span>
    </div>
    <span class="refresh-badge">Auto-updating every 3s</span>
</div>

<!-- Surveillance Grid - populated by JS -->
<div class="surv-grid" id="surv-grid">
    <div class="no-sessions">
        <div class="big-icon">📡</div>
        <h3>Loading...</h3>
        <p>Checking for active exam sessions</p>
    </div>
</div>

<!-- Violation Feed -->
<div class="viol-feed">
    <div class="viol-feed-header">
        <h2>Live Violation Feed</h2>
        <span class="badge badge-default" id="viol-count">0 recent</span>
    </div>
    <div class="viol-list" id="viol-list">
        <div class="viol-item" style="justify-content:center;color:var(--text-muted,#8c8579);padding:20px;">
            Waiting for violations...
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let pollInterval = null;
let previousSnapUrls = {};

async function fetchLiveData() {
    try {
        const resp = await fetch(BASE_URL + '/api/get_active_sessions.php');
        const data = await resp.json();
        if (!data.success) return;
        
        updateSurveillanceGrid(data.sessions);
        updateViolationFeed(data.violations);
        
        document.getElementById('live-status').innerHTML = 
            data.count > 0 
                ? data.count + ' active session' + (data.count > 1 ? 's' : '')
                : 'No active sessions';
    } catch (e) {
        console.warn('Fetch failed:', e);
    }
}

function updateSurveillanceGrid(sessions) {
    const grid = document.getElementById('surv-grid');
    
    if (sessions.length === 0) {
        grid.innerHTML = `
            <div class="no-sessions" style="grid-column:1/-1">
                <div class="big-icon">📡</div>
                <h3>No Active Sessions</h3>
                <p>No students are currently taking exams</p>
            </div>`;
        return;
    }
    
    let html = '';
    sessions.forEach(s => {
        const violations = parseInt(s.total_violations) || 0;
        const maxV = parseInt(s.max_violations) || 5;
        const vPct = Math.min(Math.round((violations / maxV) * 100), 100);
        const vColor = vPct > 70 ? '#e11d48' : vPct > 40 ? '#b45309' : '#059669';
        const hbAge = parseInt(s.heartbeat_age) || 999;
        const hbColor = hbAge < 10 ? '#059669' : hbAge < 30 ? '#b45309' : '#e11d48';
        const hbText = hbAge < 999 ? hbAge + 's' : 'N/A';
        
        const faceCount = s.latest_face_count !== null ? parseInt(s.latest_face_count) : null;
        let faceBadge = '';
        if (faceCount === null) faceBadge = '';
        else if (faceCount === 1) faceBadge = '<span class="face-badge face-ok">1 face</span>';
        else if (faceCount === 0) faceBadge = '<span class="face-badge face-warn">No face</span>';
        else faceBadge = '<span class="face-badge face-bad">' + faceCount + ' faces</span>';
        
        const isAlert = vPct > 60 || (faceCount !== null && faceCount !== 1);
        const snapUrl = s.snap_url;
        const snapChanged = snapUrl && previousSnapUrls[s.attempt_id] !== snapUrl;
        previousSnapUrls[s.attempt_id] = snapUrl;
        
        const initial = (s.student_name || 'S').charAt(0).toUpperCase();
        
        html += `
        <div class="surv-card ${isAlert ? 'alert-card' : ''}">
            <div class="surv-feed">
                ${snapUrl 
                    ? `<img src="${snapUrl}?t=${Date.now()}" alt="Live feed" style="${snapChanged ? 'opacity:.7' : ''}">`
                    : `<div class="no-feed">NO FEED</div>`
                }
                <div class="feed-overlay">
                    <span class="feed-live">Live</span>
                    <span class="feed-time">${new Date().toLocaleTimeString()}</span>
                </div>
                <div class="feed-face">${faceBadge}</div>
            </div>
            <div class="surv-info">
                <div class="surv-top">
                    <span class="surv-name">${escHtml(s.student_name)}</span>
                    <span class="badge ${s.cam_status === 'granted' ? 'badge-success' : 'badge-danger'}" style="font-size:9px">
                        ${s.cam_status === 'granted' ? 'Cam ON' : 'Cam OFF'}
                    </span>
                </div>
                <div class="surv-exam">${escHtml(s.exam_title)}</div>
                <div class="surv-stats">
                    <div class="surv-stat">
                        <div class="surv-stat-val">${violations}</div>
                        <div class="surv-stat-lbl">Violations</div>
                    </div>
                    <div class="surv-stat">
                        <div class="surv-stat-val">${parseInt(s.tab_switches) || 0}</div>
                        <div class="surv-stat-lbl">Tab switches</div>
                    </div>
                    <div class="surv-stat">
                        <div class="surv-stat-val">${parseInt(s.flagged_count) || 0}</div>
                        <div class="surv-stat-lbl">Flagged</div>
                    </div>
                </div>
                <div class="surv-bar">
                    <div class="surv-bar-track">
                        <div class="surv-bar-fill" style="width:${vPct}%;background:${vColor}"></div>
                    </div>
                    <span class="surv-bar-lbl" style="color:${vColor}">${violations}/${maxV}</span>
                </div>
            </div>
            <div class="surv-footer">
                <span class="surv-hb">
                    <span class="surv-hb-dot" style="background:${hbColor}"></span>
                    Heartbeat: ${hbText}
                </span>
                <span class="surv-ip">${escHtml(s.ip_address || '-')} · ${escHtml(s.browser || '-')}</span>
            </div>
        </div>`;
    });
    
    grid.innerHTML = html;
}

function updateViolationFeed(violations) {
    const list = document.getElementById('viol-list');
    const countEl = document.getElementById('viol-count');
    
    countEl.textContent = violations.length + ' recent';
    
    if (violations.length === 0) {
        list.innerHTML = '<div class="viol-item" style="justify-content:center;color:var(--text-muted,#8c8579);padding:20px;">No recent violations</div>';
        return;
    }
    
    const sevColors = {
        'low': 'badge-default',
        'medium': 'badge-warning', 
        'high': 'badge-danger',
        'critical': 'badge-danger'
    };
    
    let html = '';
    violations.forEach(v => {
        const time = new Date(v.created_at).toLocaleTimeString();
        html += `
        <div class="viol-item">
            <span class="viol-time">${time}</span>
            <span class="viol-name">${escHtml(v.student_name)}</span>
            <span class="badge badge-info" style="font-size:9px;margin-right:6px">${v.type.replace(/_/g, ' ')}</span>
            <span class="badge ${sevColors[v.severity] || 'badge-default'}" style="font-size:9px;margin-right:6px">${v.severity}</span>
            <span class="viol-msg">${escHtml(v.message || '-')}</span>
        </div>`;
    });
    
    list.innerHTML = html;
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Start polling
fetchLiveData();
pollInterval = setInterval(fetchLiveData, 3000);
</script>

<?php adminFooter(); ?>
