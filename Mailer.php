<?php
/*
  SMTP Mailer - Bulk Email Sending Tool
  Version: 1.0.0
  Author: Frenzyyy & Palermo
  License: MIT
  GitHub: https://github.com/yourusername/smtp-mailer
*/

session_start();

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple email sending function using PHP's mail()
function sendEmail($to, $from, $fromName, $subject, $message, $smtpConfig = null) {
    $headers = [
        "From: " . ($fromName ? "$fromName <$from>" : $from),
        "Reply-To: $from",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "X-Mailer: SMTP Mailer v1.0"
    ];
    
    // Encode subject for UTF-8 support
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    // Send the email using PHP's mail() function
    return mail($to, $encodedSubject, $message, implode("\r\n", $headers));
}

// Default HTML email template
$defaultHTML = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email from SMTP Mailer</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(90deg, #667eea, #764ba2); padding: 25px; border-radius: 10px; color: white; text-align: center; }
        .content { padding: 25px; background: #f9f9f9; border-radius: 0 0 10px 10px; margin-top: -5px; }
        .footer { margin-top: 25px; text-align: center; font-size: 12px; color: #666; padding-top: 20px; border-top: 1px solid #eee; }
        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Hello! 👋</h1>
    </div>
    <div class="content">
        <p>This email was sent using <strong>SMTP Mailer</strong> - a powerful bulk email sending tool.</p>
        <p>You can customize this template with your own HTML content.</p>
        <p style="text-align: center;">
            <a href="https://github.com/yourusername/smtp-mailer" class="button" target="_blank">
                View on GitHub
            </a>
        </p>
    </div>
    <div class="footer">
        <p>© ' . date('Y') . ' SMTP Mailer | Made with ❤️ by Frenzyyy & Palermo</p>
    </div>
</body>
</html>';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_batch') {
        // Validate required fields
        $required = ['from', 'emails', 'subject', 'letter'];
        $missing = [];
        foreach ($required as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ]);
            exit;
        }
        
        // Process email list
        $emailsRaw = trim($_POST['emails']);
        $emails = preg_split('/[\r\n,;]+/', $emailsRaw);
        $validEmails = [];
        
        foreach ($emails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            }
        }
        
        if (empty($validEmails)) {
            echo json_encode([
                'success' => false,
                'message' => 'No valid email addresses found'
            ]);
            exit;
        }
        
        // Prepare configuration
        $config = [
            'from' => filter_var(trim($_POST['from']), FILTER_VALIDATE_EMAIL),
            'name' => trim($_POST['name'] ?? 'SMTP Mailer'),
            'subject' => trim($_POST['subject']),
            'letter' => $_POST['letter']
        ];
        
        if (!$config['from']) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid sender email address'
            ]);
            exit;
        }
        
        // Create batch
        $batchId = uniqid('batch_');
        $_SESSION['batches'][$batchId] = [
            'emails' => $validEmails,
            'config' => $config,
            'progress' => [
                'total' => count($validEmails),
                'sent' => 0,
                'failed' => 0,
                'current' => 0
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'batch_id' => $batchId,
            'total' => count($validEmails)
        ]);
        exit;
        
    } elseif ($_POST['action'] === 'send_email') {
        // Send single email
        $batchId = $_POST['batch_id'] ?? '';
        $emailIndex = (int)($_POST['email_index'] ?? 0);
        
        if (!isset($_SESSION['batches'][$batchId])) {
            echo json_encode([
                'success' => false,
                'message' => 'Batch not found'
            ]);
            exit;
        }
        
        $batch = $_SESSION['batches'][$batchId];
        $config = $batch['config'];
        
        if (!isset($batch['emails'][$emailIndex])) {
            echo json_encode([
                'success' => false,
                'message' => 'Email not found in batch'
            ]);
            exit;
        }
        
        $to = $batch['emails'][$emailIndex];
        
        // Actually send the email
        $result = sendEmail(
            $to,
            $config['from'],
            $config['name'],
            $config['subject'],
            $config['letter']
        );
        
        // Update progress
        if ($result) {
            $_SESSION['batches'][$batchId]['progress']['sent']++;
            $log = "✓ Sent to $to";
            $status = 'ok';
        } else {
            $_SESSION['batches'][$batchId]['progress']['failed']++;
            $log = "✗ Failed to send to $to";
            $status = 'fail';
        }
        
        $_SESSION['batches'][$batchId]['progress']['current'] = $emailIndex + 1;
        
        // Check if completed
        $completed = ($emailIndex + 1 >= $batch['progress']['total']);
        if ($completed) {
            $_SESSION['batches'][$batchId]['completed'] = true;
        }
        
        echo json_encode([
            'success' => true,
            'log' => $log,
            'status' => $status,
            'completed' => $completed,
            'progress' => $_SESSION['batches'][$batchId]['progress']
        ]);
        exit;
    }
}

// Clear old completed batches
if (isset($_SESSION['batches'])) {
    foreach ($_SESSION['batches'] as $batchId => $batch) {
        if (isset($batch['completed']) && $batch['completed']) {
            unset($_SESSION['batches'][$batchId]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Mailer - Bulk Email Sending Tool</title>
    <meta name="description" content="Open source bulk email sender with real-time progress tracking">
    <meta name="author" content="Frenzyyy & Palermo">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📧</text></svg>">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--dark) 0%, #2d3748 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .header h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .github-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }
        
        .github-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 700px;
        }
        
        .form-section {
            padding: 35px;
            background: var(--light);
        }
        
        .terminal-section {
            padding: 35px;
            background: var(--dark);
            display: flex;
            flex-direction: column;
        }
        
        .section-title {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title:before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(var(--primary), var(--secondary));
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-group {
            position: relative;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
            background: white;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-loading:after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .terminal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #334155;
        }
        
        .terminal-header h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .status-badge {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-badge:before {
            content: '●';
            font-size: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .progress-container {
            margin-bottom: 25px;
            background: #1e293b;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #334155;
            display: none;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 500;
        }
        
        .progress-bar {
            height: 10px;
            background: #334155;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), var(--primary));
            width: 0%;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 5px;
        }
        
        .logs-container {
            flex: 1;
            overflow-y: auto;
            background: #1e293b;
            border-radius: 10px;
            padding: 20px;
            font-family: 'JetBrains Mono', 'Cascadia Code', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid #334155;
        }
        
        .log-entry {
            padding: 12px 0;
            border-bottom: 1px solid #334155;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .log-entry.ok { color: var(--success); }
        .log-entry.fail { color: var(--danger); }
        .log-entry.info { color: #60a5fa; }
        .log-entry.warning { color: var(--warning); }
        
        .timestamp {
            color: #64748b;
            margin-right: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 25px;
            display: none;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .info-box h4 {
            color: #1e40af;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .credits {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .credits p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .developer-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .dev-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 25px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .dev-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 1024px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            .form-section {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
        }
        
        @media (max-width: 640px) {
            .container {
                border-radius: 15px;
            }
            
            .header, .form-section, .terminal-section {
                padding: 25px 20px;
            }
            
            .github-badge {
                position: static;
                margin-top: 15px;
                display: inline-flex;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1e293b;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 SMTP Mailer</h1>
            <p>Open source bulk email sending tool with real-time progress tracking</p>
            <a href="https://github.com/yourusername/smtp-mailer" target="_blank" class="github-badge">
                <svg height="16" viewBox="0 0 16 16" width="16" fill="currentColor">
                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                </svg>
                Star on GitHub
            </a>
        </div>
        
        <div class="content">
            <div class="form-section">
                <div class="section-title">Configuration</div>
                
                <form id="mailForm">
                    <div class="form-group">
                        <label>Sender Information</label>
                        <div class="grid-2">
                            <div class="input-group">
                                <input type="email" name="from" placeholder="Your Email" required>
                                <span class="input-icon">📧</span>
                            </div>
                            <div class="input-group">
                                <input type="text" name="name" placeholder="Sender Name">
                                <span class="input-icon">👤</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Recipients (one per line or comma separated)</label>
                        <textarea name="emails" rows="5" placeholder="Enter email addresses:" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Content</label>
                        <div class="input-group">
                            <input type="text" name="subject" placeholder="Email Subject" required>
                            <span class="input-icon">📝</span>
                        </div>
                        <textarea name="letter" rows="8" placeholder="HTML email content..." required><?php echo htmlspecialchars($defaultHTML); ?></textarea>
                    </div>
                    
                    <input type="hidden" name="action" value="send_batch">
                    
                    <button type="submit" class="btn" id="sendBtn">
                        <span>🚀 Start Sending</span>
                    </button>
                </form>
                
                <div class="stats" id="stats">
                    <div class="stat-card">
                        <div class="stat-number" id="statTotal">0</div>
                        <div class="stat-label">Total Emails</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="statSent">0</div>
                        <div class="stat-label">Successfully Sent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="statFailed">0</div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4>💡 How to Use</h4>
                    <p style="font-size: 13px; color: #475569; line-height: 1.6;">
                        1. Enter your email as sender<br>
                        2. Add recipient emails (one per line)<br>
                        3. Write your email subject and HTML content<br>
                        4. Click "Start Sending"<br>
                        5. Watch real-time progress on the right<br>
                        <br>
                        <strong>Note:</strong> This uses PHP's mail() function. Make sure your server has a working mail configuration.
                    </p>
                </div>
                
                <div class="credits">
                    <p>Made with ❤️ by</p>
                    <div class="developer-links">
                        <a href="https://github.com/Frenzyyy" target="_blank" class="dev-link">
                            <svg height="16" viewBox="0 0 16 16" width="16" fill="currentColor">
                                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                            </svg>
                            Frenzyyy
                        </a>
                        
                        <a href="https://github.com/Palermo" target="_blank" class="dev-link">
                            <svg height="16" viewBox="0 0 16 16" width="16" fill="currentColor">
                                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                            </svg>
                            Palermo
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="terminal-section">
                <div class="terminal-header">
                    <h3>Live Progress</h3>
                    <div class="status-badge" id="statusBadge">Ready</div>
                </div>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress-info">
                        <span>Progress: <span id="progressText">0%</span></span>
                        <span><span id="currentCount">0</span>/<span id="totalCount">0</span> emails</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>
                
                <div class="logs-container" id="logsContainer">
                    <div class="log-entry info">
                        <span class="timestamp"><?php echo date('H:i:s'); ?></span>
                        SMTP Mailer initialized and ready
                    </div>
                    <div class="log-entry info">
                        <span class="timestamp"><?php echo date('H:i:s'); ?></span>
                        Enter your configuration and click "Start Sending"
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('mailForm');
            const sendBtn = document.getElementById('sendBtn');
            const logsContainer = document.getElementById('logsContainer');
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const currentCount = document.getElementById('currentCount');
            const totalCount = document.getElementById('totalCount');
            const statusBadge = document.getElementById('statusBadge');
            const stats = document.getElementById('stats');
            const statTotal = document.getElementById('statTotal');
            const statSent = document.getElementById('statSent');
            const statFailed = document.getElementById('statFailed');
            
            let currentBatchId = null;
            let isSending = false;
            
            // Add log to terminal
            function addLog(type, message) {
                const now = new Date();
                const timestamp = now.getHours().toString().padStart(2, '0') + ':' + 
                                 now.getMinutes().toString().padStart(2, '0') + ':' + 
                                 now.getSeconds().toString().padStart(2, '0');
                
                const logEntry = document.createElement('div');
                logEntry.className = `log-entry ${type}`;
                logEntry.innerHTML = `<span class="timestamp">${timestamp}</span> ${message}`;
                
                logsContainer.appendChild(logEntry);
                logsContainer.scrollTop = logsContainer.scrollHeight;
            }
            
            // Update progress display
            function updateProgress(current, total) {
                const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
                progressFill.style.width = `${percentage}%`;
                progressText.textContent = `${percentage}%`;
                currentCount.textContent = current;
                totalCount.textContent = total;
            }
            
            // Send a single email
            async function sendSingleEmail(batchId, index) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_email');
                    formData.append('batch_id', batchId);
                    formData.append('email_index', index);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        addLog(result.status, result.log);
                        
                        // Update stats
                        if (result.status === 'ok') {
                            statSent.textContent = parseInt(statSent.textContent) + 1;
                        } else {
                            statFailed.textContent = parseInt(statFailed.textContent) + 1;
                        }
                        
                        // Update progress
                        if (result.progress) {
                            updateProgress(result.progress.current, result.progress.total);
                        }
                        
                        return {
                            success: true,
                            completed: result.completed || false
                        };
                    } else {
                        addLog('fail', `Error: ${result.message}`);
                        return { success: false };
                    }
                    
                } catch (error) {
                    addLog('fail', `Network error: ${error.message}`);
                    return { success: false };
                }
            }
            
            // Process entire batch
            async function processBatch(batchId, totalEmails) {
                isSending = true;
                currentBatchId = batchId;
                
                // Show progress UI
                progressContainer.style.display = 'block';
                stats.style.display = 'grid';
                statTotal.textContent = totalEmails;
                statSent.textContent = '0';
                statFailed.textContent = '0';
                updateProgress(0, totalEmails);
                
                addLog('info', `🚀 Starting to send ${totalEmails} email(s)...`);
                statusBadge.textContent = 'Sending...';
                
                // Send emails one by one
                for (let i = 0; i < totalEmails; i++) {
                    if (!isSending) break;
                    
                    const result = await sendSingleEmail(batchId, i);
                    
                    if (!result.success) {
                        // Ask user if they want to continue after error
                        const shouldContinue = confirm('An error occurred. Continue sending?');
                        if (!shouldContinue) {
                            isSending = false;
                            addLog('warning', 'Sending stopped by user');
                            break;
                        }
                    }
                    
                    // Delay between emails to prevent server overload
                    await new Promise(resolve => setTimeout(resolve, 300));
                }
                
                // Finalize
                isSending = false;
                if (currentBatchId === batchId) {
                    statusBadge.textContent = 'Completed';
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<span>🚀 Start Sending</span>';
                    
                    // Show completion message
                    const sent = parseInt(statSent.textContent);
                    const failed = parseInt(statFailed.textContent);
                    addLog('info', `✅ Batch completed! ${sent} sent, ${failed} failed`);
                }
            }
            
            // Handle form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (isSending) {
                    alert('Already sending emails. Please wait for completion.');
                    return;
                }
                
                // Validate email list
                const emailsText = form.emails.value.trim();
                if (!emailsText) {
                    alert('Please enter at least one email address');
                    return;
                }
                
                // Disable button and show loading
                sendBtn.disabled = true;
                sendBtn.innerHTML = '';
                sendBtn.classList.add('btn-loading');
                
                try {
                    const formData = new FormData(form);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Start processing the batch
                        processBatch(result.batch_id, result.total);
                    } else {
                        // Show error
                        addLog('fail', result.message);
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<span>🚀 Start Sending</span>';
                        sendBtn.classList.remove('btn-loading');
                    }
                    
                } catch (error) {
                    console.error('Error:', error);
                    addLog('fail', 'Network error: ' + error.message);
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<span>🚀 Start Sending</span>';
                    sendBtn.classList.remove('btn-loading');
                }
            });
            
            // Fill with demo data for testing
            function fillDemoData() {
                form.from.value = 'you@example.com';
                form.name.value = 'SMTP Mailer';
                form.emails.value = 'test1@example.com\ntest2@example.com\ntest3@example.com';
                form.subject.value = 'Test from SMTP Mailer';
            }
            
            // Uncomment to auto-fill demo data
            // fillDemoData();
            
            // Stop sending with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isSending) {
                    isSending = false;
                    addLog('warning', '⏹ Sending stopped by user');
                    statusBadge.textContent = 'Stopped';
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<span>🚀 Start Sending</span>';
                    sendBtn.classList.remove('btn-loading');
                }
            });
        });
    </script>
</body>
</html>
