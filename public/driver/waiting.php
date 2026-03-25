<?php
require_once(__DIR__ . '/../../backend/app/config/database.php');
$plate = $_GET['plate'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processing Payment - Kitengela Mall</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Base Page Styles */
        body { 
            background: whitesmoke; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }

        .card { 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px silver; 
            text-align: center; 
            width: 420px; 
            border-top: 10px solid darkgreen;
        }

        /* Loading Spinner */
        .loader { 
            border: 8px solid whitesmoke; 
            border-top: 8px solid darkgreen; 
            border-radius: 50%; 
            width: 60px; 
            height: 60px; 
            animation: spin 1.5s linear infinite; 
            margin: 20px auto; 
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Success UI Styles */
        #success-ui {
            animation: fadeIn 0.8s ease-out forwards;
        }

        /* Failed UI Styles */
        #failed-ui {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .failed-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: mistyrose;
            color: firebrick;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
            box-shadow: 0 4px 15px silver;
            animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .failed-title {
            color: firebrick;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .failed-desc {
            color: dimgray;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .retry-btn {
            display: inline-block;
            background: darkgreen;
            color: white;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            margin-top: 4px;
        }

        .retry-btn:hover {
            background: seagreen;
        }

        .retry-panel {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid lightgray;
        }

        .retry-stk-btn {
            background: darkgreen;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .retry-stk-btn:hover {
            background: seagreen;
        }

        .retry-stk-btn:disabled {
            background: darkgray;
            cursor: not-allowed;
        }

        .retry-note {
            margin-top: 10px;
            color: dimgray;
            font-size: 13px;
        }

        .retry-countdown {
            margin-top: 6px;
            color: darkslategray;
            font-size: 12px;
            font-weight: 700;
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: honeydew;
            color: darkgreen;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
            box-shadow: 0 4px 15px silver;
            animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .status-title {
            color: darkslategray;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        .status-subtitle {
            color: dimgray;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }

        .plate-chip {
            display: inline-block;
            margin-top: 12px;
            margin-bottom: 8px;
            background: mintcream;
            border: 1px solid palegreen;
            border-radius: 999px;
            color: darkgreen;
            padding: 6px 14px;
            font-weight: 800;
            letter-spacing: 1px;
            font-size: 16px;
        }

        .bye-text {
            color: goldenrod;
            font-size: 21px;
            font-weight: 700;
            margin: 14px 0 8px;
            opacity: 0;
            animation: fadeInUp 0.6s ease-out 0.4s forwards;
        }

        /* Progress Bar for Redirect */
        .redirect-loader {
            width: 100%;
            height: 8px;
            background: lightgrey;
            border-radius: 10px;
            margin-top: 30px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: darkgreen;
            width: 0%;
        }

        .animate-bar {
            animation: loadingBar 3s linear forwards;
        }

        .redirect-hint {
            font-size: 13px;
            color: grey;
            margin-top: 12px;
            font-style: italic;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleUp {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes loadingBar {
            from { width: 0%; }
            to { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="card" id="status-card">
        
        <div id="loading-ui">
            <div class="loader"></div>
            <h2 style="color: darkgreen;">Verifying Payment...</h2>
            <p style="color: dimgray;">Please check your phone and enter your M-Pesa PIN for vehicle:</p>
            <div style="font-size: 22px; font-weight: bold; margin: 15px 0; color: black; letter-spacing: 1px;">
                <?php echo htmlspecialchars($plate); ?>
            </div>
            <p style="font-size: 14px; color: grey;">Waiting for Safaricom confirmation...</p>

        </div>

        <div id="success-ui" style="display:none;">
            <div class="success-checkmark">✓</div>
            
            <h2 class="status-title">Thank You for Visiting</h2>
            <p class="status-subtitle">Payment received successfully. Exit barrier unlocked.</p>
            <div class="plate-chip"><?php echo htmlspecialchars($plate); ?></div>
            <div class="bye-text">Goodbye. Have a safe journey home.</div>

            <div class="redirect-loader">
                <div id="pBar" class="progress-fill"></div>
            </div>
            <p class="redirect-hint">Returning to home screen...</p>
        </div>

        <div id="failed-ui" style="display:none;">
            <div class="failed-icon">&#10007;</div>
            <h2 class="failed-title">Payment Not Completed</h2>
            <p class="failed-desc" id="failed-desc">Your M-Pesa payment was cancelled or the PIN was incorrect. You can try again below.</p>
            <button type="button" class="retry-stk-btn" id="retry-stk-failed-btn">Retry STK Prompt</button>
            <p class="retry-note" id="retry-stk-failed-note"></p>
            <a class="retry-btn" href="pay.php?plate=<?php echo urlencode($plate); ?>">Try Again</a>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const plate = "<?php echo addslashes($plate); ?>";
        const source = new EventSource(`payment_status_sse.php?plate=${plate}`);
        const retryFailedBtn = document.getElementById('retry-stk-failed-btn');
        const retryFailedNote = document.getElementById('retry-stk-failed-note');
        let settled = false;
        let pollId = null;

        function finishWithStatus(status) {
            if (settled) return;

            if (status === 'paid') {
                settled = true;
                document.getElementById('loading-ui').style.display = 'none';
                document.getElementById('success-ui').style.display = 'block';
                document.getElementById('pBar').classList.add('animate-bar');
                if (pollId) clearInterval(pollId);
                source.close();
                setTimeout(() => { window.location.href = "../index.php?welcome=exit"; }, 1500);
            } else if (status === 'failed') {
                settled = true;
                document.getElementById('loading-ui').style.display = 'none';
                document.getElementById('failed-ui').style.display  = 'block';
                if (pollId) clearInterval(pollId);
                source.close();
            }
        }

        async function checkStatusNow() {
            try {
                const res = await fetch(`check_status.php?plate=${encodeURIComponent(plate)}&_=${Date.now()}`);
                if (!res.ok) return;
                const data = await res.json();
                finishWithStatus(data.status);
            } catch (err) {
                // Ignore transient errors; SSE/poller will retry.
            }
        }

        source.onmessage = function(e) {
            const data = JSON.parse(e.data);
            finishWithStatus(data.status);
        };

        source.onerror = function(err) {
            console.error('SSE error', err);
            // EventSource will automatically reconnect; nothing else required
        };

        // Fast fallback path in case SSE delivery is delayed by buffering/network.
        checkStatusNow();
        pollId = setInterval(checkStatusNow, 2000);

        retryFailedBtn?.addEventListener('click', async () => {
            retryFailedBtn.disabled = true;
            retryFailedBtn.textContent = 'Sending...';
            retryFailedNote.textContent = 'Requesting a new STK prompt. Please check your phone.';
            retryFailedNote.style.color = 'dimgray';

            try {
                const res = await fetch('retry_stk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ plate })
                });
                const data = await res.json();

                if (res.ok && data.ok) {
                    retryFailedNote.textContent = data.message || 'STK prompt sent. Returning to verification...';
                    retryFailedNote.style.color = 'darkgreen';
                    setTimeout(() => {
                        window.location.href = `waiting.php?plate=${encodeURIComponent(plate)}`;
                    }, 1200);
                } else {
                    retryFailedNote.textContent = data.error || 'Could not resend STK prompt. Please use Try Again.';
                    retryFailedNote.style.color = 'firebrick';
                    retryFailedBtn.disabled = false;
                    retryFailedBtn.textContent = 'Retry STK Prompt';
                }
            } catch (error) {
                retryFailedNote.textContent = 'Network issue while retrying STK. Please try again.';
                retryFailedNote.style.color = 'firebrick';
                retryFailedBtn.disabled = false;
                retryFailedBtn.textContent = 'Retry STK Prompt';
            }
        });

        // if nothing happens for a while, show a hint to user
        setTimeout(() => {
            if (settled) return;
            const notice = document.createElement('p');
            notice.style.color = 'darkred';
            notice.style.marginTop = '20px';
            notice.textContent = 'Still waiting? Confirm your M-Pesa prompt and keep this page open.';
            document.querySelector('#loading-ui').appendChild(notice);
        }, 60000);
    });
    </script>
</body>
</html>