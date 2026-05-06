<?php
session_start();

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../Rielcode/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../Rielcode/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../Rielcode/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: https://rielcode.com');
    exit;
}

$loadedAt     = (int)($_SESSION['form_loaded_at'] ?? 0);
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken  = $_POST['csrf_token']    ?? '';
$honeypot     = trim($_POST['website']  ?? '');
$inviteToken  = $_SESSION['invite_token'] ?? '';

if ($honeypot !== '' || !hash_equals($sessionToken, $postedToken) || (time() - $loadedAt) < 3) {
    http_response_code(400);
    die('Submission rejected.');
}

// Token must still be in session and must be unused
if ($inviteToken === '') {
    header('Location: https://rielcode.com');
    exit;
}

$tStmt = $conn->prepare("SELECT id, used_at FROM testimonial_invites WHERE token = ?");
$tStmt->bind_param('s', $inviteToken);
$tStmt->execute();
$tResult = $tStmt->get_result();
$invite  = $tResult->fetch_assoc();
$tStmt->close();

if (!$invite || $invite['used_at'] !== null) {
    header('Location: https://rielcode.com');
    exit;
}

$client_name    = trim($_POST['client_name']    ?? '');
$business_name  = trim($_POST['business_name']  ?? '');
$role_title     = trim($_POST['role_title']     ?? '');
$rating         = (int)($_POST['rating']        ?? 0);
$project_url    = trim($_POST['project_url']    ?? '');
$problem_before = trim($_POST['problem_before'] ?? '');
$solution_after = trim($_POST['solution_after'] ?? '');
$recommendation = trim($_POST['recommendation'] ?? '');
$headline       = trim($_POST['headline']       ?? '');
$client_email   = trim($_POST['client_email']   ?? '');
$consent_given  = isset($_POST['consent_given']) ? 1 : 0;

$errors = [];

if ($client_name === '' || mb_strlen($client_name) > 80) {
    $errors['client_name'] = 'Name is required (max 80 chars).';
}
if ($business_name === '' || mb_strlen($business_name) > 100) {
    $errors['business_name'] = 'Business name is required (max 100 chars).';
}
if ($role_title === '' || mb_strlen($role_title) > 80) {
    $errors['role_title'] = 'Role / title is required (max 80 chars).';
}
if ($rating < 1 || $rating > 5) {
    $errors['rating'] = 'Please select a star rating between 1 and 5.';
}
if ($project_url === '' || !filter_var($project_url, FILTER_VALIDATE_URL) || mb_strlen($project_url) > 255) {
    $errors['project_url'] = 'A valid project URL is required.';
}
if (mb_strlen($problem_before) < 50 || mb_strlen($problem_before) > 300) {
    $errors['problem_before'] = '"Problem before" must be between 50 and 300 characters.';
}
if (mb_strlen($solution_after) < 100 || mb_strlen($solution_after) > 500) {
    $errors['solution_after'] = '"Solution / what was built" must be between 100 and 500 characters.';
}
if (mb_strlen($recommendation) < 50 || mb_strlen($recommendation) > 300) {
    $errors['recommendation'] = '"Recommendation" must be between 50 and 300 characters.';
}
if ($headline !== '' && mb_strlen($headline) > 120) {
    $errors['headline'] = 'Headline must be 120 characters or less.';
}
if ($client_email !== '') {
    if (!filter_var($client_email, FILTER_VALIDATE_EMAIL) || mb_strlen($client_email) > 120) {
        $errors['client_email'] = 'Please provide a valid email address.';
    }
}
if ($consent_given !== 1) {
    $errors['consent_given'] = 'Consent is required to publish your testimonial.';
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old']    = $_POST;
    header('Location: index.php?t=' . urlencode($inviteToken));
    exit;
}

$ip            = $_SERVER['REMOTE_ADDR'] ?? null;
$headlineForDb = $headline === ''     ? null : $headline;
$emailForDb    = $client_email === '' ? null : $client_email;

$sql = "INSERT INTO testimonials
    (client_name, business_name, role_title, rating, project_url,
     problem_before, solution_after, recommendation, headline,
     client_email, consent_given, status, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die('Database error.');
}

// Types: client_name(s) business_name(s) role_title(s) rating(i) project_url(s)
//        problem_before(s) solution_after(s) recommendation(s) headline(s)
//        client_email(s) consent_given(i) ip_address(s)
$stmt->bind_param(
    "sssissssssis",
    $client_name,
    $business_name,
    $role_title,
    $rating,
    $project_url,
    $problem_before,
    $solution_after,
    $recommendation,
    $headlineForDb,
    $emailForDb,
    $consent_given,
    $ip
);

if (!$stmt->execute()) {
    http_response_code(500);
    die('Failed to save testimonial.');
}

$insertedId = $stmt->insert_id;
$stmt->close();

// Mark invite token as used, link to this submission
$uStmt = $conn->prepare("UPDATE testimonial_invites SET used_at = NOW(), testimonial_id = ? WHERE token = ?");
$uStmt->bind_param('is', $insertedId, $inviteToken);
$uStmt->execute();
$uStmt->close();

unset($_SESSION['csrf_token'], $_SESSION['form_loaded_at'], $_SESSION['invite_token']);

// Notify admin via PHPMailer
include __DIR__ . '/../Rielcode/smtp_config.php';

$adminEmail = 'afw1407@gmail.com';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->SMTPSecure = $SMTP_SECURE;
    $mail->Port       = $SMTP_PORT;
    $mail->setFrom($SMTP_USER, 'Rielcode Testimonials');
    $mail->addAddress($adminEmail, 'Azriel');
    $mail->isHTML(true);
    $mail->Subject = "New Testimonial Submission #{$insertedId} - {$client_name} ({$business_name})";

    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

    $mail->Body = '
<div style="font-family:Poppins,Arial,sans-serif;color:#333;line-height:1.6;max-width:640px;">
    <h2 style="margin:0 0 16px 0;color:#1c1f22;">New Testimonial Submission</h2>
    <p style="margin:0 0 20px 0;color:#666;">Submission #' . (int)$insertedId . ' is now pending review in your admin panel.</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr><td style="padding:8px;border-bottom:1px solid #eee;width:160px;color:#888;">From</td><td style="padding:8px;border-bottom:1px solid #eee;"><b>' . htmlspecialchars($client_name) . '</b> &mdash; ' . htmlspecialchars($role_title) . '</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#888;">Business</td><td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($business_name) . '</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#888;">Rating</td><td style="padding:8px;border-bottom:1px solid #eee;font-size:16px;color:#ffc73a;">' . $stars . ' (' . $rating . '/5)</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#888;">Project URL</td><td style="padding:8px;border-bottom:1px solid #eee;"><a href="' . htmlspecialchars($project_url) . '">' . htmlspecialchars($project_url) . '</a></td></tr>
        ' . ($emailForDb ? '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#888;">Email</td><td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($emailForDb) . '</td></tr>' : '') . '
        ' . ($headlineForDb ? '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#888;">Headline</td><td style="padding:8px;border-bottom:1px solid #eee;"><i>"' . htmlspecialchars($headlineForDb) . '"</i></td></tr>' : '') . '
    </table>

    <h3 style="margin:24px 0 8px 0;color:#1c1f22;">Problem Before</h3>
    <p style="margin:0;background:#f5f6fa;padding:12px;border-radius:6px;">' . nl2br(htmlspecialchars($problem_before)) . '</p>

    <h3 style="margin:20px 0 8px 0;color:#1c1f22;">Solution / What Was Built</h3>
    <p style="margin:0;background:#f5f6fa;padding:12px;border-radius:6px;">' . nl2br(htmlspecialchars($solution_after)) . '</p>

    <h3 style="margin:20px 0 8px 0;color:#1c1f22;">Recommendation</h3>
    <p style="margin:0;background:#f5f6fa;padding:12px;border-radius:6px;">' . nl2br(htmlspecialchars($recommendation)) . '</p>

    <p style="margin:24px 0 0 0;font-size:13px;color:#888;">
        Consent given: ' . ($consent_given ? 'Yes' : 'No') . ' &middot; IP: ' . htmlspecialchars($ip ?? 'unknown') . '
    </p>

    <p style="margin-top:24px;">
        <a href="https://rielcode.com/admin.php?section=testimonials" style="background:#3a7bff;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Review in Admin Panel</a>
    </p>
</div>';

    $mail->send();
} catch (MailException $e) {
    // silent fail - submission already saved, admin can still see in panel
    error_log('Testimonial mail failed: ' . $e->getMessage());
}

header('Location: thank-you.php');
exit;
