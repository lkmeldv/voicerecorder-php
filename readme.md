# 🎤 Enregistreur Vocal PHP - Simple & Efficace

Un enregistreur vocal minimaliste en PHP pur, sans framework, sans base de données complexe.

## 📸 Screenshots

<div align="center">

### Interface principale
![Interface principale](screenshot.png)

### Interface d'enregistrement  
![Interface d'enregistrement](screenshot-2.png)

</div>

## ✨ Fonctionnalités

### 🎯 Fonctionnalités de base
- 🎙️ **Enregistrement vocal** directement dans le navigateur
- ⏸️ **Pause/Reprise** pendant l'enregistrement  
- 🎵 **Visualisation audio** en temps réel pendant l'enregistrement
- 💾 **Sauvegarde** sur le serveur (fichiers + métadonnées JSON)
- 💬 **Commentaires/Notes** pour les vocaux partagés
- 🔗 **Partage** par URL unique et sécurisée (non-indexée)
- 📅 **Format de date français** (dd/mm/yyyy à HH:mm)
- 📱 **Responsive** - Fonctionne sur mobile et desktop
- 🔒 **Sécurisé** - Validation MIME, taille limitée, protection uploads

### 🆕 Nouvelles fonctionnalités (v2.0.0)
- 📊 **Compteur de vues** - Suivi automatique du nombre de consultations
- ✏️ **Modification des notes** - Édition post-upload avec interface intuitive
- 📋 **Gestion des enregistrements** - Page dédiée "Mes enregistrements" avec vue d'ensemble
- 🗑️ **Suppression sécurisée** - Possibilité de supprimer ses enregistrements avec confirmation
- ⚡ **Contrôles de vitesse** - Lecture à x1, x1.5, x2 sur les pages de partage
- 🧪 **Tests PHPUnit complets** - 141 tests automatisés avec couverture exhaustive
- 🏗️ **Architecture modulaire** - Code séparé en classes testables

## 🚀 Installation

### Prérequis
- PHP 7.4+ (avec extension `fileinfo`)
- Serveur web (Apache, Nginx) ou serveur PHP intégré

### Démarrage rapide

```bash
# Cloner ou télécharger les fichiers
cd voicerecorder

# Créer le dossier uploads (si pas déjà fait)
mkdir uploads
chmod 755 uploads

# Serveur PHP intégré pour test
php -S localhost:9000

# Ou configurer Apache/Nginx
```

## 📁 Structure des fichiers

```
voicerecorder/
├── index.php                    # Application principale
├── VoiceRecorderApp.php         # Classe métier (v2.0.0)
├── composer.json               # Configuration PHPUnit (v2.0.0)
├── phpunit.xml                 # Configuration tests (v2.0.0)
├── run_tests.sh               # Script d'exécution tests (v2.0.0)
├── CHANGELOG.md               # Historique des versions (v2.0.0)
├── tests/                     # Suite de tests complète (v2.0.0)
│   ├── VoiceRecorderAppTest.php      # Tests logique métier
│   ├── AudioControlsTest.php         # Tests contrôles utilisateur
│   └── SharingAndViewsTest.php       # Tests partage et vues
├── uploads/                   # Dossier des enregistrements
│   ├── .htaccess             # Sécurité Apache
│   └── index.php             # Bloquer accès direct
├── vendor/                   # Dépendances PHPUnit (v2.0.0)
└── readme.md                 # Ce fichier
```

## 🎯 Utilisation

1. **Enregistrer** : Cliquer sur "Commencer", parler, puis "Arrêter"
2. **Écouter** : Player audio intégré pour prévisualiser
3. **Télécharger** : Bouton de téléchargement local
4. **Sauvegarder** : Upload sur le serveur avec génération d'URL de partage
5. **Partager** : Copier l'URL générée pour partager l'enregistrement

## 🔧 Configuration

### Limites (dans `index.php`)
```php
$max_file_size = 10 * 1024 * 1024; // 10MB max
$allowed_mime_types = ['audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/webm'];
```

### Sécurité
- Types MIME vérifiés côté serveur
- Taille de fichier limitée
- Noms de fichiers uniques (uniqid + timestamp)
- Dossier uploads protégé
- Métadonnées en JSON séparées

### Stockage
```
uploads/
├── 66d6789a123456_1725271234.wav     # Fichier audio
├── 66d6789a123456_1725271234.json    # Métadonnées
└── ...
```

## 📱 Compatibilité navigateurs

| Navigateur | Support |
|------------|---------|
| Chrome 60+ | ✅ Full |
| Firefox 55+ | ✅ Full |
| Safari 11+ | ✅ Full |
| Edge 79+ | ✅ Full |
| IE | ❌ Non supporté |

## 🚀 Déploiement

### Hébergement partagé
1. Uploader les fichiers via FTP
2. Vérifier que PHP 7.4+ est installé
3. Créer le dossier `uploads` avec chmod 755

### VPS/Serveur dédié
```bash
# Apache
<VirtualHost *:80>
    DocumentRoot /var/www/voicerecorder
    ServerName votre-domaine.com
</VirtualHost>

# Nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/voicerecorder;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🎨 Personnalisation

### Couleurs (CSS dans `index.php`)
```css
.record-btn { background: #ff4757; }      /* Bouton enregistrer */
.pause-btn { background: #ffa502; }       /* Bouton pause */
.save-btn { background: #2ed573; }        /* Bouton sauvegarder */
```

### Durée max d'enregistrement (JavaScript)
```javascript
if (elapsed >= 120) {  // 2 minutes par défaut
    stopRecording();
}
```

## 🔒 Sécurité

- ✅ Validation MIME côté serveur
- ✅ Limitation taille de fichier
- ✅ Noms de fichiers sécurisés
- ✅ Dossier uploads protégé
- ✅ Headers de sécurité
- ✅ Pas d'exécution PHP dans uploads/

## 📊 Métriques

### Version 1.0.0 (Simple)
- **Fichier unique** : ~50KB (HTML + CSS + JS + PHP)
- **Pas de dépendances** externes
- **Architecture** : Monolithique

### Version 2.0.0 (Avancée)
- **Fichiers principaux** : ~100KB (code métier + tests)
- **Tests automatisés** : 141 tests, 300+ assertions
- **Architecture** : Modulaire et testable
- **Dépendances dev** : PHPUnit pour les tests
- **Couverture** : Tous les cas (nominaux, limites, erreurs, sécurité)

### Général
- **Compatible** PHP 8.0 à 8.4
- **Performance** : Excellent sur tous serveurs
- **Qualité** : Code professionnel avec tests complets

## 🆘 Support

### Problèmes courants

**"Impossible d'accéder au microphone"**
→ Autoriser l'accès microphone dans le navigateur

**"Erreur lors de la sauvegarde"**  
→ Vérifier les permissions du dossier `uploads/` (755)

**"Type de fichier non autorisé"**
→ Le navigateur utilise un format non supporté (normal sur certains navigateurs)

## ⚖️ Licence

MIT License - Libre d'utilisation et modification

## 📋 Changelog - Résumé des versions

### [2.0.0] - 2025-09-03 ✨
**Version majeure avec fonctionnalités avancées**

#### 🎯 Nouvelles fonctionnalités
- 📊 **Compteur de vues** automatique sur partages
- ✏️ **Édition des notes** post-upload
- 📋 **Page de gestion** complète des enregistrements
- 🗑️ **Suppression sécurisée** avec confirmation
- ⚡ **Contrôles de vitesse** x1/x1.5/x2 pour la lecture

#### 🧪 Qualité professionnelle
- **141 tests PHPUnit** automatisés
- **3 classes de test** spécialisées
- **Couverture exhaustive** : tous les cas possibles
- **Architecture modulaire** : code séparé et maintenable
- **Script d'exécution** automatisé

#### 🛡️ Sécurité renforcée
- Protection anti-injection XSS/SQL
- Validation stricte des identifiants
- Contrôles anti-directory traversal
- Échappement HTML systématique

#### 📈 Statistiques v2.0.0
- **+1695 fichiers** (incluant dépendances PHPUnit)
- **+147 230 lignes** de code et tests
- **Architecture professionnelle** prête pour production

### [1.0.0] - 2025-09-03 🎤
**Version initiale simple et efficace**
- Interface d'enregistrement vocal
- Partage par liens uniques
- Design responsive moderne
- Sécurité de base

> 📖 **Changelog complet** : voir [CHANGELOG.md](CHANGELOG.md)

## 🧪 Tests et qualité

### Lancer les tests
```bash
# Installation des dépendances de test
composer install

# Lancer tous les tests
./run_tests.sh

# Ou directement PHPUnit
./vendor/bin/phpunit --testdox
```

### Couverture des tests
- ✅ **Cas nominaux** : comportement normal
- ✅ **Cas limites** : valeurs nulles, vides, extrêmes  
- ✅ **Cas d'erreur** : paramètres invalides, exceptions
- ✅ **Tests sécurité** : injections, traversées de répertoires
- ✅ **Tests performance** : charge, mémoire, concurrence

---

**Auteur :** EL GNANI Mohamed  
**Version :** 2.0.0 (avec tests complets)  
**Serveur de test :** http://localhost:7778

🎤 **Simple. Efficace. Testé. Professionnel.**