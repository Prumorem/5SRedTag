<?php
// dashboard.php โ€” 5S Red Tag Dashboard
$dataDir = 'data';
$csvFile = $dataDir . '/tags.csv';

// Read tags directly from CSV for server-side rendering speed
$tags = [];
if (file_exists($csvFile) && ($handle = fopen($csvFile, 'r')) !== false) {
    if (flock($handle, LOCK_SH)) {
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $len = count($row);
            if ($len < 8)
                continue;
            $is_deleted = ($len >= 12) ? trim((string) $row[11]) : '0';
            if ($is_deleted !== '0' && $is_deleted !== '') {
                continue;
            }

            $tags[] = [
                'id' => $row[0],
                'zone' => ($len >= 10) ? $row[9] : '',
                'status' => $row[6],
                'category' => ($len >= 15) ? $row[14] : '',
                'pic' => ($len >= 14) ? $row[13] : '',
                'factory' => ($len >= 16) ? $row[15] : '',
                'productionLine' => $row[3],
                'image' => $row[7],
                'imageAfter' => ($len >= 9) ? $row[8] : '',
                'description' => $row[4],
                'solution' => $row[5],
                'createdAt' => ($len >= 11) ? $row[10] : '',
                'updatedAt' => ($len >= 13) ? $row[12] : '',
            ];
        }
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

// ---- Aggregate data ----
$statusCounts = ['Open' => 0, 'In Progress' => 0, 'Need help' => 0, 'Closed' => 0];
$zoneCounts = [];
$categoryCounts = [];
$dailyData = [];  // date => count

foreach ($tags as $t) {
    // Status
    $s = $t['status'];
    if (isset($statusCounts[$s]))
        $statusCounts[$s]++;
    else
        $statusCounts[$s] = 1;

    // Zone
    $z = $t['zone'] !== '' ? 'Zone ' . $t['zone'] : 'Unknown';
    $zoneCounts[$z] = ($zoneCounts[$z] ?? 0) + 1;

    // Category (short label)
    $cat = $t['category'];
    if ($cat === '')
        $cat = 'Uncategorised';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;

    // Daily trend (total)
    if ($t['createdAt']) {
        $day = substr($t['createdAt'], 0, 10);
        $dailyData[$day] = ($dailyData[$day] ?? 0) + 1;
        // Per-status daily
        $dailyStatusData[$day][$t['status']] = ($dailyStatusData[$day][$t['status']] ?? 0) + 1;
    }
}

ksort($zoneCounts, SORT_NATURAL);
ksort($dailyData);
ksort($dailyStatusData);

// Zone ร— Status breakdown
$zoneStatus = [];
foreach ($tags as $t) {
    $z = $t['zone'] !== '' ? 'Zone ' . $t['zone'] : 'Unknown';
    $s = $t['status'];
    $zoneStatus[$z][$s] = ($zoneStatus[$z][$s] ?? 0) + 1;
}
ksort($zoneStatus, SORT_NATURAL);

// Zone ร— Category breakdown
$zoneCategory = [];
foreach ($tags as $t) {
    $z = $t['zone'] !== '' ? 'Zone ' . $t['zone'] : 'Unknown';
    $c = $t['category'] !== '' ? $t['category'] : 'Uncategorised';
    $zoneCategory[$z][$c] = ($zoneCategory[$z][$c] ?? 0) + 1;
}
ksort($zoneCategory, SORT_NATURAL);

$total = count($tags);

// Safely JSON-encode for JS
function jse($v)
{
    return json_encode($v, JSON_UNESCAPED_UNICODE);
}

// All tags sorted by updatedAt desc, fallback to createdAt desc
$recent = $tags;
usort($recent, function ($a, $b) {
    $ua = $a['updatedAt'] ?: $a['createdAt'];
    $ub = $b['updatedAt'] ?: $b['createdAt'];
    if ($ua !== $ub)
        return strcmp($ub, $ua);
    return (int) $b['id'] - (int) $a['id'];
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard &mdash; 5S Red Tag</title>
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23DC2626%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><path d=%22M3 3h18v18H3z%22/><path d=%22M8 12h8M12 8v8%22/></svg>">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="js/chart.umd.min.js"></script>

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface2: #273549;
            --border: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #ef4444;
            --accent2: #f97316;
            --green: #22c55e;
            --blue: #3b82f6;
            --yellow: #eab308;
            --purple: #a855f7;
            --radius: 12px;
            --shadow: 0 4px 24px rgba(0, 0, 0, .4);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ---- TOP NAV ---- */
        .topnav {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .85rem 1.5rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .4);
        }

        .topnav-logo {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
        }

        .topnav-logo .badge {
            background: var(--accent);
            border-radius: 6px;
            padding: 3px 8px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: #fff;
            text-transform: uppercase;
        }

        .topnav-spacer {
            flex: 1;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem 1.1rem;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: var(--surface2);
            transition: all .2s;
        }

        .btn-nav:hover {
            background: var(--border);
        }

        .btn-nav.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .btn-nav svg {
            width: 16px;
            height: 16px;
        }

        /* ---- MAIN LAYOUT ---- */
        .page {
            padding: 1.5rem;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ---- PAGE TITLE ---- */
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: .25rem;
        }

        .page-sub {
            color: var(--muted);
            font-size: .875rem;
            margin-bottom: 1.5rem;
        }

        /* ---- KPI CARDS ---- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem 1.2rem 1.1rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, .5);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--card-accent, var(--accent));
            border-radius: var(--radius) 0 0 var(--radius);
        }

        .kpi-label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .5rem;
        }

        .kpi-value {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
            color: var(--text);
        }

        .kpi-sub {
            font-size: .75rem;
            color: var(--muted);
            margin-top: .35rem;
        }

        .kpi-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            opacity: .15;
            font-size: 3rem;
            line-height: 1;
        }

        /* ---- CHART ROW ---- */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem;
            box-shadow: var(--shadow);
        }

        .chart-title {
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .chart-title svg {
            width: 16px;
            height: 16px;
            opacity: .7;
        }

        .chart-wrap {
            position: relative;
            height: 220px;
        }

        .chart-wrap canvas {
            display: block;
        }

        /* ---- WIDE CHART ---- */
        .chart-full {
            grid-column: 1 / -1;
        }

        /* ---- TABLE ---- */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .table-header {
            padding: 1rem 1.2rem;
            font-size: .9rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
        }

        thead tr {
            background: var(--surface2);
        }

        thead th {
            padding: .7rem 1rem;
            text-align: left;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: .72rem;
            letter-spacing: .05em;
        }

        tbody tr {
            border-top: 1px solid var(--border);
            transition: background .15s;
        }

        tbody tr:hover {
            background: var(--surface2);
        }

        tbody td {
            padding: .65rem 1rem;
            color: var(--text);
            vertical-align: middle;
        }

        .td-muted {
            color: var(--muted);
        }

        /* ---- STATUS BADGE ---- */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .2rem .65rem;
            border-radius: 99px;
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-status::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .s-Open {
            background: rgba(239, 68, 68, .15);
            color: #f87171;
        }

        .s-In_Progress {
            background: rgba(59, 130, 246, .15);
            color: #60a5fa;
        }

        .s-Need_help {
            background: rgba(234, 179, 8, .15);
            color: #facc15;
        }

        .s-Closed {
            background: rgba(34, 197, 94, .15);
            color: #4ade80;
        }

        /* ---- SORTABLE TABLE HEADERS ---- */
        .sortable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            position: relative;
            padding-right: 1.4rem !important;
            transition: color .15s;
        }

        .sortable:hover {
            color: var(--text);
        }

        .sortable::after {
            content: '\2195';
            /* up-down arrow */
            position: absolute;
            right: .4rem;
            opacity: .35;
            font-size: .75rem;
        }

        .th-active {
            color: #fff !important;
        }

        .th-active.th-asc::after {
            content: '\2191';
            opacity: 1;
        }

        .th-active.th-desc::after {
            content: '\2193';
            opacity: 1;
        }

        /* ---- SEARCH BOX ---- */
        .search-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-left: auto;
        }

        .search-input {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: .82rem;
            padding: .38rem .75rem .38rem 2rem;
            outline: none;
            width: 220px;
            transition: border-color .2s, width .25s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: .55rem center;
        }

        .search-input:focus {
            border-color: var(--accent);
            width: 280px;
        }

        .search-input::placeholder {
            color: var(--muted);
        }

        .search-clear {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1;
            padding: 0 .2rem;
            display: none;
        }

        .search-clear:hover {
            color: var(--text);
        }

        .search-count {
            font-size: .75rem;
            color: var(--muted);
            white-space: nowrap;
        }

        /* ---- FOOTER ---- */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--muted);
            font-size: .78rem;
        }

        /* ---- RESPONSIVE ---- */
        @media(max-width: 640px) {
            .page {
                padding: 1rem;
            }

            .kpi-value {
                font-size: 1.7rem;
            }

            .topnav {
                padding: .7rem 1rem;
            }
        }

        /* ---- ANIMATE ---- */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .kpi-card,
        .chart-card,
        .table-card {
            animation: fadeUp .4s ease both;
        }

        .kpi-card:nth-child(1) {
            animation-delay: .05s;
        }

        .kpi-card:nth-child(2) {
            animation-delay: .10s;
        }

        .kpi-card:nth-child(3) {
            animation-delay: .15s;
        }

        .kpi-card:nth-child(4) {
            animation-delay: .20s;
        }

        .kpi-card:nth-child(5) {
            animation-delay: .25s;
        }

        /* ---- GLOBAL FILTER BAR ---- */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .6rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .7rem 1rem;
            margin-bottom: 1.2rem;
        }

        .filter-bar label {
            font-size: .72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            white-space: nowrap;
        }

        .filter-select {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--text);
            font-size: .82rem;
            padding: .32rem .65rem;
            cursor: pointer;
            outline: none;
            transition: border-color .2s;
            min-width: 130px;
        }

        .filter-select:focus,
        .filter-select:hover {
            border-color: var(--accent);
        }

        .filter-select option {
            background: #1e293b;
        }

        .filter-reset {
            margin-left: auto;
            background: none;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--muted);
            font-size: .8rem;
            padding: .32rem .75rem;
            cursor: pointer;
            transition: border-color .2s, color .2s;
            white-space: nowrap;
        }

        .filter-reset:hover {
            border-color: var(--accent);
            color: var(--text);
        }

        .filter-apply {
            background: var(--accent);
            border: 1px solid var(--accent);
            border-radius: 7px;
            color: #fff;
            font-size: .8rem;
            padding: .32rem .85rem;
            cursor: pointer;
            transition: opacity .15s;
            white-space: nowrap;
        }

        .filter-apply:hover {
            opacity: .85;
        }

        .filter-active-count {
            font-size: .72rem;
            background: var(--accent);
            color: #fff;
            border-radius: 20px;
            padding: .1rem .55rem;
            display: none;
        }
    </style>
</head>


<body>

    <!-- ===== TOP NAVIGATION ===== -->
    <nav class="topnav">
        <a class="topnav-logo" href="index.php">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z" />
                <path d="M7 7h.01" />
            </svg>
            5S Red Tag
            <span class="badge">Dashboard</span>
        </a>
        <div class="topnav-spacer"></div>
        <a class="btn-nav" href="index.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            Floor Map
        </a>
        <a class="btn-nav active" href="dashboard.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
            </svg>
            Dashboard
        </a>
    </nav>

    <!-- ===== MAIN PAGE ===== -->
    <div class="page">

        <div class="page-title">Monitoring Dashboard</div>
        <div class="page-sub">Summary of all active 5S Red Tags &nbsp;-&nbsp; <?php echo date('d M Y, H:i'); ?></div>

        <!-- ===== GLOBAL FILTER BAR ===== -->
        <div class="filter-bar" id="filterBar">
            <label>Factory 17</label>
            <div class="filter-group">
                <i data-lucide="building-2"></i>
                <select class="filter-select" id="fFactory">
                    <option value="">All Factories</option>
                    <option value="1st Floor">1st Floor</option>
                    <option value="2nd Floor">2nd Floor</option>
                </select>
            </div>
            <label>Zone</label>
            <div class="filter-group">
                <i data-lucide="map"></i>
                <select class="filter-select" id="fZone">
                    <option value="">All Zones</option>
                </select>
            </div>
            <label>Status</label>
            <select class="filter-select" id="fStatus">
                <option value="">All Status</option>
                <option>Open</option>
                <option>In Progress</option>
                <option>Need help</option>
                <option>Closed</option>
            </select>
            <label>Category</label>
            <select class="filter-select" id="fCategory">
                <option value="">All Categories</option>
            </select>
            <label>Created</label>
            <select class="filter-select" id="fCreated">
                <option value="">All Months</option>
            </select>
            <span class="filter-active-count" id="filterCount"></span>
            <button class="filter-reset" id="filterReset">&#8635; Reset</button>
        </div>

        <!-- ===== KPI CARDS ===== -->
        <div class="kpi-grid">
            <div class="kpi-card" style="--card-accent:#94a3b8">
                <div class="kpi-label">Total Active</div>
                <div class="kpi-value" id="kpiTotal"><?= $total ?></div>
                <div class="kpi-sub" id="kpiTotalSub">All non-deleted tags</div>
                <div class="kpi-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                        <line x1="7" y1="7" x2="7.01" y2="7" />
                    </svg></div>
            </div>
            <div class="kpi-card" style="--card-accent:#ef4444">
                <div class="kpi-label">Open</div>
                <div class="kpi-value" id="kpiOpen"><?= $statusCounts['Open'] ?? 0 ?></div>
                <div class="kpi-sub" id="kpiOpenSub">
                    <?= $total ? round(($statusCounts['Open'] ?? 0) / $total * 100) : 0 ?>% of total
                </div>
                <div class="kpi-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ef4444"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg></div>
            </div>
            <div class="kpi-card" style="--card-accent:#3b82f6">
                <div class="kpi-label">In Progress</div>
                <div class="kpi-value" id="kpiInProgress"><?= $statusCounts['In Progress'] ?? 0 ?></div>
                <div class="kpi-sub" id="kpiInProgressSub">
                    <?= $total ? round(($statusCounts['In Progress'] ?? 0) / $total * 100) : 0 ?>% of total
                </div>
                <div class="kpi-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3b82f6"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10" />
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                    </svg></div>
            </div>
            <div class="kpi-card" style="--card-accent:#eab308">
                <div class="kpi-label">Need Help</div>
                <div class="kpi-value" id="kpiNeedHelp"><?= $statusCounts['Need help'] ?? 0 ?></div>
                <div class="kpi-sub" id="kpiNeedHelpSub">
                    <?= $total ? round(($statusCounts['Need help'] ?? 0) / $total * 100) : 0 ?>% of total
                </div>
                <div class="kpi-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#eab308"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg></div>
            </div>
            <div class="kpi-card" style="--card-accent:#22c55e">
                <div class="kpi-label">Closed</div>
                <div class="kpi-value" id="kpiClosed"><?= $statusCounts['Closed'] ?? 0 ?></div>
                <div class="kpi-sub" id="kpiClosedSub">
                    <?= $total ? round(($statusCounts['Closed'] ?? 0) / $total * 100) : 0 ?>% of total
                </div>
                <div class="kpi-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg></div>
            </div>
        </div>

        <!-- ===== CHARTS ROW 1 ===== -->
        <div class="chart-grid">

            <!-- Status Doughnut -->
            <div class="chart-card">
                <div class="chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="chart-icon"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    Status Breakdown
                </div>
                <div class="chart-wrap"><canvas id="chartStatus"></canvas></div>
            </div>

            <!-- Category Doughnut -->
            <div class="chart-card">
                <div class="chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="chart-icon"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    Tags by Category
                </div>
                <div class="chart-wrap"><canvas id="chartCategory"></canvas></div>
            </div>

        </div>

        <!-- ===== ZONE ร— STATUS + ZONE ร— CATEGORY (Side by Side) ===== -->
        <div class="chart-grid" style="grid-template-columns: 1fr 1fr;">

            <!-- Zone ร— Status Stacked -->
            <div class="chart-card">
                <div class="chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="chart-icon"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    Zone &times; Status (Stacked)
                </div>
                <div class="chart-wrap" style="height:240px;"><canvas id="chartZoneStatus"></canvas></div>
            </div>

            <!-- Zone ร— Category Stacked -->
            <div class="chart-card">
                <div class="chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="chart-icon"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    Zone &times; Category (Stacked)
                </div>
                <div class="chart-wrap" style="height:240px;"><canvas id="chartZoneCategory"></canvas></div>
            </div>

        </div>

        <!-- ===== TAG TREND โ€” Full Width (Total bar + Status lines) ===== -->
        <div class="chart-grid" style="grid-template-columns:1fr;">
            <div class="chart-card">
                <div class="chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="chart-icon"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10" />
                        <line x1="12" y1="20" x2="12" y2="4" />
                        <line x1="6" y1="20" x2="6" y2="14" />
                    </svg>
                    Tag Trend on Created by Status (Daily) &mdash; Bar = Total
                </div>
                <div class="chart-wrap" style="height:260px;"><canvas id="chartTrendStatus"></canvas></div>
            </div>
        </div>

        <!-- ===== ALL TAGS TABLE ===== -->
        <div class="table-card">
            <div class="table-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                </svg>
                All Tags (<span id="tableHeaderCount"><?= $total ?></span>) &nbsp;<span id="sortLabel"
                    style="font-weight:400;color:var(--muted);font-size:.8rem;">sorted by Updated &#8595;</span>
                <div class="search-wrap" style="display:flex; gap:0.5rem; align-items:center;">
                    <button class="filter-apply" id="exportCsvBtn" title="Export to CSV"
                        style="padding:0.4rem 0.8rem; font-size:0.8rem; background-color: var(--green);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            style="vertical-align:middle;margin-right:2px;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg> Export
                    </button>
                    <span class="search-count" id="searchCount"></span>
                    <input type="search" class="search-input" id="tagSearch" placeholder="Search all fields..."
                        autocomplete="off">
                    <button class="search-clear" id="searchClear" title="Clear search">&times;</button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table id="allTagsTable">
                    <thead>
                        <tr>
                            <th style="width:48px;cursor:default;">#</th>
                            <th data-col="zone" class="sortable">Zone</th>
                            <th data-col="line" class="sortable">Line</th>
                            <th data-col="status" class="sortable">Status</th>
                            <th data-col="category" class="sortable">Category</th>
                            <th data-col="pic" class="sortable">PIC</th>
                            <th data-col="description" class="sortable">Description</th>
                            <th data-col="solution" class="sortable">Solution</th>
                            <th data-col="createdAt" class="sortable">Created</th>
                            <th data-col="updatedAt" class="sortable th-active th-desc">Updated</th>
                        </tr>
                    </thead>
                    <tbody id="allTagsTbody">
                        <!-- Rendered by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer">5S Red Tag &copy; Murata MT5200</div>
    </div>

    <!-- All Tags JSON for JS table -->
    <script>
        const ALL_TAGS = <?= json_encode(array_values($recent), JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- ===== CHART SCRIPTS ===== -->
    <script>
        // ---- Data from PHP ----
        const statusLabels = <?= jse(array_keys($statusCounts)) ?>;
        const statusValues = <?= jse(array_values($statusCounts)) ?>;

        const zoneLabels = <?= jse(array_keys($zoneCounts)) ?>;
        const zoneValues = <?= jse(array_values($zoneCounts)) ?>;

        const catLabels = <?= jse(array_keys($categoryCounts)) ?>;
        const catValues = <?= jse(array_values($categoryCounts)) ?>;

        const trendDates = <?= jse(array_keys($dailyData)) ?>;
        const trendCounts = <?= jse(array_values($dailyData)) ?>;

        const zoneStatusData = <?= jse($zoneStatus) ?>;
        const zoneCategoryData = <?= jse($zoneCategory) ?>;
        const dailyStatusData = <?= jse($dailyStatusData ?? []) ?>;

        // ---- Shared chart defaults ----
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = '#334155';
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.font.size = 12;

        const COLORS = {
            Open: 'rgba(239,68,68,.85)',
            'In Progress': 'rgba(59,130,246,.85)',
            'Need help': 'rgba(234,179,8,.85)',
            Closed: 'rgba(34,197,94,.85)',
        };

        // ---- Status Doughnut ----
        const chartStatus = new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusLabels.map(l => COLORS[l] || 'rgba(148,163,184,.7)'),
                    borderColor: '#1e293b',
                    borderWidth: 2,
                    hoverOffset: 8,
                }]
            },
            options: {
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyleWidth: 10 } }
                }
            }
        });

        // ---- Category Doughnut ----
        const catColors = ['rgba(168,85,247,.85)', 'rgba(249,115,22,.85)', 'rgba(20,184,166,.85)', 'rgba(236,72,153,.85)', 'rgba(245,158,11,.85)', 'rgba(99,102,241,.85)'];
        const chartCategory = new Chart(document.getElementById('chartCategory'), {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catValues,
                    backgroundColor: catColors,
                    borderColor: '#1e293b', borderWidth: 2, hoverOffset: 8,
                }]
            },
            options: {
                cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, pointStyleWidth: 10 } }
                }
            }
        });

        // ---- Trend Line ----
        const chartTrend = new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: trendDates,
                datasets: [{
                    label: 'New Tags',
                    data: trendCounts,
                    borderColor: 'rgba(239,68,68,1)',
                    backgroundColor: 'rgba(239,68,68,.12)',
                    pointBackgroundColor: 'rgba(239,68,68,1)',
                    pointRadius: 4, pointHoverRadius: 6,
                    fill: true, tension: .4,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: '#334155' }, ticks: { maxTicksLimit: 12, maxRotation: 45 } },
                    y: { grid: { color: '#334155' }, ticks: { precision: 0 } }
                }
            }
        });

        // ---- Combined Trend: Total (bar) + Per-Status (lines) ----
        (function () {
            const allDates = [...new Set([
                ...trendDates,
                ...Object.keys(dailyStatusData)
            ])].sort();

            const statuses = ['Open', 'In Progress', 'Need help', 'Closed'];
            const LINE_COLORS = {
                'Open': { border: 'rgba(239,68,68,1)', bg: 'rgba(239,68,68,.10)' },
                'In Progress': { border: 'rgba(59,130,246,1)', bg: 'rgba(59,130,246,.10)' },
                'Need help': { border: 'rgba(234,179,8,1)', bg: 'rgba(234,179,8,.10)' },
                'Closed': { border: 'rgba(34,197,94,1)', bg: 'rgba(34,197,94,.10)' },
            };

            // Bar dataset: daily total
            const totalDataset = {
                type: 'bar',
                label: 'Total',
                data: allDates.map(d => {
                    const idx = trendDates.indexOf(d);
                    return idx >= 0 ? trendCounts[idx] : 0;
                }),
                backgroundColor: 'rgba(148,163,184,.35)',
                borderColor: 'rgba(148,163,184,.6)',
                borderWidth: 1,
                borderRadius: 4,
                order: 2, // draw behind lines
            };

            // Line datasets: per status
            const lineDatasets = statuses.map(s => ({
                type: 'line',
                label: s,
                data: allDates.map(d => (dailyStatusData[d] && dailyStatusData[d][s]) ? dailyStatusData[d][s] : 0),
                borderColor: LINE_COLORS[s].border,
                backgroundColor: LINE_COLORS[s].bg,
                pointBackgroundColor: LINE_COLORS[s].border,
                pointRadius: 3, pointHoverRadius: 5,
                fill: false, tension: .4, borderWidth: 2,
                order: 1, // draw on top of bar
            }));

            window.chartTrendStatus = new Chart(document.getElementById('chartTrendStatus'), {
                type: 'bar',
                data: { labels: allDates, datasets: [totalDataset, ...lineDatasets] },
                options: {
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 10 } } },
                    scales: {
                        x: { grid: { color: '#334155' }, ticks: { maxTicksLimit: 12, maxRotation: 45 } },
                        y: { grid: { color: '#334155' }, ticks: { precision: 0 } }
                    }
                }
            });
        })();

        // ---- Zone ร— Status Stacked ----
        (function () {
            const zones = Object.keys(zoneStatusData);
            const allStatuses = ['Open', 'In Progress', 'Need help', 'Closed'];
            const datasets = allStatuses.map(s => ({
                label: s,
                data: zones.map(z => (zoneStatusData[z] && zoneStatusData[z][s]) ? zoneStatusData[z][s] : 0),
                backgroundColor: COLORS[s] || 'rgba(148,163,184,.7)',
                borderRadius: 4, borderSkipped: false,
            }));
            window.chartZoneStatus = new Chart(document.getElementById('chartZoneStatus'), {
                type: 'bar',
                data: { labels: zones, datasets },
                options: {
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 10 } } },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, grid: { color: '#334155' }, ticks: { precision: 0 } }
                    }
                }
            });
        })();

        // ---- Zone ร— Category Stacked ----
        (function () {
            const zones = Object.keys(zoneCategoryData);
            // Collect all unique categories across all zones
            const allCats = [...new Set(Object.values(zoneCategoryData).flatMap(z => Object.keys(z)))];
            window.catColors = ['rgba(168,85,247,.85)', 'rgba(249,115,22,.85)', 'rgba(20,184,166,.85)', 'rgba(236,72,153,.85)', 'rgba(245,158,11,.85)', 'rgba(99,102,241,.85)'];
            const datasets = allCats.map((c, i) => ({
                label: c,
                data: zones.map(z => (zoneCategoryData[z] && zoneCategoryData[z][c]) ? zoneCategoryData[z][c] : 0),
                backgroundColor: window.catColors[i % window.catColors.length],
                borderRadius: 4, borderSkipped: false,
            }));
            window.chartZoneCategory = new Chart(document.getElementById('chartZoneCategory'), {
                type: 'bar',
                data: { labels: zones, datasets },
                options: {
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 10 } } },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, grid: { color: '#334155' }, ticks: { precision: 0 } }
                    }
                }
            });
        })();

        // ======= ALL TAGS TABLE RENDER + SORT =======
        (function () {
            const TRUNCATE = 60;
            function trunc(s) { return (!s || s.length <= TRUNCATE) ? (s || '') : s.slice(0, TRUNCATE) + '...'; }
            function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
            function dash(s) { return s ? esc(s) : '<span class="td-muted">&mdash;</span>'; }
            const SC = { 'Open': 's-Open', 'In Progress': 's-In_Progress', 'Need help': 's-Need_help', 'Closed': 's-Closed' };

            let sortCol = 'updatedAt';
            let sortAsc = false;
            let searchQuery = '';
            // Use global-filtered set when available, otherwise fall back to all tags
            let data = [];
            function getSource() { return window.ALL_TAGS_FILTERED || ALL_TAGS; }

            // Fields to search across
            function tagSearchStr(tag) {
                return [
                    tag.zone ? 'Zone ' + tag.zone : '',
                    tag.productionLine, tag.status, tag.category,
                    tag.pic, tag.description, tag.solution,
                    tag.createdAt, tag.updatedAt
                ].join(' ').toLowerCase();
            }

            function filterAndSort() {
                const q = searchQuery.trim().toLowerCase();
                const source = getSource();
                data = q ? source.filter(tag => tagSearchStr(tag).includes(q)) : [...source];
                // then sort
                data.sort((a, b) => {
                    const va = getVal(a, sortCol).toLowerCase();
                    const vb = getVal(b, sortCol).toLowerCase();
                    if (va < vb) return sortAsc ? -1 : 1;
                    if (va > vb) return sortAsc ? 1 : -1;
                    return 0;
                });
                // update match count
                const cnt = document.getElementById('searchCount');
                if (cnt) cnt.textContent = q ? `${data.length} match${data.length !== 1 ? 'es' : ''}` : '';
            }

            function getVal(tag, col) {
                if (col === 'zone') return tag.zone ? String(tag.zone) : '';
                if (col === 'line') return tag.productionLine || '';
                if (col === 'updatedAt') return tag.updatedAt || tag.createdAt || '';
                if (col === 'createdAt') return tag.createdAt || '';
                return tag[col] || '';
            }

            function sortData() {
                data.sort((a, b) => {
                    const va = getVal(a, sortCol).toLowerCase();
                    const vb = getVal(b, sortCol).toLowerCase();
                    if (va < vb) return sortAsc ? -1 : 1;
                    if (va > vb) return sortAsc ? 1 : -1;
                    return 0;
                });
            }

            function renderTable() {
                const tbody = document.getElementById('allTagsTbody');
                if (!data.length) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--muted);">No tags found.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.map((tag, i) => {
                    const zone = tag.zone ? esc(tag.zone) : '<span class="td-muted">&mdash;</span>';
                    const line = dash(tag.productionLine);
                    const sc = SC[tag.status] || '';
                    const cat = dash(tag.category);
                    const pic = dash(tag.pic);
                    const desc = tag.description ? esc(trunc(tag.description)) : '<span class="td-muted">&mdash;</span>';
                    const sol = tag.solution ? esc(trunc(tag.solution)) : '<span class="td-muted">&mdash;</span>';
                    const cre = tag.createdAt ? esc(tag.createdAt) : '<span class="td-muted">&mdash;</span>';
                    const updRaw = tag.updatedAt || tag.createdAt;
                    const upd = updRaw ? esc(updRaw) : '<span class="td-muted">&mdash;</span>';
                    return `<tr>
                        <td class="td-muted" style="text-align:center;">${i + 1}</td>
                        <td>${zone}</td><td>${line}</td>
                        <td><span class="badge-status ${sc}">${esc(tag.status)}</span></td>
                        <td>${cat}</td><td>${pic}</td>
                        <td>${desc}</td><td>${sol}</td>
                        <td class="td-muted">${cre}</td>
                        <td class="td-muted">${upd}</td>
                    </tr>`;
                }).join('');
            }

            const COL_NAMES = {
                zone: 'Zone', line: 'Line', status: 'Status', category: 'Category',
                pic: 'PIC', description: 'Description', solution: 'Solution', createdAt: 'Created', updatedAt: 'Updated'
            };

            function updateHeaders() {
                document.querySelectorAll('#allTagsTable th.sortable').forEach(th => {
                    th.classList.remove('th-active', 'th-asc', 'th-desc');
                    if (th.dataset.col === sortCol)
                        th.classList.add('th-active', sortAsc ? 'th-asc' : 'th-desc');
                });
                const lbl = document.getElementById('sortLabel');
                if (lbl) lbl.textContent = `sorted by ${COL_NAMES[sortCol] || sortCol} ${sortAsc ? '\u2191' : '\u2193'}`;
            }

            document.querySelectorAll('#allTagsTable th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.dataset.col;
                    sortAsc = col === sortCol ? !sortAsc : (col !== 'updatedAt' && col !== 'createdAt');
                    sortCol = col;
                    filterAndSort(); renderTable(); updateHeaders();
                });
            });

            // ---- Search input wiring ----
            const searchInput = document.getElementById('tagSearch');
            const searchClear = document.getElementById('searchClear');
            searchInput.addEventListener('input', () => {
                searchQuery = searchInput.value;
                searchClear.style.display = searchQuery ? 'inline' : 'none';
                filterAndSort(); renderTable(); updateHeaders();
            });
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchQuery = '';
                searchClear.style.display = 'none';
                filterAndSort(); renderTable(); updateHeaders();
                searchInput.focus();
            });

            // Expose refresh hook for global filter engine
            window.__tableRefresh = function () {
                searchQuery = '';
                const searchInput = document.getElementById('tagSearch');
                if (searchInput) searchInput.value = '';
                const searchClear = document.getElementById('searchClear');
                if (searchClear) searchClear.style.display = 'none';
                filterAndSort(); renderTable(); updateHeaders();
            };

            // Export CSV
            const exportBtn = document.getElementById('exportCsvBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', () => {
                    if (!data.length) return alert('No data to export.');
                    let csv = '\uFEFF'; // BOM for Excel Thai fonts
                    const headers = ['ID', 'Zone', 'Line', 'Status', 'Category', 'PIC', 'Description', 'Solution', 'Photo (Before)', 'Photo (After)', 'Created', 'Updated'];
                    csv += headers.join(',') + '\n';
                    data.forEach((t, i) => {
                        const row = [
                            i + 1, getVal(t, 'zone'), getVal(t, 'line'), t.status, getVal(t, 'category'),
                            getVal(t, 'pic'), t.description, t.solution, getVal(t, 'image'), getVal(t, 'imageAfter'), getVal(t, 'createdAt'), getVal(t, 'updatedAt')
                        ].map(v => {
                            let s = String(v || '').replace(/"/g, '""');
                            return /[,\n"]/.test(s) ? `"${s}"` : s;
                        });
                        csv += row.join(',') + '\n';
                    });
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `5S_RedTags_Export_${new Date().toISOString().slice(0, 10)}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }

            // Initial render
            filterAndSort();
            renderTable();
            updateHeaders();
        })();

    </script>

    <script>
        // ======= GLOBAL FILTER ENGINE =======
        (function () {

            // ---- Populate Zone dropdown with raw-number values ----
            (function () {
                const zones = [...new Set(ALL_TAGS.map(t => t.zone).filter(Boolean))].sort((a, b) => Number(a) - Number(b));
                const sel = document.getElementById('fZone');
                if (!sel) return;
                zones.forEach(z => {
                    const o = document.createElement('option');
                    o.value = String(z);          // value = '1', '2', โ€ฆ
                    o.text = 'Zone ' + z;        // display = 'Zone 1', โ€ฆ
                    sel.appendChild(o);
                });
            })();

            // ---- Populate Category dropdown ----
            (function () {
                const cats = [...new Set(ALL_TAGS.map(t => t.category || 'Uncategorised').filter(Boolean))].sort();
                const sel = document.getElementById('fCategory');
                if (!sel) return;
                // only add if not already present (avoid duplicating hardcoded options)
                const existing = [...sel.options].map(o => o.value);
                cats.forEach(c => {
                    if (!existing.includes(c)) {
                        const o = document.createElement('option');
                        o.value = o.text = c;
                        sel.appendChild(o);
                    }
                });
            })();

            // ---- Populate Created (mmm-yy) dropdown in chronological order ----
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            function toMmmYy(iso) {
                if (!iso) return null;
                const d = new Date(iso);
                return isNaN(d) ? null : MONTHS[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2);
            }
            (function () {
                const monthMap = {};
                ALL_TAGS.forEach(t => {
                    if (!t.createdAt) return;
                    const d = new Date(t.createdAt);
                    if (isNaN(d)) return;
                    const key = d.getFullYear() * 100 + d.getMonth();
                    monthMap[key] = MONTHS[d.getMonth()] + '-' + String(d.getFullYear()).slice(-2);
                });
                const sel = document.getElementById('fCreated');
                if (!sel) return;
                Object.keys(monthMap).sort((a, b) => a - b).forEach(k => {
                    const o = document.createElement('option');
                    o.value = o.text = monthMap[k];
                    sel.appendChild(o);
                });
            })();

            // ---- Aggregate helper ----
            function computeAggregates(tags) {
                const sc = {}, cc = {}, dd = {}, dsd = {}, zst = {}, zcat = {};
                tags.forEach(t => {
                    const z = t.zone ? 'Zone ' + t.zone : 'Unknown';
                    const c = t.category || 'Uncategorised';
                    sc[t.status] = (sc[t.status] || 0) + 1;
                    cc[c] = (cc[c] || 0) + 1;
                    if (t.createdAt) {
                        const day = t.createdAt.slice(0, 10);
                        dd[day] = (dd[day] || 0) + 1;
                        if (!dsd[day]) dsd[day] = {};
                        dsd[day][t.status] = (dsd[day][t.status] || 0) + 1;
                    }
                    if (!zst[z]) zst[z] = {};
                    if (!zcat[z]) zcat[z] = {};
                    zst[z][t.status] = (zst[z][t.status] || 0) + 1;
                    zcat[z][c] = (zcat[z][c] || 0) + 1;
                });
                const sort = o => Object.fromEntries(Object.entries(o).sort());
                return { sc, cc, dd: sort(dd), dsd: sort(dsd), zst: sort(zst), zcat: sort(zcat) };
            }

            // ---- Update all visual components ----
            function updateAll(agg, tags) {
                const total = tags.length;
                const CC = window.catColors || ['rgba(168,85,247,.85)', 'rgba(249,115,22,.85)', 'rgba(20,184,166,.85)', 'rgba(236,72,153,.85)', 'rgba(245,158,11,.85)', 'rgba(99,102,241,.85)'];

                // KPIs and Table Header Count
                const kpiMap = { 'Open': 'kpiOpen', 'In Progress': 'kpiInProgress', 'Need help': 'kpiNeedHelp', 'Closed': 'kpiClosed' };
                const subMap = { 'Open': 'kpiOpenSub', 'In Progress': 'kpiInProgressSub', 'Need help': 'kpiNeedHelpSub', 'Closed': 'kpiClosedSub' };
                const elT = document.getElementById('kpiTotal');
                if (elT) elT.textContent = total;
                const elTHC = document.getElementById('tableHeaderCount');
                if (elTHC) elTHC.textContent = total;
                const elTS = document.getElementById('kpiTotalSub');
                if (elTS) elTS.textContent = total + ' tag' + (total !== 1 ? 's' : '');
                ['Open', 'In Progress', 'Need help', 'Closed'].forEach(s => {
                    const n = agg.sc[s] || 0;
                    const pct = total ? Math.round(n / total * 100) : 0;
                    const ev = document.getElementById(kpiMap[s]);
                    const es = document.getElementById(subMap[s]);
                    if (ev) ev.textContent = n;
                    if (es) es.textContent = pct + '% of total';
                });

                // Status doughnut
                try {
                    const sKeys = Object.keys(agg.sc);
                    const COLORS = { Open: 'rgba(239,68,68,.85)', 'In Progress': 'rgba(59,130,246,.85)', 'Need help': 'rgba(234,179,8,.85)', Closed: 'rgba(34,197,94,.85)' };
                    chartStatus.data.labels = sKeys;
                    chartStatus.data.datasets[0].data = sKeys.map(k => agg.sc[k]);
                    chartStatus.data.datasets[0].backgroundColor = sKeys.map(k => COLORS[k] || 'rgba(148,163,184,.7)');
                    chartStatus.update();
                } catch (e) { console.warn('chartStatus update failed', e); }

                // Category doughnut
                try {
                    const cKeys = Object.keys(agg.cc);
                    chartCategory.data.labels = cKeys;
                    chartCategory.data.datasets[0].data = cKeys.map(k => agg.cc[k]);
                    chartCategory.data.datasets[0].backgroundColor = CC.slice(0, cKeys.length);
                    chartCategory.update();
                } catch (e) { console.warn('chartCategory update failed', e); }

                // Trend (daily total)
                try {
                    const tDates = Object.keys(agg.dd);
                    chartTrend.data.labels = tDates;
                    chartTrend.data.datasets[0].data = tDates.map(d => agg.dd[d]);
                    chartTrend.update();
                } catch (e) { console.warn('chartTrend update failed', e); }

                // Zone ร— Status
                try {
                    const zsZ = Object.keys(agg.zst);
                    window.chartZoneStatus.data.labels = zsZ;
                    ['Open', 'In Progress', 'Need help', 'Closed'].forEach((s, i) => {
                        window.chartZoneStatus.data.datasets[i].data = zsZ.map(z => (agg.zst[z] && agg.zst[z][s]) || 0);
                    });
                    window.chartZoneStatus.update();
                } catch (e) { console.warn('chartZoneStatus update failed', e); }

                // Zone ร— Category
                try {
                    const zcZ = Object.keys(agg.zcat);
                    const cats = [...new Set(Object.values(agg.zcat).flatMap(z => Object.keys(z)))];
                    window.chartZoneCategory.data.labels = zcZ;
                    window.chartZoneCategory.data.datasets = cats.map((c, i) => ({
                        label: c, data: zcZ.map(z => (agg.zcat[z] && agg.zcat[z][c]) || 0),
                        backgroundColor: CC[i % CC.length], borderRadius: 4, borderSkipped: false,
                    }));
                    window.chartZoneCategory.update();
                } catch (e) { console.warn('chartZoneCategory update failed', e); }

                // Combined Trend Status
                try {
                    const tDates = Object.keys(agg.dd);
                    const allDates = [...new Set([...tDates, ...Object.keys(agg.dsd)])].sort();
                    window.chartTrendStatus.data.labels = allDates;
                    window.chartTrendStatus.data.datasets[0].data = allDates.map(d => agg.dd[d] || 0);
                    ['Open', 'In Progress', 'Need help', 'Closed'].forEach((s, i) => {
                        if (window.chartTrendStatus.data.datasets[i + 1])
                            window.chartTrendStatus.data.datasets[i + 1].data = allDates.map(d => (agg.dsd[d] && agg.dsd[d][s]) || 0);
                    });
                    window.chartTrendStatus.update();
                } catch (e) { console.warn('chartTrendStatus update failed', e); }
            }

            // ---- Apply filters ----
            function applyGlobalFilters() {
                const get = id => { const el = document.getElementById(id); return el ? el.value : ''; };
                const factory = get('fFactory');
                const zone = get('fZone');
                const status = get('fStatus');
                const cat = get('fCategory');
                const created = get('fCreated');

                const filtered = ALL_TAGS.filter(t => {
                    const tFac = t.factory || 'F10-11'; // Fallback to F10-11 if missing
                    if (factory && tFac !== factory) return false;
                    if (zone && String(t.zone || '') !== zone) return false;
                    if (status && t.status !== status) return false;
                    if (cat && (t.category || 'Uncategorised') !== cat) return false;
                    if (created && toMmmYy(t.createdAt) !== created) return false;
                    return true;
                });

                const activeCount = [factory, zone, status, cat, created].filter(Boolean).length;
                const badge = document.getElementById('filterCount');
                if (badge) {
                    badge.textContent = activeCount + ' filter' + (activeCount !== 1 ? 's' : '') + ' active';
                    badge.style.display = activeCount ? 'inline' : 'none';
                }

                updateAll(computeAggregates(filtered), filtered);
                window.ALL_TAGS_FILTERED = activeCount ? filtered : null;
                if (window.__tableRefresh) window.__tableRefresh();
            }

            // Expose globally
            window.applyGlobalFilters = applyGlobalFilters;

            // Wire all filter controls
            ['fFactory', 'fZone', 'fStatus', 'fCategory', 'fCreated'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', applyGlobalFilters);
            });

            const resetBtn = document.getElementById('filterReset');
            if (resetBtn) resetBtn.addEventListener('click', () => {
                ['fFactory', 'fZone', 'fStatus', 'fCategory', 'fCreated'].forEach(id => {
                    const el = document.getElementById(id); if (el) el.value = '';
                });
                window.ALL_TAGS_FILTERED = null;
                applyGlobalFilters();
            });

        })();
    </script>

</body>

</html>