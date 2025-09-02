<?php
/**
 * Enregistreur Vocal Simple
 * Auteur: EL GNANI Mohamed
 * Version: 2.0
 */
session_start();

// Configuration
$upload_dir = 'uploads/';
$max_file_size = 10 * 1024 * 1024; // 10MB max
$max_duration = 300; // 5 minutes max
$cleanup_days = 7; // Nettoyer les fichiers de plus de 7 jours
$allowed_mime_types = [
    'audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/webm', 'audio/mp4', 
    'audio/x-wav', 'audio/wave', 'application/octet-stream', 
    'video/webm', 'video/mp4'
];

// Créer le dossier uploads s'il n'existe pas
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Nettoyage automatique des anciens fichiers (probabilité 1/20)
if (rand(1, 20) === 1) {
    $cutoff_time = time() - ($cleanup_days * 24 * 60 * 60);
    foreach (glob($upload_dir . '*') as $file) {
        if (filemtime($file) < $cutoff_time) {
            @unlink($file);
        }
    }
}

// Traitement de l'upload
$message = '';
$error = '';
$share_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    try {
        $file = $_FILES['audio'];
        
        // Vérifications de sécurité
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier.');
        }
        
        if ($file['size'] > $max_file_size) {
            throw new Exception('Le fichier est trop volumineux (max 10MB).');
        }
        
        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            throw new Exception("Type de fichier non autorisé: $mime_type");
        }
        
        // Générer un nom de fichier unique et sécurisé
        $file_id = bin2hex(random_bytes(8)) . '_' . time();
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'wav';
        $filename = $file_id . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Erreur lors de la sauvegarde du fichier.');
        }
        
        // Sauvegarder les métadonnées en JSON
        $metadata = [
            'id' => $file_id,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'mime_type' => $mime_type,
            'upload_date' => date('Y-m-d H:i:s'),
            'duration' => isset($_POST['duration']) ? (int)$_POST['duration'] : 0
        ];
        
        $metadata_file = $upload_dir . $file_id . '.json';
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
        
        // Générer l'URL de partage
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $share_url = $protocol . '://' . $host . $script . '?play=' . $file_id;
        
        $message = 'Enregistrement sauvegardé avec succès ! ✅';
        
    } catch (Exception $e) {
        $error = '❌ ' . $e->getMessage();
    }
}

// Mode lecture d'un enregistrement partagé
$play_mode = false;
$audio_file = '';
$audio_metadata = null;

if (isset($_GET['play']) && !empty($_GET['play'])) {
    $play_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['play']);
    $audio_file = $upload_dir . $play_id . '.wav';
    $metadata_file = $upload_dir . $play_id . '.json';
    
    // Essayer différentes extensions si .wav n'existe pas
    if (!file_exists($audio_file)) {
        $extensions = ['wav', 'webm', 'mp4', 'ogg', 'mp3'];
        foreach ($extensions as $ext) {
            $test_file = $upload_dir . $play_id . '.' . $ext;
            if (file_exists($test_file)) {
                $audio_file = $test_file;
                break;
            }
        }
    }
    
    if (file_exists($audio_file)) {
        $play_mode = true;
        if (file_exists($metadata_file)) {
            $audio_metadata = json_decode(file_get_contents($metadata_file), true);
        }
    } else {
        $error = '❌ Enregistrement non trouvé ou expiré.';
    }
}

$page_title = $play_mode ? '🎵 Écouter l\'enregistrement' : '🎤 Enregistreur Vocal Simple';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .main-card {
            margin-top: -50px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .record-btn {
            background: linear-gradient(45deg, #ff4757, #ff6b6b);
            border: none;
            border-radius: 50px;
            padding: 20px 40px;
            font-size: 18px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
            animation: pulse 2s infinite;
        }
        
        .record-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 71, 87, 0.4);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .is-recording {
            animation: recording-pulse 1s infinite;
        }
        
        @keyframes recording-pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(255, 71, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
        }
        
        .timer {
            font-size: 2.5rem;
            font-weight: bold;
            color: #ff4757;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            animation: timerGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes timerGlow {
            from { text-shadow: 2px 2px 4px rgba(255, 71, 87, 0.3); }
            to { text-shadow: 2px 2px 20px rgba(255, 71, 87, 0.6); }
        }
        
        .visualizer {
            display: flex;
            justify-content: center;
            align-items: end;
            height: 80px;
            margin: 30px 0;
            gap: 3px;
            border-radius: 10px;
            background: linear-gradient(to right, rgba(255, 71, 87, 0.1), rgba(255, 107, 107, 0.1));
            padding: 10px;
        }
        
        .bar {
            width: 5px;
            background: linear-gradient(to top, #ff4757, #ff6b6b);
            border-radius: 3px;
            transition: all 0.1s ease;
            box-shadow: 0 0 10px rgba(255, 71, 87, 0.3);
        }
        
        .status-badge {
            font-size: 1.2rem;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .history-card {
            border-left: 4px solid #3273dc;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .delete-btn {
            background: linear-gradient(45deg, #ff4757, #ff6b6b);
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .delete-btn:hover {
            transform: rotate(90deg);
            background: linear-gradient(45deg, #ff6b6b, #ff4757);
        }
        
        .floating-action {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .gradient-text {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .empty-state {
            opacity: 0.7;
            animation: fadeIn 1s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <?php if (!$play_mode): ?>
    <!-- Hero Section -->
    <section class="hero is-primary is-medium">
        <div class="hero-body">
            <div class="container has-text-centered">
                <h1 class="title is-1">
                    <i class="fas fa-microphone-alt"></i>
                    Enregistreur Vocal
                </h1>
                <h2 class="subtitle is-4">
                    Enregistrez, partagez et gérez vos vocaux facilement
                </h2>
            </div>
        </div>
    </section>
    
    <div class="container">
        <div class="columns is-centered">
            <div class="column is-8">
                <!-- Main Recording Card -->
                <div class="card main-card">
                    <div class="card-content">
                        <?php if ($message): ?>
                        <div class="notification is-success">
                            <button class="delete"></button>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                        <div class="notification is-danger">
                            <button class="delete"></button>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status -->
                        <div class="has-text-centered mb-4">
                            <span id="status" class="tag status-badge is-info is-large">
                                <i class="fas fa-circle"></i>
                                &nbsp;Prêt à enregistrer
                            </span>
                        </div>
                        
                        <!-- Timer -->
                        <div class="has-text-centered mb-5">
                            <div id="timer" class="timer">00:00</div>
                        </div>
                        
                        <!-- Visualizer -->
                        <div class="visualizer" id="visualizer">
                            <!-- Les barres seront générées dynamiquement -->
                        </div>
                        
                        <!-- Controls -->
                        <div class="field is-grouped is-grouped-centered mb-5">
                            <div class="control">
                                <button id="startBtn" class="button is-large record-btn">
                                    <i class="fas fa-microphone"></i>
                                    &nbsp;Commencer
                                </button>
                            </div>
                            <div class="control">
                                <button id="pauseBtn" class="button is-large is-warning" disabled>
                                    <i class="fas fa-pause"></i>
                                    &nbsp;Pause
                                </button>
                            </div>
                            <div class="control">
                                <button id="stopBtn" class="button is-large is-danger" disabled>
                                    <i class="fas fa-stop"></i>
                                    &nbsp;Arrêter
                                </button>
                            </div>
                        </div>
                        
                        <!-- Audio Player -->
                        <div id="audioPlayerSection" class="has-text-centered" style="display: none;">
                            <audio id="audioPlayer" controls class="mb-4" style="width: 100%;"></audio>
                            
                            <div class="field is-grouped is-grouped-centered">
                                <div class="control">
                                    <button id="downloadBtn" class="button is-primary is-medium">
                                        <i class="fas fa-download"></i>
                                        &nbsp;Télécharger
                                    </button>
                                </div>
                                <div class="control">
                                    <button id="shareBtn" class="button is-success is-medium">
                                        <i class="fas fa-share-alt"></i>
                                        &nbsp;Partager
                                    </button>
                                </div>
                                <div class="control">
                                    <button id="newRecordBtn" class="button is-info is-medium">
                                        <i class="fas fa-plus"></i>
                                        &nbsp;Nouvel enregistrement
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Share URL Section -->
                        <div id="shareSection" style="display: none;" class="box mt-5">
                            <h4 class="title is-5 has-text-centered">
                                <i class="fas fa-link"></i>
                                &nbsp;Lien de partage
                            </h4>
                            <div class="field has-addons">
                                <div class="control is-expanded">
                                    <input id="shareUrl" class="input" type="text" readonly>
                                </div>
                                <div class="control">
                                    <button id="copyBtn" class="button is-success">
                                        <i class="fas fa-copy"></i>
                                        &nbsp;Copier
                                    </button>
                                </div>
                                <div class="control">
                                    <a id="openBtn" class="button is-info" target="_blank">
                                        <i class="fas fa-external-link-alt"></i>
                                        &nbsp;Ouvrir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History Section -->
                <div class="card mt-6">
                    <div class="card-header">
                        <p class="card-header-title">
                            <i class="fas fa-history"></i>
                            &nbsp;Historique des enregistrements
                        </p>
                        <div class="card-header-icon">
                            <button id="clearHistoryBtn" class="button is-small is-danger is-outlined">
                                <i class="fas fa-trash"></i>
                                &nbsp;Vider
                            </button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div id="historyList">
                            <!-- L'historique sera généré dynamiquement -->
                        </div>
                        <div id="emptyHistory" class="has-text-centered empty-state" style="display: none;">
                            <i class="fas fa-microphone-slash fa-3x mb-3"></i>
                            <p class="title is-5">Aucun enregistrement</p>
                            <p class="subtitle is-6">Vos enregistrements apparaîtront ici</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Play Mode -->
    <section class="hero is-info is-fullheight">
        <div class="hero-body">
            <div class="container has-text-centered">
                <div class="columns is-centered">
                    <div class="column is-6">
                        <div class="card">
                            <div class="card-content">
                                <h1 class="title">
                                    <i class="fas fa-play-circle"></i>
                                    &nbsp;Écouter l'enregistrement
                                </h1>
                                
                                <?php if ($error): ?>
                                <div class="notification is-danger">
                                    <?= htmlspecialchars($error) ?>
                                </div>
                                <?php else: ?>
                                
                                <?php if ($audio_metadata): ?>
                                <div class="content mb-4">
                                    <p><strong>Date :</strong> <?= htmlspecialchars($audio_metadata['upload_date'] ?? 'Inconnue') ?></p>
                                    <p><strong>Taille :</strong> <?= round(($audio_metadata['size'] ?? 0) / 1024) ?> KB</p>
                                    <?php if ($audio_metadata['duration'] ?? 0): ?>
                                    <p><strong>Durée :</strong> <?= gmdate("i:s", $audio_metadata['duration']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <audio controls style="width: 100%;" class="mb-4">
                                    <source src="<?= htmlspecialchars($audio_file) ?>" type="audio/wav">
                                    Votre navigateur ne supporte pas l'élément audio.
                                </audio>
                                
                                <div class="field is-grouped is-grouped-centered">
                                    <div class="control">
                                        <a href="<?= htmlspecialchars($audio_file) ?>" download class="button is-primary">
                                            <i class="fas fa-download"></i>
                                            &nbsp;Télécharger
                                        </a>
                                    </div>
                                    <div class="control">
                                        <a href="?" class="button is-info">
                                            <i class="fas fa-microphone"></i>
                                            &nbsp;Nouvel enregistrement
                                        </a>
                                    </div>
                                </div>
                                
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="footer has-background-transparent">
        <div class="content has-text-centered">
            <p class="has-text-white">
                Créé en France avec ❤️ par EL GNANI Mohamed de Linkuma.com
            </p>
        </div>
    </footer>
    
    <!-- Floating Action Button for History -->
    <div class="floating-action">
        <button id="historyToggle" class="button is-primary is-large is-rounded">
            <i class="fas fa-history"></i>
        </button>
    </div>

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let stream;
        let startTime;
        let timerInterval;
        let isPaused = false;
        let pausedTime = 0;
        let audioContext;
        let analyser;
        let microphone;
        let dataArray;
        let animationId;
        
        // Elements
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const stopBtn = document.getElementById('stopBtn');
        const status = document.getElementById('status');
        const timer = document.getElementById('timer');
        const audioPlayer = document.getElementById('audioPlayer');
        const audioPlayerSection = document.getElementById('audioPlayerSection');
        const downloadBtn = document.getElementById('downloadBtn');
        const shareBtn = document.getElementById('shareBtn');
        const newRecordBtn = document.getElementById('newRecordBtn');
        const shareSection = document.getElementById('shareSection');
        const shareUrl = document.getElementById('shareUrl');
        const copyBtn = document.getElementById('copyBtn');
        const openBtn = document.getElementById('openBtn');
        const visualizer = document.getElementById('visualizer');
        
        // History management
        const historyList = document.getElementById('historyList');
        const emptyHistory = document.getElementById('emptyHistory');
        const clearHistoryBtn = document.getElementById('clearHistoryBtn');
        const historyToggle = document.getElementById('historyToggle');
        
        let recordingHistory = JSON.parse(localStorage.getItem('recordingHistory') || '[]');
        
        // Initialize visualizer bars
        function initializeVisualizer() {
            visualizer.innerHTML = '';
            for (let i = 0; i < 40; i++) {
                const bar = document.createElement('div');
                bar.className = 'bar';
                bar.style.height = '2px';
                visualizer.appendChild(bar);
            }
        }
        
        // Animate visualizer
        function animateVisualizer() {
            if (!analyser) return;
            
            analyser.getByteFrequencyData(dataArray);
            const bars = visualizer.querySelectorAll('.bar');
            
            bars.forEach((bar, index) => {
                const value = dataArray[index * 2] || 0;
                const height = Math.max(2, (value / 255) * 60);
                bar.style.height = height + 'px';
            });
            
            animationId = requestAnimationFrame(animateVisualizer);
        }
        
        // Setup Web Audio API for visualization
        async function setupAudioVisualization(stream) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                microphone = audioContext.createMediaStreamSource(stream);
                
                analyser.fftSize = 256;
                const bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);
                
                microphone.connect(analyser);
                animateVisualizer();
            } catch (error) {
                console.warn('Visualisation audio non disponible:', error);
            }
        }
        
        // Start recording
        async function startRecording() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    } 
                });
                
                setupAudioVisualization(stream);
                
                const options = {};
                if (MediaRecorder.isTypeSupported('audio/webm')) {
                    options.mimeType = 'audio/webm';
                } else if (MediaRecorder.isTypeSupported('audio/wav')) {
                    options.mimeType = 'audio/wav';
                }
                
                mediaRecorder = new MediaRecorder(stream, options);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = event => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
                    const audioUrl = URL.createObjectURL(audioBlob);
                    audioPlayer.src = audioUrl;
                    audioPlayerSection.style.display = 'block';
                    
                    // Save to history
                    const recording = {
                        id: Date.now(),
                        blob: audioBlob,
                        url: audioUrl,
                        date: new Date().toLocaleString('fr-FR'),
                        duration: Math.floor((Date.now() - startTime - pausedTime) / 1000),
                        size: audioBlob.size
                    };
                    
                    recordingHistory.unshift(recording);
                    localStorage.setItem('recordingHistory', JSON.stringify(recordingHistory.map(r => ({
                        ...r,
                        blob: null,
                        url: null
                    }))));
                    
                    updateHistoryDisplay();
                    
                    // Cleanup
                    if (animationId) {
                        cancelAnimationFrame(animationId);
                    }
                    if (audioContext) {
                        audioContext.close();
                    }
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                };
                
                mediaRecorder.start();
                startTime = Date.now();
                pausedTime = 0;
                isPaused = false;
                
                startBtn.disabled = true;
                pauseBtn.disabled = false;
                stopBtn.disabled = false;
                startBtn.classList.add('is-recording');
                
                status.innerHTML = '<i class="fas fa-recording-circle"></i>&nbsp;Enregistrement en cours';
                status.className = 'tag status-badge is-danger is-large';
                
                timerInterval = setInterval(updateTimer, 1000);
                
            } catch (error) {
                console.error('Erreur d\'accès au microphone:', error);
                status.innerHTML = '<i class="fas fa-exclamation-triangle"></i>&nbsp;Erreur microphone';
                status.className = 'tag status-badge is-danger is-large';
            }
        }
        
        // Pause/Resume recording
        function pauseResumeRecording() {
            if (!mediaRecorder) return;
            
            if (!isPaused) {
                mediaRecorder.pause();
                isPaused = true;
                pauseBtn.innerHTML = '<i class="fas fa-play"></i>&nbsp;Reprendre';
                status.innerHTML = '<i class="fas fa-pause-circle"></i>&nbsp;En pause';
                status.className = 'tag status-badge is-warning is-large';
                clearInterval(timerInterval);
            } else {
                mediaRecorder.resume();
                isPaused = false;
                pauseBtn.innerHTML = '<i class="fas fa-pause"></i>&nbsp;Pause';
                status.innerHTML = '<i class="fas fa-recording-circle"></i>&nbsp;Enregistrement en cours';
                status.className = 'tag status-badge is-danger is-large';
                timerInterval = setInterval(updateTimer, 1000);
            }
        }
        
        // Stop recording
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            
            startBtn.disabled = false;
            pauseBtn.disabled = true;
            stopBtn.disabled = true;
            startBtn.classList.remove('is-recording');
            
            status.innerHTML = '<i class="fas fa-check-circle"></i>&nbsp;Enregistrement terminé';
            status.className = 'tag status-badge is-success is-large';
            
            clearInterval(timerInterval);
            
            // Reset visualizer
            const bars = visualizer.querySelectorAll('.bar');
            bars.forEach(bar => {
                bar.style.height = '2px';
            });
        }
        
        // Update timer
        function updateTimer() {
            if (!startTime) return;
            
            const elapsed = Math.floor((Date.now() - startTime - pausedTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            timer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Auto-stop after 5 minutes
            if (elapsed >= 300) {
                stopRecording();
            }
        }
        
        // Download audio
        function downloadAudio() {
            const audioUrl = audioPlayer.src;
            if (audioUrl) {
                const a = document.createElement('a');
                a.href = audioUrl;
                a.download = `enregistrement_${new Date().toISOString().slice(0,19).replace(/[:]/g, '-')}.wav`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        }
        
        // Share audio
        async function shareAudio() {
            const audioUrl = audioPlayer.src;
            if (audioUrl) {
                try {
                    const response = await fetch(audioUrl);
                    const blob = await response.blob();
                    
                    const formData = new FormData();
                    formData.append('audio', blob, 'enregistrement.wav');
                    formData.append('duration', Math.floor((Date.now() - startTime - pausedTime) / 1000));
                    
                    const uploadResponse = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (uploadResponse.ok) {
                        location.reload();
                    } else {
                        throw new Error('Erreur lors de l\'upload');
                    }
                } catch (error) {
                    console.error('Erreur lors du partage:', error);
                    alert('❌ Erreur lors du partage de l\'enregistrement');
                }
            }
        }
        
        // Copy share URL
        function copyShareUrl() {
            shareUrl.select();
            document.execCommand('copy');
            copyBtn.innerHTML = '<i class="fas fa-check"></i>&nbsp;Copié !';
            setTimeout(() => {
                copyBtn.innerHTML = '<i class="fas fa-copy"></i>&nbsp;Copier';
            }, 2000);
        }
        
        // New recording
        function newRecording() {
            audioPlayerSection.style.display = 'none';
            shareSection.style.display = 'none';
            timer.textContent = '00:00';
            status.innerHTML = '<i class="fas fa-circle"></i>&nbsp;Prêt à enregistrer';
            status.className = 'tag status-badge is-info is-large';
            
            // Reset visualizer
            const bars = visualizer.querySelectorAll('.bar');
            bars.forEach(bar => {
                bar.style.height = '2px';
            });
        }
        
        // Update history display
        function updateHistoryDisplay() {
            if (recordingHistory.length === 0) {
                historyList.innerHTML = '';
                emptyHistory.style.display = 'block';
                return;
            }
            
            emptyHistory.style.display = 'none';
            historyList.innerHTML = recordingHistory.map((recording, index) => `
                <div class="card history-card" data-index="${index}">
                    <div class="card-content">
                        <div class="level is-mobile">
                            <div class="level-left">
                                <div class="level-item">
                                    <div>
                                        <p class="has-text-weight-semibold">
                                            <i class="fas fa-microphone"></i>
                                            Enregistrement #${recordingHistory.length - index}
                                        </p>
                                        <p class="is-size-7 has-text-grey">
                                            ${recording.date} • ${Math.floor(recording.size / 1024)} KB
                                            ${recording.duration ? ` • ${Math.floor(recording.duration / 60)}:${(recording.duration % 60).toString().padStart(2, '0')}` : ''}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <button class="delete-btn" onclick="deleteRecording(${index})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Delete recording from history
        function deleteRecording(index) {
            if (confirm('Supprimer cet enregistrement ?')) {
                recordingHistory.splice(index, 1);
                localStorage.setItem('recordingHistory', JSON.stringify(recordingHistory));
                updateHistoryDisplay();
            }
        }
        
        // Clear all history
        function clearHistory() {
            if (confirm('Supprimer tout l\'historique ?')) {
                recordingHistory = [];
                localStorage.removeItem('recordingHistory');
                updateHistoryDisplay();
            }
        }
        
        // Event listeners
        startBtn.addEventListener('click', startRecording);
        pauseBtn.addEventListener('click', pauseResumeRecording);
        stopBtn.addEventListener('click', stopRecording);
        downloadBtn.addEventListener('click', downloadAudio);
        shareBtn.addEventListener('click', shareAudio);
        newRecordBtn.addEventListener('click', newRecording);
        copyBtn.addEventListener('click', copyShareUrl);
        clearHistoryBtn.addEventListener('click', clearHistory);
        
        // History toggle
        historyToggle.addEventListener('click', () => {
            const historyCard = document.querySelector('.card.mt-6');
            if (historyCard.style.display === 'none') {
                historyCard.style.display = 'block';
                historyToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                historyCard.style.display = 'none';
                historyToggle.innerHTML = '<i class="fas fa-history"></i>';
            }
        });
        
        // Notification close buttons
        document.querySelectorAll('.notification .delete').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.parentElement.style.display = 'none';
            });
        });
        
        // Initialize
        initializeVisualizer();
        updateHistoryDisplay();
        
        // Show share URL if present
        <?php if ($share_url): ?>
        shareSection.style.display = 'block';
        shareUrl.value = '<?= htmlspecialchars($share_url) ?>';
        openBtn.href = '<?= htmlspecialchars($share_url) ?>';
        <?php endif; ?>
    </script>
</body>
</html>