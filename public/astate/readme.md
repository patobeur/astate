# API PHP pour l'extension Chrome "Astate"

Ce répertoire contient le point d'entrée de l'API backend pour l'extension Chrome.

## Fichiers

-  `api.php`: Le script principal qui gère les requêtes de l'extension. Il est responsable de l'authentification, du traitement des données et de la communication sécurisée (chiffrement/déchiffrement).
-  `../../private_astate/config.php`: Fichier de configuration (situé en dehors du répertoire public) contenant les informations sensibles comme les clés d'API, les mots de passe et les paramètres de l'environnement.
