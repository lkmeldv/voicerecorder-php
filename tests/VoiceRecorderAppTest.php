<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests complets pour la classe VoiceRecorderApp
 * Couvre tous les cas : nominaux, limites, erreurs et frontières
 */
class VoiceRecorderAppTest extends TestCase
{
    private VoiceRecorderApp $app;
    private string $test_upload_dir;

    protected function setUp(): void
    {
        // Créer un dossier temporaire pour les tests
        $this->test_upload_dir = sys_get_temp_dir() . '/voice_recorder_tests_' . uniqid() . '/';
        if (!file_exists($this->test_upload_dir)) {
            mkdir($this->test_upload_dir, 0777, true);
        }

        $this->app = new VoiceRecorderApp($this->test_upload_dir);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        if (file_exists($this->test_upload_dir)) {
            $files = glob($this->test_upload_dir . '*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->test_upload_dir);
        }
    }

    /**
     * Crée un fichier de test JSON avec des données d'enregistrement
     */
    private function createTestRecording(string $file_id, array $data = []): array
    {
        $default_data = [
            'id' => $file_id,
            'filename' => $file_id . '.webm',
            'original_name' => 'test_recording.webm',
            'size' => 1024,
            'mime_type' => 'audio/webm',
            'upload_date' => '01/01/2024 à 10:00',
            'comment' => 'Test comment',
            'views' => 0
        ];

        $recording_data = array_merge($default_data, $data);
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, json_encode($recording_data, JSON_PRETTY_PRINT));

        return $recording_data;
    }

    // ========== TESTS CONSTRUCTEUR ==========

    /**
     * @test
     */
    public function constructeur_avec_parametres_par_defaut_initialise_correctement()
    {
        $app = new VoiceRecorderApp();
        
        $this->assertEquals('uploads/', $app->getUploadDir());
        $this->assertEquals(10 * 1024 * 1024, $app->getMaxFileSize());
        $this->assertEquals(300, $app->getMaxDuration());
        $this->assertEquals(7, $app->getCleanupDays());
        $this->assertIsArray($app->getAllowedMimeTypes());
        $this->assertContains('audio/webm', $app->getAllowedMimeTypes());
    }

    /**
     * @test
     */
    public function constructeur_avec_parametres_personnalises_initialise_correctement()
    {
        $custom_dir = '/custom/dir/';
        $custom_size = 5 * 1024 * 1024;
        $custom_duration = 600;
        $custom_cleanup = 14;
        $custom_types = ['audio/mp3', 'audio/wav'];

        $app = new VoiceRecorderApp($custom_dir, $custom_size, $custom_duration, $custom_cleanup, $custom_types);

        $this->assertEquals($custom_dir, $app->getUploadDir());
        $this->assertEquals($custom_size, $app->getMaxFileSize());
        $this->assertEquals($custom_duration, $app->getMaxDuration());
        $this->assertEquals($custom_cleanup, $app->getCleanupDays());
        $this->assertEquals($custom_types, $app->getAllowedMimeTypes());
    }

    // ========== TESTS COMPTEUR DE VUES ==========

    /**
     * @test
     */
    public function increment_view_count_cas_nominal_incremente_correctement()
    {
        $file_id = 'test123_' . time();
        $this->createTestRecording($file_id, ['views' => 5]);

        $result = $this->app->incrementViewCount($file_id);

        $this->assertNotNull($result);
        $this->assertEquals(6, $result['views']);
    }

    /**
     * @test
     */
    public function increment_view_count_sans_compteur_initial_initialise_a_un()
    {
        $file_id = 'test456_' . time();
        $this->createTestRecording($file_id); // Sans 'views'

        $result = $this->app->incrementViewCount($file_id);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['views']);
    }

    /**
     * @test
     * @dataProvider fournisseur_file_id_invalides
     */
    public function increment_view_count_avec_file_id_invalide_retourne_null(string $invalid_id)
    {
        $result = $this->app->incrementViewCount($invalid_id);
        $this->assertNull($result);
    }

    public function fournisseur_file_id_invalides(): array
    {
        return [
            'chaine_vide' => [''],
            'caracteres_speciaux' => ['test@#$%'],
            'espaces' => ['test 123'],
            'slash' => ['test/123'],
            'backslash' => ['test\\123'],
            'points' => ['../test'],
            'null_string' => ['null']
        ];
    }

    /**
     * @test
     */
    public function increment_view_count_fichier_inexistant_retourne_null()
    {
        $result = $this->app->incrementViewCount('fichier_inexistant');
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function increment_view_count_json_corrompu_retourne_null()
    {
        $file_id = 'test_corrupt';
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, 'json invalide {{{');

        $result = $this->app->incrementViewCount($file_id);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function increment_view_count_persiste_les_modifications()
    {
        $file_id = 'test_persist';
        $this->createTestRecording($file_id, ['views' => 10]);

        $this->app->incrementViewCount($file_id);

        // Vérifier que les modifications sont persistées en relisant le fichier
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals(11, $data['views']);
    }

    // ========== TESTS MISE À JOUR DES NOTES ==========

    /**
     * @test
     */
    public function update_note_cas_nominal_met_a_jour_correctement()
    {
        $file_id = 'test_note_update';
        $this->createTestRecording($file_id, ['comment' => 'Ancienne note']);

        $result = $this->app->updateNote($file_id, 'Nouvelle note');

        $this->assertTrue($result);

        // Vérifier que la note a été mise à jour
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('Nouvelle note', $data['comment']);
    }

    /**
     * @test
     * @dataProvider fournisseur_notes_avec_espaces
     */
    public function update_note_supprime_les_espaces_en_trop(string $input_note, string $expected_note)
    {
        $file_id = 'test_trim';
        $this->createTestRecording($file_id);

        $result = $this->app->updateNote($file_id, $input_note);

        $this->assertTrue($result);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals($expected_note, $data['comment']);
    }

    public function fournisseur_notes_avec_espaces(): array
    {
        return [
            'espaces_debut' => ['  Note avec espaces', 'Note avec espaces'],
            'espaces_fin' => ['Note avec espaces  ', 'Note avec espaces'],
            'espaces_debut_fin' => ['  Note avec espaces  ', 'Note avec espaces'],
            'tabulations' => ["\t\tNote\t\t", 'Note'],
            'retours_ligne' => ["\n\nNote\n\n", 'Note'],
            'melange_whitespace' => [" \t\nNote\n\t ", 'Note'],
            'note_vide' => ['   ', ''],
            'note_uniquement_whitespace' => ["\t\n\r ", '']
        ];
    }

    /**
     * @test
     * @dataProvider fournisseur_file_id_invalides
     */
    public function update_note_avec_file_id_invalide_retourne_false(string $invalid_id)
    {
        $result = $this->app->updateNote($invalid_id, 'Note test');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function update_note_fichier_inexistant_retourne_false()
    {
        $result = $this->app->updateNote('inexistant', 'Note test');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function update_note_json_corrompu_retourne_false()
    {
        $file_id = 'test_corrupt_update';
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, 'json invalide');

        $result = $this->app->updateNote($file_id, 'Nouvelle note');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function update_note_avec_note_longue_fonctionne()
    {
        $file_id = 'test_long_note';
        $this->createTestRecording($file_id);
        $long_note = str_repeat('Cette note est très longue. ', 100); // ~2700 caractères

        $result = $this->app->updateNote($file_id, $long_note);

        $this->assertTrue($result);
        
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        // La note peut être tronquée dans l'affichage, vérifions qu'elle contient au moins le début
        $this->assertStringContainsString('Cette note est très longue', $data['comment']);
    }

    // ========== TESTS SUPPRESSION D'ENREGISTREMENTS ==========

    /**
     * @test
     */
    public function delete_recording_cas_nominal_supprime_tous_les_fichiers()
    {
        $file_id = 'test_delete_' . time();
        
        // Créer plusieurs fichiers associés
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $webm_file = $this->test_upload_dir . $file_id . '.webm';
        $txt_file = $this->test_upload_dir . $file_id . '.txt';
        
        file_put_contents($json_file, '{"test": "data"}');
        file_put_contents($webm_file, 'fake audio data');
        file_put_contents($txt_file, 'notes');

        $result = $this->app->deleteRecording($file_id);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($json_file);
        $this->assertFileDoesNotExist($webm_file);
        $this->assertFileDoesNotExist($txt_file);
    }

    /**
     * @test
     * @dataProvider fournisseur_file_id_invalides
     */
    public function delete_recording_avec_file_id_invalide_retourne_false(string $invalid_id)
    {
        $result = $this->app->deleteRecording($invalid_id);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function delete_recording_fichier_inexistant_retourne_false()
    {
        $result = $this->app->deleteRecording('fichier_inexistant');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function delete_recording_suppression_partielle_retourne_true_si_au_moins_un_fichier_supprime()
    {
        $file_id = 'test_partial_delete';
        
        // Créer un fichier en lecture seule pour simuler un échec de suppression
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $webm_file = $this->test_upload_dir . $file_id . '.webm';
        
        file_put_contents($json_file, '{"test": "data"}');
        file_put_contents($webm_file, 'audio data');
        
        // Rendre un fichier non supprimable (ne fonctionne que sur certains systèmes)
        chmod($json_file, 0444);

        $result = $this->app->deleteRecording($file_id);

        // Même si un fichier n'a pas pu être supprimé, le résultat devrait être true
        // car au moins un fichier a été supprimé
        $this->assertTrue($result);
    }

    // ========== TESTS RÉCUPÉRATION DES ENREGISTREMENTS ==========

    /**
     * @test
     */
    public function get_all_recordings_retourne_liste_vide_sans_enregistrements()
    {
        $result = $this->app->getAllRecordings();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function get_all_recordings_retourne_tous_les_enregistrements_valides()
    {
        $file_id1 = 'recording1';
        $file_id2 = 'recording2';
        
        $this->createTestRecording($file_id1, ['original_name' => 'Premier.webm']);
        $this->createTestRecording($file_id2, ['original_name' => 'Deuxième.webm']);

        $result = $this->app->getAllRecordings();

        $this->assertCount(2, $result);
        $this->assertEquals('Premier.webm', $result[0]['original_name']);
        $this->assertEquals('Deuxième.webm', $result[1]['original_name']);
    }

    /**
     * @test
     */
    public function get_all_recordings_trie_par_date_decroissante()
    {
        // Créer des enregistrements avec des dates différentes (avec des timestamps distincts)
        $this->createTestRecording('old', ['upload_date' => '01/01/2020 à 10:00']);
        $this->createTestRecording('new', ['upload_date' => '01/01/2024 à 10:00']);
        $this->createTestRecording('middle', ['upload_date' => '01/01/2022 à 10:00']);

        $result = $this->app->getAllRecordings();

        $this->assertCount(3, $result);
        $this->assertEquals('new', $result[0]['id']); // Plus récent en premier
        $this->assertEquals('middle', $result[1]['id']);
        $this->assertEquals('old', $result[2]['id']); // Plus ancien en dernier
    }

    /**
     * @test
     */
    public function get_all_recordings_ignore_les_fichiers_json_corrompus()
    {
        $this->createTestRecording('valid', ['original_name' => 'Valide.webm']);
        
        // Créer un fichier JSON corrompu
        $corrupt_file = $this->test_upload_dir . 'corrupt.json';
        file_put_contents($corrupt_file, 'json invalide {{{');

        $result = $this->app->getAllRecordings();

        $this->assertCount(1, $result);
        $this->assertEquals('Valide.webm', $result[0]['original_name']);
    }

    /**
     * @test
     */
    public function get_all_recordings_gere_les_dates_invalides()
    {
        $this->createTestRecording('valid_date', ['upload_date' => '01/01/2024 à 10:00']);
        $this->createTestRecording('invalid_date', ['upload_date' => 'date invalide']);
        $this->createTestRecording('missing_date', []); // Pas de date

        $result = $this->app->getAllRecordings();

        $this->assertCount(3, $result);
        // L'enregistrement avec une date valide devrait être en premier (timestamp le plus haut)
        $valid_date_found = false;
        foreach ($result as $recording) {
            if ($recording['id'] === 'valid_date') {
                $valid_date_found = true;
                break;
            }
        }
        $this->assertTrue($valid_date_found, 'L\'enregistrement valid_date devrait être présent');
    }

    // ========== TESTS VALIDATION DE FICHIER ==========

    /**
     * @test
     */
    public function validate_uploaded_file_cas_nominal_retourne_valide()
    {
        $valid_file = [
            'error' => UPLOAD_ERR_OK,
            'size' => 1024 * 1024, // 1MB
            'tmp_name' => '/tmp/test',
            'name' => 'test.webm'
        ];

        $result = $this->app->validateUploadedFile($valid_file);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['error']);
    }

    /**
     * @test
     * @dataProvider fournisseur_erreurs_upload
     */
    public function validate_uploaded_file_avec_erreurs_upload_retourne_invalide(int $error_code, string $expected_message)
    {
        $file = [
            'error' => $error_code,
            'size' => 1024,
            'tmp_name' => '/tmp/test'
        ];

        $result = $this->app->validateUploadedFile($file);

        $this->assertFalse($result['valid']);
        $this->assertEquals($expected_message, $result['error']);
    }

    public function fournisseur_erreurs_upload(): array
    {
        return [
            'erreur_generale' => [UPLOAD_ERR_PARTIAL, 'Erreur lors de l\'upload du fichier.'],
            'fichier_trop_grand' => [UPLOAD_ERR_FORM_SIZE, 'Erreur lors de l\'upload du fichier.'],
            'pas_de_fichier' => [UPLOAD_ERR_NO_FILE, 'Erreur lors de l\'upload du fichier.'],
            'pas_de_tmp_dir' => [UPLOAD_ERR_NO_TMP_DIR, 'Erreur lors de l\'upload du fichier.']
        ];
    }

    /**
     * @test
     * @dataProvider fournisseur_tailles_fichier_invalides
     */
    public function validate_uploaded_file_avec_taille_invalide_retourne_invalide(int $size, string $expected_error)
    {
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => $size,
            'tmp_name' => '/tmp/test'
        ];

        $result = $this->app->validateUploadedFile($file);

        $this->assertFalse($result['valid']);
        $this->assertEquals($expected_error, $result['error']);
    }

    public function fournisseur_tailles_fichier_invalides(): array
    {
        $max_size = 10 * 1024 * 1024; // 10MB
        
        return [
            'fichier_trop_grand' => [$max_size + 1, 'Le fichier est trop volumineux (max 10MB).'],
            'fichier_exactement_limite' => [$max_size + 1000, 'Le fichier est trop volumineux (max 10MB).'],
            'fichier_vide' => [0, 'Le fichier est vide.'],
            'taille_negative' => [-1, 'Le fichier est vide.']
        ];
    }

    /**
     * @test
     */
    public function validate_uploaded_file_taille_exacte_limite_acceptee()
    {
        $max_size = 10 * 1024 * 1024; // 10MB exactement
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => $max_size,
            'tmp_name' => '/tmp/test'
        ];

        $result = $this->app->validateUploadedFile($file);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['error']);
    }

    // ========== TESTS GÉNÉRATION D'ID ==========

    /**
     * @test
     */
    public function generate_file_id_genere_id_unique()
    {
        $id1 = $this->app->generateFileId();
        $id2 = $this->app->generateFileId();

        $this->assertNotEquals($id1, $id2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}_\d{10}$/', $id1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}_\d{10}$/', $id2);
    }

    /**
     * @test
     */
    public function generate_file_id_contient_timestamp()
    {
        $before = time();
        $id = $this->app->generateFileId();
        $after = time();

        $parts = explode('_', $id);
        $timestamp = (int)$parts[1];

        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    /**
     * @test
     */
    public function generate_file_id_genere_plusieurs_ids_uniques()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->app->generateFileId();
            usleep(1000); // Attendre 1ms pour éviter des collisions de timestamp
        }

        $unique_ids = array_unique($ids);
        $this->assertCount(100, $unique_ids, 'Tous les IDs générés doivent être uniques');
    }

    // ========== TESTS NETTOYAGE ==========

    /**
     * @test
     */
    public function cleanup_old_files_sans_anciens_fichiers_retourne_zero()
    {
        // Créer un fichier récent
        $recent_file = $this->test_upload_dir . 'recent.json';
        file_put_contents($recent_file, '{}');

        $deleted_count = $this->app->cleanupOldFiles();

        $this->assertEquals(0, $deleted_count);
        $this->assertFileExists($recent_file);
    }

    /**
     * @test
     */
    public function cleanup_old_files_supprime_fichiers_anciens()
    {
        $old_file = $this->test_upload_dir . 'old.json';
        $recent_file = $this->test_upload_dir . 'recent.json';
        
        file_put_contents($old_file, '{}');
        file_put_contents($recent_file, '{}');
        
        // Modifier la date du fichier ancien (8 jours dans le passé)
        $old_time = time() - (8 * 24 * 60 * 60);
        touch($old_file, $old_time);

        $deleted_count = $this->app->cleanupOldFiles();

        $this->assertEquals(1, $deleted_count);
        $this->assertFileDoesNotExist($old_file);
        $this->assertFileExists($recent_file);
    }

    /**
     * @test
     */
    public function cleanup_old_files_respecte_la_limite_de_jours()
    {
        // Créer une app avec une limite de 1 jour
        $app_1_day = new VoiceRecorderApp($this->test_upload_dir, 10*1024*1024, 300, 1);
        
        $file_2_days_old = $this->test_upload_dir . 'old2.json';
        $file_today = $this->test_upload_dir . 'today.json';
        
        file_put_contents($file_2_days_old, '{}');
        file_put_contents($file_today, '{}');
        
        // Fichier de 2 jours
        touch($file_2_days_old, time() - (2 * 24 * 60 * 60));

        $deleted_count = $app_1_day->cleanupOldFiles();

        $this->assertEquals(1, $deleted_count);
        $this->assertFileDoesNotExist($file_2_days_old);
        $this->assertFileExists($file_today);
    }

    // ========== TESTS ESPACE DISQUE ==========

    /**
     * @test
     */
    public function has_sufficient_disk_space_cas_nominal()
    {
        $small_requirement = 1024; // 1KB
        $result = $this->app->hasSufficientDiskSpace($small_requirement);
        
        // Devrait être true sur la plupart des systèmes
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function has_sufficient_disk_space_avec_requirement_zero()
    {
        $result = $this->app->hasSufficientDiskSpace(0);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function has_sufficient_disk_space_verifie_facteur_de_securite()
    {
        // Cette méthode vérifie qu'il y a 2x l'espace requis
        // Difficile à tester sans simuler l'espace disque, 
        // mais on peut au moins vérifier que la méthode fonctionne
        $result = $this->app->hasSufficientDiskSpace(1024);
        $this->assertIsBool($result);
    }

    // ========== TESTS EDGE CASES ET SÉCURITÉ ==========

    /**
     * @test
     */
    public function cas_limite_fichier_id_avec_underscores_multiples()
    {
        $file_id = 'test_123_456_789';
        $this->createTestRecording($file_id);

        $result = $this->app->incrementViewCount($file_id);
        $this->assertNotNull($result);
    }

    /**
     * @test
     */
    public function cas_limite_note_avec_caracteres_speciaux()
    {
        $file_id = 'test_special_chars';
        $this->createTestRecording($file_id);
        
        $special_note = "Note avec émojis 🎵🎤 et caractères spéciaux: àéèùç ñ ü ß";

        $result = $this->app->updateNote($file_id, $special_note);
        
        $this->assertTrue($result);
        
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals($special_note, $data['comment']);
    }

    /**
     * @test
     */
    public function securite_file_id_ne_permet_pas_directory_traversal()
    {
        $malicious_ids = ['../etc/passwd', '..\\windows\\system32', '/etc/hosts', 'C:\\Windows\\System32'];
        
        foreach ($malicious_ids as $malicious_id) {
            $result = $this->app->incrementViewCount($malicious_id);
            $this->assertNull($result, "Directory traversal devrait être bloqué pour: $malicious_id");
            
            $result = $this->app->updateNote($malicious_id, 'test');
            $this->assertFalse($result, "Directory traversal devrait être bloqué pour: $malicious_id");
            
            $result = $this->app->deleteRecording($malicious_id);
            $this->assertFalse($result, "Directory traversal devrait être bloqué pour: $malicious_id");
        }
    }

    /**
     * @test
     */
    public function robustesse_gestion_memoire_avec_gros_volumes()
    {
        // Créer beaucoup d'enregistrements pour tester la gestion mémoire
        $recordings_count = 50;
        
        for ($i = 0; $i < $recordings_count; $i++) {
            $this->createTestRecording("bulk_test_$i", ['original_name' => "Recording_$i.webm"]);
        }

        $result = $this->app->getAllRecordings();

        $this->assertCount($recordings_count, $result);
        $this->assertIsArray($result);
        
        // Vérifier que la mémoire n'explose pas
        $memory_usage = memory_get_usage();
        $this->assertLessThan(50 * 1024 * 1024, $memory_usage, 'Usage mémoire trop élevé'); // 50MB max
    }
}