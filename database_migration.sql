BEGIN;

-- Rôles de la plateforme
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;

ALTER TABLE users
ADD CONSTRAINT users_role_check
CHECK (role IN (
    'client',
    'agent',
    'technicien',
    'superviseur',
    'admin'
));

-- Informations complémentaires sur les clients
ALTER TABLE clients
ADD COLUMN IF NOT EXISTS quartier VARCHAR(150),
ADD COLUMN IF NOT EXISTS repere VARCHAR(255),
ADD COLUMN IF NOT EXISTS latitude NUMERIC(10,7),
ADD COLUMN IF NOT EXISTS longitude NUMERIC(10,7);

-- Évolution des dossiers de souscription
ALTER TABLE souscriptions
ADD COLUMN IF NOT EXISTS numero_demande VARCHAR(40),
ADD COLUMN IF NOT EXISTS identifiant_fibre VARCHAR(60),
ADD COLUMN IF NOT EXISTS repere VARCHAR(255),
ADD COLUMN IF NOT EXISTS latitude NUMERIC(10,7),
ADD COLUMN IF NOT EXISTS longitude NUMERIC(10,7),
ADD COLUMN IF NOT EXISTS agent_id BIGINT,
ADD COLUMN IF NOT EXISTS technicien_id BIGINT,
ADD COLUMN IF NOT EXISTS date_validation TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS date_attribution_fibre TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS date_debut_installation TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS date_fin_installation TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS motif_rejet TEXT,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP;

-- Contraintes géographiques
ALTER TABLE souscriptions
ADD CONSTRAINT souscriptions_latitude_check
CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90);

ALTER TABLE souscriptions
ADD CONSTRAINT souscriptions_longitude_check
CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180);

-- Références aux agents et techniciens
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'souscriptions_agent_id_fkey'
    ) THEN
        ALTER TABLE souscriptions
        ADD CONSTRAINT souscriptions_agent_id_fkey
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'souscriptions_technicien_id_fkey'
    ) THEN
        ALTER TABLE souscriptions
        ADD CONSTRAINT souscriptions_technicien_id_fkey
        FOREIGN KEY (technicien_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Communes
CREATE TABLE IF NOT EXISTS communes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    region VARCHAR(150),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(nom)
);

-- Repères et quartiers
CREATE TABLE IF NOT EXISTS reperes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    commune_id BIGINT NOT NULL REFERENCES communes(id),
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE(commune_id, nom)
);

-- Informations techniques d'installation
CREATE TABLE IF NOT EXISTS installations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    souscription_id BIGINT NOT NULL UNIQUE
        REFERENCES souscriptions(id) ON DELETE CASCADE,
    technicien_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    date_prevue TIMESTAMPTZ,
    date_realisation TIMESTAMPTZ,
    numero_serie_box VARCHAR(100),
    modele_box VARCHAR(150),
    puissance_signal VARCHAR(100),
    observations TEXT,
    preuve_installation TEXT,
    statut VARCHAR(30) NOT NULL DEFAULT 'a_planifier',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CHECK (statut IN (
        'a_planifier',
        'planifiee',
        'en_cours',
        'terminee',
        'echec'
    ))
);

-- Historique des changements de statut
CREATE TABLE IF NOT EXISTS historique_statuts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    souscription_id BIGINT NOT NULL
        REFERENCES souscriptions(id) ON DELETE CASCADE,
    ancien_statut VARCHAR(30),
    nouveau_statut VARCHAR(30) NOT NULL,
    commentaire TEXT,
    utilisateur_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    visible_client BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    souscription_id BIGINT REFERENCES souscriptions(id) ON DELETE CASCADE,
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    lu BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Index de recherche
CREATE INDEX IF NOT EXISTS idx_souscriptions_agent
ON souscriptions(agent_id);

CREATE INDEX IF NOT EXISTS idx_souscriptions_technicien
ON souscriptions(technicien_id);

CREATE INDEX IF NOT EXISTS idx_souscriptions_identifiant
ON souscriptions(identifiant_fibre);

CREATE UNIQUE INDEX IF NOT EXISTS idx_souscriptions_numero_unique
ON souscriptions(numero_demande)
WHERE numero_demande IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_souscriptions_fibre_unique
ON souscriptions(identifiant_fibre)
WHERE identifiant_fibre IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_historique_souscription
ON historique_statuts(souscription_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_user
ON notifications(user_id, lu);

COMMIT;
