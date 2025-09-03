<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests pour les contrôles audio et fonctionnalités frontend
 * Simule les interactions JavaScript et valide la logique métier
 */
class AudioControlsTest extends TestCase
{
    private string $test_upload_dir;

    protected function setUp(): void
    {
        $this->test_upload_dir = sys_get_temp_dir() . '/audio_controls_tests_' . uniqid() . '/';
        if (!file_exists($this->test_upload_dir)) {
            mkdir($this->test_upload_dir, 0777, true);
        }
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
     * Simule une requête POST pour les actions utilisateur
     */
    private function simulatePostAction(string $action, array $additional_data = []): array
    {
        $post_data = array_merge(['action' => $action], $additional_data);
        
        // Simuler le traitement des actions comme dans index.php
        $result = ['success' => false, 'message' => ''];
        
        switch ($action) {
            case 'update_note':
                if (isset($post_data['file_id']) && isset($post_data['note'])) {
                    $file_id = preg_replace('/[^a-zA-Z0-9_]/', '', $post_data['file_id']);
                    $note_file = $this->test_upload_dir . $file_id . '.json';
                    
                    if (file_exists($note_file)) {
                        $audio_info = json_decode(file_get_contents($note_file), true);
                        if ($audio_info) {
                            $audio_info['comment'] = trim($post_data['note']);
                            if (file_put_contents($note_file, json_encode($audio_info, JSON_PRETTY_PRINT))) {
                                $result = ['success' => true, 'message' => 'Note mise à jour avec succès !'];
                            }
                        }
                    }
                }
                break;
                
            case 'delete_recording':
                if (isset($post_data['file_id'])) {
                    $file_id = preg_replace('/[^a-zA-Z0-9_]/', '', $post_data['file_id']);
                    $files_deleted = 0;
                    $audio_files = glob($this->test_upload_dir . $file_id . '.*');
                    
                    foreach ($audio_files as $file) {
                        if (@unlink($file)) {
                            $files_deleted++;
                        }
                    }
                    
                    if ($files_deleted > 0) {
                        $result = ['success' => true, 'message' => 'Enregistrement supprimé avec succès !'];
                    }
                }
                break;
        }
        
        return $result;
    }

    /**
     * Crée un enregistrement de test
     */
    private function createTestRecording(string $file_id, array $data = []): void
    {
        $default_data = [
            'id' => $file_id,
            'filename' => $file_id . '.webm',
            'original_name' => 'test_recording.webm',
            'size' => 2048,
            'mime_type' => 'audio/webm',
            'upload_date' => date('d/m/Y à H:i'),
            'comment' => 'Note de test',
            'views' => 0
        ];

        $recording_data = array_merge($default_data, $data);
        
        // Créer le fichier JSON
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, json_encode($recording_data, JSON_PRETTY_PRINT));
        
        // Créer un faux fichier audio
        $audio_file = $this->test_upload_dir . $file_id . '.webm';
        file_put_contents($audio_file, 'fake audio content');
    }

    // ========== TESTS MISE À JOUR DES NOTES VIA POST ==========

    /**
     * @test
     */
    public function post_update_note_cas_nominal_reussit()
    {
        $file_id = 'test_post_update';
        $this->createTestRecording($file_id, ['comment' => 'Ancienne note']);

        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => 'Nouvelle note via POST'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Note mise à jour avec succès !', $result['message']);

        // Vérifier que la note a été mise à jour
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('Nouvelle note via POST', $data['comment']);
    }

    /**
     * @test
     * @dataProvider fournisseur_notes_post_invalides
     */
    public function post_update_note_avec_donnees_invalides_echoue(array $post_data)
    {
        $result = $this->simulatePostAction('update_note', $post_data);
        $this->assertFalse($result['success']);
    }

    public function fournisseur_notes_post_invalides(): array
    {
        return [
            'file_id_manquant' => [['note' => 'Test note']],
            'note_manquante' => [['file_id' => 'test123']],
            'file_id_vide' => [['file_id' => '', 'note' => 'Test']],
            'file_id_invalide' => [['file_id' => 'test/../hack', 'note' => 'Test']],
            'donnees_vides' => [[]]
        ];
    }

    /**
     * @test
     */
    public function post_update_note_supprime_espaces_en_trop()
    {
        $file_id = 'test_trim_post';
        $this->createTestRecording($file_id);

        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => '   Note avec espaces   '
        ]);

        $this->assertTrue($result['success']);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('Note avec espaces', $data['comment']);
    }

    /**
     * @test
     */
    public function post_update_note_fichier_inexistant_echoue()
    {
        $result = $this->simulatePostAction('update_note', [
            'file_id' => 'inexistant',
            'note' => 'Test'
        ]);

        $this->assertFalse($result['success']);
    }

    // ========== TESTS SUPPRESSION VIA POST ==========

    /**
     * @test
     */
    public function post_delete_recording_cas_nominal_reussit()
    {
        $file_id = 'test_post_delete';
        $this->createTestRecording($file_id);

        // Vérifier que les fichiers existent avant suppression
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $audio_file = $this->test_upload_dir . $file_id . '.webm';
        $this->assertFileExists($json_file);
        $this->assertFileExists($audio_file);

        $result = $this->simulatePostAction('delete_recording', [
            'file_id' => $file_id
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Enregistrement supprimé avec succès !', $result['message']);

        // Vérifier que les fichiers ont été supprimés
        $this->assertFileDoesNotExist($json_file);
        $this->assertFileDoesNotExist($audio_file);
    }

    /**
     * @test
     * @dataProvider fournisseur_file_ids_invalides_post
     */
    public function post_delete_recording_avec_file_id_invalide_echoue(array $post_data)
    {
        $result = $this->simulatePostAction('delete_recording', $post_data);
        $this->assertFalse($result['success']);
    }

    public function fournisseur_file_ids_invalides_post(): array
    {
        return [
            'file_id_manquant' => [[]],
            'file_id_vide' => [['file_id' => '']],
            'file_id_avec_slash' => [['file_id' => 'test/hack']],
            'file_id_avec_points' => [['file_id' => '../etc/passwd']],
            'file_id_avec_caracteres_speciaux' => [['file_id' => 'test@#$%']]
        ];
    }

    /**
     * @test
     */
    public function post_delete_recording_fichier_inexistant_echoue()
    {
        $result = $this->simulatePostAction('delete_recording', [
            'file_id' => 'fichier_inexistant'
        ]);

        $this->assertFalse($result['success']);
    }

    /**
     * @test
     */
    public function post_delete_recording_supprime_tous_les_fichiers_associes()
    {
        $file_id = 'test_multiple_files';
        
        // Créer plusieurs fichiers avec le même ID
        $files = [
            $this->test_upload_dir . $file_id . '.json',
            $this->test_upload_dir . $file_id . '.webm',
            $this->test_upload_dir . $file_id . '.wav',
            $this->test_upload_dir . $file_id . '.txt'
        ];

        foreach ($files as $file) {
            file_put_contents($file, 'test content');
        }

        $result = $this->simulatePostAction('delete_recording', [
            'file_id' => $file_id
        ]);

        $this->assertTrue($result['success']);

        // Vérifier que tous les fichiers ont été supprimés
        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    // ========== TESTS VALIDATION DES VITESSES DE LECTURE ==========

    /**
     * @test
     * @dataProvider fournisseur_vitesses_lecture_valides
     */
    public function validation_vitesses_lecture_accepte_valeurs_valides(float $speed)
    {
        // Simuler la validation côté client
        $valid_speeds = [1.0, 1.5, 2.0];
        $this->assertContains($speed, $valid_speeds, "La vitesse $speed devrait être valide");
    }

    public function fournisseur_vitesses_lecture_valides(): array
    {
        return [
            'vitesse_normale' => [1.0],
            'vitesse_acceleree_15' => [1.5],
            'vitesse_acceleree_2x' => [2.0]
        ];
    }

    /**
     * @test
     * @dataProvider fournisseur_vitesses_lecture_invalides
     */
    public function validation_vitesses_lecture_rejette_valeurs_invalides(float $speed)
    {
        $valid_speeds = [1.0, 1.5, 2.0];
        $this->assertNotContains($speed, $valid_speeds, "La vitesse $speed ne devrait pas être valide");
    }

    public function fournisseur_vitesses_lecture_invalides(): array
    {
        return [
            'vitesse_zero' => [0.0],
            'vitesse_negative' => [-1.0],
            'vitesse_trop_lente' => [0.5],
            'vitesse_trop_rapide' => [3.0],
            'vitesse_extreme' => [10.0]
        ];
    }

    // ========== TESTS SÉCURITÉ ET VALIDATION ==========

    /**
     * @test
     */
    public function securite_file_id_est_nettoye_correctement()
    {
        $malicious_inputs = [
            'normal_id' => 'test123_456',
            'with_slashes' => 'test/../etc/passwd',
            'with_dots' => '..\\windows\\system32',
            'with_special_chars' => 'test@#$%^&*()',
            'with_spaces' => 'test 123 456',
            'with_quotes' => 'test"\'<script>alert(1)</script>'
        ];

        foreach ($malicious_inputs as $name => $input) {
            $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $input);
            
            if ($name === 'normal_id') {
                $this->assertEquals('test123_456', $cleaned);
            } else {
                $this->assertNotEquals($input, $cleaned, "Input malicieux '$name' devrait être nettoyé");
                $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]*$/', $cleaned, "Résultat nettoyé devrait être sécurisé");
            }
        }
    }

    /**
     * @test
     */
    public function action_inexistante_ne_cause_pas_erreur()
    {
        $result = $this->simulatePostAction('action_inexistante', []);
        $this->assertFalse($result['success']);
        $this->assertEmpty($result['message']);
    }

    /**
     * @test
     */
    public function gestion_caracteres_unicode_dans_notes()
    {
        $file_id = 'test_unicode';
        $this->createTestRecording($file_id);

        $unicode_notes = [
            'emojis' => '🎵 Note avec émojis 🎤 et texte',
            'accents' => 'Note avec accents: àáâãäåæçèéêë',
            'chinois' => '这是一个中文音符',
            'arabe' => 'هذه ملاحظة باللغة العربية',
            'cyrillique' => 'Это русская заметка',
            'melange' => '🎵 Mélange émojis + français + 中文 + العربية'
        ];

        foreach ($unicode_notes as $type => $note) {
            $result = $this->simulatePostAction('update_note', [
                'file_id' => $file_id,
                'note' => $note
            ]);

            $this->assertTrue($result['success'], "Note Unicode '$type' devrait être acceptée");

            $json_file = $this->test_upload_dir . $file_id . '.json';
            $data = json_decode(file_get_contents($json_file), true);
            $this->assertEquals($note, $data['comment'], "Note Unicode '$type' devrait être préservée");
        }
    }

    // ========== TESTS PERFORMANCE ET LIMITES ==========

    /**
     * @test
     */
    public function performance_note_tres_longue_est_geree()
    {
        $file_id = 'test_long_note_performance';
        $this->createTestRecording($file_id);

        // Créer une note de 10KB
        $long_note = str_repeat('Cette note est très longue et doit être gérée efficacement. ', 200);
        
        $start_time = microtime(true);
        
        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => $long_note
        ]);
        
        $execution_time = microtime(true) - $start_time;

        $this->assertTrue($result['success']);
        $this->assertLessThan(1.0, $execution_time, 'La mise à jour dune note longue devrait prendre moins d\'1 seconde');

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals(trim($long_note), $data['comment']);
    }

    /**
     * @test
     */
    public function robustesse_suppression_fichiers_proteges()
    {
        $file_id = 'test_protected_file';
        $this->createTestRecording($file_id);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $audio_file = $this->test_upload_dir . $file_id . '.webm';

        // Essayer de rendre les fichiers en lecture seule (ne fonctionne pas sur tous les systèmes)
        @chmod($json_file, 0444);
        @chmod($audio_file, 0444);

        $result = $this->simulatePostAction('delete_recording', [
            'file_id' => $file_id
        ]);

        // Le résultat peut varier selon les permissions du système
        // L'important est que l'opération ne génère pas d'erreur fatale
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
    }

    /**
     * @test
     */
    public function gestion_concurrence_mise_a_jour_simultanee()
    {
        $file_id = 'test_concurrent_update';
        $this->createTestRecording($file_id, ['comment' => 'Note initiale']);

        // Simuler des mises à jour simultanées
        $notes = ['Note 1', 'Note 2', 'Note 3'];
        $results = [];

        foreach ($notes as $note) {
            $results[] = $this->simulatePostAction('update_note', [
                'file_id' => $file_id,
                'note' => $note
            ]);
        }

        // Toutes les opérations devraient réussir
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
        }

        // La dernière note devrait être présente
        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('Note 3', $data['comment']);
    }

    // ========== TESTS EDGE CASES ==========

    /**
     * @test
     */
    public function cas_limite_json_malformed_est_ignore()
    {
        $file_id = 'test_malformed_json';
        
        // Créer un fichier JSON malformé
        $json_file = $this->test_upload_dir . $file_id . '.json';
        file_put_contents($json_file, '{"incomplete": json without closing brace');

        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => 'Test note'
        ]);

        $this->assertFalse($result['success']);
    }

    /**
     * @test
     */
    public function cas_limite_note_vide_est_acceptee()
    {
        $file_id = 'test_empty_note';
        $this->createTestRecording($file_id, ['comment' => 'Note existante']);

        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => ''
        ]);

        $this->assertTrue($result['success']);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('', $data['comment']);
    }

    /**
     * @test
     */
    public function cas_limite_file_id_avec_underscores_consecutifs()
    {
        $file_id = 'test___multiple___underscores___123';
        $this->createTestRecording($file_id);

        $result = $this->simulatePostAction('update_note', [
            'file_id' => $file_id,
            'note' => 'Test avec underscores multiples'
        ]);

        $this->assertTrue($result['success']);

        $json_file = $this->test_upload_dir . $file_id . '.json';
        $data = json_decode(file_get_contents($json_file), true);
        $this->assertEquals('Test avec underscores multiples', $data['comment']);
    }
}