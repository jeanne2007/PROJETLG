
-- Script DDL pour rétro-conception dans Looping
-- Projet : LG PHARMA

CREATE TABLE utilisateurs (
    id INT PRIMARY KEY,
    prenom VARCHAR(255),
    nom VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    photo VARCHAR(255),
    role VARCHAR(50),
    premier_login BOOLEAN,
    date_creation DATETIME,
    derniere_connexion DATETIME
);

CREATE TABLE categories (
    id INT PRIMARY KEY,
    nom VARCHAR(100) UNIQUE,
    description TEXT
);

CREATE TABLE medicaments (
    id INT PRIMARY KEY,
    nom VARCHAR(200),
    code_barre VARCHAR(50) UNIQUE,
    dci VARCHAR(200),
    forme VARCHAR(50),
    dosage VARCHAR(50),
    laboratoire VARCHAR(100),
    prix_achat DECIMAL(10,2),
    prix_vente DECIMAL(10,2),
    stock INT,
    seuil_alerte INT,
    date_peremption DATE,
    numero_lot VARCHAR(50),
    fournisseur VARCHAR(100),
    date_ajout DATETIME,
    date_modification DATETIME,
    ajoute_par INT,
    FOREIGN KEY (ajoute_par) REFERENCES utilisateurs(id)
);

CREATE TABLE ventes (
    id INT PRIMARY KEY,
    date_vente DATETIME,
    client_nom VARCHAR(255),
    vendeur_id INT,
    total_global DECIMAL(10,2),
    notes TEXT,
    FOREIGN KEY (vendeur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE ventes_lignes (
    id INT PRIMARY KEY,
    vente_id INT,
    medicament_id INT,
    quantite INT,
    prix_unitaire DECIMAL(10,2),
    total_ligne DECIMAL(10,2),
    FOREIGN KEY (vente_id) REFERENCES ventes(id),
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);

CREATE TABLE journal (
    id INT PRIMARY KEY,
    user_id INT,
    user_nom VARCHAR(255),
    action VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    date_action DATETIME,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id)
);

CREATE TABLE alertes (
    id INT PRIMARY KEY,
    type VARCHAR(50),
    medicament_id INT,
    message TEXT,
    niveau VARCHAR(20),
    vue BOOLEAN,
    date_creation DATETIME,
    date_vue DATETIME,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);

CREATE TABLE medicaments_categories (
    medicament_id INT,
    categorie_id INT,
    PRIMARY KEY (medicament_id, categorie_id),
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id),
    FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

CREATE TABLE parametres (
    id INT PRIMARY KEY,
    cle VARCHAR(100) UNIQUE,
    valeur TEXT,
    description TEXT,
    date_modification DATETIME
);
