<?php
session_start();

require_once __DIR__ . '/connection.php';

// --- Token gate ---
$token = trim($_GET['t'] ?? '');

if ($token === '') {
    header('Location: https://rielcode.com');
    exit;
}

// Look up token in invites table
$stmt = $conn->prepare("SELECT id, used_at FROM testimonial_invites WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$invite = $result->fetch_assoc();
$stmt->close();

if (!$invite) {
    // Invalid token
    header('Location: https://rielcode.com');
    exit;
}

if ($invite['used_at'] !== null) {
    // Already used
    header('Location: https://rielcode.com?testimonial=used');
    exit;
}

// Valid token -- show form
$_SESSION['invite_token'] = $token;

$_SESSION['form_loaded_at'] = time();
$csrf = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old']    ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

function old(string $k, string $default = ''): string {
    global $old;
    return htmlspecialchars($old[$k] ?? $default, ENT_QUOTES, 'UTF-8');
}
function hasError(string $k): bool {
    global $errors;
    return isset($errors[$k]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Your Experience | Rielcode</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="IMG/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Syne:wght@700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/testimonial-form.css">
</head>
<body class="rc-redesign">
    <div class="noise"></div>

    <div class="page-topbar">
        <a href="https://rielcode.com" class="back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to rielcode.com
        </a>
        <a href="https://rielcode.com" class="topbar-logo">
            <img src="IMG/Rielcode Logo Transparent.png" alt="Rielcode">
        </a>
    </div>

    <main class="page-wrapper">

        <div class="intro" style="animation-delay:0.05s">
            <div class="tag-line rc-eyebrow">client testimonial</div>
            <h1>Tell us about your <br><span class="gradient-text">Rielcode experience</span></h1>
            <p>Your feedback shapes how Rielcode grows and helps future clients make confident decisions. Takes about 2 minutes.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-banner" style="animation-delay:0.1s">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="testimonial-form" method="POST" action="submit.php?t=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" novalidate style="animation-delay:0.15s">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="form-section">
                <div class="section-label">01 &mdash; About you</div>
                <div class="row-2col">
                    <div class="field-group <?= hasError('client_name') ? 'has-error' : '' ?>">
                        <label class="field-label" for="client_name">Your name <span class="req">*</span></label>
                        <input type="text" id="client_name" name="client_name" maxlength="80" required value="<?= old('client_name') ?>">
                    </div>
                    <div class="field-group <?= hasError('role_title') ? 'has-error' : '' ?>">
                        <label class="field-label" for="role_title">Role / title <span class="req">*</span></label>
                        <input type="text" id="role_title" name="role_title" maxlength="80" required placeholder="e.g. Owner, Marketing Manager" value="<?= old('role_title') ?>">
                    </div>
                </div>
                <div class="field-group <?= hasError('business_name') ? 'has-error' : '' ?>">
                    <label class="field-label" for="business_name">Business / company name <span class="req">*</span></label>
                    <input type="text" id="business_name" name="business_name" maxlength="100" required value="<?= old('business_name') ?>">
                </div>
            </div>

            <div class="form-section">
                <div class="section-label">02 &mdash; Your project</div>
                <div class="field-group <?= hasError('rating') ? 'has-error' : '' ?>">
                    <label class="field-label">Overall rating <span class="req">*</span></label>
                    <div class="rating-group">
                        <?php
                        $current = (int)($old['rating'] ?? 0);
                        foreach ([5,4,3,2,1] as $r):
                            $checked = $current === $r ? 'checked' : '';
                        ?>
                            <input type="radio" id="rating-<?= $r ?>" name="rating" value="<?= $r ?>" <?= $checked ?> required>
                            <label for="rating-<?= $r ?>" title="<?= $r ?> star<?= $r > 1 ? 's' : '' ?>">&#9733;</label>
                        <?php endforeach; ?>
                    </div>
                    <span class="hint">1 = poor &mdash; 5 = excellent</span>
                </div>
                <div class="field-group <?= hasError('project_url') ? 'has-error' : '' ?>">
                    <label class="field-label" for="project_url">Project URL <span class="req">*</span></label>
                    <input type="url" id="project_url" name="project_url" maxlength="255" required placeholder="https://yoursite.com" value="<?= old('project_url') ?>">
                    <span class="hint">The site Rielcode built or worked on for you.</span>
                </div>
            </div>

            <div class="form-section">
                <div class="section-label">03 &mdash; Your story</div>
                <div class="field-group <?= hasError('problem_before') ? 'has-error' : '' ?>">
                    <label class="field-label" for="problem_before">What problem did you have before working with Rielcode? <span class="req">*</span></label>
                    <textarea id="problem_before" name="problem_before" maxlength="300" minlength="40" required data-counter><?= old('problem_before') ?></textarea>
                    <div class="field-meta">
                        <span class="hint">Min 40 characters. The "before" picture.</span>
                        <span class="char-count" data-target="problem_before">0 / 300</span>
                    </div>
                </div>
                <div class="field-group <?= hasError('solution_after') ? 'has-error' : '' ?>">
                    <label class="field-label" for="solution_after">What did Rielcode build for you, and how did it solve that problem? <span class="req">*</span></label>
                    <textarea id="solution_after" name="solution_after" maxlength="500" minlength="50" required data-counter><?= old('solution_after') ?></textarea>
                    <div class="field-meta">
                        <span class="hint">Min 50 characters. The main story.</span>
                        <span class="char-count" data-target="solution_after">0 / 500</span>
                    </div>
                </div>
                <div class="field-group <?= hasError('recommendation') ? 'has-error' : '' ?>">
                    <label class="field-label" for="recommendation">Would you recommend Rielcode to others? Why? <span class="req">*</span></label>
                    <textarea id="recommendation" name="recommendation" maxlength="300" minlength="40" required data-counter><?= old('recommendation') ?></textarea>
                    <div class="field-meta">
                        <span class="hint">Min 40 characters.</span>
                        <span class="char-count" data-target="recommendation">0 / 300</span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-label">04 &mdash; Optional extras</div>
                <div class="field-group <?= hasError('headline') ? 'has-error' : '' ?>">
                    <label class="field-label" for="headline">One-line headline summary</label>
                    <input type="text" id="headline" name="headline" maxlength="120" placeholder='e.g. "Rielcode delivered a clean, fast site in 2 weeks."' value="<?= old('headline') ?>">
                    <span class="hint">If blank, we'll pull a quote from your answers above.</span>
                </div>
                <div class="field-group <?= hasError('client_email') ? 'has-error' : '' ?>">
                    <label class="field-label" for="client_email">Email</label>
                    <input type="email" id="client_email" name="client_email" maxlength="120" placeholder="for follow-up only, never displayed" value="<?= old('client_email') ?>">
                    <span class="hint">Only used if we need to clarify something. Never published.</span>
                </div>
            </div>

            <div class="consent-group <?= hasError('consent_given') ? 'has-error' : '' ?>">
                <input type="checkbox" id="consent_given" name="consent_given" value="1" required <?= !empty($old['consent_given']) ? 'checked' : '' ?>>
                <label for="consent_given">
                    I allow Rielcode to display this testimonial publicly on rielcode.com and use quotes from it in marketing materials. <span class="req">*</span>
                </label>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <span class="btn-text">Submit Testimonial</span>
                <svg class="btn-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </form>

        <p class="footer-note">Your testimonial will be reviewed before going public.</p>

    </main>

    <script>
        document.querySelectorAll('textarea[data-counter]').forEach(t => {
            const counter = document.querySelector(`[data-target="${t.id}"]`);
            const max = parseInt(t.getAttribute('maxlength'), 10);
            const update = () => {
                const len = t.value.length;
                counter.textContent = `${len} / ${max}`;
                counter.classList.toggle('over', len > max);
            };
            t.addEventListener('input', update);
            update();
        });

        const form = document.querySelector('form.testimonial-form');
        const btn = document.getElementById('submitBtn');
        form.addEventListener('submit', () => {
            btn.disabled = true;
            btn.querySelector('.btn-text').textContent = 'Submitting...';
        });

        // Fade-in sections on scroll
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.form-section').forEach(s => observer.observe(s));
    </script>
</body>
</html>
