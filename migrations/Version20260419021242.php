<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419021242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendar_events CHANGE note note VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes CHANGE requested_date requested_date DATETIME DEFAULT NULL, CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT NULL, CHANGE status status ENUM(\'PENDING\',\'ACCEPTED\',\'REJECTED\',\'CANCELLED\'), CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE invoices CHANGE issue_date issue_date DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE matching_history CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE messages CHANGE timestamp timestamp DATETIME DEFAULT CURRENT_TIMESTAMP');

        $this->addSql('ALTER TABLE payments CHANGE method method ENUM(\'PHYSICAL\',\'VIRTUAL\')');
        $this->addSql('ALTER TABLE publications CHANGE type_publication type_publication ENUM(\'OFFRE_SERVICE\',\'DEMANDE_SERVICE\',\'VENTE_OBJET\'), CHANGE image_url image_url VARCHAR(500) DEFAULT NULL, CHANGE localisation localisation VARCHAR(200) DEFAULT NULL, CHANGE prix_vente prix_vente NUMERIC(10, 2) DEFAULT NULL, CHANGE requested_date requested_date DATETIME DEFAULT NULL, CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT NULL, CHANGE status status ENUM(\'ACTIVE\',\'EN_COURS\',\'TERMINEE\',\'ANNULEE\'), CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE reservations CHANGE status status ENUM(\'PENDING\',\'CONFIRMED\',\'CANCELLED\'), CHANGE localisation localisation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reviews CHANGE reported_at reported_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE services DROP report_count, CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE status status ENUM(\'EN_ATTENTE\',\'CONFIRMEE\',\'REFUSEE\',\'TERMINEE\'), CHANGE moderation_status moderation_status VARCHAR(20) DEFAULT \'CLEAR\' NOT NULL');
        $this->addSql('ALTER TABLE trust_point_history CHANGE reason reason ENUM(\'RESERVATION_COMPLETED\',\'REVIEW_RATING\'), CHANGE date date DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE users CHANGE user_type user_type ENUM(\'ETUDIANT\',\'PRESTATAIRE\',\'ADMIN\'), CHANGE phone phone VARCHAR(255) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE gender gender VARCHAR(255) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE address address VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE universite universite VARCHAR(255) DEFAULT NULL, CHANGE filiere filiere VARCHAR(255) DEFAULT NULL, CHANGE specialization specialization VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendar_events CHANGE note note VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE demandes CHANGE requested_date requested_date DATETIME DEFAULT \'NULL\', CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE status status ENUM(\'PENDING\', \'ACCEPTED\', \'REJECTED\', \'CANCELLED\') DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE invoices CHANGE issue_date issue_date DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE matching_history CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE updated_at updated_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE messages CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp()\'');

        $this->addSql('ALTER TABLE payments CHANGE method method ENUM(\'PHYSICAL\', \'VIRTUAL\') DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE publications CHANGE type_publication type_publication ENUM(\'OFFRE_SERVICE\', \'DEMANDE_SERVICE\', \'VENTE_OBJET\') DEFAULT \'NULL\', CHANGE image_url image_url VARCHAR(500) DEFAULT \'NULL\', CHANGE localisation localisation VARCHAR(200) DEFAULT \'NULL\', CHANGE prix_vente prix_vente NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE requested_date requested_date DATETIME DEFAULT \'NULL\', CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE status status ENUM(\'ACTIVE\', \'EN_COURS\', \'TERMINEE\', \'ANNULEE\') DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE updated_at updated_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE reservations CHANGE status status ENUM(\'PENDING\', \'CONFIRMED\', \'CANCELLED\') DEFAULT \'NULL\', CHANGE localisation localisation VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reviews CHANGE reported_at reported_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE services ADD report_count INT DEFAULT 0 NOT NULL, CHANGE image image VARCHAR(255) DEFAULT \'NULL\', CHANGE status status ENUM(\'EN_ATTENTE\', \'CONFIRMEE\', \'REFUSEE\', \'TERMINEE\') DEFAULT \'NULL\', CHANGE moderation_status moderation_status VARCHAR(20) DEFAULT \'\'\'CLEAR\'\'\' NOT NULL');
        $this->addSql('ALTER TABLE trust_point_history CHANGE reason reason ENUM(\'RESERVATION_COMPLETED\', \'REVIEW_RATING\') DEFAULT \'NULL\', CHANGE date date DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE users CHANGE user_type user_type ENUM(\'ETUDIANT\', \'PRESTATAIRE\', \'ADMIN\') DEFAULT \'NULL\', CHANGE phone phone VARCHAR(255) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE gender gender VARCHAR(255) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE address address VARCHAR(255) DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE status status VARCHAR(255) DEFAULT \'NULL\', CHANGE universite universite VARCHAR(255) DEFAULT \'NULL\', CHANGE filiere filiere VARCHAR(255) DEFAULT \'NULL\', CHANGE specialization specialization VARCHAR(255) DEFAULT \'NULL\'');
    }
}
