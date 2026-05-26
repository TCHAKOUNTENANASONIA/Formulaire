<?php
// =====================================================
// config.php : Paramètres de connexion à la base de données
// =====================================================

// Définition des constantes (elles ne changent pas)
define('DB_HOST', 'localhost');       // Serveur MySQL (local)
define('DB_NAME', 'bd_inscription');  // Nom de la base
define('DB_USER', 'root');            // Utilisateur (par défaut sur XAMPP)
define('DB_PASS', '');                // Mot de passe (vide sous Windows)

// Définition du dossier où seront stockés les CV
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Créer le dossier uploads s'il n'existe pas (droits d'écriture)
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Fonction qui retourne une connexion PDO (prête à l'emploi)
function getPDOConnection() {
    try {
        // Chaîne de connexion (DSN)
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        // Créer l'objet PDO
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        // En cas d'erreur SQL, PDO lance une exception
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Si la connexion échoue, on arrête tout avec un message clair
        die("Erreur de connexion à la base : " . $e->getMessage());
    }
}
?>