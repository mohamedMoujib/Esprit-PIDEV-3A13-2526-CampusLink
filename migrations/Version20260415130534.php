<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415130534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_6000B0D3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_events CHANGE note note VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendar_events RENAME INDEX reservation_id TO IDX_F9E14F16B83297E7');
        $this->addSql('ALTER TABLE categories CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes CHANGE message message LONGTEXT DEFAULT NULL, CHANGE requested_date requested_date DATETIME DEFAULT NULL, CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT NULL, CHANGE status status ENUM(\'PENDING\',\'ACCEPTED\',\'REJECTED\',\'CANCELLED\'), CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE demandes RENAME INDEX student_id TO IDX_BD940CBBCB944F1A');
        $this->addSql('ALTER TABLE demandes RENAME INDEX service_id TO IDX_BD940CBBED5CA9E6');
        $this->addSql('ALTER TABLE demandes RENAME INDEX prestataire_id TO IDX_BD940CBBBE3DB2B7');
        $this->addSql('ALTER TABLE invoices ADD user_id INT NOT NULL, CHANGE issue_date issue_date DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE details details LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_6A2F2F95A76ED395 ON invoices (user_id)');
        $this->addSql('ALTER TABLE invoices RENAME INDEX payment_id TO IDX_6A2F2F954C3A3BB');
        $this->addSql('DROP INDEX uq_pub_service ON matching_history');
        $this->addSql('ALTER TABLE matching_history CHANGE compatibility_score compatibility_score NUMERIC(5, 2) NOT NULL, CHANGE notified notified TINYINT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE matching_history ADD CONSTRAINT FK_A3B4B4F438B217A7 FOREIGN KEY (publication_id) REFERENCES publications (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE matching_history ADD CONSTRAINT FK_A3B4B4F4ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE matching_history RENAME INDEX idx_publication_id TO IDX_A3B4B4F438B217A7');
        $this->addSql('ALTER TABLE matching_history RENAME INDEX idx_service_id TO IDX_A3B4B4F4ED5CA9E6');
        $this->addSql('ALTER TABLE messages CHANGE content content LONGTEXT NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE messages RENAME INDEX sender_id TO IDX_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages RENAME INDEX receiver_id TO IDX_DB021E96CD53EDB6');
        $this->addSql('ALTER TABLE payments CHANGE method method ENUM(\'PHYSICAL\',\'VIRTUAL\')');
        $this->addSql('ALTER TABLE payments RENAME INDEX reservation_id TO IDX_65D29B32B83297E7');
        $this->addSql('ALTER TABLE publications CHANGE type_publication type_publication ENUM(\'OFFRE_SERVICE\',\'DEMANDE_SERVICE\',\'VENTE_OBJET\'), CHANGE message message LONGTEXT NOT NULL, CHANGE image_url image_url VARCHAR(500) DEFAULT NULL, CHANGE localisation localisation VARCHAR(200) DEFAULT NULL, CHANGE prix_vente prix_vente NUMERIC(10, 2) DEFAULT NULL, CHANGE requested_date requested_date DATETIME DEFAULT NULL, CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT NULL, CHANGE status status ENUM(\'ACTIVE\',\'EN_COURS\',\'TERMINEE\',\'ANNULEE\'), CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE publications RENAME INDEX student_id TO IDX_32783AF4CB944F1A');
        $this->addSql('ALTER TABLE publications RENAME INDEX service_id TO IDX_32783AF4ED5CA9E6');
        $this->addSql('ALTER TABLE publications RENAME INDEX category_id TO IDX_32783AF412469DE2');
        $this->addSql('ALTER TABLE reservations CHANGE status status ENUM(\'PENDING\',\'CONFIRMED\',\'CANCELLED\'), CHANGE price price NUMERIC(10, 2) NOT NULL, CHANGE localisation localisation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservations RENAME INDEX idx_student_reservations TO IDX_4DA239CB944F1A');
        $this->addSql('ALTER TABLE reservations RENAME INDEX idx_service_reservations TO IDX_4DA239ED5CA9E6');
        $this->addSql('ALTER TABLE reviews CHANGE comment comment LONGTEXT DEFAULT NULL, CHANGE is_reported is_reported TINYINT DEFAULT NULL, CHANGE report_reason report_reason LONGTEXT DEFAULT NULL, CHANGE reported_at reported_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reviews RENAME INDEX student_id TO IDX_6970EB0FCB944F1A');
        $this->addSql('ALTER TABLE reviews RENAME INDEX idx_reviews_prestataire TO IDX_6970EB0FBE3DB2B7');
        $this->addSql('ALTER TABLE reviews RENAME INDEX reservation_id TO IDX_6970EB0FB83297E7');
        $this->addSql('ALTER TABLE services CHANGE title title VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE status status ENUM(\'EN_ATTENTE\',\'CONFIRMEE\',\'REFUSEE\',\'TERMINEE\')');
        $this->addSql('ALTER TABLE services RENAME INDEX prestataire_id TO IDX_7332E169BE3DB2B7');
        $this->addSql('ALTER TABLE services RENAME INDEX category_id TO IDX_7332E16912469DE2');
        $this->addSql('ALTER TABLE trust_point_history CHANGE reason reason ENUM(\'RESERVATION_COMPLETED\',\'REVIEW_RATING\'), CHANGE date date DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE trust_point_history RENAME INDEX prestataire_id TO IDX_8303B0C5BE3DB2B7');
        $this->addSql('DROP INDEX idx_status ON users');
        $this->addSql('DROP INDEX idx_user_type ON users');
        $this->addSql('DROP INDEX idx_email ON users');
        $this->addSql('ALTER TABLE users CHANGE user_type user_type ENUM(\'ETUDIANT\',\'PRESTATAIRE\',\'ADMIN\'), CHANGE name name VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE password password VARCHAR(255) NOT NULL, CHANGE phone phone VARCHAR(255) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE gender gender VARCHAR(255) DEFAULT NULL, CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT NULL, CHANGE address address VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE universite universite VARCHAR(255) DEFAULT NULL, CHANGE filiere filiere VARCHAR(255) DEFAULT NULL, CHANGE specialization specialization VARCHAR(255) DEFAULT NULL, CHANGE trust_points trust_points INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('ALTER TABLE calendar_events CHANGE note note VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE calendar_events RENAME INDEX idx_f9e14f16b83297e7 TO reservation_id');
        $this->addSql('ALTER TABLE categories CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE demandes CHANGE message message TEXT DEFAULT NULL, CHANGE requested_date requested_date DATETIME DEFAULT \'NULL\', CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE status status ENUM(\'PENDING\', \'ACCEPTED\', \'REJECTED\', \'CANCELLED\') DEFAULT \'\'\'PENDING\'\'\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE demandes RENAME INDEX idx_bd940cbbed5ca9e6 TO service_id');
        $this->addSql('ALTER TABLE demandes RENAME INDEX idx_bd940cbbbe3db2b7 TO prestataire_id');
        $this->addSql('ALTER TABLE demandes RENAME INDEX idx_bd940cbbcb944f1a TO student_id');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_6A2F2F95A76ED395');
        $this->addSql('DROP INDEX IDX_6A2F2F95A76ED395 ON invoices');
        $this->addSql('ALTER TABLE invoices DROP user_id, CHANGE issue_date issue_date DATETIME DEFAULT \'current_timestamp()\', CHANGE details details TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices RENAME INDEX idx_6a2f2f954c3a3bb TO payment_id');
        $this->addSql('ALTER TABLE matching_history DROP FOREIGN KEY FK_A3B4B4F438B217A7');
        $this->addSql('ALTER TABLE matching_history DROP FOREIGN KEY FK_A3B4B4F4ED5CA9E6');
        $this->addSql('ALTER TABLE matching_history CHANGE compatibility_score compatibility_score NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, CHANGE notified notified TINYINT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE updated_at updated_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('CREATE UNIQUE INDEX uq_pub_service ON matching_history (publication_id, service_id)');
        $this->addSql('ALTER TABLE matching_history RENAME INDEX idx_a3b4b4f438b217a7 TO idx_publication_id');
        $this->addSql('ALTER TABLE matching_history RENAME INDEX idx_a3b4b4f4ed5ca9e6 TO idx_service_id');
        $this->addSql('ALTER TABLE messages CHANGE content content TEXT NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE messages RENAME INDEX idx_db021e96f624b39d TO sender_id');
        $this->addSql('ALTER TABLE messages RENAME INDEX idx_db021e96cd53edb6 TO receiver_id');
        $this->addSql('ALTER TABLE payments CHANGE method method ENUM(\'PHYSICAL\', \'VIRTUAL\') NOT NULL');
        $this->addSql('ALTER TABLE payments RENAME INDEX idx_65d29b32b83297e7 TO reservation_id');
        $this->addSql('ALTER TABLE publications CHANGE type_publication type_publication ENUM(\'OFFRE_SERVICE\', \'DEMANDE_SERVICE\', \'VENTE_OBJET\') DEFAULT \'\'\'VENTE_OBJET\'\'\' NOT NULL, CHANGE message message TEXT NOT NULL, CHANGE image_url image_url VARCHAR(500) DEFAULT \'NULL\', CHANGE localisation localisation VARCHAR(200) DEFAULT \'NULL\', CHANGE prix_vente prix_vente NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE requested_date requested_date DATETIME DEFAULT \'NULL\', CHANGE proposed_price proposed_price NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE status status ENUM(\'ACTIVE\', \'EN_COURS\', \'TERMINEE\', \'ANNULEE\') DEFAULT \'\'\'ACTIVE\'\'\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE updated_at updated_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE publications RENAME INDEX idx_32783af4cb944f1a TO student_id');
        $this->addSql('ALTER TABLE publications RENAME INDEX idx_32783af4ed5ca9e6 TO service_id');
        $this->addSql('ALTER TABLE publications RENAME INDEX idx_32783af412469de2 TO category_id');
        $this->addSql('ALTER TABLE reservations CHANGE status status ENUM(\'PENDING\', \'CONFIRMED\', \'CANCELLED\') DEFAULT \'\'\'PENDING\'\'\', CHANGE price price NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE localisation localisation VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reservations RENAME INDEX idx_4da239cb944f1a TO idx_student_reservations');
        $this->addSql('ALTER TABLE reservations RENAME INDEX idx_4da239ed5ca9e6 TO idx_service_reservations');
        $this->addSql('ALTER TABLE reviews CHANGE comment comment TEXT DEFAULT NULL, CHANGE is_reported is_reported TINYINT DEFAULT 0, CHANGE report_reason report_reason TEXT DEFAULT NULL, CHANGE reported_at reported_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reviews RENAME INDEX idx_6970eb0fbe3db2b7 TO idx_reviews_prestataire');
        $this->addSql('ALTER TABLE reviews RENAME INDEX idx_6970eb0fcb944f1a TO student_id');
        $this->addSql('ALTER TABLE reviews RENAME INDEX idx_6970eb0fb83297e7 TO reservation_id');
        $this->addSql('ALTER TABLE services CHANGE title title VARCHAR(100) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE image image VARCHAR(100) DEFAULT \'NULL\', CHANGE status status ENUM(\'EN_ATTENTE\', \'CONFIRMEE\', \'REFUSEE\', \'TERMINEE\') DEFAULT \'\'\'EN_ATTENTE\'\'\' NOT NULL');
        $this->addSql('ALTER TABLE services RENAME INDEX idx_7332e16912469de2 TO category_id');
        $this->addSql('ALTER TABLE services RENAME INDEX idx_7332e169be3db2b7 TO prestataire_id');
        $this->addSql('ALTER TABLE trust_point_history CHANGE reason reason ENUM(\'RESERVATION_COMPLETED\', \'REVIEW_RATING\') NOT NULL, CHANGE date date DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE trust_point_history RENAME INDEX idx_8303b0c5be3db2b7 TO prestataire_id');
        $this->addSql('ALTER TABLE users CHANGE user_type user_type ENUM(\'ETUDIANT\', \'PRESTATAIRE\', \'ADMIN\') NOT NULL, CHANGE name name VARCHAR(100) NOT NULL, CHANGE email email VARCHAR(100) NOT NULL, CHANGE password password VARCHAR(100) NOT NULL, CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE gender gender VARCHAR(20) DEFAULT \'NULL\', CHANGE profile_picture profile_picture VARCHAR(255) DEFAULT \'NULL\', CHANGE address address VARCHAR(255) DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE updated_at updated_at DATETIME DEFAULT \'current_timestamp()\', CHANGE status status ENUM(\'ACTIVE\', \'INACTIVE\', \'BANNED\') DEFAULT \'\'\'INACTIVE\'\'\', CHANGE universite universite VARCHAR(100) DEFAULT \'NULL\', CHANGE filiere filiere VARCHAR(100) DEFAULT \'NULL\', CHANGE specialization specialization VARCHAR(100) DEFAULT \'NULL\', CHANGE trust_points trust_points INT DEFAULT 0');
        $this->addSql('CREATE INDEX idx_status ON users (status)');
        $this->addSql('CREATE INDEX idx_user_type ON users (user_type)');
        $this->addSql('CREATE INDEX idx_email ON users (email)');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO email');
    }
}
