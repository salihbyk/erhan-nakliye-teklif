<?php
/**
 * Git Hook'larını kurulum scripti
 */

echo "=== Git Hook'ları Kurulum Scripti ===\n\n";

$projectDir = dirname(__DIR__);
$gitHooksDir = $projectDir . '/.git/hooks';
$sourceHooksDir = __DIR__ . '/git-hooks';

// Git repository kontrolü
if (!is_dir($projectDir . '/.git')) {
    echo "❌ Bu proje bir Git repository'si değil!\n";
    echo "Git repository'si başlatmak için: git init\n";
    exit(1);
}

// Hooks dizini kontrolü
if (!is_dir($gitHooksDir)) {
    echo "❌ Git hooks dizini bulunamadı: $gitHooksDir\n";
    exit(1);
}

// Kaynak hooks dizini kontrolü
if (!is_dir($sourceHooksDir)) {
    echo "❌ Kaynak hooks dizini bulunamadı: $sourceHooksDir\n";
    exit(1);
}

echo "📁 Git hooks dizini: $gitHooksDir\n";
echo "📁 Kaynak hooks dizini: $sourceHooksDir\n\n";

// Hook dosyalarını kopyala
$hooks = ['post-commit'];
$installedHooks = [];

foreach ($hooks as $hook) {
    $sourceFile = $sourceHooksDir . '/' . $hook;
    $targetFile = $gitHooksDir . '/' . $hook;

    if (!file_exists($sourceFile)) {
        echo "⚠️  Kaynak hook dosyası bulunamadı: $hook\n";
        continue;
    }

    // Mevcut hook'u yedekle
    if (file_exists($targetFile)) {
        $backupFile = $targetFile . '.backup.' . time();
        copy($targetFile, $backupFile);
        echo "💾 Mevcut hook yedeklendi: " . basename($backupFile) . "\n";
    }

    // Hook dosyasını kopyala
    if (copy($sourceFile, $targetFile)) {
        // Çalıştırma izni ver (Unix/Linux/MacOS)
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($targetFile, 0755);
        }

        echo "✅ Hook kuruldu: $hook\n";
        $installedHooks[] = $hook;
    } else {
        echo "❌ Hook kurulamadı: $hook\n";
    }
}

// Config dosyası oluştur
$configFile = __DIR__ . '/auto-update-config.json';
if (!file_exists($configFile)) {
    $config = [
        'enabled' => true,
        'update_server_url' => 'https://updates.your-domain.com/api',
        'project_id' => 'nakliye-teklif-system',
        'auto_upload' => false,
        'auto_notify' => true,
        'min_changes' => 1,
        'excluded_files' => [
            'tools/',
            'backups/',
            'uploads/',
            '*.log',
            '*.backup.*'
        ]
    ];

    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "⚙️  Config dosyası oluşturuldu: auto-update-config.json\n";
}

// Özet
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 KURULUM ÖZETİ\n";
echo str_repeat("=", 50) . "\n";
echo "✅ Kurulan hook'lar: " . count($installedHooks) . "/" . count($hooks) . "\n";

foreach ($installedHooks as $hook) {
    echo "   - $hook\n";
}

echo "\n🔧 YAPıLANDıRMA:\n";
echo "   - Config dosyası: tools/auto-update-config.json\n";
echo "   - Log dosyası: tools/auto-update.log\n";

echo "\n💡 KULLANIM:\n";
echo "   - Normal commit: Otomatik güncelleme paketi oluşur\n";
echo "   - Skip commit: git commit -m 'mesaj [skip-update]'\n";
echo "   - Manual çalıştırma: php tools/auto-update-system.php detect\n";

echo "\n⚙️  YAPILANDıRMA SEÇENEKLERİ:\n";
echo "   - Otomatik yükleme etkin: " . ($config['enabled'] ? 'Evet' : 'Hayır') . "\n";
echo "   - Sunucu URL: " . $config['update_server_url'] . "\n";
echo "   - Proje ID: " . $config['project_id'] . "\n";

echo "\n🚀 Hook'lar başarıyla kuruldu!\n";
echo "Artık her commit sonrası otomatik güncelleme paketi oluşacak.\n\n";

// Test commit önerisi
echo "🧪 TEST İÇİN:\n";
echo "git add .\n";
echo "git commit -m 'Test commit for auto-update system'\n";
echo "git log tools/auto-update.log\n\n";
?>
