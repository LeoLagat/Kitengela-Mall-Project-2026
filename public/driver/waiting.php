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
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .bye-text {
            color: goldenrod;
            font-size: 22px;
            font-weight: 600;
            margin: 20px 0;
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
            
            <h2 class="status-title">Payment Confirmed</h2>
            <p style="color: dimgray; margin: 0;">The exit barrier is now opening.</p>
            
            <div class="bye-text">Goodbye! Safe Travels 👋</div>

            <div class="redirect-loader">
                <div id="pBar" class="progress-fill"></div>
            </div>
            <p class="redirect-hint">Preparing for the next user...</p>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const plate = "<?php echo addslashes($plate); ?>";
        const source = new EventSource(`payment_status_sse.php?plate=${plate}`);

        source.onmessage = function(e) {
            const data = JSON.parse(e.data);
            if (data.status === 'paid') {
                document.getElementById('loading-ui').style.display = 'none';
                document.getElementById('success-ui').style.display = 'block';
                document.getElementById('pBar').classList.add('animate-bar');
                setTimeout(() => { window.location.href = "../gate/exit.php"; }, 3000);
                source.close();
            } else if (data.status === 'failed') {
                alert("Payment failed. Please try again or visit the management office.");
                source.close();
                window.location.href = "pay.php?plate=" + plate;
            }
        };

        source.onerror = function(err) {
            console.error('SSE error', err);
            // EventSource will automatically reconnect; nothing else required
        };

        // automatically trigger simulation after 20 seconds of no change
        const autoSimTimer = setTimeout(() => {
            const notice = document.createElement('p');
            notice.style.color = 'orange';
            notice.style.marginTop = '20px';
            notice.textContent = 'No callback received yet – simulating payment now.';
            document.querySelector('#loading-ui').appendChild(notice);
            fetch('simulate_payment.php?plate=' + encodeURIComponent(plate))
              .then(()=>console.log('auto-simulated'));
        }, 20000);

        // if nothing happens for a while, show a hint to user
        setTimeout(() => {
            const notice = document.createElement('p');
            notice.style.color = 'darkred';
            notice.style.marginTop = '20px';
            notice.textContent = 'Still waiting? You can click the button above or return to exit page.';
            document.querySelector('#loading-ui').appendChild(notice);
        }, 60000);
    });
    </script>
</body>
</html>