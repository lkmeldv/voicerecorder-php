#!/bin/bash

echo "🧪 Lancement des tests PHPUnit pour l'Enregistreur Vocal"
echo "========================================================"

# Vérifier que composer est installé
if ! command -v composer &> /dev/null; then
    echo "❌ Composer n'est pas installé. Veuillez l'installer d'abord."
    exit 1
fi

# Installer les dépendances si nécessaire
if [ ! -d "vendor" ]; then
    echo "📦 Installation des dépendances..."
    composer install
fi

echo "🏃 Lancement des tests..."
echo

# Lancer les tests avec affichage détaillé
./vendor/bin/phpunit --testdox --colors=always

echo
echo "📊 Résumé des tests terminé."
echo

# Optionnel: générer le rapport de couverture
read -p "Voulez-vous générer le rapport de couverture HTML? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📈 Génération du rapport de couverture..."
    ./vendor/bin/phpunit --coverage-html coverage/
    echo "✅ Rapport généré dans le dossier coverage/"
fi

echo "🎉 Tests terminés!"