<?php
/**
 * Check: Which domain is used to send emails
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/check_mailgun_domain.php
 * 3. Check the result
 */

require_once 'config.php';

echo "<h1>Mailgun Domain Check</h1>";

echo "<h2>Current settings in config.php:</h2>";
echo "<ul>";
echo "<li><strong>MAILGUN_DOMAIN:</strong> " . (defined('MAILGUN_DOMAIN') ? htmlspecialchars(MAILGUN_DOMAIN) : 'NOT CONFIGURED') . "</li>";
echo "<li><strong>MAILGUN_FROM_EMAIL:</strong> " . (defined('MAILGUN_FROM_EMAIL') ? htmlspecialchars(MAILGUN_FROM_EMAIL) : 'NOT CONFIGURED') . "</li>";
echo "<li><strong>MAILGUN_API_KEY:</strong> " . (defined('MAILGUN_API_KEY') && !empty(MAILGUN_API_KEY) ? 'Configured (' . substr(MAILGUN_API_KEY, 0, 10) . '...)' : 'NOT CONFIGURED') . "</li>";
echo "</ul>";

echo "<h2>What domain is used for sending:</h2>";

if (defined('MAILGUN_DOMAIN') && !empty(MAILGUN_DOMAIN)) {
    echo "<p><strong>Current domain:</strong> <code>" . htmlspecialchars(MAILGUN_DOMAIN) . "</code></p>";
    
    if (strpos(MAILGUN_DOMAIN, 'sandbox') !== false) {
        echo "<p style='color: orange;'><strong>⚠️ Used Sandbox Domain</strong></p>";
        echo "<p>Sandbox Domain has limitations:</p>";
        echo "<ul>";
        echo "<li>Can only be sent to authorized email-addresses</li>";
        echo "<li>Emails may end up in spam</li>";
        echo "<li>Deliverability restrictions</li>";
        echo "</ul>";
        echo "<p><strong>Recommendation:</strong> If the domain <code>new.backtobase.ca</code> verified in Mailgun, switch to it!</p>";
    } else {
        echo "<p style='color: green;'><strong>✅ Verified domain is used</strong></p>";
        echo "<p>This is good! A verified domain has better deliverability.</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ MAILGUN_DOMAIN not configured!</strong></p>";
}

echo "<hr>";

echo "<h2>Check in Mailgun Dashboard:</h2>";
echo "<p>Check domain status <code>new.backtobase.ca</code> V Mailgun:</p>";
echo "<ol>";
echo "<li>Mailgun Dashboard → <strong>Sending</strong> → <strong>Domains</strong></li>";
echo "<li>Find a domain <code>new.backtobase.ca</code></li>";
echo "<li>Check status:</li>";
echo "<ul>";
echo "<li>✅ <strong>Verified</strong> - domain verified, can be used</li>";
echo "<li>⏳ <strong>Pending</strong> - awaiting verification (Wait)</li>";
echo "<li>⚠️ <strong>Unverified</strong> - DNS records not found or incorrect</li>";
echo "</ul>";
echo "</ol>";

echo "<h2>Recommendation:</h2>";

if (defined('MAILGUN_DOMAIN') && strpos(MAILGUN_DOMAIN, 'sandbox') !== false) {
    echo "<p>If the domain <code>new.backtobase.ca</code> has the status <strong>Verified</strong> V Mailgun:</p>";
    echo "<ol>";
    echo "<li>Update <code>config.php</code>:</li>";
    echo "<pre>";
    echo "define('MAILGUN_DOMAIN', 'new.backtobase.ca'); // ✅ Instead of Sandbox\n";
    echo "define('MAILGUN_FROM_EMAIL', 'bookings@new.backtobase.ca'); // ✅ Optional\n";
    echo "</pre>";
    echo "<li>Download the updated <code>config.php</code> for hosting</li>";
    echo "<li>Test sending emails</li>";
    echo "</ol>";
    echo "<p><strong>Benefits of a verified domain:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Can be sent to any email-addresses (without restrictions)</li>";
    echo "<li>✅ Better deliverability (doesn't go to spam)</li>";
    echo "<li>✅ Professionally - letters from @new.backtobase.ca</li>";
    echo "</ul>";
} else {
    echo "<p>The current setup uses a verified domain - this is good!</p>";
}

echo "<hr>";
echo "<p><small>To delete this file after verification: delete check_mailgun_domain.php from hosting</small></p>";
?>



