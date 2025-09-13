<?php
require_once __DIR__ . '/../config/database.php';

try {
	$database = new Database();
	$db = $database->getConnection();

	if (!$db) {
		throw new Exception('Veritabanı bağlantısı kurulamadı');
	}

	function columnExists(PDO $db, string $table, string $column): bool {
		$stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
		$stmt->execute([$column]);
		return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
	}

	// quote_templates tablosu için gerekli sütunlar
	$columns = [
		['name' => 'services_title', 'ddl' => "ALTER TABLE `quote_templates` ADD COLUMN `services_title` VARCHAR(255) DEFAULT 'Hizmetlerimiz' AFTER `terms_content`"],
		['name' => 'transport_process_title', 'ddl' => "ALTER TABLE `quote_templates` ADD COLUMN `transport_process_title` VARCHAR(255) DEFAULT 'Taşıma Süreci' AFTER `services_title`"],
		['name' => 'terms_title', 'ddl' => "ALTER TABLE `quote_templates` ADD COLUMN `terms_title` VARCHAR(255) DEFAULT 'Şartlar ve Koşullar' AFTER `transport_process_title`"],
		['name' => 'dynamic_sections', 'ddl' => "ALTER TABLE `quote_templates` ADD COLUMN `dynamic_sections` JSON NULL AFTER `terms_title`"],
		['name' => 'section_order', 'ddl' => "ALTER TABLE `quote_templates` ADD COLUMN `section_order` JSON NULL AFTER `dynamic_sections`"],
	];

	foreach ($columns as $col) {
		try {
			if (!columnExists($db, 'quote_templates', $col['name'])) {
				try {
					$db->exec($col['ddl']);
				} catch (Exception $e) {
					// JSON desteklenmiyorsa TEXT'e düş
					if (strpos($col['ddl'], 'JSON') !== false) {
						$ddlText = str_replace(' JSON NULL', ' TEXT NULL', $col['ddl']);
						$db->exec($ddlText);
					} else {
						throw $e;
					}
				}
			}
		} catch (Exception $e) {
			error_log('Migration sütun ekleme hatası (' . $col['name'] . '): ' . $e->getMessage());
		}
	}

	// Null değerleri varsayılana çek
	try { $db->exec("UPDATE `quote_templates` SET `services_title` = COALESCE(`services_title`, 'Hizmetlerimiz')"); } catch (Exception $e) { /* yoksay */ }
	try { $db->exec("UPDATE `quote_templates` SET `transport_process_title` = COALESCE(`transport_process_title`, 'Taşıma Süreci')"); } catch (Exception $e) { /* yoksay */ }
	try { $db->exec("UPDATE `quote_templates` SET `terms_title` = COALESCE(`terms_title`, 'Şartlar ve Koşullar')"); } catch (Exception $e) { /* yoksay */ }

	// Başarılı log
	error_log('Template editor migration başarıyla kontrol edildi/uygulandı.');

} catch (Exception $e) {
	error_log('Template editor migration genel hata: ' . $e->getMessage());
}
