<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../libs/fpdf/fpdf.php';

$db = getDB();

// Vérifier que l'utilisateur est admin
$stmt = $db->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    die("Accès non autorisé");
}

// Récupérer tous les médicaments à commander (stock <= seuil)
$stmt = $db->query("
    SELECT 
        m.id,
        m.nom,
        m.forme,
        m.dosage,
        m.stock,
        m.seuil_alerte,
        (m.seuil_alerte - m.stock) as quantite_a_commander,
        m.fournisseur,
        m.prix_achat,
        (m.prix_achat * (m.seuil_alerte - m.stock)) as cout_total
    FROM medicaments m
    WHERE m.stock <= m.seuil_alerte
    ORDER BY quantite_a_commander DESC
");
$medicaments = $stmt->fetchAll();

// Récupérer les paramètres de la pharmacie
$nom_pharmacie = getParametre('nom_pharmacie') ?: 'LG PHARMA';
$pharmacienne = getParametre('pharmacienne') ?: 'Jeanne Ngbo';

// Création du PDF
class PDF extends FPDF {
    function Header() {
        global $nom_pharmacie;
        
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, $nom_pharmacie, 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Liste d\'achat - Marché', 0, 1, 'C');
        $this->Cell(0, 8, 'Date : ' . date('d/m/Y'), 0, 1, 'C');
        $this->Ln(10);
        
        // Ligne de séparation
        $this->SetDrawColor(59, 130, 246);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// En-tête du tableau
$pdf->SetFillColor(59, 130, 246);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 10, 'Médicament', 1, 0, 'L', true);
$pdf->Cell(25, 10, 'Stock', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Seuil', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'À commander', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Fournisseur', 1, 1, 'L', true);

// Lignes du tableau
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);

$total_articles = 0;
$total_cout = 0;

foreach ($medicaments as $med) {
    $total_articles += $med['quantite_a_commander'];
    $total_cout += $med['cout_total'];
    
    // Nom du médicament
    $nom = $med['nom'];
    if ($med['forme'] || $med['dosage']) {
        $nom .= ' (' . trim($med['forme'] . ' ' . $med['dosage']) . ')';
    }
    
    $pdf->Cell(70, 8, $pdf->String($nom, 70), 1, 0, 'L');
    $pdf->Cell(25, 8, $med['stock'], 1, 0, 'C');
    $pdf->Cell(25, 8, $med['seuil_alerte'], 1, 0, 'C');
    $pdf->Cell(30, 8, $med['quantite_a_commander'], 1, 0, 'C');
    $pdf->Cell(40, 8, $med['fournisseur'] ?: 'Marché', 1, 1, 'L');
}

// Totaux
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(120, 10, 'TOTAL', 1, 0, 'R');
$pdf->Cell(30, 10, $total_articles . ' articles', 1, 0, 'C');
$pdf->Cell(40, 10, number_format($total_cout, 0, ',', ' ') . ' FCFA', 1, 1, 'L');

$pdf->Ln(10);

// Notes
$pdf->SetFont('Arial', 'I', 10);
$pdf->MultiCell(0, 6, "Liste générée automatiquement le " . date('d/m/Y à H:i') . " par le système d'alertes LG PHARMA.");
$pdf->Ln(5);
$pdf->Cell(0, 6, "Signature de la pharmacienne : ______________________________", 0, 1, 'L');
$pdf->Cell(0, 6, "Nom : " . $pharmacienne, 0, 1, 'L');

// Générer le PDF
$pdf->Output('I', 'Liste_achat_' . date('Y-m-d') . '.pdf');
?>