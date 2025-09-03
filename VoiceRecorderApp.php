<?php
/**
 * Classe principale de l'application Enregistreur Vocal
 * Extrait les fonctionnalités principales pour faciliter les tests
 */
class VoiceRecorderApp
{
    private string $upload_dir;
    private int $max_file_size;
    private int $max_duration;
    private int $cleanup_days;
    private array $allowed_mime_types;

    public function __construct(
        string $upload_dir = 'uploads/',
        int $max_file_size = 10 * 1024 * 1024,
        int $max_duration = 300,
        int $cleanup_days = 7,
        array $allowed_mime_types = [
            'audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/webm', 'audio/mp4',
            'audio/x-wav', 'audio/wave', 'application/octet-stream',
            'video/webm', 'video/mp4'
        ]
    ) {
        $this->upload_dir = $upload_dir;
        $this->max_file_size = $max_file_size;
        $this->max_duration = $max_duration;
        $this->cleanup_days = $cleanup_days;
        $this->allowed_mime_types = $allowed_mime_types;
    }

    /**
     * Incrémente le compteur de vues pour un enregistrement
     * @param string $file_id ID unique du fichier
     * @return array|null Données de l'enregistrement avec vues mises à jour
     */
    public function incrementViewCount(string $file_id): ?array
    {
        if (empty($file_id) || !preg_match('/^[a-zA-Z0-9_]+$/', $file_id)) {
            return null;
        }

        $json_file = $this->upload_dir . $file_id . '.json';
        
        if (!file_exists($json_file)) {
            return null;
        }

        $audio_info = json_decode(file_get_contents($json_file), true);
        
        if (!$audio_info) {
            return null;
        }

        if (!isset($audio_info['views'])) {
            $audio_info['views'] = 0;
        }
        
        $audio_info['views']++;
        
        if (file_put_contents($json_file, json_encode($audio_info, JSON_PRETTY_PRINT)) === false) {
            return null;
        }
        
        return $audio_info;
    }

    /**
     * Met à jour la note d'un enregistrement
     * @param string $file_id ID unique du fichier
     * @param string $note Nouvelle note
     * @return bool Succès de la mise à jour
     */
    public function updateNote(string $file_id, string $note): bool
    {
        if (empty($file_id) || !preg_match('/^[a-zA-Z0-9_]+$/', $file_id)) {
            return false;
        }

        $json_file = $this->upload_dir . $file_id . '.json';
        
        if (!file_exists($json_file)) {
            return false;
        }

        $audio_info = json_decode(file_get_contents($json_file), true);
        
        if (!$audio_info) {
            return false;
        }

        $audio_info['comment'] = trim($note);
        
        return file_put_contents($json_file, json_encode($audio_info, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Supprime un enregistrement et ses métadonnées
     * @param string $file_id ID unique du fichier
     * @return bool Succès de la suppression
     */
    public function deleteRecording(string $file_id): bool
    {
        if (empty($file_id) || !preg_match('/^[a-zA-Z0-9_]+$/', $file_id)) {
            return false;
        }

        $files_deleted = 0;
        $files_to_delete = glob($this->upload_dir . $file_id . '.*');
        
        foreach ($files_to_delete as $file) {
            if (@unlink($file)) {
                $files_deleted++;
            }
        }
        
        return $files_deleted > 0;
    }

    /**
     * Récupère la liste de tous les enregistrements
     * @return array Liste des enregistrements triés par date
     */
    public function getAllRecordings(): array
    {
        $recordings = [];
        $json_files = glob($this->upload_dir . '*.json');
        
        foreach ($json_files as $json_file) {
            $audio_info = json_decode(file_get_contents($json_file), true);
            if ($audio_info && is_array($audio_info)) {
                $recordings[] = $audio_info;
            }
        }
        
        // Trier par date de création (plus récent en premier)
        usort($recordings, function($a, $b) {
            $date_a = isset($a['upload_date']) ? strtotime($a['upload_date']) : 0;
            $date_b = isset($b['upload_date']) ? strtotime($b['upload_date']) : 0;
            return $date_b - $date_a;
        });
        
        return $recordings;
    }

    /**
     * Valide un fichier audio uploadé
     * @param array $file Données du fichier $_FILES
     * @return array Résultat de validation ['valid' => bool, 'error' => string]
     */
    public function validateUploadedFile(array $file): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Erreur lors de l\'upload du fichier.'];
        }
        
        if (!isset($file['size']) || $file['size'] > $this->max_file_size) {
            return ['valid' => false, 'error' => 'Le fichier est trop volumineux (max 10MB).'];
        }
        
        if ($file['size'] <= 0) {
            return ['valid' => false, 'error' => 'Le fichier est vide.'];
        }
        
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Génère un ID unique pour un fichier
     * @return string ID unique
     */
    public function generateFileId(): string
    {
        return bin2hex(random_bytes(16)) . '_' . time();
    }

    /**
     * Nettoie les fichiers anciens
     * @return int Nombre de fichiers supprimés
     */
    public function cleanupOldFiles(): int
    {
        $deleted_count = 0;
        $cutoff_time = time() - ($this->cleanup_days * 24 * 60 * 60);
        
        foreach (glob($this->upload_dir . '*') as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (@unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }

    /**
     * Vérifie si l'espace disque est suffisant
     * @param int $required_space Espace requis en octets
     * @return bool Vrai si l'espace est suffisant
     */
    public function hasSufficientDiskSpace(int $required_space): bool
    {
        $free_space = disk_free_space($this->upload_dir);
        return $free_space !== false && $free_space >= ($required_space * 2);
    }

    // Getters pour les tests
    public function getUploadDir(): string { return $this->upload_dir; }
    public function getMaxFileSize(): int { return $this->max_file_size; }
    public function getMaxDuration(): int { return $this->max_duration; }
    public function getCleanupDays(): int { return $this->cleanup_days; }
    public function getAllowedMimeTypes(): array { return $this->allowed_mime_types; }
}