<?php
// filename: index.php
// location: root/index.php

require_once 'config/database.php';

$active_election = $pdo->query("
    SELECT * FROM elections 
    WHERE is_active = 1 AND NOW() BETWEEN start_date AND end_date 
    LIMIT 1
")->fetch();

$upcoming_elections = $pdo->query("
    SELECT * FROM elections 
    WHERE NOW() < start_date AND is_active = 0
    ORDER BY start_date ASC LIMIT 3
")->fetchAll();

$total_elections  = $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$total_voters     = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_votes      = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$total_candidates = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

include 'includes/header.php';
?>

<style>
/* ── Index Page Styles ── */
/* body/bg already set by header.php */

.page-wrap {
    position: relative; z-index: 1;
    padding-bottom: 5rem;
}

/* ── glass helper ── */
.glass {
    background: rgba(255,255,255,0.55);
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255,255,255,0.82);
    border-radius: 24px;
    box-shadow: 0 4px 24px rgba(100,120,200,0.08), 0 1px 3px rgba(0,0,0,0.04);
}

/* ── HERO ── */
.hero {
    margin-bottom: 3rem;
    padding: 3.5rem 2.5rem;
    position: relative; overflow: hidden;
    background: rgba(255,255,255,0.45);
    backdrop-filter: blur(24px) saturate(170%);
    -webkit-backdrop-filter: blur(24px) saturate(170%);
    border: 1px solid rgba(255,255,255,0.85);
    border-radius: 28px;
    box-shadow: 0 8px 40px rgba(100,120,200,0.1);
}

/* decorative orbs inside hero */
.hero::before, .hero::after {
    content: ''; position: absolute; border-radius: 50%;
    filter: blur(55px); pointer-events: none;
}
.hero::before {
    width: 320px; height: 320px; top: -80px; right: -60px;
    background: rgba(167,139,250,0.22);
    animation: heroOrb 10s ease-in-out infinite alternate;
}
.hero::after {
    width: 220px; height: 220px; bottom: -60px; left: -40px;
    background: rgba(99,179,237,0.2);
    animation: heroOrb 13s ease-in-out infinite alternate-reverse;
}
@keyframes heroOrb {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(30px,20px) scale(1.1); }
}

.hero-inner { position: relative; z-index: 1; }

.hero-eyebrow {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.22);
    border-radius: 99px; padding: 0.3rem 1rem;
    font-size: 0.8rem; font-weight: 700; color: #6366f1;
    letter-spacing: .04em; text-transform: uppercase;
    margin-bottom: 1.25rem;
}
.hero-eyebrow i { font-size: 0.9rem; }

.hero-title {
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 800; color: #0f172a;
    letter-spacing: -0.035em; line-height: 1.15;
    margin-bottom: 1rem;
}
.hero-title em { font-style: normal; color: #6366f1; }

.hero-sub {
    font-size: 1.05rem; color: #475569; line-height: 1.7;
    max-width: 560px; margin-bottom: 2rem;
}

/* hero stat pills */
.hero-stats { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.h-stat {
    display: flex; align-items: center; gap: 0.65rem;
    background: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.95);
    border-radius: 14px; padding: 0.75rem 1.1rem;
    backdrop-filter: blur(10px);
    transition: transform .2s ease, box-shadow .2s ease;
}
.h-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.1); }
.h-stat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;
}
.h-stat-icon.c1 { background: rgba(99,102,241,0.12); }
.h-stat-icon.c2 { background: rgba(52,211,153,0.15); }
.h-stat-icon.c3 { background: rgba(251,146,60,0.12); }
.h-stat-num  { font-size: 1.3rem; font-weight: 800; color: #0f172a; line-height: 1; }
.h-stat-lbl  { font-size: 0.72rem; color: #64748b; font-weight: 500; margin-top: 1px; }

/* hero illustration */
.hero-art {
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.9);
    border-radius: 24px; height: 100%; min-height: 240px;
    backdrop-filter: blur(10px);
}
.hero-icon-big {
    font-size: 7rem; line-height: 1;
    background: linear-gradient(135deg, #6366f1, #a78bfa);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    animation: floatIcon 4s ease-in-out infinite;
}
@keyframes floatIcon {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-12px); }
}

/* ── LIVE ALERT ── */
.live-alert {
    padding: 1.25rem 1.5rem; margin-bottom: 2rem;
    background: rgba(220,252,231,0.7);
    border: 1px solid rgba(134,239,172,0.6);
    border-radius: 20px;
    backdrop-filter: blur(14px);
    box-shadow: 0 4px 16px rgba(34,197,94,0.08);
    animation: slideDown .4s ease;
}
@keyframes slideDown {
    from { opacity:0; transform:translateY(-10px); }
    to   { opacity:1; transform:translateY(0); }
}
.live-badge-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3);
    border-radius: 99px; padding: 0.25rem 0.8rem;
    font-size: 0.75rem; font-weight: 700; color: #16a34a; letter-spacing:.04em;
    text-transform: uppercase; flex-shrink: 0;
}
.live-dot { width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);animation:pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.live-alert h4 { font-size: 1rem; font-weight: 700; color: #166534; margin: 0 0 0.2rem; }
.live-alert p  { font-size: 0.82rem; color: #15803d; margin: 0; }
.btn-vote-now {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff; border: none; border-radius: 99px;
    padding: 0.6rem 1.4rem; font-size: 0.875rem; font-weight: 700;
    text-decoration: none; white-space: nowrap;
    box-shadow: 0 4px 14px rgba(34,197,94,0.35);
    transition: all .2s ease;
}
.btn-vote-now:hover { transform: scale(1.04); opacity:.9; color:#fff; text-decoration:none; }

/* ── SECTION LABEL ── */
.section-label {
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing:.08em; color: #6366f1; margin-bottom: 0.5rem;
}
.section-title {
    font-size: clamp(1.4rem,2.5vw,1.85rem); font-weight: 800;
    color: #0f172a; letter-spacing:-0.025em; margin-bottom: 0.5rem;
}
.section-sub { font-size: 0.9rem; color: #64748b; margin-bottom: 2rem; }

/* ── FEATURE CARDS ── */
.feature-card {
    height: 100%; padding: 2rem 1.75rem;
    transition: transform .25s ease, box-shadow .25s ease;
    display: flex; flex-direction: column;
    animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
}
.feature-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(99,102,241,0.13); }
.fc-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; margin-bottom: 1.25rem; flex-shrink: 0;
}
.fc-icon.green   { background: rgba(52,211,153,0.15); }
.fc-icon.indigo  { background: rgba(99,102,241,0.12); }
.fc-icon.amber   { background: rgba(251,191,36,0.15); }
.fc-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.6rem; }
.fc-desc  { font-size: 0.875rem; color: #64748b; line-height: 1.7; flex: 1; margin-bottom: 1.25rem; }

.fc-divider { border-color: rgba(200,210,240,0.35); margin: 1rem 0; }

.btn-glass {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.6rem 1rem; border-radius: 12px;
    font-size: 0.85rem; font-weight: 600;
    text-decoration: none; transition: all .2s ease;
    border: 1px solid rgba(255,255,255,0.75);
    background: rgba(255,255,255,0.6);
    color: #334155 !important;
}
.btn-glass:hover { background: rgba(99,102,241,0.08); border-color: rgba(99,102,241,0.3); color: #6366f1 !important; text-decoration:none; }
.btn-glass.primary {
    background: linear-gradient(135deg,#6366f1,#818cf8);
    color: #fff !important; border-color: transparent;
    box-shadow: 0 4px 14px rgba(99,102,241,.3);
}
.btn-glass.primary:hover { opacity:.9; color:#fff !important; }
.btn-glass.green-fill {
    background: linear-gradient(135deg,#22c55e,#16a34a);
    color:#fff !important; border-color:transparent;
    box-shadow: 0 4px 14px rgba(34,197,94,.28);
    animation: gentlePulse 2.5s infinite;
}
.btn-glass.green-fill:hover { opacity:.9; color:#fff !important; text-decoration:none; }
@keyframes gentlePulse { 0%,100%{transform:scale(1)}50%{transform:scale(1.025)} }

.fc-footer { font-size: 0.75rem; color: #94a3b8; }
.fc-footer i { color: #22c55e; }
.fc-footer .dot { color: #cbd5e1; margin: 0 0.4rem; }

/* countdown badge */
.countdown-pill {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: rgba(251,191,36,0.12); border: 1px solid rgba(251,191,36,0.3);
    border-radius: 99px; padding: 0.3rem 0.8rem;
    font-size: 0.78rem; font-weight: 700; color: #92400e;
}
.election-mini {
    background: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.9);
    border-radius: 14px; padding: 0.9rem 1rem; margin-bottom: 1rem;
}
.election-mini.live-border { border-left: 3px solid #22c55e; }
.election-mini.upcoming-border { border-left: 3px solid #fbbf24; }
.election-mini-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 0.3rem; }
.election-mini-date  { font-size: 0.75rem; color: #64748b; display:flex;align-items:center;gap:.35rem; }

/* ── STATS STRIP ── */
.stats-strip {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 1rem; margin: 3rem 0;
}
@media(max-width:900px){ .stats-strip{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .stats-strip{ grid-template-columns:1fr; } }

.ss-card {
    padding: 1.5rem 1.25rem; text-align: center;
    transition: transform .25s ease, box-shadow .25s ease;
    animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
    position: relative; overflow: hidden;
}
.ss-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(99,102,241,0.12); }
.ss-card:nth-child(1){ animation-delay:.05s } .ss-card:nth-child(2){ animation-delay:.1s }
.ss-card:nth-child(3){ animation-delay:.15s } .ss-card:nth-child(4){ animation-delay:.2s }
.ss-blob {
    position:absolute;width:100px;height:100px;border-radius:50%;
    right:-20px;top:-20px;filter:blur(32px);opacity:.2;pointer-events:none;
}
.ss-card.c1 .ss-blob{background:#818cf8} .ss-card.c2 .ss-blob{background:#34d399}
.ss-card.c3 .ss-blob{background:#f472b6} .ss-card.c4 .ss-blob{background:#fb923c}
.ss-icon { font-size: 1.5rem; margin-bottom: 0.75rem; display:block; }
.ss-num {
    font-size: 2rem; font-weight: 800; color: #0f172a;
    letter-spacing: -0.03em; line-height: 1;
}
.ss-lbl { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing:.06em; margin-top:.4rem; }

/* ── UPCOMING ELECTIONS ── */
.upcoming-card {
    padding: 1.4rem 1.5rem;
    transition: transform .2s ease, box-shadow .2s ease;
}
.upcoming-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(99,102,241,0.1); }
.uc-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(251,191,36,0.14); border: 1px solid rgba(251,191,36,0.3);
    border-radius: 99px; padding: 0.2rem 0.7rem;
    font-size: 0.7rem; font-weight: 700; color: #92400e;
    letter-spacing:.04em; text-transform:uppercase; margin-bottom: 0.65rem;
}
.uc-title { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
.uc-meta  { font-size: 0.8rem; color: #64748b; display:flex;align-items:center;gap:.35rem;margin-bottom:.25rem; }
.uc-countdown {
    display: inline-flex; align-items: center; gap: .35rem;
    background: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,.95);
    border-radius: 99px; padding: .22rem .7rem;
    font-size: 0.75rem; font-weight: 600; color: #475569;
    margin-top: .5rem;
}

/* ── TRUST BADGES ── */
.trust-strip { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin: 2rem 0; }
.trust-item {
    display: flex; align-items: center; gap: 0.75rem;
    background: rgba(255,255,255,0.65); border: 1px solid rgba(255,255,255,0.9);
    border-radius: 16px; padding: 0.85rem 1.2rem;
    backdrop-filter: blur(12px); flex: 1; min-width: 180px; max-width: 240px;
    transition: transform .2s ease;
}
.trust-item:hover { transform: translateY(-2px); }
.trust-icon { font-size: 1.4rem; flex-shrink: 0; }
.trust-title { font-size: 0.875rem; font-weight: 700; color: #1e293b; }
.trust-sub   { font-size: 0.75rem; color: #94a3b8; margin-top: 1px; }

/* ── CTA BANNER ── */
.cta-banner {
    padding: 2.5rem 2rem; margin-top: 3rem;
    background: rgba(99,102,241,0.07);
    border: 1px solid rgba(99,102,241,0.15);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1.5rem;
    border-radius: 24px;
    position: relative; overflow: hidden;
}
.cta-banner::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background: linear-gradient(135deg, rgba(99,102,241,0.06) 0%, rgba(167,139,250,0.06) 100%);
}
.cta-banner h2 { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin: 0; letter-spacing:-.025em; }
.cta-banner p  { font-size: 0.9rem; color: #64748b; margin: 0.35rem 0 0; }
.btn-cta {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    color: #fff; border: none; border-radius: 99px;
    padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 700;
    text-decoration: none; white-space: nowrap;
    box-shadow: 0 6px 20px rgba(99,102,241,0.35);
    transition: all .2s ease; flex-shrink: 0;
}
.btn-cta:hover { transform: scale(1.04); opacity:.9; color:#fff; text-decoration:none; }

/* ── FOOTER ── */
.site-footer {
    margin-top: 3.5rem;
    padding: 2rem 0 1rem;
    border-top: 1px solid rgba(200,210,240,0.35);
    color: #94a3b8; font-size: 0.82rem;
}
.site-footer a { color: #94a3b8; text-decoration: none; transition: color .2s; }
.site-footer a:hover { color: #6366f1; }
.site-footer strong { color: #475569; }

/* animation */
@keyframes popIn {
    from { opacity:0; transform:translateY(16px) scale(.96); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
</style>

<div class="page-wrap">

    <!-- ── HERO ── -->
    <section class="hero mb-4" style="animation: popIn .5s ease both;">
        <div class="hero-inner row align-items-center gy-4">
            <div class="col-lg-7">
                <div class="hero-eyebrow">
                    <i class="bi bi-shield-check"></i> Secure &amp; Transparent
                </div>
                <h1 class="hero-title">Student Council<br><em>Voting System</em></h1>
                <p class="hero-sub">
                    A modern, secure, and transparent platform for student democracy.
                    Cast your vote with confidence and make your voice heard.
                </p>
                <div class="hero-stats">
                    <div class="h-stat">
                        <div class="h-stat-icon c1">🗳️</div>
                        <div>
                            <div class="h-stat-num"><?php echo number_format($total_elections); ?></div>
                            <div class="h-stat-lbl">Elections</div>
                        </div>
                    </div>
                    <div class="h-stat">
                        <div class="h-stat-icon c2">👥</div>
                        <div>
                            <div class="h-stat-num"><?php echo number_format($total_voters); ?></div>
                            <div class="h-stat-lbl">Voters</div>
                        </div>
                    </div>
                    <div class="h-stat">
                        <div class="h-stat-icon c3">✅</div>
                        <div>
                            <div class="h-stat-num"><?php echo number_format($total_votes); ?></div>
                            <div class="h-stat-lbl">Votes Cast</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-flex">
                <div class="hero-art w-100">
                    <div class="hero-icon-big">
                        <i class="bi bi-check2-square"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── LIVE ELECTION ALERT ── -->
    <?php if($active_election): ?>
    <div class="live-alert d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="live-badge-pill">
                <span class="live-dot"></span> Live Now
            </div>
            <div>
                <h4>🗳️ <?php echo htmlspecialchars($active_election['title']); ?></h4>
                <p><i class="bi bi-clock"></i> Ends: <?php echo date('M d, Y h:i A', strtotime($active_election['end_date'])); ?></p>
            </div>
        </div>
        <a href="student/login.php" class="btn-vote-now">
            <i class="bi bi-check2-circle"></i> Vote Now
        </a>
    </div>
    <?php endif; ?>

    <!-- ── FEATURE CARDS ── -->
    <div class="mb-2">
        <div class="section-label">Get Started</div>
        <h2 class="section-title">What would you like to do?</h2>
        <p class="section-sub">Choose your role to get started with the voting system.</p>
    </div>

    <div class="row g-3 mb-5" id="features">

        <!-- Students -->
        <div class="col-md-4">
            <div class="feature-card glass" style="animation-delay:.05s">
                <div class="fc-icon green">👩‍🎓</div>
                <div class="fc-title">For Students</div>
                <div class="fc-desc">
                    Register with your Student ID and vote for your preferred candidates
                    in a secure, anonymous environment.
                </div>
                <div class="d-grid gap-2 mb-3">
                    <a href="student/register.php" class="btn-glass green-fill">
                        <i class="bi bi-person-plus"></i> Register Now
                    </a>
                    <a href="student/login.php" class="btn-glass">
                        <i class="bi bi-box-arrow-in-right"></i> Student Login
                    </a>
                </div>
                <hr class="fc-divider">
                <div class="fc-footer">
                    <i class="bi bi-check-circle-fill"></i> Secure voting
                    <span class="dot">•</span>
                    <i class="bi bi-shield-check"></i> One vote per student
                </div>
            </div>
        </div>

        <!-- Admins -->
        <div class="col-md-4">
            <div class="feature-card glass" style="animation-delay:.12s">
                <div class="fc-icon indigo">🛡️</div>
                <div class="fc-title">For Administrators</div>
                <div class="fc-desc">
                    Manage elections, upload eligible IDs, add candidates,
                    and view real-time results with comprehensive analytics.
                </div>
                <div class="d-grid gap-2 mb-3">
                    <a href="admin/login.php" class="btn-glass primary">
                        <i class="bi bi-shield-lock"></i> Admin Login
                    </a>
                    <a href="admin/register.php" class="btn-glass">
                        <i class="bi bi-person-plus"></i> Register Admin
                    </a>
                </div>
                <hr class="fc-divider">
                <div class="fc-footer">
                    <i class="bi bi-bar-chart text-primary"></i> Real-time results
                    <span class="dot">•</span>
                    <i class="bi bi-cloud-upload text-primary"></i> Bulk upload
                </div>
            </div>
        </div>

        <!-- Current Election -->
        <div class="col-md-4">
            <div class="feature-card glass" style="animation-delay:.19s">
                <div class="fc-icon amber">📅</div>
                <div class="fc-title">Current Election</div>

                <?php if($active_election): ?>
                    <div class="election-mini live-border mb-3">
                        <div class="election-mini-title"><?php echo htmlspecialchars($active_election['title']); ?></div>
                        <div class="election-mini-date">
                            <i class="bi bi-clock"></i>
                            Ends <?php echo date('M d, Y h:i A', strtotime($active_election['end_date'])); ?>
                        </div>
                        <div class="mt-2" id="countdown-<?php echo $active_election['id']; ?>">
                            <span class="countdown-pill"><i class="bi bi-hourglass-split"></i> Calculating…</span>
                        </div>
                    </div>
                    <a href="student/login.php" class="btn-glass green-fill w-100 mb-3">
                        <i class="bi bi-check2-circle"></i> Vote Now
                    </a>

                <?php elseif(count($upcoming_elections) > 0): ?>
                    <div class="election-mini upcoming-border mb-3">
                        <div class="election-mini-title"><?php echo htmlspecialchars($upcoming_elections[0]['title']); ?></div>
                        <div class="election-mini-date">
                            <i class="bi bi-calendar"></i>
                            Starts <?php echo date('M d, Y', strtotime($upcoming_elections[0]['start_date'])); ?>
                        </div>
                    </div>
                    <div class="fc-desc">No active election right now. Register to be ready.</div>
                    <a href="student/register.php" class="btn-glass w-100 mb-3">
                        <i class="bi bi-person-plus"></i> Register to Vote
                    </a>

                <?php else: ?>
                    <div class="election-mini mb-3 text-center py-3">
                        <div style="font-size:2rem;opacity:.35">🗳️</div>
                        <div class="fc-footer mt-2">No scheduled elections</div>
                    </div>
                    <div class="fc-desc">Check back later for upcoming elections.</div>
                <?php endif; ?>

                <hr class="fc-divider">
                <div class="fc-footer">
                    <i class="bi bi-calendar-event" style="color:#fbbf24"></i>
                    <?php echo count($upcoming_elections); ?> upcoming election<?php echo count($upcoming_elections) != 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── STATS STRIP ── -->
    <div class="stats-strip">
        <div class="ss-card glass c1">
            <div class="ss-blob"></div>
            <span class="ss-icon">🗳️</span>
            <div class="ss-num"><?php echo number_format($total_elections); ?></div>
            <div class="ss-lbl">Total Elections</div>
        </div>
        <div class="ss-card glass c2">
            <div class="ss-blob"></div>
            <span class="ss-icon">👥</span>
            <div class="ss-num"><?php echo number_format($total_voters); ?></div>
            <div class="ss-lbl">Registered Voters</div>
        </div>
        <div class="ss-card glass c3">
            <div class="ss-blob"></div>
            <span class="ss-icon">🧑‍💼</span>
            <div class="ss-num"><?php echo number_format($total_candidates); ?></div>
            <div class="ss-lbl">Total Candidates</div>
        </div>
        <div class="ss-card glass c4">
            <div class="ss-blob"></div>
            <span class="ss-icon">✅</span>
            <div class="ss-num"><?php echo number_format($total_votes); ?></div>
            <div class="ss-lbl">Votes Cast</div>
        </div>
    </div>

    <!-- ── UPCOMING ELECTIONS ── -->
    <?php if(count($upcoming_elections) > 0): ?>
    <div class="mb-3 mt-1">
        <div class="section-label">Scheduled</div>
        <h2 class="section-title">Upcoming Elections</h2>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach($upcoming_elections as $el):
            $days_until = (new DateTime())->diff(new DateTime($el['start_date']))->days;
        ?>
        <div class="col-md-4">
            <div class="upcoming-card glass">
                <div class="uc-badge"><i class="bi bi-calendar-event"></i> Upcoming</div>
                <div class="uc-title"><?php echo htmlspecialchars($el['title']); ?></div>
                <div class="uc-meta"><i class="bi bi-calendar3"></i><?php echo date('M d, Y', strtotime($el['start_date'])); ?></div>
                <div class="uc-meta"><i class="bi bi-clock"></i><?php echo date('h:i A', strtotime($el['start_date'])); ?></div>
                <div class="uc-countdown">
                    <i class="bi bi-hourglass"></i> Starts in <?php echo $days_until; ?> day<?php echo $days_until != 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── TRUST BADGES ── -->
    <div class="mb-3">
        <div class="section-label">Why Us</div>
        <h2 class="section-title">Trusted by Students</h2>
        <p class="section-sub">Built on transparency, security, and ease of use.</p>
    </div>
    <div class="trust-strip">
        <div class="trust-item">
            <span class="trust-icon">🔒</span>
            <div><div class="trust-title">Secure</div><div class="trust-sub">256-bit encryption</div></div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">🕵️</span>
            <div><div class="trust-title">Anonymous</div><div class="trust-sub">Private voting</div></div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">⚡</span>
            <div><div class="trust-title">Real-time</div><div class="trust-sub">Instant results</div></div>
        </div>
        <div class="trust-item">
            <span class="trust-icon">📱</span>
            <div><div class="trust-title">Mobile Ready</div><div class="trust-sub">Vote anywhere</div></div>
        </div>
    </div>

    <!-- ── CTA BANNER ── -->
    <div class="cta-banner glass">
        <div>
            <h2>Ready to make your voice heard?</h2>
            <p>Join thousands of students who have already participated in democratic elections.</p>
        </div>
        <a href="student/register.php" class="btn-cta">
            Get Started <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <!-- ── FOOTER ── -->
    <footer class="site-footer">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong>Student Council Voting System</strong><br>
                A secure and transparent platform for student democracy. &copy; <?php echo date('Y'); ?>
            </div>
            <div class="d-flex gap-3 flex-wrap">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Use</a>
                <a href="#">Contact Support</a>
            </div>
        </div>
    </footer>

</div><!-- /page-wrap -->

<script>
// Countdown timer
<?php if($active_election): ?>
(function() {
    const endDate = new Date("<?php echo date('c', strtotime($active_election['end_date'])); ?>").getTime();
    const el = document.getElementById('countdown-<?php echo $active_election['id']; ?>');
    function tick() {
        const dist = endDate - Date.now();
        if(dist <= 0) { el.innerHTML = '<span class="countdown-pill">Election ended</span>'; return; }
        const d = Math.floor(dist/86400000),
              h = Math.floor((dist%86400000)/3600000),
              m = Math.floor((dist%3600000)/60000),
              s = Math.floor((dist%60000)/1000);
        let t = '';
        if(d) t += d+'d ';
        if(h) t += h+'h ';
        if(m) t += m+'m ';
        t += s+'s';
        el.innerHTML = '<span class="countdown-pill"><i class="bi bi-hourglass-split"></i> '+t+' remaining</span>';
    }
    tick(); setInterval(tick, 1000);
})();
<?php endif; ?>

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if(target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
});
</script>

<?php include 'includes/footer.php'; ?>