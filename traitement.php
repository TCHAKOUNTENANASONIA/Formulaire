<?php
// =====================================================
// traitement.php : reçoit les données, valide, upload le PDF, insère dans MySQL
// Retourne une réponse JSON pour l'AJAX
// =====================================================

// Inclure le fichier de configuration (connexion BDD + dossier uploads)
require_once 'config.php';

// Indiquer que la réponse sera du JSON
header('Content-Type: application/json');

// Vérifier que la requête est bien de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// -----------------------------------------------------
// 1. Récupération et nettoyage des données POST
// -----------------------------------------------------
$matricule       = trim($_POST['matricule'] ?? '');
$noms            = trim($_POST['noms'] ?? '');
$date_naissance  = $_POST['date_naissance'] ?? '';
$sexe            = $_POST['sexe'] ?? '';
$nationalite     = $_POST['nationalite'] ?? '';

// -----------------------------------------------------
// 2. Validation serveur (obligatoire même si JS a déjà validé)
// -----------------------------------------------------
$erreurs = [];

// Matricule : format 2 chiffres + 1 lettre + 4 chiffres
if (!preg_match('/^[0-9]{2}[A-Z]{1}[0-9]{4}$/', $matricule)) {
    $erreurs[] = "Matricule invalide (ex: 22P4001).";
}
// Noms : entre 3 et 100 caractères
if (strlen($noms) < 3 || strlen($noms) > 100) {
    $erreurs[] = "Le nom doit contenir entre 3 et 100 caractères.";
}
// Date de naissance : présence et âge >= 16 ans
if (empty($date_naissance)) {
    $erreurs[] = "Date de naissance obligatoire.";
} else {
    $ddn = new DateTime($date_naissance);
    $auj = new DateTime();
    $age = $auj->diff($ddn)->y;
    if ($age < 16) {
        $erreurs[] = "Vous devez avoir au moins 16 ans.";
    }
}
// Sexe : doit être 'M' ou 'F'
if (!in_array($sexe, ['M', 'F'])) {
    $erreurs[] = "Sexe non valide.";
}
// Nationalité : non vide
if (empty($nationalite)) {
    $erreurs[] = "Veuillez choisir une nationalité.";
}

// -----------------------------------------------------
// 3. Gestion du fichier uploadé (CV)
// -----------------------------------------------------
$cv_path = '';   // chemin final (relatif)
if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
    $fichier = $_FILES['cv'];
    $nomOriginal = $fichier['name'];
    // Récupérer le vrai type MIME (plus fiable que $_FILES['type'])
    $typeMime = mime_content_type($fichier['tmp_name']);
    $taille = $fichier['size'];

    if ($typeMime !== 'application/pdf') {
        $erreurs[] = "Le fichier doit être un PDF (MIME: application/pdf).";
    }
    if ($taille > 2 * 1024 * 1024) {
        $erreurs[] = "Le fichier dépasse 2 Mo.";
    }

    if (empty($erreurs)) {
        // Générer un nom unique pour éviter les collisions
        $extension = '.pdf';
        $nomUnique = uniqid('cv_') . '_' . md5($nomOriginal) . $extension;
        $destination = UPLOAD_DIR . $nomUnique;

        // Déplacer le fichier depuis le dossier temporaire vers uploads/
        if (move_uploaded_file($fichier['tmp_name'], $destination)) {
            $cv_path = 'uploads/' . $nomUnique;   // chemin relatif pour la base
        } else {
            $erreurs[] = "Erreur lors de l'enregistrement du fichier.";
        }
    }
} else {
    $erreurs[] = "Veuillez fournir un fichier CV.";
}

// -----------------------------------------------------
// 4. S'il y a des erreurs, on répond avec la liste
// -----------------------------------------------------
if (!empty($erreurs)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $erreurs)]);
    exit;
}

// -----------------------------------------------------
// 5. Insertion en base de données (avec PDO préparé)
// -----------------------------------------------------
try {
    $pdo = getPDOConnection();

    // Vérifier l'unicité du matricule (éviter les doublons)
    $checkStmt = $pdo->prepare("SELECT id FROM etudiants WHERE matricule = ?");
    $checkStmt->execute([$matricule]);
    if ($checkStmt->fetch()) {
        // Nettoyer le fichier déjà uploadé car l'insertion va échouer
        if (file_exists(UPLOAD_DIR . basename($cv_path))) {
            unlink(UPLOAD_DIR . basename($cv_path));
        }
        echo json_encode(['success' => false, 'message' => 'Ce matricule existe déjà.']);
        exit;
    }

    // Requête d'insertion
    $sql = "INSERT INTO etudiants (matricule, noms, date_naissance, sexe, nationale, cv_path)
            VALUES (:matricule, :noms, :date_naissance, :sexe, :nationale, :cv_path)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':matricule' => $matricule,
        ':noms' => $noms,
        ':date_naissance' => $date_naissance,
        ':sexe' => $sexe,
        ':nationale' => $nationalite,
        ':cv_path' => $cv_path
    ]);

    // Succès
    echo json_encode(['success' => true, 'message' => 'Inscription réussie ! Bienvenue.']);

} catch (PDOException $e) {
    // En cas d'erreur SQL, on supprime le fichier pour ne pas laisser d'orphan
    if (!empty($cv_path) && file_exists(UPLOAD_DIR . basename($cv_path))) {
        unlink(UPLOAD_DIR . basename($cv_path));
    }
    echo json_encode(['success' => false, 'message' => 'Erreur base de données : ' . $e->getMessage()]);
}
?>