<?php
// Simple router for AJAX actions
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer");

$dataFile = __DIR__ . DIRECTORY_SEPARATOR . 'scores.json';
$historyFile = __DIR__ . DIRECTORY_SEPARATOR . 'history.json';
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'load') {
        header('Content-Type: application/json; charset=utf-8');
        if (!file_exists($dataFile)) {
            echo json_encode(["error" => "scores.json not found"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $json = file_get_contents($dataFile);
        if ($json === false) {
            echo json_encode(["error" => "Cannot read scores.json"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo $json;
        exit;
    }
    if ($action === 'uploadLogo') {
        if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "No file"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "Upload error"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "Only PNG/JPG/WEBP"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ext = $allowed[$mime];
        $base = preg_replace('/[^a-z0-9\-]/i', '-', pathinfo($f['name'], PATHINFO_FILENAME));
        if ($base === '') { $base = 'logo'; }
        $name = $base . '-' . substr(sha1(uniqid('', true)), 0, 8) . '.' . $ext;
        $dest = $uploadsDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "Failed to move file"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $url = 'uploads/' . $name;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["url" => $url], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'save') {
        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ok = file_put_contents($dataFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($ok === false) {
            http_response_code(500);
            echo json_encode(["error" => "Failed to write scores.json"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(["status" => "ok"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'save_result') {
        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $list = [];
        if (file_exists($historyFile)) {
            $j = file_get_contents($historyFile);
            $arr = json_decode($j, true);
            if (is_array($arr)) { $list = $arr; }
        }
        $payload['savedAt'] = date('c');
        $list[] = $payload;
        file_put_contents($historyFile, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["status" => "ok"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'list_history') {
        header('Content-Type: application/json; charset=utf-8');
        if (!file_exists($historyFile)) { echo '[]'; exit; }
        $j = file_get_contents($historyFile);
        echo $j !== false ? $j : '[]';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tỉ số bóng đá - Live</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pitch-dark: #0b3d2e;
            --pitch: #0f5b41;
            --pitch-light: #198f65;
            --accent: #ffd166;
            --panel: #0b1320;
            --text: #e8f3ef;
            --muted: #a8c3bb;
            --danger: #ff6b6b;
            --ok: #06d6a0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(180deg, rgba(0,0,0,0.55), rgba(0,0,0,0.6)),
                url('https://images.pexels.com/photos/114296/pexels-photo-114296.jpeg?auto=compress&cs=tinysrgb&h=1080') center/cover no-repeat fixed;
            color: var(--text);
            font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 24px;
            padding: 24px;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .brand .dot { width: 10px; height: 10px; background: var(--ok); border-radius: 50%; box-shadow: 0 0 12px var(--ok); }
        .container {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 24px;
        }
        @media (max-width: 960px) { .container { grid-template-columns: 1fr; } }
        .scoreboard {
            background: rgba(11, 19, 32, 0.6);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        .score-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .status {
            padding: 6px 10px;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            border: 1px solid rgba(255,255,255,0.12);
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .time { font-family: 'Roboto Condensed', monospace; font-size: 28px; letter-spacing: 1px; }
        .teams { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 16px; margin-top: 10px; }
        .team { display: grid; grid-template-columns: 56px 1fr; align-items: center; gap: 12px; }
        .team.right { direction: rtl; }
        .logo { width: 56px; height: 56px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); display: grid; place-items: center; overflow: hidden; }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .name { font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .score {
            font-family: 'Roboto Condensed', sans-serif;
            font-size: 60px;
            font-weight: 700;
            background: linear-gradient(180deg, #fff, #cde7df);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            text-shadow: 0 12px 28px rgba(0,0,0,0.35);
            min-width: 120px; text-align: center;
        }
        .controls { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 18px; }
        .panel {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 16px;
        }
        .panel h3 { margin: 0 0 10px; font-size: 14px; letter-spacing: 0.5px; color: var(--muted); text-transform: uppercase; }
        label { font-size: 13px; color: var(--muted); display: block; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(11, 19, 32, 0.5);
            color: var(--text);
            outline: none;
        }
        .btn {
            appearance: none;
            border: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.02));
            color: var(--text);
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.15s ease;
        }
        .btn:hover { transform: translateY(-1px); border-color: rgba(255,255,255,0.25); }
        .btn.primary { background: linear-gradient(180deg, var(--accent), #ffb703); color: #0b1320; border: none; font-weight: 700; }
        .btn.danger { background: linear-gradient(180deg, #ff7b7b, #ff4d4d); color: #0b1320; border: none; font-weight: 700; }
        footer { color: var(--muted); font-size: 12px; text-align: center; }
    </style>
    <script>
        const FIXED = {
            homeName: 'a CHề',
            homeLogo: 'https://i.imgur.com/m4M5tJz.jpeg',
            awayName: 'Huy Messi',
            awayLogo: 'https://i.imgur.com/2GNbDW6.jpeg'
        };
        async function loadScores() {
            const res = await fetch('?action=load', { cache: 'no-store' });
            const data = await res.json();
            if (data && !data.error) {
                document.getElementById('homeName').value = data.home.name || '';
                document.getElementById('awayName').value = data.away.name || '';
                document.getElementById('homeLogo').value = data.home.logo || '';
                document.getElementById('awayLogo').value = data.away.logo || '';
                document.getElementById('homeScore').textContent = Number(data.home.score || 0);
                document.getElementById('awayScore').textContent = Number(data.away.score || 0);
                if (document.getElementById('homeScoreInput')) document.getElementById('homeScoreInput').value = Number(data.home.score || 0);
                if (document.getElementById('awayScoreInput')) document.getElementById('awayScoreInput').value = Number(data.away.score || 0);
                applyFixed();
                syncPreview();
            }
        }
        function applyFixed() {
            const homeNameEl = document.getElementById('homeName');
            const awayNameEl = document.getElementById('awayName');
            const homeLogoEl = document.getElementById('homeLogo');
            const awayLogoEl = document.getElementById('awayLogo');
            homeNameEl.value = FIXED.homeName; homeNameEl.disabled = true;
            awayNameEl.value = FIXED.awayName; awayNameEl.disabled = true;
            homeLogoEl.value = FIXED.homeLogo; homeLogoEl.disabled = true;
            awayLogoEl.value = FIXED.awayLogo; awayLogoEl.disabled = true;
            const homeBtnWrap = homeLogoEl.nextElementSibling; if (homeBtnWrap) homeBtnWrap.style.display = 'none';
            const awayBtnWrap = awayLogoEl.nextElementSibling; if (awayBtnWrap) awayBtnWrap.style.display = 'none';
        }
        function syncPreview() {
            const homeName = FIXED.homeName;
            const awayName = FIXED.awayName;
            const homeLogo = FIXED.homeLogo;
            const awayLogo = FIXED.awayLogo;
            document.getElementById('homeNamePreview').textContent = homeName;
            document.getElementById('awayNamePreview').textContent = awayName;
            const hl = document.getElementById('homeLogoImg');
            const al = document.getElementById('awayLogoImg');
            hl.src = homeLogo;
            al.src = awayLogo;
            const hsi = document.getElementById('homeScoreInput');
            const asi = document.getElementById('awayScoreInput');
            if (hsi && asi) {
                document.getElementById('homeScore').textContent = Number(hsi.value || 0);
                document.getElementById('awayScore').textContent = Number(asi.value || 0);
            }
        }
        async function saveScores() {
            const payload = {
                home: {
                    name: document.getElementById('homeName').value.trim(),
                    logo: document.getElementById('homeLogo').value.trim(),
                    score: Number(document.getElementById('homeScoreInput') ? document.getElementById('homeScoreInput').value : document.getElementById('homeScore').textContent)
                },
                away: {
                    name: document.getElementById('awayName').value.trim(),
                    logo: document.getElementById('awayLogo').value.trim(),
                    score: Number(document.getElementById('awayScoreInput') ? document.getElementById('awayScoreInput').value : document.getElementById('awayScore').textContent)
                }
            };
            const res = await fetch('?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.status === 'ok') {
                toast('Đã lưu tỉ số');
            } else {
                toast('Lỗi lưu dữ liệu', true);
            }
        }
        async function saveResult() {
            const payload = {
                home: {
                    name: document.getElementById('homeName').value.trim(),
                    logo: document.getElementById('homeLogo').value.trim(),
                    score: Number(document.getElementById('homeScoreInput') ? document.getElementById('homeScoreInput').value : document.getElementById('homeScore').textContent)
                },
                away: {
                    name: document.getElementById('awayName').value.trim(),
                    logo: document.getElementById('awayLogo').value.trim(),
                    score: Number(document.getElementById('awayScoreInput') ? document.getElementById('awayScoreInput').value : document.getElementById('awayScore').textContent)
                }
            };
            const res = await fetch('?action=save_result', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.status === 'ok') { toast('Đã lưu vào lịch sử'); listHistory(); }
            else { toast('Lỗi lưu lịch sử', true); }
        }
        async function listHistory() {
            const res = await fetch('?action=list_history', { cache: 'no-store' });
            const arr = await res.json();
            const wrap = document.getElementById('history');
            if (!wrap) return;
            wrap.innerHTML = '';
            arr.slice(-10).reverse().forEach(item => {
                const row = document.createElement('div');
                row.style.display = 'grid';
                row.style.gridTemplateColumns = '1fr auto 1fr';
                row.style.alignItems = 'center';
                row.style.gap = '8px';
                row.style.padding = '6px 0';
                row.innerHTML = `<span>${item.home.name} <b>${item.home.score}</b></span><span style=\"opacity:.7\">${new Date(item.savedAt||'').toLocaleString()}</span><span style=\"text-align:right\"><b>${item.away.score}</b> ${item.away.name}</span>`;
                wrap.appendChild(row);
            });
        }
        async function uploadLogo(side) {
            const input = document.getElementById(side + 'LogoFile');
            if (!input || !input.files || !input.files[0]) return;
            const fd = new FormData();
            fd.append('file', input.files[0]);
            const res = await fetch('?action=uploadLogo', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.url) { document.getElementById(side + 'Logo').value = data.url; syncPreview(); toast('Tải ảnh logo thành công'); }
            else { toast('Tải ảnh thất bại', true); }
            input.value = '';
        }
        function toast(msg, danger) {
            const el = document.createElement('div');
            el.textContent = msg;
            el.style.position = 'fixed';
            el.style.bottom = '20px';
            el.style.right = '20px';
            el.style.padding = '10px 14px';
            el.style.borderRadius = '10px';
            el.style.background = danger ? '#ff4d4d' : '#06d6a0';
            el.style.color = '#0b1320';
            el.style.fontWeight = '700';
            el.style.boxShadow = '0 10px 24px rgba(0,0,0,.25)';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 1600);
        }
        function inc(id, delta) {
            const el = document.getElementById(id === 'homeScore' ? 'homeScoreInput' : 'awayScoreInput');
            if (!el) return;
            const v = Math.max(0, Number(el.value) + delta);
            el.value = v;
            syncPreview();
        }
        document.addEventListener('DOMContentLoaded', () => {
            ['homeName','awayName','homeLogo','awayLogo'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', syncPreview);
            });
            ['homeScoreInput','awayScoreInput'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', syncPreview);
            });
            loadScores();
            listHistory();
        });
    </script>
</head>
<body>
    <header>
        <div class="brand"><span class="dot"></span> Live Football Board</div>
    </header>

    <div class="container">
        <section class="scoreboard">
            <div class="score-header">
                <div class="status">Giải đấu: <span id="league">Giao hữu</span></div>
            </div>
            <div class="teams">
                <div class="team">
                    <div class="logo"><img id="homeLogoImg" alt="home logo"></div>
                    <div class="name" id="homeNamePreview">Đội nhà</div>
                </div>
                <div class="score" id="homeScore">0</div>
                <div></div>
                <div></div>
                <div class="score" id="awayScore">0</div>
                <div class="team right">
                    <div class="logo"><img id="awayLogoImg" alt="away logo"></div>
                    <div class="name" id="awayNamePreview">Đội khách</div>
                </div>
            </div>
        </section>

        <aside class="panel">
            <h3>Điều khiển</h3>
            <div class="controls">
                <div>
                    <label>Tên đội nhà</label>
                    <input id="homeName" placeholder="Ví dụ: Vietnam">
                </div>
                <div>
                    <label>Tên đội khách</label>
                    <input id="awayName" placeholder="Ví dụ: Thailand">
                </div>
                <div>
                    <label>Logo đội nhà (URL hoặc tải lên)</label>
                    <input id="homeLogo" placeholder="https://...png">
                    <input id="homeLogoFile" type="file" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="uploadLogo('home')">
                    <div style="margin-top:6px"><button class="btn" onclick="document.getElementById('homeLogoFile').click()">Chọn ảnh đội nhà</button></div>
                </div>
                <div>
                    <label>Logo đội khách (URL hoặc tải lên)</label>
                    <input id="awayLogo" placeholder="https://...png">
                    <input id="awayLogoFile" type="file" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="uploadLogo('away')">
                    <div style="margin-top:6px"><button class="btn" onclick="document.getElementById('awayLogoFile').click()">Chọn ảnh đội khách</button></div>
                </div>
                
                <div>
                    <label>Tỉ số đội nhà</label>
                    <input id="homeScoreInput" type="number" min="0" value="0">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button class="btn" onclick="inc('homeScore', -1)">-1</button>
                        <button class="btn primary" onclick="inc('homeScore', +1)">+1</button>
                    </div>
                </div>
                <div>
                    <label>Tỉ số đội khách</label>
                    <input id="awayScoreInput" type="number" min="0" value="0">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button class="btn" onclick="inc('awayScore', -1)">-1</button>
                        <button class="btn primary" onclick="inc('awayScore', +1)">+1</button>
                    </div>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button class="btn primary" onclick="saveScores()">Lưu tỉ số</button>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button class="btn" onclick="loadScores()">Tải lại</button>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button class="btn danger" onclick="document.getElementById('homeScoreInput').value=0;document.getElementById('awayScoreInput').value=0;syncPreview();">Reset tỉ số</button>
                </div>
                <div style="grid-column: 1 / -1; margin-top:4px;">
                    <button class="btn" onclick="saveResult()">Lưu kết quả vào lịch sử</button>
                </div>
                <div style="grid-column: 1 / -1;">
                    <h3>Lịch sử gần đây</h3>
                    <div id="history"></div>
                </div>
            </div>
        </aside>
    </div>

    <footer>
        Made for a simple single-screen scoreboard. Lưu ý: dữ liệu lưu trong file scores.json.
    </footer>
</body>
</html>


