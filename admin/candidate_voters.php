<?php
// filename: admin/candidate_voters.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$candidate_id = isset($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : 0;
$election_id  = isset($_GET['election_id'])  ? (int)$_GET['election_id']  : 0;

if(!$candidate_id) {
    header("Location: dashboard.php");
    exit();
}

// Get candidate details
$stmt = $pdo->prepare("
    SELECT c.*, p.position_name, p.election_id,
           e.title as election_title,
           s.profile_photo as student_photo,
           (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) as vote_count
    FROM candidates c
    JOIN positions p ON c.position_id = p.id
    JOIN elections e ON p.election_id = e.id
    LEFT JOIN students s ON c.student_id = s.student_id
    WHERE c.id = ?
");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if(!$candidate) {
    header("Location: dashboard.php");
    exit();
}

if(!$election_id) $election_id = $candidate['election_id'];

// Get all voters for this candidate with student details
$stmt = $pdo->prepare("
    SELECT v.student_id, v.voted_at, s.full_name, s.contact_number, s.profile_photo
    FROM votes v
    LEFT JOIN students s ON v.student_id = s.student_id
    WHERE v.candidate_id = ?
    ORDER BY v.voted_at DESC
");
$stmt->execute([$candidate_id]);
$voters = $stmt->fetchAll();

// Get total votes in this election (for percentage)
$stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM votes WHERE election_id = ?");
$stmt2->execute([$election_id]);
$total_election_voters = $stmt2->fetchColumn();

// Get total registered students
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// Candidate photo resolution
$photo = '';
if(!empty($candidate['photo']) && file_exists('../' . $candidate['photo'])) {
    $photo = '../' . $candidate['photo'];
} elseif(!empty($candidate['student_photo']) && file_exists('../' . $candidate['student_photo'])) {
    $photo = '../' . $candidate['student_photo'];
}
$initials = '';
foreach(explode(' ', $candidate['full_name']) as $p) { if($p) $initials .= strtoupper($p[0]); }
$initials = substr($initials, 0, 2);

include '../includes/header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap');

/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background:
        radial-gradient(ellipse at 0% 0%, rgba(99,102,241,0.18) 0%, transparent 50%),
        radial-gradient(ellipse at 100% 0%, rgba(139,92,246,0.14) 0%, transparent 45%),
        radial-gradient(ellipse at 100% 100%, rgba(16,185,129,0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 0% 100%, rgba(251,191,36,0.08) 0%, transparent 40%),
        #f0f2ff;
    background-attachment: fixed;
    min-height: 100vh;
    color: #1e293b;
}

.cv-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem 1.75rem 5rem;
    position: relative;
    z-index: 1;
}

/* ── Back Button ── */
.cv-back {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: white; border: 1px solid rgba(0,0,0,0.09);
    color: #475569; border-radius: 99px; padding: 0.55rem 1.25rem;
    font-weight: 700; font-size: 0.875rem; text-decoration: none;
    transition: all 0.2s; margin-bottom: 1.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.cv-back:hover { background: #f8fafc; border-color: rgba(99,102,241,0.3); color: #6366f1; transform: translateX(-2px); }
[data-theme="dark"] .cv-back { background: rgba(30,41,59,0.85); border-color: rgba(255,255,255,0.08); color: #cbd5e1; }
[data-theme="dark"] .cv-back:hover { background: rgba(51,65,85,0.9); color: #818cf8; }

/* ── Hero Card ── */
.cv-hero {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    border-radius: 28px;
    padding: 2.5rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(99,102,241,0.3), 0 0 0 1px rgba(255,255,255,0.12) inset;
}
.cv-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
    pointer-events: none;
}
.cv-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 40%;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    pointer-events: none;
}

.cv-avatar {
    width: 100px; height: 100px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; font-weight: 800; color: white; flex-shrink: 0;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25), 0 0 0 4px rgba(255,255,255,0.25);
    overflow: hidden;
    position: relative; z-index: 1;
}
.cv-avatar img { width: 100%; height: 100%; object-fit: cover; }

.cv-info { flex: 1; min-width: 200px; position: relative; z-index: 1; }
.cv-name {
    font-family: 'Sora', sans-serif;
    font-size: 1.8rem; font-weight: 800; color: white;
    margin-bottom: 0.35rem; line-height: 1.2;
    text-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.cv-position {
    font-size: 0.95rem; font-weight: 700;
    color: rgba(255,255,255,0.85); margin-bottom: 0.4rem;
    display: flex; align-items: center; gap: 0.4rem;
}
.cv-meta { font-size: 0.82rem; color: rgba(255,255,255,0.65); font-weight: 600; }

.cv-vote-pill {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.25);
    color: white; border-radius: 20px; padding: 1.25rem 2rem;
    text-align: center; min-width: 130px; flex-shrink: 0;
    position: relative; z-index: 1;
}
.cv-vote-num { font-size: 3rem; font-weight: 800; line-height: 1; }
.cv-vote-lbl { font-size: 0.72rem; font-weight: 800; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.07em; margin-top: 4px; }

/* ── Stats Strip ── */
.cv-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}
.cv-stat {
    background: white;
    border-radius: 20px;
    padding: 1.25rem 1.5rem;
    display: flex; align-items: center; gap: 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.06);
    transition: transform 0.2s;
}
.cv-stat:hover { transform: translateY(-2px); }
[data-theme="dark"] .cv-stat { background: rgba(20,22,40,0.85); border-color: rgba(99,102,241,0.12); box-shadow: 0 4px 20px rgba(0,0,0,0.25); }

.cv-stat-icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.cv-stat-icon.purple { background: rgba(99,102,241,0.1); }
.cv-stat-icon.green  { background: rgba(16,185,129,0.1); }
.cv-stat-icon.amber  { background: rgba(245,158,11,0.1); }

.cv-stat-val {
    font-family: 'Sora', sans-serif;
    font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1;
}
[data-theme="dark"] .cv-stat-val { color: #e2e8f0; }
.cv-stat-lbl { font-size: 0.78rem; font-weight: 600; color: #64748b; margin-top: 3px; }
[data-theme="dark"] .cv-stat-lbl { color: #94a3b8; }

/* ── Main Panel ── */
.cv-panel {
    background: white;
    border-radius: 24px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.06);
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .cv-panel { background: rgba(20,22,40,0.88); border-color: rgba(99,102,241,0.12); box-shadow: 0 8px 40px rgba(0,0,0,0.3); }

/* ── Panel Head ── */
.cv-panel-head {
    padding: 1.25rem 1.75rem;
    background: rgba(248,250,252,0.8);
    border-bottom: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
}
[data-theme="dark"] .cv-panel-head { background: rgba(15,17,30,0.7); border-color: rgba(255,255,255,0.05); }

.cv-panel-title {
    font-family: 'Sora', sans-serif;
    font-weight: 800; font-size: 1rem; color: #0f172a;
    display: flex; align-items: center; gap: 0.5rem; flex: 1;
}
[data-theme="dark"] .cv-panel-title { color: #e2e8f0; }

.cv-count-badge {
    background: rgba(99,102,241,0.1); color: #6366f1;
    border: 1px solid rgba(99,102,241,0.2); border-radius: 99px;
    padding: 0.25rem 0.9rem; font-size: 0.78rem; font-weight: 800;
    white-space: nowrap;
}

/* ── Search Bar ── */
.cv-search-wrap {
    padding: 1rem 1.75rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
}
[data-theme="dark"] .cv-search-wrap { border-color: rgba(255,255,255,0.05); }

.cv-search {
    position: relative; flex: 1; min-width: 200px;
}
.cv-search input {
    width: 100%;
    padding: 0.6rem 0.6rem 0.6rem 2.5rem;
    border-radius: 12px;
    border: 1.5px solid rgba(0,0,0,0.09);
    font-size: 0.875rem; font-weight: 500; font-family: inherit;
    background: white; color: #1e293b;
    transition: all 0.2s; outline: none;
}
.cv-search input:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.08); }
[data-theme="dark"] .cv-search input { background: rgba(15,17,30,0.8); border-color: rgba(99,102,241,0.15); color: #e2e8f0; }
[data-theme="dark"] .cv-search input:focus { border-color: #6366f1; }
.cv-search-icon {
    position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; pointer-events: none;
}

/* ── Voters Table ── */
.cv-table { width: 100%; border-collapse: collapse; }
.cv-table thead th {
    padding: 0.85rem 1.5rem;
    font-size: 0.72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;
    background: rgba(248,250,252,0.5); text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .cv-table thead th { background: rgba(15,17,30,0.4); color: #94a3b8; border-color: rgba(255,255,255,0.04); }

.cv-table tbody tr {
    border-bottom: 1px solid rgba(0,0,0,0.04);
    transition: background 0.15s;
}
.cv-table tbody tr:last-child { border-bottom: none; }
.cv-table tbody tr:hover { background: rgba(99,102,241,0.03); }
[data-theme="dark"] .cv-table tbody tr { border-color: rgba(255,255,255,0.04); }
[data-theme="dark"] .cv-table tbody tr:hover { background: rgba(99,102,241,0.07); }

.cv-table td { padding: 1rem 1.5rem; vertical-align: middle; }

/* row number */
.cv-row-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 8px;
    background: rgba(99,102,241,0.08); color: #6366f1;
    font-size: 0.75rem; font-weight: 800;
}

/* voter avatar */
.cv-v-avatar {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 800; color: white;
    overflow: hidden; box-shadow: 0 3px 10px rgba(99,102,241,0.25);
}
.cv-v-avatar img { width: 100%; height: 100%; object-fit: cover; }

.cv-v-name { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
[data-theme="dark"] .cv-v-name { color: #e2e8f0; }
.cv-v-id { font-size: 0.78rem; color: #64748b; margin-top: 2px; font-weight: 600; }
[data-theme="dark"] .cv-v-id { color: #94a3b8; }

.cv-voted-date { font-size: 0.85rem; color: #0f172a; font-weight: 700; }
[data-theme="dark"] .cv-voted-date { color: #e2e8f0; }
.cv-voted-time { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; font-weight: 600; }

.cv-time-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(16,185,129,0.08); color: #059669;
    border: 1px solid rgba(16,185,129,0.15);
    border-radius: 99px; padding: 3px 10px;
    font-size: 0.75rem; font-weight: 700;
}

/* ── Empty State ── */
.cv-empty {
    text-align: center; padding: 5rem 2rem; color: #94a3b8;
}
.cv-empty-icon {
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(99,102,241,0.06);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.cv-empty svg { width: 44px; height: 44px; opacity: 0.4; }
.cv-empty h5 { font-family: 'Sora', sans-serif; font-weight: 800; color: #64748b; margin-bottom: 0.5rem; font-size: 1.1rem; }
.cv-empty p { font-size: 0.875rem; color: #94a3b8; }

/* ── Footer bar ── */
.cv-footer {
    padding: 1rem 1.75rem;
    border-top: 1px solid rgba(0,0,0,0.05);
    background: rgba(248,250,252,0.5);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;
}
[data-theme="dark"] .cv-footer { background: rgba(15,17,30,0.5); border-color: rgba(255,255,255,0.04); }

.cv-footer-info { font-size: 0.8rem; font-weight: 600; color: #94a3b8; }

.cv-btn-export {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; border-radius: 10px; padding: 0.55rem 1.1rem;
    font-size: 0.8rem; font-weight: 700; text-decoration: none;
    transition: all 0.2s; border: none; cursor: pointer;
    box-shadow: 0 4px 14px rgba(99,102,241,0.3);
}
.cv-btn-export:hover { transform: translateY(-1px); color: white; box-shadow: 0 6px 18px rgba(99,102,241,0.35); }

/* ── Timeline (alternate view) ── */
.no-results-msg { display: none; text-align: center; padding: 2rem; color: #94a3b8; font-weight: 600; }

/* Responsive */
@media(max-width: 640px) {
    .cv-hero { padding: 1.5rem; gap: 1.25rem; }
    .cv-name { font-size: 1.4rem; }
    .cv-table thead { display: none; }
    .cv-table td { display: block; padding: 0.4rem 1rem; }
    .cv-table tr { display: block; padding: 0.75rem 0; border-bottom: 1px solid rgba(0,0,0,0.06); }
    .cv-vote-num { font-size: 2.2rem; }
}

/* Print styles */
@media print {
    .cv-back, .cv-search-wrap, .cv-btn-export { display: none !important; }
    .cv-hero { background: #6366f1 !important; -webkit-print-color-adjust: exact; }
    .cv-panel { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
}
</style>

<div class="cv-wrap">

    <a href="add_candidates.php?election_id=<?php echo $election_id; ?>" class="cv-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Candidates
    </a>

    <!-- ── Hero ── -->
    <div class="cv-hero">
        <div class="cv-avatar">
            <?php if($photo): ?>
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="Photo">
            <?php else: ?>
                <?php echo $initials; ?>
            <?php endif; ?>
        </div>
        <div class="cv-info">
            <div class="cv-name"><?php echo htmlspecialchars($candidate['full_name']); ?></div>
            <div class="cv-position">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <?php echo htmlspecialchars($candidate['position_name']); ?>
            </div>
            <div class="cv-meta">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/></svg>
                <?php echo htmlspecialchars($candidate['election_title']); ?>
                &nbsp;·&nbsp; Student ID: <?php echo htmlspecialchars($candidate['student_id']); ?>
            </div>
        </div>
        <div class="cv-vote-pill">
            <div class="cv-vote-num"><?php echo $candidate['vote_count']; ?></div>
            <div class="cv-vote-lbl"><?php echo $candidate['vote_count'] == 1 ? 'Vote' : 'Votes'; ?></div>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="cv-stats">
        <div class="cv-stat">
            <div class="cv-stat-icon purple">🗳️</div>
            <div>
                <div class="cv-stat-val"><?php echo count($voters); ?></div>
                <div class="cv-stat-lbl">Total Voters</div>
            </div>
        </div>
        <div class="cv-stat">
            <div class="cv-stat-icon green">📊</div>
            <div>
                <?php $share = $total_election_voters > 0 ? round((count($voters) / $total_election_voters) * 100, 1) : 0; ?>
                <div class="cv-stat-val"><?php echo $share; ?>%</div>
                <div class="cv-stat-lbl">Share of Total Votes Cast</div>
            </div>
        </div>
        <div class="cv-stat">
            <div class="cv-stat-icon amber">👥</div>
            <div>
                <?php $turnout = $total_students > 0 ? round((count($voters) / $total_students) * 100, 1) : 0; ?>
                <div class="cv-stat-val"><?php echo $turnout; ?>%</div>
                <div class="cv-stat-lbl">Student Turnout</div>
            </div>
        </div>
        <?php if(count($voters) > 0): ?>
        <div class="cv-stat">
            <div class="cv-stat-icon purple">🕐</div>
            <div>
                <div class="cv-stat-val" style="font-size:1rem;"><?php echo date('M d', strtotime($voters[0]['voted_at'])); ?></div>
                <div class="cv-stat-lbl">Latest Vote</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Voters Panel ── -->
    <div class="cv-panel">
        <!-- Head -->
        <div class="cv-panel-head">
            <div class="cv-panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                All Voters for <em style="font-style:normal;color:#6366f1;margin-left:4px;"><?php echo htmlspecialchars($candidate['full_name']); ?></em>
            </div>
            <span class="cv-count-badge"><?php echo count($voters); ?> voter<?php echo count($voters) != 1 ? 's' : ''; ?></span>
        </div>

        <?php if(count($voters) > 0): ?>

        <!-- Search -->
        <div class="cv-search-wrap">
            <div class="cv-search">
                <span class="cv-search-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" id="voterSearch" placeholder="Search by name or ID…" oninput="filterVoters()">
            </div>
            <span class="cv-count-badge" id="visibleCount"><?php echo count($voters); ?> showing</span>
        </div>

        <!-- Table -->
        <div style="overflow-x: auto;">
            <table class="cv-table" id="voterTable">
                <thead>
                    <tr>
                        <th style="width:56px;">#</th>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Voted At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($voters as $i => $voter): ?>
                    <?php
                    $vphoto = '';
                    if(!empty($voter['profile_photo']) && file_exists('../' . $voter['profile_photo'])) {
                        $vphoto = '../' . $voter['profile_photo'];
                    }
                    $vi = '';
                    if($voter['full_name']) {
                        foreach(explode(' ', $voter['full_name']) as $p) { if($p) $vi .= strtoupper($p[0]); }
                        $vi = substr($vi, 0, 2);
                    } else { $vi = '?'; }
                    ?>
                    <tr class="voter-row" data-name="<?php echo strtolower($voter['full_name'] ?? ''); ?>" data-id="<?php echo strtolower($voter['student_id']); ?>">
                        <td><span class="cv-row-num"><?php echo $i + 1; ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.85rem;">
                                <div class="cv-v-avatar">
                                    <?php if($vphoto): ?>
                                        <img src="<?php echo htmlspecialchars($vphoto); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo $vi; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="cv-v-name"><?php echo htmlspecialchars($voter['full_name'] ?? 'Unknown Student'); ?></div>
                                    <?php if(!empty($voter['contact_number'])): ?>
                                    <div class="cv-v-id"><?php echo htmlspecialchars($voter['contact_number']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cv-v-id" style="font-size:0.9rem;color:#374151;"><?php echo htmlspecialchars($voter['student_id']); ?></div>
                        </td>
                        <td>
                            <div class="cv-voted-date"><?php echo date('M d, Y', strtotime($voter['voted_at'])); ?></div>
                            <div class="cv-voted-time"><?php echo date('h:i A', strtotime($voter['voted_at'])); ?></div>
                        </td>
                        <td>
                            <span class="cv-time-badge">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Voted
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="no-results-msg" id="noResults">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom:12px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <div style="font-weight:700;color:#64748b;">No results found</div>
        </div>

        <!-- Footer -->
        <div class="cv-footer">
            <div class="cv-footer-info">
                Showing <span id="shownCount"><?php echo count($voters); ?></span> of <?php echo count($voters); ?> voters
            </div>
            <button onclick="window.print()" class="cv-btn-export" style="background:linear-gradient(135deg,#475569,#334155);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print
            </button>
        </div>

        <?php else: ?>
        <div class="cv-empty">
            <div class="cv-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </div>
            <h5>No votes yet</h5>
            <p>No students have voted for <?php echo htmlspecialchars($candidate['full_name']); ?> yet.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function filterVoters() {
    const q = document.getElementById('voterSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.voter-row');
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const id   = row.dataset.id   || '';
        const match = !q || name.includes(q) || id.includes(q);
        row.style.display = match ? '' : 'none';
        if(match) visible++;
    });

    document.getElementById('visibleCount').textContent = visible + ' showing';
    document.getElementById('shownCount').textContent = visible;

    const noRes = document.getElementById('noResults');
    if(noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
