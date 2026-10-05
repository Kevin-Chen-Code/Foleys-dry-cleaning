<?php // Self-contained: edit all home-page HTML and CSS in this one file.
$isAdmin = (bool)$this->request->getSession()->read('Foleys.admin');
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Foley's List - Dry cleaning service</title>
        <!-- Branding: replaces CakePHP's default browser-tab icon. -->
        <link rel="icon" type="image/png" href="/img/foleys-list-icon.png">
<style>
:root{--navy:#10213a;--deep:#09162b;--green:#2bc866;--ink:#1b304d;--paper:#f6f7f9}*{box-sizing:border-box}html,body{margin:0;width:100%;min-height:100%;font-family:Arial,sans-serif;background:var(--paper);color:var(--ink)}a{text-decoration:none}.site-header{height:78px;background:#193451;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 5%;width:100%}.brand{display:block;line-height:0}.brand img{display:block;width:165px;height:auto}.sign-in{background:#294564;color:#fff;padding:.7rem 1rem;border-radius:5px;font-size:.82rem;font-weight:700}.hero{width:100%;min-height:455px;padding:70px max(6%,calc((100% - 1180px)/2));background:linear-gradient(90deg,#0c1b33ef,#0b1a31d7),radial-gradient(circle at 80% 25%,#314b6d,#09162b 65%);color:#fff}.eyebrow{text-transform:uppercase;color:#59dfa0;font-weight:800;font-size:.7rem;letter-spacing:.08em}.hero h1{font:3.7rem/1.04 Georgia,serif;margin:16px 0 22px;max-width:680px}.hero p{font-size:1.08rem;line-height:1.65;color:#d1dbea;max-width:590px}.button{display:inline-block;margin:20px 8px 0 0;padding:15px 28px;border-radius:5px;font-size:.86rem;font-weight:800;letter-spacing:.03em}.button-primary{background:#2bc866;color:#fff}.button-dark{background:#050911;color:#fff}.how{max-width:1180px;margin:0 auto;padding:62px 24px 70px;text-align:center}.how h2{font:2.7rem Georgia,serif;margin:16px 0 34px}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;text-align:left}.step{background:#fff;border:1px solid #dbe2eb;border-radius:10px;padding:23px;min-height:210px}.number{width:35px;height:35px;border-radius:50%;display:grid;place-items:center;background:#fff1c4;color:#a77509;font-weight:800}.step h3{font:1.35rem Georgia,serif;margin:20px 0 12px}.step p{line-height:1.55;color:#526579}.site-footer{background:#0c1b33;color:#d5ddeb;padding:32px 5%;font-size:.9rem}.site-footer strong{color:#fff;font-size:1rem}@media(max-width:720px){.hero{padding:55px 7%}.hero h1{font-size:2.65rem}.steps{grid-template-columns:1fr}.site-header{padding:0 6%}.brand img{width:145px}}
</style>
<style>.admin-links{display:flex;align-items:center;gap:14px}.admin-links a{color:#fff;font-size:.78rem}@media(max-width:720px){.admin-links a:not(.sign-in){display:none}}</style>
</head>

<body>
<header class="site-header">
    <a class="brand" href="/" aria-label="Foley's List home page">
        <img src="/img/foleys-list-logo.png" alt="Foley's List">
    </a>
        <?php if ($isAdmin): ?>
            <div class="admin-links">
                <a href="/admin/incoming-requests">Incoming requests</a>
                <a href="/admin/manage-pricing">Manage pricing</a>
                <a href="/admin/weekly-report">Weekly report</a>
                <a href="/admin/analytics">Analytics</a>
                <a class="sign-in" href="/admin/logout">Sign out as administrator</a>
            </div>
        <?php else: ?>
            <a class="sign-in" href="/admin/login">Sign in as administrator</a>
        <?php endif; ?>
    </header>
<main>
    <section class="hero">
        <div class="eyebrow">Chambers priority care</div>
        <h1>Crisp collars, pristine robes,<br>impeccable timing</h1>
        <p>Introducing our dry cleaning dispatch service for members of Foley's List. Submit your pieces online, leave them with clerks, and retrieve them in absolute court-ready perfection.</p>
        <a class="button button-primary" href="/request">SUBMIT A REQUEST</a>
        <a class="button button-dark" href="/prices">VIEW PRICES</a>
    </section>
    <section class="how">
        <div class="eyebrow" style="color:#218e8d">Chambers protocol</div>
        <h2>How the service operates</h2>
        <div class="steps">
            <article class="step">
            <div class="number">1</div>
            <h3>Register your items</h3>
            <p>Select your garments with a few simple clicks.</p>
        </article><article class="step">
            <div class="number">2</div>
            <h3>Get an instant quote</h3>
            <p>Pricing and discounts are calculated automatically.</p>
        </article>
        <article class="step">
            <div class="number">3</div>
            <h3>We handle the rest</h3>
            <p>Clerks collect, return, and deliver your garments.</p>
        </article>
        </div>
    </section>
</main>
<footer class="site-footer">
    <h3>
        Foley's List dry cleaning portal:
    </h3>
    <p>
        A dedicated premium service for members of Foley's List, ensuring pristine court presentation and garment care daily.
    </p>
    <p>
        © 2026 Foley's List Pty Ltd. All rights reserved. 
    </p>
</footer>
</body>
</html>
