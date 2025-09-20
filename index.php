<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'libs/telegram.php';

session_start();
$admin_password = 'admin123'; // Change this!

if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['password'] === $admin_password) {
        $_SESSION['admin'] = true;
        sendTelegramMessage("Admin logged in from IP: ".$_SERVER['REMOTE_ADDR']);
    } else {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Admin Login</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%); min-height:100vh; display:flex; align-items:center; justify-content:center;}
                .login-card { background: #fff; padding: 2rem; border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(31,38,135,.37);}
            </style>
        </head>
        <body>
            <div class="login-card">
                <h3 class="mb-3 text-center">Admin Login</h3>
                <form method="post">
                    <input type="password" name="password" class="form-control mb-2" placeholder="Admin Password" required>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </body>
        </html>';
        exit;
    }
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'block_ip' && $_POST['ip']) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_ips (ip) VALUES (?)");
        $stmt->execute([$_POST['ip']]);
        sendTelegramMessage("Admin blocked IP: ".$_POST['ip']);
    }
    if ($_POST['action'] === 'revoke_user' && $_POST['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET revoked=1 WHERE user_id=?");
        $stmt->execute([$_POST['user_id']]);
        sendTelegramMessage("Admin revoked user: ".$_POST['user_id']);
    }
    if ($_POST['action'] === 'revoke_session' && $_POST['session_id']) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO revoked_sessions (session_id) VALUES (?)");
        $stmt->execute([$_POST['session_id']]);
        sendTelegramMessage("Admin revoked session: ".$_POST['session_id']);
    }
    if ($_POST['action'] === 'whitelist_ip' && $_POST['ip']) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO whitelisted_ips (ip) VALUES (?)");
        $stmt->execute([$_POST['ip']]);
        sendTelegramMessage("Admin whitelisted IP: ".$_POST['ip']);
    }
    if ($_POST['action'] === 'delete_whitelist_ip' && $_POST['ip']) {
        $stmt = $pdo->prepare("DELETE FROM whitelisted_ips WHERE ip=?");
        $stmt->execute([$_POST['ip']]);
        sendTelegramMessage("Admin deleted whitelisted IP: ".$_POST['ip']);
    }
    if ($_POST['action'] === 'delete_log' && $_POST['log_id']) {
        $stmt = $pdo->prepare("DELETE FROM access_logs WHERE id=?");
        $stmt->execute([$_POST['log_id']]);
        sendTelegramMessage("Admin deleted access log: ".$_POST['log_id']);
    }
    if ($_POST['action'] === 'delete_session' && $_POST['session_id']) {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id=?");
        $stmt->execute([$_POST['session_id']]);
        sendTelegramMessage("Admin deleted session: ".$_POST['session_id']);
    }
    if ($_POST['action'] === 'delete_all_whitelist_ips') {
        $pdo->exec("DELETE FROM whitelisted_ips");
        sendTelegramMessage("Admin deleted all whitelisted IPs");
    }
    if ($_POST['action'] === 'delete_all_blocked_ips') {
        $pdo->exec("DELETE FROM blocked_ips");
        sendTelegramMessage("Admin deleted all blocked IPs");
    }
    if ($_POST['action'] === 'delete_all_sessions') {
        $pdo->exec("DELETE FROM sessions");
        sendTelegramMessage("Admin deleted all sessions");
    }
    if ($_POST['action'] === 'delete_all_logs') {
        $pdo->exec("DELETE FROM access_logs");
        sendTelegramMessage("Admin deleted all access logs");
    }
}

// Fetch recent logs
$stmt = $pdo->query("SELECT * FROM access_logs ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent sessions
$stmt = $pdo->query("SELECT * FROM sessions ORDER BY session_id DESC LIMIT 20");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch whitelisted IPs
try {
    $stmt = $pdo->query("SELECT * FROM whitelisted_ips ORDER BY ip ASC");
    $whitelisted = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist, create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS whitelisted_ips (ip VARCHAR(45) PRIMARY KEY)");
    $whitelisted = [];
}

// Fetch live sessions (last 10 minutes)
$live_sessions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE created_at >= (NOW() - INTERVAL 10 MINUTE) ORDER BY created_at DESC");
    $stmt->execute();
    $live_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If created_at column doesn't exist, fallback to all sessions
    $live_sessions = $sessions;
}

// Fetch blocked IPs count
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM blocked_ips");
$blocked_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// AJAX endpoint for live data
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // Prepare HTML for all sections
    ob_start();
    ?>
    <!-- Whitelisted IPs -->
    <form method="post" class="mb-2">
        <input type="hidden" name="action" value="delete_all_whitelist_ips">
        <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Whitelisted IPs</button>
    </form>
    <?php if (count($whitelisted)): ?>
        <div class="mt-3">
            <strong>Whitelisted IPs:</strong>
            <ul class="list-group list-group-flush">
                <?php foreach ($whitelisted as $w): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($w['ip']); ?>
                        <form method="post" class="ms-2">
                            <input type="hidden" name="action" value="delete_whitelist_ip">
                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($w['ip']); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Blocked IPs -->
    <form method="post" class="mb-2">
        <input type="hidden" name="action" value="delete_all_blocked_ips">
        <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Blocked IPs</button>
    </form>
    <div class="mb-2">
        <strong>Blocked IPs: <?php echo $blocked_count; ?></strong>
    </div>

    <!-- Recent Sessions -->
    <form method="post" class="mb-2">
        <input type="hidden" name="action" value="delete_all_sessions">
        <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Sessions</button>
    </form>
    <?php if (count($sessions) === 0): ?>
        <p class="p-3">No sessions found.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0">
            <thead>
                <tr>
                    <th>Session ID</th>
                    <th>User ID</th>
                    <th>Created At</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?php echo htmlspecialchars($session['session_id']); ?></td>
                    <td><?php echo htmlspecialchars($session['user_id']); ?></td>
                    <td><?php echo isset($session['created_at']) ? htmlspecialchars($session['created_at']) : ''; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_session">
                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <!-- Live Sessions -->
    <?php if (count($live_sessions) === 0): ?>
        <p class="p-3">No live sessions found.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0">
            <thead>
                <tr>
                    <th>Session ID</th>
                    <th>User ID</th>
                    <th>Created At</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($live_sessions as $session): ?>
                <tr>
                    <td><?php echo htmlspecialchars($session['session_id']); ?></td>
                    <td><?php echo htmlspecialchars($session['user_id']); ?></td>
                    <td><?php echo isset($session['created_at']) ? htmlspecialchars($session['created_at']) : ''; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_session">
                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <!-- Recent Access Logs -->
    <form method="post" class="mb-2">
        <input type="hidden" name="action" value="delete_all_logs">
        <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Logs</button>
    </form>
    <?php if (count($logs) === 0): ?>
        <p class="p-3">No access logs found.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Session ID</th>
                    <th>IP</th>
                    <th>Page</th>
                    <th>UA</th>
                    <th>Suspicious</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($log['session_id']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip']); ?></td>
                    <td><?php echo htmlspecialchars($log['page']); ?></td>
                    <td><?php echo htmlspecialchars($log['ua']); ?></td>
                    <td><?php echo ($log['suspicious'] ? '<span class="badge bg-danger">Yes</span>' : ''); ?></td>
                    <td class="table-actions">
                        <form method="post">
                            <input type="hidden" name="action" value="block_ip">
                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($log['ip']); ?>">
                            <button type="submit" class="btn btn-sm btn-danger mb-1">Block IP</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="revoke_user">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($log['user_id']); ?>">
                            <button type="submit" class="btn btn-sm btn-warning mb-1">Revoke User</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($log['session_id']); ?>">
                            <button type="submit" class="btn btn-sm btn-secondary mb-1">Revoke Session</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_log">
                            <input type="hidden" name="log_id" value="<?php echo htmlspecialchars($log['id']); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger mb-1">Delete Log</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif;
    $html = ob_get_clean();
    echo $html;
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%); min-height:100vh;}
        .container { margin-top: 40px; }
        .card { box-shadow: 0 8px 32px 0 rgba(31,38,135,.15);}
        .table th, .table td { vertical-align: middle; }
        .table-actions form { display:inline; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4 text-center text-primary">Security Admin Panel</h1>
    <div class="mb-4 text-center">
        <form method="post" class="d-inline me-2">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Logout</button>
        </form>
        <span class="badge bg-danger fs-5">Blocked IPs: <?php echo $blocked_count; ?></span>
        <form method="post" class="d-inline ms-2">
            <input type="hidden" name="action" value="delete_all_blocked_ips">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Blocked IPs</button>
        </form>
        <form method="post" class="d-inline ms-2">
            <input type="hidden" name="action" value="delete_all_sessions">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Sessions</button>
        </form>
        <form method="post" class="d-inline ms-2">
            <input type="hidden" name="action" value="delete_all_logs">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Logs</button>
        </form>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">Whitelist IP</div>
                <div class="card-body">
                    <form method="post" class="row g-2" id="whitelist-form">
                        <input type="hidden" name="action" value="whitelist_ip">
                        <div class="col-8">
                            <input type="text" name="ip" class="form-control" placeholder="Enter IP to whitelist" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-success w-100">Whitelist</button>
                        </div>
                    </form>
                    <div id="whitelisted-ips">
                        <div class="text-center my-2"><div class="spinner-border text-success"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">Recent Sessions</div>
                <div class="card-body p-0" id="recent-sessions">
                    <div class="text-center my-2"><div class="spinner-border text-info"></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">Live Sessions (last 10 min)</div>
                <div class="card-body p-0" id="live-sessions">
                    <div class="text-center my-2"><div class="spinner-border text-warning"></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Recent Access Logs</div>
        <div class="card-body p-0" id="access-logs">
            <div class="text-center my-2"><div class="spinner-border text-primary"></div></div>
        </div>
    </div>
</div>
<script>
function loadAdminData() {
    fetch('?ajax=1')
        .then(res => res.text())
        .then(html => {
            // Split HTML into sections using DOM
            var temp = document.createElement('div');
            temp.innerHTML = html;

            // Whitelisted IPs
            var wl = temp.querySelector('.mt-3');
            document.getElementById('whitelisted-ips').innerHTML = wl ? wl.outerHTML : '';

            // Recent Sessions
            var rs = temp.querySelector('.table-responsive:nth-of-type(1)');
            document.getElementById('recent-sessions').innerHTML = rs ? rs.outerHTML : temp.querySelector('p.p-3') ? temp.querySelector('p.p-3').outerHTML : '';

            // Live Sessions
            var ls = temp.querySelector('.table-responsive:nth-of-type(2)');
            document.getElementById('live-sessions').innerHTML = ls ? ls.outerHTML : temp.querySelectorAll('p.p-3')[1] ? temp.querySelectorAll('p.p-3')[1].outerHTML : '';

            // Access Logs
            var al = temp.querySelector('.table-responsive:nth-of-type(3)');
            document.getElementById('access-logs').innerHTML = al ? al.outerHTML : temp.querySelectorAll('p.p-3')[2] ? temp.querySelectorAll('p.p-3')[2].outerHTML : '';
        });
}
// Initial load
loadAdminData();
// Auto-refresh every 10 seconds
setInterval(loadAdminData, 10000);
</script>
</body>
</html>
