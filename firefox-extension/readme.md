# Astate - Extension Mozilla Firefox

Cette extension Mozilla Firefox fournit une interface de configuration simple pour interagir avec une API backend.
Elle sera developpée après la version Chrome.

## Architecture et Structure des Fichiers

L'extension est construite avec le **Manifest V3** de Chrome, ce qui impose des contraintes de sécurité modernes, notamment une politique de sécurité de contenu (CSP) stricte qui interdit les scripts en ligne.

-  `manifest.json`: Le fichier de manifeste qui définit la structure, les permissions et les capacités de l'extension. Il déclare les permissions `storage` et `host_permissions` pour communiquer avec l'API.
-  `options.html`: La page de configuration de l'extension. Elle contient une interface à onglets pour organiser les différents paramètres.
-  `options.css`: La feuille de style pour la page de configuration.
-  `options.js`: Le cœur logique de la page de configuration. Il gère l'interface (navigation par onglets), la sauvegarde des paramètres dans `chrome.storage`, et la communication avec l'API backend.
-  `background.js`: Le service worker de l'extension. Son rôle actuel est d'ouvrir la page `options.html` lorsque l'utilisateur clique sur l'icône de l'extension dans la barre d'outils.
-  `config.js`: Fichier de configuration contenant les constantes utilisées par l'extension, comme l'URL de l'API.
-  `icons/`: Répertoire contenant les différentes tailles d'icônes pour l'extension (16x16, 48x48, 128x128).

## Configuration et Stockage des Données

Les paramètres de l'utilisateur sont stockés en utilisant l'API `chrome.storage.sync`, ce qui permet de les synchroniser sur les différents appareils de l'utilisateur via son compte Google.

Les données stockées sont :

-  `login`: L'identifiant de l'utilisateur (email), stocké en clair.
-  `code`: Le mot de passe ou code secret, stocké sous forme **chiffrée**.
-  `encryptKey`: La clé secrète fournie par l'utilisateur, utilisée pour chiffrer et déchiffrer le `code`. Elle est stockée en clair.

## Sécurité

-  **Chiffrement**: Pour des raisons de sécurité, le champ `code` n'est pas stocké en clair. Il est chiffré côté client dans `options.js` en utilisant l'API Web Crypto (`AES-256-CBC`) avant d'être sauvegardé. La clé de dérivation est un hash SHA-256 de la `encryptKey` fournie par l'utilisateur.
-  **Politique de Sécurité de Contenu (CSP)**: En raison du Manifest V3, les scripts en ligne (ex: `onclick="..."`) sont interdits. Toute la logique JavaScript est gérée via des écouteurs d'événements (`addEventListener`) dans le fichier `options.js`.

## Installation en Mode Développeur

Pour tester cette extension localement :

1. Ouvrez Google Chrome et accédez à `chrome://extensions`.
2. Activez le **Mode développeur** en haut à droite.
3. Cliquez sur **"Charger l'extension non empaquetée"**.
4. Sélectionnez le répertoire `chrome-extension` de ce projet.
5. L'icône de l'extension devrait apparaître dans votre barre d'outils.
