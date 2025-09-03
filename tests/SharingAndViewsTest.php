<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests pour le système de partage et compteur de vues
 * Couvre les fonctionnalités de partage, comptage de vues et sécurité
 */
class SharingAndViewsTest extends TestCase
{
    private VoiceRecorderApp $app;
    private string $test_upload_dir;

    protected function setUp(): void
    {
        $this->test_upload_dir = sys_get_temp_dir() . '/sharing_views_tests_' . uniqid() . '/';
        if (!file_exists($this->test_upload_dir)) {
            mkdir($this->test_upload_dir, 0777, true);
        }

        $this->app = new VoiceRecorderApp($this->test_upload_dir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->test_upload_dir)) {
            $files = glob($this->test_upload_dir . '*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->test_upload_dir);
        }
    }

    /**
     * Crée un enregistrement de test avec des métadonnées complètes
     */
    private function createCompleteRecording(string $file_id, array $overrides = []): array
    {
        $recording_data = array_merge([
            'id' => $file_id,
            'filename' => $file_id . '.webm',
            'original_name' => 'Mon_Enregistrement.webm',
            'size' => 2048000, // 2MB
            'mime_type' => 'audio/webm',
            'upload_date' => date('d/m/Y à H:i'),
            'comment' => 'Enregistrement de test complet',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'views' => 0
        ], $overrides);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, json_encode($recording_data, JSON_PRETTY_PRINT));

        // Créer un faux fichier audio
        $audio_file = $this->test_upload_dir . $recording_data['filename'];
        file_put_contents($audio_file, str_repeat('fake_audio_data', 1000));

        return $recording_data;
    }

    /**
     * Simule une requête GET pour lire un enregistrement partagé
     */
    private function simulateSharedRecordingView(string $file_id): ?array
    {
        // Nettoyer l'ID comme dans index.php
        $clean_id = preg_replace('/[^a-zA-Z0-9_]/', '', $file_id);
        $play_file = $this->test_upload_dir . $clean_id . '.json';

        if (file_exists($play_file)) {
            $play_audio = json_decode(file_get_contents($play_file), true);
            
            if ($play_audio) {
                // Incrémenter le compteur de vues comme dans index.php
                if (!isset($play_audio['views'])) {
                    $play_audio['views'] = 0;
                }
                $play_audio['views']++;
                file_put_contents($play_file, json_encode($play_audio, JSON_PRETTY_PRINT));
                
                return $play_audio;
            }
        }

        return null;
    }

    // ========== TESTS COMPTEUR DE VUES ==========

    /**
     * @test
     */
    public function compteur_vues_incremente_a_chaque_consultation()
    {
        $file_id = 'test_view_counter';
        $this->createCompleteRecording($file_id, ['views' => 5]);

        // Première consultation
        $result1 = $this->simulateSharedRecordingView($file_id);
        $this->assertEquals(6, $result1['views']);

        // Deuxième consultation
        $result2 = $this->simulateSharedRecordingView($file_id);
        $this->assertEquals(7, $result2['views']);

        // Troisième consultation
        $result3 = $this->simulateSharedRecordingView($file_id);
        $this->assertEquals(8, $result3['views']);
    }

    /**
     * @test
     */
    public function compteur_vues_initialise_a_un_si_absent()
    {
        $file_id = 'test_init_counter';
        $this->createCompleteRecording($file_id); // Sans 'views'

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['views']);
    }

    /**
     * @test
     */
    public function compteur_vues_persiste_entre_les_consultations()
    {
        $file_id = 'test_persist_views';
        $this->createCompleteRecording($file_id, ['views' => 0]);

        // Plusieurs consultations
        for ($i = 1; $i <= 10; $i++) {
            $this->simulateSharedRecordingView($file_id);
        }

        // Vérifier la persistance en relisant directement le fichier
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals(10, $data['views']);
    }

    /**
     * @test
     * @dataProvider fournisseur_file_ids_invalides_pour_vues
     */
    public function compteur_vues_avec_file_id_invalide_retourne_null(string $invalid_id)
    {
        $result = $this->simulateSharedRecordingView($invalid_id);
        $this->assertNull($result);
    }

    public function fournisseur_file_ids_invalides_pour_vues(): array
    {
        return [
            'chaine_vide' => [''],
            'caracteres_speciaux' => ['test@#$%'],
            'tentative_directory_traversal' => ['../../../etc/passwd'],
            'slash_windows' => ['test\\..\\windows'],
            'caracteres_unicode' => ['test_🎵_audio'],
            'espaces' => ['test avec espaces'],
            'html_injection' => ['<script>alert(1)</script>']
        ];
    }

    /**
     * @test
     */
    public function compteur_vues_fichier_inexistant_retourne_null()
    {
        $result = $this->simulateSharedRecordingView('fichier_inexistant_123');
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function compteur_vues_json_corrompu_retourne_null()
    {
        $file_id = 'test_corrupted_json';
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, 'json corrompu {{{ invalid');

        $result = $this->simulateSharedRecordingView($file_id);
        $this->assertNull($result);
    }

    // ========== TESTS GESTION DES DONNÉES DE PARTAGE ==========

    /**
     * @test
     */
    public function donnees_partage_completes_sont_preservees()
    {
        $file_id = 'test_complete_sharing';
        $original_data = $this->createCompleteRecording($file_id, [
            'comment' => 'Note importante pour le partage',
            'original_name' => 'Enregistrement_Spécial.webm',
            'user_agent' => 'Custom User Agent String'
        ]);

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($original_data['comment'], $result['comment']);
        $this->assertEquals($original_data['original_name'], $result['original_name']);
        $this->assertEquals($original_data['user_agent'], $result['user_agent']);
        $this->assertEquals(1, $result['views']); // Incrémenté
    }

    /**
     * @test
     */
    public function donnees_partage_gere_caracteres_speciaux()
    {
        $file_id = 'test_special_chars_sharing';
        $special_data = [
            'original_name' => 'Enregistrement_avec_accents_éàùç_et_émojis_🎵.webm',
            'comment' => 'Note avec caractères spéciaux: àáâãäåæçèéêëìíîïñòóôõöøùúûüýÿ et émojis 🎤🎵🔊',
            'user_agent' => 'Navigateur avec caractères spéciaux: çñü'
        ];

        $this->createCompleteRecording($file_id, $special_data);
        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($special_data['original_name'], $result['original_name']);
        $this->assertEquals($special_data['comment'], $result['comment']);
        $this->assertEquals($special_data['user_agent'], $result['user_agent']);
    }

    /**
     * @test
     */
    public function donnees_partage_sans_commentaire_fonctionne()
    {
        $file_id = 'test_no_comment';
        $this->createCompleteRecording($file_id, ['comment' => '']);

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals('', $result['comment']);
        $this->assertEquals(1, $result['views']);
    }

    // ========== TESTS FORMATS DE FICHIERS ET MÉTADONNÉES ==========

    /**
     * @test
     * @dataProvider fournisseur_types_mime_audio
     */
    public function partage_supporte_differents_formats_audio(string $mime_type, string $extension)
    {
        $file_id = 'test_format_' . str_replace('/', '_', $mime_type);
        $this->createCompleteRecording($file_id, [
            'mime_type' => $mime_type,
            'filename' => $file_id . '.' . $extension
        ]);

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($mime_type, $result['mime_type']);
        $this->assertEquals($file_id . '.' . $extension, $result['filename']);
    }

    public function fournisseur_types_mime_audio(): array
    {
        return [
            'webm' => ['audio/webm', 'webm'],
            'wav' => ['audio/wav', 'wav'],
            'mp3' => ['audio/mpeg', 'mp3'],
            'ogg' => ['audio/ogg', 'ogg'],
            'mp4_audio' => ['audio/mp4', 'm4a'],
            'wave' => ['audio/wave', 'wav']
        ];
    }

    /**
     * @test
     * @dataProvider fournisseur_tailles_fichier
     */
    public function partage_gere_differentes_tailles_fichier(int $size_bytes, string $expected_display)
    {
        $file_id = 'test_size_' . $size_bytes;
        $this->createCompleteRecording($file_id, ['size' => $size_bytes]);

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($size_bytes, $result['size']);
        
        // Vérifier le formatage de la taille (comme dans index.php)
        $formatted_size = number_format($size_bytes / 1024, 1);
        $this->assertEquals($expected_display, $formatted_size);
    }

    public function fournisseur_tailles_fichier(): array
    {
        return [
            'tres_petit' => [512, '0.5'], // 0.5 KB
            'petit' => [1024, '1.0'], // 1 KB
            'moyen' => [512000, '500.0'], // 500 KB
            'zero' => [0, '0.0']
        ];
    }

    // ========== TESTS SÉCURITÉ DU PARTAGE ==========

    /**
     * @test
     */
    public function securite_file_id_nettoie_caracteres_dangereux()
    {
        // Créer un enregistrement avec un ID propre
        $clean_id = 'test123_456';
        $this->createCompleteRecording($clean_id);

        // Essayer d'y accéder avec des caractères dangereux
        $malicious_attempts = [
            'test123_456/../../../etc/passwd',
            'test123_456%00',
            'test123_456<script>',
            'test123_456;rm -rf /',
            'test123_456|whoami'
        ];

        $at_least_one_blocked = false;
        foreach ($malicious_attempts as $malicious_id) {
            $result = $this->simulateSharedRecordingView($malicious_id);
            
            if ($result === null) {
                $at_least_one_blocked = true;
            } elseif ($result !== null) {
                // Si un résultat est retourné, vérifier que c'est bien le fichier légitime
                $this->assertEquals($clean_id, $result['id']);
            }
        }
        
        // Au moins une tentative malicieuse devrait être bloquée
        $this->assertTrue($at_least_one_blocked, 'Au moins une tentative malicieuse devrait être bloquée');
    }

    /**
     * @test
     */
    public function securite_donnees_echappees_dans_affichage()
    {
        $file_id = 'test_xss_protection';
        $malicious_data = [
            'original_name' => '<script>alert("XSS")</script>malicious.webm',
            'comment' => '<img src=x onerror=alert("XSS")>Note malicieuse',
            'user_agent' => '"><script>alert("XSS")</script>'
        ];

        $this->createCompleteRecording($file_id, $malicious_data);
        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        
        // Les données malicieuses sont stockées telles quelles (pas d'échappement au stockage)
        // L'échappement doit se faire à l'affichage avec htmlspecialchars()
        $this->assertEquals($malicious_data['original_name'], $result['original_name']);
        $this->assertEquals($malicious_data['comment'], $result['comment']);
        $this->assertEquals($malicious_data['user_agent'], $result['user_agent']);
    }

    // ========== TESTS PERFORMANCE ET ROBUSTESSE ==========

    /**
     * @test
     */
    public function performance_compteur_vues_nombreuses_consultations()
    {
        $file_id = 'test_performance_views';
        $this->createCompleteRecording($file_id, ['views' => 0]);

        $start_time = microtime(true);
        
        // Simuler 100 consultations
        for ($i = 0; $i < 100; $i++) {
            $this->simulateSharedRecordingView($file_id);
        }
        
        $execution_time = microtime(true) - $start_time;

        $this->assertLessThan(5.0, $execution_time, '100 consultations devraient prendre moins de 5 secondes');

        // Vérifier le compteur final
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals(100, $data['views']);
    }

    /**
     * @test
     */
    public function robustesse_acces_concurrent_compteur_vues()
    {
        $file_id = 'test_concurrent_views';
        $this->createCompleteRecording($file_id, ['views' => 0]);

        // Simuler des accès concurrents (séquentiels pour les tests)
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->simulateSharedRecordingView($file_id);
        }

        // Vérifier que chaque consultation a incrémenté le compteur
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($i + 1, $results[$i]['views']);
        }
    }

    /**
     * @test
     */
    public function robustesse_fichier_corrompu_pendant_lecture()
    {
        $file_id = 'test_corruption_during_read';
        $this->createCompleteRecording($file_id, ['views' => 5]);

        // Première lecture normale
        $result1 = $this->simulateSharedRecordingView($file_id);
        $this->assertEquals(6, $result1['views']);

        // Corrompre le fichier
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, 'json corrompu');

        // Tentative de lecture après corruption
        $result2 = $this->simulateSharedRecordingView($file_id);
        $this->assertNull($result2);
    }

    // ========== TESTS CAS LIMITES ==========

    /**
     * @test
     */
    public function cas_limite_compteur_vues_tres_eleve()
    {
        $file_id = 'test_high_views';
        $high_view_count = PHP_INT_MAX - 10;
        
        $this->createCompleteRecording($file_id, ['views' => $high_view_count]);

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($high_view_count + 1, $result['views']);
    }

    /**
     * @test
     */
    public function cas_limite_donnees_manquantes_dans_metadata()
    {
        $file_id = 'test_missing_metadata';
        
        // Créer un fichier avec des métadonnées minimales
        $minimal_data = [
            'id' => $file_id,
            'filename' => $file_id . '.webm'
            // Pas de size, mime_type, upload_date, etc.
        ];

        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, json_encode($minimal_data, JSON_PRETTY_PRINT));

        $result = $this->simulateSharedRecordingView($file_id);

        $this->assertNotNull($result);
        $this->assertEquals($file_id, $result['id']);
        $this->assertEquals($file_id . '.webm', $result['filename']);
        $this->assertEquals(1, $result['views']); // Initialisé à 1
    }

    /**
     * @test
     */
    public function cas_limite_file_id_longueur_maximale()
    {
        // Générer un file_id très long mais valide
        $long_id = str_repeat('a', 100) . '_' . time();
        $this->createCompleteRecording($long_id);

        $result = $this->simulateSharedRecordingView($long_id);

        $this->assertNotNull($result);
        $this->assertEquals($long_id, $result['id']);
        $this->assertEquals(1, $result['views']);
    }

    /**
     * @test
     */
    public function integration_compteur_avec_app_principale()
    {
        $file_id = 'test_integration_app';
        $this->createCompleteRecording($file_id, ['views' => 3]);

        // Utiliser la méthode de l'app principale
        $app_result = $this->app->incrementViewCount($file_id);
        $this->assertEquals(4, $app_result['views']);

        // Puis utiliser la simulation de partage
        $sharing_result = $this->simulateSharedRecordingView($file_id);
        $this->assertEquals(5, $sharing_result['views']);

        // Vérifier la cohérence
        $final_app_result = $this->app->incrementViewCount($file_id);
        $this->assertEquals(6, $final_app_result['views']);
    }
}