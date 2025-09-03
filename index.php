<?php
/**
 * Enregistreur Vocal Simple
 * Auteur: EL GNANI Mohamed
 * Version: 1.0
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

// Variables d'état
$play_mode = false;
$message = '';
$error = '';
$share_url = '';
$my_recordings = [];

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
        
        // Vérifier le type MIME (plus permissif)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Accepter tous les fichiers audio/video (WebM contient souvent de l'audio)
        if (!in_array($mime_type, $allowed_mime_types) && 
            !str_starts_with($mime_type, 'audio/') && 
            !str_starts_with($mime_type, 'video/')) {
            throw new Exception('Type de fichier non autorisé: ' . $mime_type);
        }
        
        // Générer un nom de fichier unique et sécurisé
        $file_id = bin2hex(random_bytes(16)) . '_' . time();
        $extension = 'webm'; // Extension fixe pour éviter les problèmes
        $filename = $file_id . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Vérifier l'espace disque disponible
        if (disk_free_space($upload_dir) < ($file['size'] * 2)) {
            throw new Exception('Espace disque insuffisant.');
        }
        
        // Déplacer le fichier
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Stocker les métadonnées
            $audio_info = [
                'id' => $file_id,
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $mime_type,
                'upload_date' => date('d/m/Y à H:i'),
                'comment' => isset($_POST['comment']) ? trim($_POST['comment']) : '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
            ];
            
            file_put_contents($upload_dir . $file_id . '.json', json_encode($audio_info, JSON_PRETTY_PRINT));
            
            $message = 'Lien de partage généré avec succès !';
            // Générer l'URL de partage (compatible avec tous les serveurs)
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/index.php';
            $share_url = $scheme . '://' . $host . $uri . '?play=' . $file_id;
        } else {
            throw new Exception('Erreur lors de la génération du lien.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Traitement des actions utilisateur
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_note':
            if (isset($_POST['file_id']) && isset($_POST['note'])) {
                $file_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['file_id']);
                $note_file = $upload_dir . $file_id . '.json';
                
                if (file_exists($note_file)) {
                    $audio_info = json_decode(file_get_contents($note_file), true);
                    $audio_info['comment'] = trim($_POST['note']);
                    file_put_contents($note_file, json_encode($audio_info, JSON_PRETTY_PRINT));
                    $message = 'Note mise à jour avec succès !';
                }
            }
            break;
            
        case 'delete_recording':
            if (isset($_POST['file_id'])) {
                $file_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['file_id']);
                $audio_file = glob($upload_dir . $file_id . '.*');
                
                foreach ($audio_file as $file) {
                    @unlink($file);
                }
                $message = 'Enregistrement supprimé avec succès !';
            }
            break;
    }
}

// Affichage d'un enregistrement partagé
$play_audio = null;
if (isset($_GET['play'])) {
    $play_mode = true;
    $play_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['play']);
    $play_file = $upload_dir . $play_id . '.json';
    
    if (file_exists($play_file)) {
        $play_audio = json_decode(file_get_contents($play_file), true);
        
        // Incrémenter le compteur de vues
        if (!isset($play_audio['views'])) {
            $play_audio['views'] = 0;
        }
        $play_audio['views']++;
        file_put_contents($play_file, json_encode($play_audio, JSON_PRETTY_PRINT));
    }
}

// Affichage de la liste des enregistrements
if (isset($_GET['my_recordings'])) {
    $json_files = glob($upload_dir . '*.json');
    foreach ($json_files as $json_file) {
        $audio_info = json_decode(file_get_contents($json_file), true);
        if ($audio_info) {
            $my_recordings[] = $audio_info;
        }
    }
    // Trier par date de création (plus récent en premier)
    usort($my_recordings, function($a, $b) {
        return strtotime($b['upload_date']) - strtotime($a['upload_date']);
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($play_mode): ?>
    <meta name="robots" content="noindex, nofollow">
    <title>🎵 Écouter l'enregistrement</title>
    <?php else: ?>
    <title>🎤 Enregistreur Vocal Simple</title>
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1em;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .share-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .share-url {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 2px solid #dee2e6;
            font-family: monospace;
            font-size: 0.9em;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .record-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 20px 40px;
            border-radius: 50px;
            font-size: 1.2em;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
            min-width: 150px;
        }
        
        .record-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255,71,87,0.3);
        }
        
        .record-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .record-btn.recording {
            background: #ff3742;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pause-btn {
            background: #ffa502;
        }
        
        .stop-btn {
            background: #747d8c;
        }
        
        .save-btn {
            background: #2ed573;
        }
        
        .timer {
            font-size: 1.8em;
            color: #333;
            margin: 20px 0;
            font-weight: bold;
            font-family: monospace;
        }
        
        .waveform {
            height: 80px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ddd;
            overflow: hidden;
        }
        
        .waveform.active {
            border-color: #ff4757;
            background: #fff5f5;
        }
        
        .wave-bars {
            display: flex;
            align-items: center;
            height: 100%;
            gap: 3px;
        }
        
        .bar {
            width: 4px;
            background: #ff4757;
            border-radius: 2px;
            transition: height 0.1s ease;
        }
        
        .bar.animate {
            animation: wave 0.6s ease-in-out infinite alternate;
        }
        
        @keyframes wave {
            0% { height: 4px; }
            100% { height: 50px; }
        }
        
        .audio-player {
            margin: 20px 0;
            width: 100%;
        }
        
        .audio-player audio {
            width: 100%;
            margin: 10px 0;
        }
        
        .download-btn {
            background: #3742fa;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            cursor: pointer;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            background: #2f3edd;
            transform: translateY(-2px);
        }
        
        .controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .status.recording {
            background: #fff5f5;
            color: #ff4757;
            border: 2px solid #ffe0e0;
        }
        
        .status.ready {
            background: #f0fff4;
            color: #2ed573;
            border: 2px solid #d4edda;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #ffcdd2;
        }
        
        .play-section {
            background: #e8f5e8;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .copy-btn {
            background: #5352ed;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 0.9em;
        }
        
        .speed-btn {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #dee2e6;
            padding: 8px 16px;
            margin: 0 5px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .speed-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .speed-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .recordings-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .recording-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .recording-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .edit-btn {
            background: #ffa502;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .delete-btn {
            background: #ff3742;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .nav-btn {
            background: #3742fa;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(55, 66, 250, 0.3);
        }
        
        .note-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px 0;
            font-family: inherit;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .record-btn {
                padding: 15px 25px;
                font-size: 1em;
                min-width: 120px;
            }
            
            .controls {
                flex-direction: column;
                align-items: center;
            }
            
            .share-url {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($play_audio): ?>
            <!-- Mode lecture d'un enregistrement partagé -->
            <h1>🎵 Enregistrement Partagé</h1>
            <p class="subtitle">Écoutez cet enregistrement vocal</p>
            
            <div class="play-section">
                <h3>📅 <?= htmlspecialchars($play_audio['upload_date']) ?></h3>
                <p>Nom original: <?= htmlspecialchars($play_audio['original_name']) ?></p>
                <p>Taille: <?= number_format($play_audio['size'] / 1024, 1) ?> KB</p>
                <p>👁️ Vues: <?= isset($play_audio['views']) ? $play_audio['views'] : 1 ?></p>
                
                <?php if (!empty($play_audio['comment'])): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #667eea;">
                    <h4 style="margin: 0 0 8px 0; color: #333;">💬 Note :</h4>
                    <p style="margin: 0; font-style: italic; color: #555;"><?= nl2br(htmlspecialchars($play_audio['comment'])) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="audio-controls-section" style="margin: 20px 0;">
                    <div class="speed-controls" style="margin-bottom: 15px; text-align: center;">
                        <label style="margin-right: 10px; font-weight: bold;">Vitesse de lecture:</label>
                        <button onclick="setPlaybackRate(1)" class="speed-btn active" id="speed1">x1</button>
                        <button onclick="setPlaybackRate(1.5)" class="speed-btn" id="speed15">x1.5</button>
                        <button onclick="setPlaybackRate(2)" class="speed-btn" id="speed2">x2</button>
                    </div>
                    
                    <audio controls id="mainAudio" style="width: 100%;">
                        <source src="<?= $upload_dir . htmlspecialchars($play_audio['filename']) ?>" type="<?= htmlspecialchars($play_audio['mime_type']) ?>">
                        Votre navigateur ne supporte pas l'audio HTML5.
                    </audio>
                </div>
                
                <div>
                    <a href="<?= $upload_dir . htmlspecialchars($play_audio['filename']) ?>" download="<?= htmlspecialchars($play_audio['original_name']) ?>" class="download-btn">
                        💾 Télécharger
                    </a>
                </div>
            </div>
            
            <p>
                <a href="index.php" class="nav-btn" style="margin-right: 10px;">← Nouvel enregistrement</a>
                <a href="index.php?my_recordings=1" class="nav-btn">📋 Mes enregistrements</a>
            </p>
            
        <?php elseif (!empty($my_recordings)): ?>
            <!-- Mode visualisation des enregistrements -->
            <h1>📋 Mes Enregistrements</h1>
            <p class="subtitle">Gérez vos enregistrements vocaux</p>
            
            <?php if ($message): ?>
                <div class="alert success">
                    ✅ <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="recordings-list">
                <?php foreach ($my_recordings as $recording): ?>
                    <div class="recording-item">
                        <h4>🎧 <?= htmlspecialchars($recording['original_name']) ?></h4>
                        <p><strong>Date:</strong> <?= htmlspecialchars($recording['upload_date']) ?></p>
                        <p><strong>Taille:</strong> <?= number_format($recording['size'] / 1024, 1) ?> KB</p>
                        <p><strong>Vues:</strong> <?= isset($recording['views']) ? $recording['views'] : 0 ?></p>
                        
                        <?php if (!empty($recording['comment'])): ?>
                        <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                            <strong>💬 Note:</strong> <?= nl2br(htmlspecialchars($recording['comment'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="recording-actions">
                            <button class="edit-btn" onclick="editNote('<?= $recording['id'] ?>', '<?= htmlspecialchars(addslashes($recording['comment'] ?? '')) ?>')">✏️ Éditer la note</button>
                            <a href="index.php?play=<?= $recording['id'] ?>" class="nav-btn" style="padding: 6px 12px; margin: 0; font-size: 0.9em; text-decoration: none;">🎧 Écouter</a>
                            <button class="delete-btn" onclick="deleteRecording('<?= $recording['id'] ?>', '<?= htmlspecialchars($recording['original_name']) ?>')">🗑️ Supprimer</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($my_recordings)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">Aucun enregistrement trouvé</p>
                <?php endif; ?>
            </div>
            
            <p style="text-align: center;">
                <a href="index.php" class="nav-btn">← Créer un enregistrement</a>
            </p>
            
        <?php else: ?>
            <!-- Mode enregistrement normal -->
            <h1>🎤 Enregistreur Vocal</h1>
            <p class="subtitle">Simple, rapide et efficace</p>
            
            <?php if ($message): ?>
                <div class="alert success">
                    ✅ <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($share_url): ?>
                <div class="share-section">
                    <h3 style="color: #2ed573;">✨ Ça y est ! Votre enregistrement est en ligne</h3>
                    <div class="share-url" id="shareUrl"><?= htmlspecialchars($share_url) ?></div>
                    <div style="margin: 15px 0;">
                        <button class="copy-btn" onclick="copyToClipboard()" style="background: #2ed573; padding: 12px 24px; font-size: 1.1em;">
                            📋 Copier le lien
                        </button>
                        <button onclick="window.open('<?= htmlspecialchars($share_url) ?>', '_blank')" style="background: #5352ed; color: white; border: none; padding: 12px 24px; border-radius: 5px; margin-left: 10px; cursor: pointer; font-size: 1.1em;">
                            🔗 Ouvrir
                        </button>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.95em; color: #2ed573; font-weight: bold;">
                        ✅ Partagez ce lien - tout le monde peut écouter et télécharger
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="status" id="status">
                Cliquez sur "Commencer" pour démarrer l'enregistrement
            </div>
            
            <div class="timer" id="timer">00:00</div>
            
            <div class="waveform" id="waveform">
                <span style="color: #999;">🎵 Visualisation audio</span>
            </div>
            
            <div class="controls">
                <button class="record-btn" id="startBtn" onclick="startRecording()">
                    🎙️ Commencer
                </button>
                <button class="record-btn" id="newBtn" onclick="newRecording()" style="display: none; background: #5352ed;">
                    🔄 Nouveau
                </button>
                <button class="record-btn pause-btn" id="pauseBtn" onclick="pauseRecording()" style="display: none;">
                    ⏸️ Pause
                </button>
                <button class="record-btn stop-btn" id="stopBtn" onclick="stopRecording()" style="display: none;">
                    ⏹️ Arrêter
                </button>
            </div>
            
            <div class="audio-player" id="audioPlayer" style="display: none;">
                <audio controls id="audioPlayback"></audio>
                <br>
                <button class="download-btn" onclick="downloadRecording()">
                    💾 Télécharger
                </button>
                <button class="record-btn save-btn" onclick="shareRecording()">
                    🔗 Partager
                </button>
                <button class="record-btn" onclick="newRecording()" style="background: #5352ed;">
                    🔄 Nouveau
                </button>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php?my_recordings=1" class="nav-btn">📋 Voir mes enregistrements</a>
            </div>
        <?php endif; ?>
    </div>
    

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let currentPlaybackRate = 1;
        let isRecording = false;
        let isPaused = false;
        let startTime;
        let timerInterval;
        let currentBlob;
        let audioContext;
        let analyser;
        let microphone;
        let dataArray;
        let animationFrame;
        
        const statusEl = document.getElementById('status');
        const timerEl = document.getElementById('timer');
        const waveformEl = document.getElementById('waveform');
        const startBtn = document.getElementById('startBtn');
        const newBtn = document.getElementById('newBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const stopBtn = document.getElementById('stopBtn');
        const audioPlayer = document.getElementById('audioPlayer');
        const audioPlayback = document.getElementById('audioPlayback');
        
        // Copier l'URL dans le presse-papier
        function copyToClipboard() {
            const shareUrl = document.getElementById('shareUrl');
            const text = shareUrl.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ URL copiée dans le presse-papier !');
            }).catch(err => {
                // Fallback pour les anciens navigateurs
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ URL copiée !');
            });
        }
        
        // Formater le temps
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        
        // Mettre à jour le timer
        function updateTimer() {
            if (isRecording && !isPaused) {
                const elapsed = (Date.now() - startTime) / 1000;
                timerEl.textContent = formatTime(elapsed);
                
                // Arrêter automatiquement après 5 minutes
                if (elapsed >= 300) {
                    stopRecording();
                    showStatus('Enregistrement arrêté automatiquement (5 min max)', 'error');
                }
            }
        }
        
        // Afficher le statut
        function showStatus(message, type = 'ready') {
            if (statusEl) {
                statusEl.textContent = message;
                statusEl.className = `status ${type}`;
            }
        }
        
        // Démarrer l'enregistrement
        async function startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                // Essayer d'utiliser un format plus compatible
                let options = {};
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    options = { mimeType: 'audio/webm;codecs=opus' };
                } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                    options = { mimeType: 'audio/webm' };
                } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                    options = { mimeType: 'audio/mp4' };
                }
                
                mediaRecorder = new MediaRecorder(stream, options);
                audioChunks = [];
                
                console.log('MediaRecorder utilise:', mediaRecorder.mimeType);
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                    currentBlob = audioBlob;
                    const audioUrl = URL.createObjectURL(audioBlob);
                    
                    audioPlayback.src = audioUrl;
                    audioPlayer.style.display = 'block';
                    
                    // Arrêter le stream
                    stream.getTracks().forEach(track => track.stop());
                    
                    showStatus('✅ Enregistrement terminé ! Vous pouvez l\'écouter, le télécharger ou le partager.');
                };
                
                mediaRecorder.start();
                isRecording = true;
                isPaused = false;
                startTime = Date.now();
                
                // UI
                startBtn.style.display = 'none';
                newBtn.style.display = 'inline-block';
                pauseBtn.style.display = 'inline-block';
                stopBtn.style.display = 'inline-block';
                audioPlayer.style.display = 'none';
                
                waveformEl.classList.add('active');
                createWaveVisualization(stream);
                
                showStatus('🔴 Enregistrement en cours...', 'recording');
                
                timerInterval = setInterval(updateTimer, 100);
                
            } catch (error) {
                console.error('Erreur:', error);
                showStatus('❌ Erreur: Impossible d\'accéder au microphone. Vérifiez les autorisations.', 'error');
            }
        }
        
        // Pause/Reprendre
        function pauseRecording() {
            if (mediaRecorder && isRecording) {
                if (!isPaused) {
                    mediaRecorder.pause();
                    isPaused = true;
                    pauseBtn.innerHTML = '▶️ Reprendre';
                    showStatus('⏸️ Enregistrement en pause', 'ready');
                } else {
                    mediaRecorder.resume();
                    isPaused = false;
                    pauseBtn.innerHTML = '⏸️ Pause';
                    showStatus('🔴 Enregistrement en cours...', 'recording');
                }
            }
        }
        
        // Arrêter l'enregistrement
        function stopRecording() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                isRecording = false;
                isPaused = false;
                
                clearInterval(timerInterval);
                
                // UI
                startBtn.style.display = 'inline-block';
                pauseBtn.style.display = 'none';
                stopBtn.style.display = 'none';
                
                startBtn.innerHTML = '🎙️ Commencer';
                pauseBtn.innerHTML = '⏸️ Pause';
                
                waveformEl.classList.remove('active');
                waveformEl.innerHTML = '<span style="color: #999;">🎵 Visualisation audio</span>';
                stopVisualization();
            }
        }
        
        // Télécharger l'enregistrement
        function downloadRecording() {
            if (currentBlob) {
                const url = URL.createObjectURL(currentBlob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `enregistrement-${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.wav`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        }
        
        // Partager l'enregistrement
        function shareRecording() {
            if (!currentBlob) {
                alert('❌ Aucun enregistrement à partager');
                return;
            }
            
            // Demander un commentaire/note optionnel
            const comment = prompt('💬 Ajouter une note ou commentaire (optionnel) :');
            
            const formData = new FormData();
            formData.append('audio', currentBlob, 'enregistrement.wav');
            if (comment && comment.trim()) {
                formData.append('comment', comment.trim());
            }
            
            // Afficher un indicateur de chargement
            const shareBtn = document.querySelector('.save-btn');
            const originalText = shareBtn.textContent;
            shareBtn.textContent = '⏳ Partage...';
            shareBtn.disabled = true;
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Recharger la page pour afficher le résultat
                document.body.innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('❌ Erreur lors du partage');
                shareBtn.textContent = originalText;
                shareBtn.disabled = false;
            });
        }
        
        // Nouveau enregistrement
        function newRecording() {
            timerEl.textContent = '00:00';
            audioPlayer.style.display = 'none';
            currentBlob = null;
            
            // Remettre les boutons dans l'état initial
            startBtn.style.display = 'inline-block';
            newBtn.style.display = 'none';
            pauseBtn.style.display = 'none';
            stopBtn.style.display = 'none';
            
            showStatus('Cliquez sur "Commencer" pour démarrer l\'enregistrement');
        }
        
        // Créer la visualisation audio
        function createWaveVisualization(stream) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                microphone = audioContext.createMediaStreamSource(stream);
                
                analyser.fftSize = 256;
                const bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);
                
                microphone.connect(analyser);
                
                // Créer les barres de visualisation
                const numBars = 20;
                waveformEl.innerHTML = '';
                const waveBars = document.createElement('div');
                waveBars.className = 'wave-bars';
                
                for (let i = 0; i < numBars; i++) {
                    const bar = document.createElement('div');
                    bar.className = 'bar';
                    bar.style.height = '4px';
                    waveBars.appendChild(bar);
                }
                
                waveformEl.appendChild(waveBars);
                
                // Animation en temps réel
                function animate() {
                    if (isRecording && !isPaused) {
                        analyser.getByteFrequencyData(dataArray);
                        
                        const bars = waveBars.querySelectorAll('.bar');
                        bars.forEach((bar, index) => {
                            const dataIndex = Math.floor(index * bufferLength / numBars);
                            const amplitude = dataArray[dataIndex] || 0;
                            const height = Math.max(4, (amplitude / 255) * 60);
                            bar.style.height = height + 'px';
                        });
                        
                        animationFrame = requestAnimationFrame(animate);
                    }
                }
                
                animate();
                
            } catch (error) {
                console.error('Erreur Web Audio:', error);
                // Fallback simple si Web Audio ne fonctionne pas
                createSimpleAnimation();
            }
        }
        
        // Animation simple de fallback
        function createSimpleAnimation() {
            const numBars = 15;
            waveformEl.innerHTML = '';
            const waveBars = document.createElement('div');
            waveBars.className = 'wave-bars';
            
            for (let i = 0; i < numBars; i++) {
                const bar = document.createElement('div');
                bar.className = 'bar animate';
                bar.style.animationDelay = (i * 0.1) + 's';
                bar.style.height = Math.random() * 30 + 10 + 'px';
                waveBars.appendChild(bar);
            }
            
            waveformEl.appendChild(waveBars);
        }
        
        // Arrêter la visualisation
        function stopVisualization() {
            if (animationFrame) {
                cancelAnimationFrame(animationFrame);
                animationFrame = null;
            }
            if (audioContext && audioContext.state !== 'closed') {
                audioContext.close();
            }
        }
        
        // Gestion de la vitesse de lecture
        function setPlaybackRate(rate) {
            currentPlaybackRate = rate;
            const audio = document.getElementById('mainAudio');
            if (audio) {
                audio.playbackRate = rate;
            }
            
            // Mettre à jour les boutons de vitesse
            document.querySelectorAll('.speed-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (rate === 1) document.getElementById('speed1').classList.add('active');
            else if (rate === 1.5) document.getElementById('speed15').classList.add('active');
            else if (rate === 2) document.getElementById('speed2').classList.add('active');
        }
        
        // Édition des notes
        function editNote(fileId, currentNote) {
            const newNote = prompt('💬 Modifier la note:', currentNote || '');
            if (newNote !== null) {
                const formData = new FormData();
                formData.append('action', 'update_note');
                formData.append('file_id', fileId);
                formData.append('note', newNote);
                
                fetch('index.php?my_recordings=1', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('❌ Erreur lors de la mise à jour de la note');
                });
            }
        }
        
        // Suppression d'enregistrement
        function deleteRecording(fileId, fileName) {
            if (confirm(`⚠️ Êtes-vous sûr de vouloir supprimer l'enregistrement "${fileName}" ?\n\nCette action est irréversible.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_recording');
                formData.append('file_id', fileId);
                
                fetch('index.php?my_recordings=1', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('❌ Erreur lors de la suppression');
                });
            }
        }
        
        // Initialiser la vitesse de lecture au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('mainAudio');
            if (audio) {
                audio.addEventListener('loadedmetadata', function() {
                    audio.playbackRate = currentPlaybackRate;
                });
            }
        });
        
        // Vérifier les capacités du navigateur
        if (statusEl && (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia)) {
            showStatus('❌ Votre navigateur ne supporte pas l\'enregistrement audio.', 'error');
            if (startBtn) startBtn.disabled = true;
        }
    </script>
</body>
</html>