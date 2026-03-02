<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$filename = $_GET['file'] ?? '';
$site_name = 'TheArchive';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Download - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-dark text-white">
    <div class="container">
        <div class="row min-vh-100 align-items-center">
            <div class="col-md-6 mx-auto">
                <div class="card bg-secondary">
                    <div class="card-body text-center p-5">
                        <div id="progressSection">
                            <h4 class="mb-4">Creating Bulk Download...</h4>
                            <div class="progress mb-3" style="height: 30px;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                            </div>
                            <div id="progressText" class="text-muted">Starting...</div>
                        </div>
                        
                        <div id="downloadSection" style="display: none;">
                            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 mb-4">Download Ready!</h4>
                            <a href="data/temp/<?php echo htmlspecialchars($filename); ?>" class="btn btn-success btn-lg" download id="downloadLink">
                                <i class="bi bi-download"></i> Download ZIP
                            </a>
                            <div class="mt-3">
                                <a href="gallery.php" class="btn btn-outline-light">Back to Gallery</a>
                            </div>
                            <p class="text-muted small mt-3">File will be deleted after you download it</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const checkProgress = setInterval(function() {
            fetch('bulk_download.php?check_progress=1')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'idle') return;
                    
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    
                    progressBar.style.width = data.progress + '%';
                    progressBar.textContent = data.progress + '%';
                    progressText.textContent = data.message;
                    
                    if (data.status === 'complete') {
                        clearInterval(checkProgress);
                        document.getElementById('progressSection').style.display = 'none';
                        document.getElementById('downloadSection').style.display = 'block';
                        
                        // Delete file after download
                        document.getElementById('downloadLink').addEventListener('click', function() {
                            setTimeout(function() {
                                fetch('delete_temp_file.php?file=<?php echo urlencode($filename); ?>');
                            }, 1000); // Give download a second to start
                        });
                    }
                });
        }, 500);
    </script>
</body>
</html>
?>