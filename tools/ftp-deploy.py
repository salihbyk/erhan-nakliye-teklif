#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
FTP Deployment Script
Güvenli ve akıllı FTP deployment
Verilerin kaybolmaması için özel koruma
"""

import ftplib
import os
import sys
from pathlib import Path
import hashlib

# FTP Ayarları
FTP_HOST = "europagroup.com.tr"
FTP_USER = "cursor@europagroup.com.tr"
FTP_PASS = "Sgb3297282++"
FTP_REMOTE_DIR = "/public_html/teklif"

# Güvenli dosyalar - BU DOSYALAR ASLÂ ÜZERİNE YAZILMAZ
PROTECTED_FILES = [
    "config/database.php",
    "config/database.example.php",
    ".htaccess",
    ".env"
]

# Güvenli dizinler - BU DİZİNLERE DOKUNULMAZ
PROTECTED_DIRS = [
    "uploads/",
    "backups/",
    "vendor/",
    ".git/"
]

# Yüklenecek dosyalar (v2.8.0 için)
FILES_TO_UPLOAD = [
    "version.txt",
    "CHANGELOG-v2.8.0.md",
    "view-quote.php",
    "view-quote-pdf.php",
    "admin/view-quote.php",
    "api/update-general-info.php",
    "setup/add-custom-fields-order-column.php"
]

def calculate_md5(file_path):
    """Dosya MD5 hash'ini hesapla"""
    hash_md5 = hashlib.md5()
    try:
        with open(file_path, "rb") as f:
            for chunk in iter(lambda: f.read(4096), b""):
                hash_md5.update(chunk)
        return hash_md5.hexdigest()
    except:
        return None

def is_protected(file_path):
    """Dosya korumalı mı kontrol et"""
    file_path = file_path.replace("\\", "/")
    
    # Korumalı dosya mı?
    if file_path in PROTECTED_FILES:
        return True
    
    # Korumalı dizinde mi?
    for protected_dir in PROTECTED_DIRS:
        if file_path.startswith(protected_dir):
            return True
    
    return False

def connect_ftp():
    """FTP bağlantısı kur"""
    try:
        print(f"🔌 FTP bağlantısı kuruluyor: {FTP_HOST}")
        ftp = ftplib.FTP(FTP_HOST, timeout=30)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.encoding = 'utf-8'
        
        # Hedef dizine geç
        try:
            ftp.cwd(FTP_REMOTE_DIR)
            print(f"✅ Dizin: {FTP_REMOTE_DIR}")
        except:
            print(f"⚠️  Dizin bulunamadı, oluşturuluyor...")
            ftp.mkd(FTP_REMOTE_DIR)
            ftp.cwd(FTP_REMOTE_DIR)
        
        return ftp
    except Exception as e:
        print(f"❌ FTP bağlantı hatası: {e}")
        return None

def get_remote_version(ftp):
    """Uzaktaki version.txt'yi oku"""
    try:
        remote_version = []
        ftp.retrlines('RETR version.txt', remote_version.append)
        return remote_version[0].strip() if remote_version else "unknown"
    except:
        return "unknown"

def upload_file(ftp, local_path, remote_path):
    """Tek dosya yükle"""
    try:
        # Korumalı dosya kontrolü
        if is_protected(remote_path):
            print(f"🔒 Korumalı dosya atlanıyor: {remote_path}")
            return False
        
        # Uzak dizini oluştur
        remote_dir = os.path.dirname(remote_path)
        if remote_dir:
            create_remote_dir(ftp, remote_dir)
        
        # Dosya hash'ini al
        local_md5 = calculate_md5(local_path)
        
        # Dosyayı yükle
        print(f"📤 Yükleniyor: {remote_path}", end=" ")
        with open(local_path, 'rb') as f:
            ftp.storbinary(f'STOR {remote_path}', f)
        
        print(f"✅ [MD5: {local_md5[:8]}...]")
        return True
        
    except Exception as e:
        print(f"❌ Hata: {e}")
        return False

def create_remote_dir(ftp, path):
    """Uzak dizin oluştur (recursive)"""
    if not path or path == '.':
        return
    
    try:
        ftp.cwd(path)
        ftp.cwd('..')
    except:
        # Dizin yok, oluştur
        parent = os.path.dirname(path)
        if parent:
            create_remote_dir(ftp, parent)
        
        try:
            ftp.mkd(path)
        except:
            pass

def backup_remote_file(ftp, file_path):
    """Uzaktaki dosyayı yedekle"""
    try:
        backup_name = f"{file_path}.backup"
        ftp.rename(file_path, backup_name)
        print(f"💾 Yedeklendi: {file_path} -> {backup_name}")
        return True
    except:
        return False

def deploy():
    """Ana deployment fonksiyonu"""
    print("\n" + "="*60)
    print("🚀 FTP DEPLOYMENT - v2.8.0")
    print("="*60 + "\n")
    
    # Çalışma dizinini kontrol et
    base_dir = Path(__file__).parent.parent
    os.chdir(base_dir)
    
    print(f"📁 Yerel dizin: {base_dir}")
    print(f"🌐 Uzak sunucu: {FTP_HOST}")
    print(f"📂 Hedef dizin: {FTP_REMOTE_DIR}\n")
    
    # FTP bağlan
    ftp = connect_ftp()
    if not ftp:
        return False
    
    try:
        # Uzaktaki versiyonu kontrol et
        remote_version = get_remote_version(ftp)
        print(f"\n📊 Mevcut versiyon: {remote_version}")
        print(f"📊 Yeni versiyon: 2.8.0\n")
        
        if remote_version == "2.8.0":
            print("⚠️  Uzaktaki versiyon zaten 2.8.0!")
            response = input("Yine de devam edilsin mi? (e/h): ")
            if response.lower() != 'e':
                return False
        
        # Onay al
        print("\n📋 Yüklenecek dosyalar:")
        for file in FILES_TO_UPLOAD:
            if os.path.exists(file):
                size = os.path.getsize(file)
                print(f"  • {file} ({size} bytes)")
            else:
                print(f"  ⚠️  {file} [BULUNAMADI]")
        
        print("\n⚠️  DİKKAT: Aşağıdaki dosyalar/dizinler KESİNLİKLE korunacak:")
        for item in PROTECTED_FILES:
            print(f"  🔒 {item}")
        for item in PROTECTED_DIRS:
            print(f"  🔒 {item}")
        
        response = input("\n✅ Deployment başlatılsın mı? (e/h): ")
        if response.lower() != 'e':
            print("❌ İptal edildi.")
            return False
        
        # Dosyaları yükle
        print("\n" + "="*60)
        print("📤 DOSYA YÜKLENİYOR...")
        print("="*60 + "\n")
        
        success_count = 0
        failed_count = 0
        
        for file in FILES_TO_UPLOAD:
            if not os.path.exists(file):
                print(f"⚠️  Atlanıyor (bulunamadı): {file}")
                failed_count += 1
                continue
            
            if upload_file(ftp, file, file):
                success_count += 1
            else:
                failed_count += 1
        
        # Özet
        print("\n" + "="*60)
        print("📊 DEPLOYMENT ÖZETİ")
        print("="*60)
        print(f"✅ Başarılı: {success_count}")
        print(f"❌ Başarısız: {failed_count}")
        print(f"📦 Toplam: {len(FILES_TO_UPLOAD)}")
        
        # Final versiyon kontrolü
        final_version = get_remote_version(ftp)
        print(f"\n🎯 Final versiyon: {final_version}")
        
        if final_version == "2.8.0":
            print("\n🎉 DEPLOYMENT BAŞARILI! 🎉")
            print(f"\n🌐 Kontrol et: https://{FTP_HOST}{FTP_REMOTE_DIR.replace('/public_html', '')}")
            return True
        else:
            print("\n⚠️  Versiyon güncellenemedi!")
            return False
        
    except Exception as e:
        print(f"\n❌ Deployment hatası: {e}")
        import traceback
        traceback.print_exc()
        return False
    finally:
        ftp.quit()
        print("\n🔌 FTP bağlantısı kapatıldı.")

if __name__ == "__main__":
    try:
        success = deploy()
        sys.exit(0 if success else 1)
    except KeyboardInterrupt:
        print("\n\n❌ Kullanıcı tarafından iptal edildi.")
        sys.exit(1)
