<?php
$file = file_get_contents('add_candidates.php');

$newStyle = "<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Sora:wght@600;700&display=swap');
  
  .glass-panel {
      background: rgba(255, 255, 255, 0.4);
      backdrop-filter: blur(25px) saturate(180%);
      -webkit-backdrop-filter: blur(25px) saturate(180%);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
      height: fit-content;
  }
  
  .ac-header {
      background: rgba(255, 255, 255, 0.45);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.6);
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.03);
  }
  
  .ac-logo { display:flex; align-items:center; gap:10px; }
  .ac-logo-icon { width:34px; height:34px; background:linear-gradient(135deg,#5b4ef0,#8b5cf6); border-radius:10px; display:flex; align-items:center; justify-content:center; }
  .ac-logo-icon svg { width:18px; height:18px; stroke:#fff; fill:none; stroke-width:2.5; }
  .ac-logo-text { font-family:'Sora',sans-serif; font-size:17px; font-weight:700; color:#1a1d2e; margin:0;}
  .ac-logo-text span { color:#5b4ef0; }
  .ac-header-right { display:flex; align-items:center; gap:10px; }
  .ac-election-badge { background:rgba(255, 243, 205, 0.4); border:1px solid rgba(255, 230, 156, 0.5); color:#664d03; font-size:11px; font-weight:700; padding:5px 12px; border-radius:20px; letter-spacing:.04em; backdrop-filter:blur(5px); }
  .ac-election-badge.active { background:rgba(209, 231, 221, 0.4); border:1px solid rgba(163, 207, 187, 0.5); color:#0f5132; }
  .ac-date-badge { background:rgba(241, 240, 254, 0.4); border:1.5px solid rgba(199, 210, 254, 0.5); color:#4338ca !important; font-size:11px; font-weight:600; padding:5px 12px; border-radius:20px; backdrop-filter:blur(5px); }
  
  .ac-page-title { margin-bottom: 16px; display:flex; align-items:center; gap:12px; }
  .ac-page-title h1 { font-family:'Sora',sans-serif; font-size:22px; font-weight:700; color:#1a1d2e; margin:0;}
  
  .ac-info-banner { margin-bottom: 20px; background:rgba(239, 246, 255, 0.4); border:1px solid rgba(191, 219, 254, 0.5); border-radius:16px; padding:14px 18px; display:flex; gap:12px; align-items:flex-start; backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); }
  .ac-info-banner svg { flex-shrink:0; margin-top:1px; }
  .ac-info-banner p { font-size:13px; color:#1e40af; line-height:1.6; margin:0; }
  
  .ac-body { display:grid; grid-template-columns:380px 1fr; gap:24px; margin-bottom: 30px; }
  
  .ac-form-title { font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:#1a1d2e; display:flex; align-items:center; gap:8px; margin-bottom:20px; padding-bottom:14px; border-bottom:0.5px solid rgba(0,0,0,0.05); }
  .ac-field { margin-bottom:18px; }
  .ac-label { font-size:12px; font-weight:600; color:#6b7280; letter-spacing:.04em; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
  
  /* GLASS DROPDOWNS */
  .ac-select { 
      width:100%; 
      border:1.5px solid rgba(255,255,255,0.7); 
      border-radius:12px; 
      padding:12px 18px; 
      font-family:'DM Sans',sans-serif; 
      font-size:14px; 
      color:#1a1d2e; 
      background:rgba(255,255,255,0.45); 
      backdrop-filter:blur(12px); 
      -webkit-backdrop-filter:blur(12px); 
      outline:none; 
      transition:all .3s ease; 
      box-shadow:0 4px 12px rgba(100,120,240,0.05); 
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235b4ef0' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\");
      background-repeat: no-repeat;
      background-position: right 18px center;
  }
  .ac-select:focus { border-color:#5b4ef0; background:rgba(255,255,255,0.7); box-shadow:0 8px 24px rgba(91,78,240,0.12); }
  
  .ac-textarea { 
      width:100%; 
      border:1.5px solid rgba(255,255,255,0.7); 
      border-radius:12px; 
      padding:12px 18px; 
      font-family:'DM Sans',sans-serif; 
      font-size:14px; 
      color:#1a1d2e; 
      background:rgba(255,255,255,0.4); 
      backdrop-filter:blur(12px); 
      -webkit-backdrop-filter:blur(12px); 
      outline:none; 
      transition:all .3s ease; 
      resize:vertical; 
      min-height:100px; 
      box-shadow:0 4px 12px rgba(100,120,240,0.05);
  }
  .ac-textarea:focus { border-color:#5b4ef0; background:rgba(255,255,255,0.7); box-shadow:0 8px 24px rgba(91,78,240,0.12); }";

$file = preg_replace('/<style>.*?<\/style>/s', $newStyle, $file);

file_put_contents('add_candidates.php', $file);
echo "Glass style applied.";
?>
