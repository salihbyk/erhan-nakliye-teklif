// Form adımları yönetimi
let currentStep = 1;
const totalSteps = 3;

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up transport mode listeners...');

    // İlk adımı göster
    showStep(1);
    updateProgressBar();
    updateButtons();

    // Konteyner tipi seçimi
    const containerTypeOptions = document.querySelectorAll('.container-type-option');
    containerTypeOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Önceki seçimi temizle
            document.querySelectorAll('.container-type-option').forEach(opt => {
                opt.classList.remove('selected');
            });

            // Yeni seçimi işaretle
            this.classList.add('selected');
            document.getElementById('selectedContainerType').value = this.dataset.type;

            console.log('Container type selected:', this.dataset.type);
        });
    });

    // Taşıma modu ve işlem türü seçimi
    const tradeButtons = document.querySelectorAll('.trade-btn');
    console.log('Found trade buttons:', tradeButtons.length);

    tradeButtons.forEach((button, index) => {
        console.log(`Setting up listener for trade button ${index + 1}:`, button.dataset.mode, button.dataset.trade);

        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const selectedMode = this.dataset.mode;
            const selectedTrade = this.dataset.trade;

            console.log('Trade button clicked:', selectedMode, selectedTrade);

            // Önceki seçimleri kaldır
            document.querySelectorAll('.transport-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelectorAll('.trade-btn').forEach(btn => {
                btn.classList.remove('selected');
            });

            // Yeni seçimi ekle
            this.classList.add('selected');
            this.closest('.transport-option').classList.add('selected');

            // Hidden inputları güncelle
            document.getElementById('selectedMode').value = selectedMode;
            document.getElementById('tradeType').value = selectedTrade;

            console.log('Selected mode and trade set to:', selectedMode, selectedTrade);

            // Ağırlık ve parça sayısı alanlarının görünürlüğünü kontrol et
            toggleWeightAndPiecesFields(selectedMode);

            // Konteyner tipi bölümünü göster/gizle
            toggleContainerTypeSection(selectedMode);

            // Şablon bölümünü göster (sadece düzenleme modunda değilse)
            const templateSection = document.getElementById('templateSection');
            if (templateSection && !window.isEditMode) {
                templateSection.style.display = 'block';
                templateSection.classList.add('slide-up');
                console.log('Template section made visible');
            } else if (window.isEditMode) {
                console.log('Edit mode: Template section hidden');
            } else {
                console.error('Template section not found!');
            }

            // Şablonları yükle (sadece düzenleme modunda değilse)
            if (!window.isEditMode) {
                console.log('Loading templates for:', selectedMode, selectedTrade);
                loadTemplates(selectedMode, selectedTrade);

                // Maliyet listelerini de yükle
                console.log('Loading cost lists for:', selectedMode);
                loadCostLists(selectedMode);
            }
        });
    });


});

// Adım değiştirme fonksiyonu
function changeStep(direction) {
    const currentStepDiv = document.getElementById(`step${currentStep}`);

    if (direction === 1) {
        // İleri git - önce doğrulama yap
        if (!validateCurrentStep()) {
            return;
        }

        if (currentStep < totalSteps) {
            // Mevcut adımı gizle
            currentStepDiv.classList.remove('active');
            currentStep++;
            showStep(currentStep);
        }
    } else {
        // Geri git
        if (currentStep > 1) {
            currentStepDiv.classList.remove('active');
            currentStep--;
            showStep(currentStep);
        }
    }

    updateProgressBar();
    updateButtons();
}

// Adımı göster
function showStep(step) {
    // Tüm adımları gizle
    document.querySelectorAll('.step-content').forEach(stepDiv => {
        stepDiv.classList.remove('active');
    });

    // Seçilen adımı göster
    const stepDiv = document.getElementById(`step${step}`);
    if (stepDiv) {
        stepDiv.classList.add('active');
    }
}

// Progress bar güncelle
function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const stepLabels = document.querySelectorAll('.step-label');

    // Progress line genişliği
    const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
    if (progressLine) {
        progressLine.style.width = percentage + '%';
    }

    // Step indicators güncelle
    stepIndicators.forEach((indicator, index) => {
        const stepNumber = index + 1;
        indicator.classList.remove('active', 'completed');

        if (stepNumber < currentStep) {
            indicator.classList.add('completed');
            indicator.innerHTML = '<i class="fas fa-check"></i>';
        } else if (stepNumber === currentStep) {
            indicator.classList.add('active');
            indicator.innerHTML = stepNumber;
        } else {
            indicator.innerHTML = stepNumber;
        }
    });

    // Step labels güncelle
    stepLabels.forEach((label, index) => {
        const stepNumber = index + 1;
        label.classList.remove('active');

        if (stepNumber === currentStep) {
            label.classList.add('active');
        }
    });
}

// Butonları güncelle
function updateButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    // Önceki buton
    if (currentStep === 1) {
        prevBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'inline-block';
    }

    // Sonraki/Gönder butonları
    if (currentStep === totalSteps) {
        nextBtn.classList.add('d-none');
        submitBtn.classList.remove('d-none');
    } else {
        nextBtn.classList.remove('d-none');
        submitBtn.classList.add('d-none');
    }
}

// Mevcut adımı doğrula
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            return validateStep1();
        case 2:
            return validateStep2();
        case 3:
            return validateStep3();
        default:
            return true;
    }
}

// Adım 1 doğrulaması (Müşteri Bilgileri)
function validateStep1() {
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const email = document.getElementById('email').value.trim();

    if (!firstName) {
        showError('Ad alanı boş olamaz!');
        document.getElementById('firstName').focus();
        return false;
    }

    if (!lastName) {
        showError('Soyad alanı boş olamaz!');
        document.getElementById('lastName').focus();
        return false;
    }

    if (!phone) {
        showError('Telefon alanı boş olamaz!');
        document.getElementById('phone').focus();
        return false;
    }

    // Telefon numarası format kontrolü - en az 7 karakter olmalı
    const phoneDigits = phone.replace(/\D/g, '');
    if (phoneDigits.length < 7) {
        showError('Geçerli bir telefon numarası girin! (Minimum 7 rakam)');
        document.getElementById('phone').focus();
        return false;
    }

    if (!email) {
        showError('E-posta alanı boş olamaz!');
        document.getElementById('email').focus();
        return false;
    }

    // E-posta formatı kontrolü
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('Geçerli bir e-posta adresi girin!');
        document.getElementById('email').focus();
        return false;
    }

    return true;
}

// Adım 2 doğrulaması (Taşıma Modu ve Şablon Seçimi)
function validateStep2() {
    const selectedMode = document.getElementById('selectedMode').value;
    const tradeType = document.getElementById('tradeType').value;

    if (!selectedMode || !tradeType) {
        showError('Lütfen bir taşıma modu ve işlem türü seçin!');
        return false;
    }

    // Düzenleme modunda şablon seçimi zorunlu değil
    if (window.isEditMode) {
        return true;
    }

    // Şablon bölümü görünür mü kontrol et
    const templateSection = document.getElementById('templateSection');
    if (!templateSection || templateSection.style.display === 'none') {
        showError('Lütfen önce bir taşıma modu ve işlem türü seçin!');
        return false;
    }

    // Şablonlar yüklenmiş mi kontrol et
    const templateOptions = document.getElementById('templateOptions');
    if (!templateOptions || templateOptions.innerHTML.includes('Şablonlar yükleniyor')) {
        showError('Şablonlar henüz yükleniyor, lütfen bekleyin...');
        return false;
    }

    // Şablon seçilmiş mi kontrol et
    const selectedTemplate = document.getElementById('selectedTemplate').value;
    if (!selectedTemplate) {
        showError('Lütfen bir şablon seçin!');
        return false;
    }

    return true;
}

// Adım 3 doğrulaması (Yük Detayları)
function validateStep3() {
    const origin = document.getElementById('origin').value.trim();
    const destination = document.getElementById('destination').value.trim();

    if (!origin) {
        showError('Yükleme adresi boş olamaz!');
        document.getElementById('origin').focus();
        return false;
    }

    if (!destination) {
        showError('Teslim adresi boş olamaz!');
        document.getElementById('destination').focus();
        return false;
    }

    return true;
}

// Hata mesajı göster
function showError(message) {
    // Mevcut alert'leri kaldır
    const existingAlerts = document.querySelectorAll('.alert-danger');
    existingAlerts.forEach(alert => alert.remove());

    // Yeni alert oluştur
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Form content'in başına ekle
    const formContent = document.querySelector('.form-content');
    formContent.insertBefore(alertDiv, formContent.firstChild);

    // Otomatik kapat
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);

    // Scroll to top
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Başarı mesajı göster
function showSuccess(message) {
    // Mevcut alert'leri kaldır
    const existingAlerts = document.querySelectorAll('.alert-success');
    existingAlerts.forEach(alert => alert.remove());

    // Yeni alert oluştur
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-check-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Form content'in başına ekle
    const formContent = document.querySelector('.form-content');
    formContent.insertBefore(alertDiv, formContent.firstChild);

    // Otomatik kapat
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Form gönder
function submitForm() {
    if (!validateCurrentStep()) {
        return;
    }

    // Loading state
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner"></span> Teklif Oluşturuluyor...';
    submitBtn.disabled = true;

    // Form verilerini topla
    const formData = {
        firstName: document.getElementById('firstName').value.trim(),
        lastName: document.getElementById('lastName').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        email: document.getElementById('email').value.trim(),
        company: document.getElementById('company').value.trim(),
        transportMode: document.getElementById('selectedMode').value,
        containerType: document.getElementById('selectedContainerType').value,
        selectedTemplate: document.getElementById('selectedTemplate').value,
        selectedCostList: document.getElementById('selectedCostList').value,
        origin: document.getElementById('origin').value.trim(),
        destination: document.getElementById('destination').value.trim(),
        weight: document.getElementById('weight').value,
        volume: document.getElementById('volume').value,
        unitPrice: document.getElementById('unitPrice').value,
        pieces: document.getElementById('pieces').value,
        cargoType: document.getElementById('cargoType').value,
        tradeType: document.getElementById('tradeType').value,
        startDate: document.getElementById('startDate').value,
        deliveryDate: document.getElementById('deliveryDate').value,
        description: document.getElementById('description').value.trim()
    };

    console.log('Submitting form data:', formData);

    // AJAX ile form gönder
    fetch('api/submit-quote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);

        // Loading state'i kaldır
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

                if (data.success) {
            // Başarı modalını göster
            document.getElementById('quoteNumber').textContent = data.quoteId;

            // Teklifi düzenle butonuna teklif ID'sini ekle - dinamik yol
            const editBtn = document.getElementById('editQuoteBtn');
            if (editBtn) {
                // Mevcut domain ve path'i al
                const currentPath = window.location.pathname;
                const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
                editBtn.href = `${basePath}admin/quotes.php?id=${data.quoteId}`;
            }

            const modal = new bootstrap.Modal(document.getElementById('resultModal'));
            modal.show();

            // Modal kapandığında formu sıfırla
            document.getElementById('resultModal').addEventListener('hidden.bs.modal', function() {
                resetForm();
            });
        } else {
            showError(data.message || 'Teklif oluşturulurken bir hata oluştu!');
        }
    })
        .catch(error => {
        console.error('Error:', error);

        // Loading state'i kaldır
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        showError('Bağlantı hatası! Lütfen tekrar deneyin.');
    });
}

// Formu sıfırla
function resetForm() {
    // Form alanlarını temizle
    document.getElementById('quoteForm').reset();

    // Seçimleri temizle
    document.querySelectorAll('.transport-option').forEach(opt => {
        opt.classList.remove('selected');
    });

    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('selected');
    });

    document.querySelectorAll('.cost-list-card').forEach(card => {
        card.classList.remove('selected');
    });

    document.querySelectorAll('.container-type-option').forEach(option => {
        option.classList.remove('selected');
    });

    // Hidden alanları temizle
    document.getElementById('selectedMode').value = '';
    document.getElementById('selectedContainerType').value = '';
    document.getElementById('selectedTemplate').value = '';
    document.getElementById('selectedCostList').value = '';

    // Ağırlık ve parça sayısı alanlarını gizle
    const weightField = document.getElementById('weightField');
    const piecesField = document.getElementById('piecesField');
    const weightInput = document.getElementById('weight');

    if (weightField) {
        weightField.style.display = 'none';
        weightInput.required = false;
        weightInput.value = ''; // Alanı temizle
    }
    if (piecesField) {
        piecesField.style.display = 'none';
        document.getElementById('pieces').value = ''; // Alanı temizle
    }

    // Unit price alanını temizle
    document.getElementById('unitPrice').value = '';

    // Şablon ve maliyet listesi bölümlerini gizle
    const templateSection = document.getElementById('templateSection');
    if (templateSection) {
        templateSection.style.display = 'none';
    }

    const costListSection = document.getElementById('costListSection');
    if (costListSection) {
        costListSection.style.display = 'none';
    }

    // Konteyner tipi bölümünü gizle
    const containerTypeSection = document.getElementById('containerTypeSection');
    if (containerTypeSection) {
        containerTypeSection.style.display = 'none';
    }

    // Düzenleme modunu sıfırla
    window.isEditMode = false;

    // İlk adıma dön
    currentStep = 1;
    showStep(1);
    updateProgressBar();
    updateButtons();

    // Alert'leri temizle
    document.querySelectorAll('.alert').forEach(alert => alert.remove());

    console.log('Form reset completed');
}

// Telefon numarası için daha esnek validation
document.getElementById('phone').addEventListener('input', function(e) {
    // Sadece sayıları, + işaretini ve boşlukları kabul et
    let value = e.target.value.replace(/[^\d\+\s\-\(\)]/g, '');

    // Maksimum 20 karakter sınırı (uluslararası numaralar için)
    if (value.length > 20) {
        value = value.substring(0, 20);
    }

    e.target.value = value;
});

// Enter tuşu ile sonraki adıma geç
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (currentStep < totalSteps) {
            changeStep(1);
        } else {
            submitForm();
        }
    }
});

// Şablonları yükle
function loadTemplates(transportMode, tradeType = 'ithalat') {
    const templateOptions = document.getElementById('templateOptions');

    // Loading göster
    templateOptions.innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Şablonlar yükleniyor...</span>
            </div>
            <p class="mt-2 text-muted">Şablonlar yükleniyor...</p>
        </div>
    `;

    console.log('Loading templates for transport mode:', transportMode, 'trade type:', tradeType);

    // Trade type'ı API'ye uygun formata çevir
    const apiTradeType = tradeType === 'ithalat' ? 'import' : 'export';

    // AJAX ile şablonları getir
    fetch(`api/get-templates.php?mode=${encodeURIComponent(transportMode)}&trade_type=${apiTradeType}`)
        .then(response => {
            console.log('Templates response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Templates data:', data);

            if (data.success && data.templates && data.templates.length > 0) {
                let templatesHtml = '';

                data.templates.forEach(template => {
                    const languageText = template.language === 'tr' ? 'Türkçe' : 'English';
                    const tradeTypeText = template.trade_type === 'import' ? 'İthalat' : 'İhracat';
                    const tradeTypeBadgeClass = template.trade_type === 'import' ? 'badge-import' : 'badge-export';

                    templatesHtml += `
                        <div class="template-card" onclick="selectTemplate(${template.id}, '${template.template_name}')">
                            <div class="template-header">
                                <h6 class="template-title">${template.template_name}</h6>
                                <div class="template-badges">
                                    <span class="template-badge badge-language">${languageText}</span>
                                    <span class="template-badge badge-currency">${template.currency}</span>
                                    <span class="template-badge ${tradeTypeBadgeClass}">${tradeTypeText}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                templateOptions.innerHTML = templatesHtml;
            } else {
                templateOptions.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Bu taşıma modu için henüz şablon bulunmuyor.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading templates:', error);
            templateOptions.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <p class="text-danger">Şablonlar yüklenirken hata oluştu!</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="loadTemplates('${transportMode}')">
                        <i class="fas fa-redo"></i> Tekrar Dene
                    </button>
                </div>
            `;
        });
}

// Şablon seç
function selectTemplate(templateId, templateName) {
    console.log('Template selected:', templateId, templateName);

    // Önceki seçimi kaldır
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Yeni seçimi ekle
    event.target.closest('.template-card').classList.add('selected');

    // Hidden input'u güncelle
    document.getElementById('selectedTemplate').value = templateId;

    console.log('Selected template ID set to:', templateId);
}

// Maliyet listelerini yükle
function loadCostLists(transportMode) {
    const costListOptions = document.getElementById('costListOptions');
    const costListSection = document.getElementById('costListSection');

    // Bölümü göster
    if (costListSection && !window.isEditMode) {
        costListSection.style.display = 'block';
        costListSection.classList.add('slide-up');
    }

    // Loading göster
    costListOptions.innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Maliyet listeleri yükleniyor...</span>
            </div>
            <p class="mt-2 text-muted">Maliyet listeleri yükleniyor...</p>
        </div>
    `;

    console.log('Loading cost lists for transport mode:', transportMode);

        // AJAX ile maliyet listelerini getir
    fetch(`api/get-cost-lists.php?mode=${encodeURIComponent(transportMode)}`)
        .then(response => {
            return response.json();
        })
        .then(data => {

            if (data.success && data.cost_lists && data.cost_lists.length > 0) {
                let costListsHtml = '';

                data.cost_lists.forEach(costList => {
                    const extension = costList.file_extension;
                    let iconClass = 'fas fa-file text-secondary';

                    switch(extension) {
                        case 'pdf':
                            iconClass = 'fas fa-file-pdf text-danger';
                            break;
                        case 'xlsx':
                        case 'xls':
                            iconClass = 'fas fa-file-excel text-success';
                            break;
                        case 'doc':
                        case 'docx':
                            iconClass = 'fas fa-file-word text-primary';
                            break;
                        case 'csv':
                            iconClass = 'fas fa-file-csv text-info';
                            break;
                    }

                    costListsHtml += `
                        <div class="cost-list-card" onclick="selectCostList(${costList.id}, '${costList.name.replace(/'/g, "\\'")}')">
                            <div class="cost-list-header">
                                <div class="cost-list-icon">
                                    <i class="${iconClass}"></i>
                                </div>
                                <div class="cost-list-info">
                                    <h6 class="cost-list-title">${costList.name}</h6>
                                    <div class="cost-list-filename">${costList.file_name}</div>
                                    <div class="cost-list-meta">
                                        ${costList.transport_mode_name ?
                                            `<span class="cost-list-badge badge-mode">${costList.transport_mode_name}</span>` :
                                            `<span class="cost-list-badge badge-mode">Genel</span>`
                                        }
                                        <span class="cost-list-badge badge-size">${costList.file_size_formatted}</span>
                                    </div>
                                    ${costList.description ? `<p class="small text-muted mt-2 mb-0">${costList.description}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });

                costListOptions.innerHTML = costListsHtml;
            } else {
                costListOptions.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-file-excel fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Bu taşıma modu için maliyet listesi bulunmuyor.</p>
                        <small class="text-muted">Maliyet listesi seçimi opsiyoneldir, devam edebilirsiniz.</small>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading cost lists:', error);
            costListOptions.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                    <p class="text-warning">Maliyet listeleri yüklenirken hata oluştu!</p>
                    <small class="text-muted">Maliyet listesi seçimi opsiyoneldir, devam edebilirsiniz.</small>
                    <br>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="loadCostLists('${transportMode}')">
                        <i class="fas fa-redo"></i> Tekrar Dene
                    </button>
                </div>
            `;
        });
}

// Maliyet listesi seç
function selectCostList(costListId, costListName) {
    console.log('Cost list selected:', costListId, costListName);

    // Önceki seçimi kaldır
    document.querySelectorAll('.cost-list-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Yeni seçimi ekle
    event.target.closest('.cost-list-card').classList.add('selected');

    // Hidden input'u güncelle
    document.getElementById('selectedCostList').value = costListId;

    console.log('Selected cost list ID set to:', costListId);
}

// Ağırlık ve parça sayısı alanlarının görünürlüğünü kontrol et
function toggleWeightAndPiecesFields(transportMode) {
    const weightField = document.getElementById('weightField');
    const piecesField = document.getElementById('piecesField');
    const weightInput = document.getElementById('weight');

    if (transportMode === 'havayolu') {
        // Havayolu seçilirse ağırlık ve parça sayısı alanlarını göster
        if (weightField) {
            weightField.style.display = 'block';
            // Weight alanı hiçbir zaman zorunlu değil
            weightInput.required = false;
        }
        if (piecesField) {
            piecesField.style.display = 'block';
        }
        console.log('Havayolu seçildi - Ağırlık ve parça sayısı alanları gösterildi');
    } else {
        // Diğer taşıma modları için ağırlık ve parça sayısı alanlarını gizle
        if (weightField) {
            weightField.style.display = 'none';
            weightInput.required = false;
            weightInput.value = ''; // Alanı temizle
        }
        if (piecesField) {
            piecesField.style.display = 'none';
            document.getElementById('pieces').value = ''; // Alanı temizle
        }
        console.log('Diğer taşıma modu seçildi - Ağırlık ve parça sayısı alanları gizlendi');
    }
}

// Konteyner tipi bölümünü göster/gizle
function toggleContainerTypeSection(transportMode) {
    const containerTypeSection = document.getElementById('containerTypeSection');

    if (transportMode === 'denizyolu') {
        // Denizyolu seçilirse konteyner tipi bölümünü göster
        if (containerTypeSection) {
            containerTypeSection.style.display = 'block';
        }
        console.log('Denizyolu seçildi - Konteyner tipi bölümü gösterildi');
    } else {
        // Diğer taşıma modları için konteyner tipi bölümünü gizle
        if (containerTypeSection) {
            containerTypeSection.style.display = 'none';
            // Seçimi temizle
            document.getElementById('selectedContainerType').value = '';
            document.querySelectorAll('.container-type-option').forEach(opt => {
                opt.classList.remove('selected');
            });
        }
        console.log('Diğer taşıma modu seçildi - Konteyner tipi bölümü gizlendi');
    }
}

// Tarih alanları için geliştirilmiş etkileşim
document.addEventListener('DOMContentLoaded', function() {
    setupDateInputs();
});

function setupDateInputs() {
    const dateInputs = document.querySelectorAll('.date-input');

    dateInputs.forEach(input => {
        // Input alanına tıklayınca tarih seçiciyi aç
        input.addEventListener('click', function(e) {
            e.preventDefault();
            // showPicker desteği varsa kullan
            if (this.showPicker) {
                try {
                    this.showPicker();
                } catch (error) {
                    // Fallback: focus ile tarih seçiciyi tetikle
                    this.focus();
                }
            } else {
                // Fallback: focus ile tarih seçiciyi tetikle
                this.focus();
            }
        });

        // Input wrapper'a da click event ekle
        const wrapper = input.parentElement;
        if (wrapper && wrapper.classList.contains('date-input-wrapper')) {
            wrapper.addEventListener('click', function(e) {
                e.preventDefault();
                input.click();
            });
        }

        // Input alanı üzerine gelindiğinde stil değişikliği
        input.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
        });

        input.addEventListener('mouseleave', function() {
            if (!this.matches(':focus')) {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Tarih seçildiğinde ikonu renklendir
        input.addEventListener('change', function() {
            const icon = this.nextElementSibling;
            if (this.value) {
                icon.style.color = 'var(--success-color)';
                this.style.fontWeight = '500';
            } else {
                icon.style.color = 'var(--primary-color)';
                this.style.fontWeight = 'normal';
            }
        });

        // Focus ve blur olayları
        input.addEventListener('focus', function() {
            const icon = this.nextElementSibling;
            icon.style.color = 'var(--secondary-color)';
        });

        input.addEventListener('blur', function() {
            const icon = this.nextElementSibling;
            if (this.value) {
                icon.style.color = 'var(--success-color)';
            } else {
                icon.style.color = 'var(--primary-color)';
            }
        });
    });

    // Minimum tarih ayarları
    setMinimumDates();
}

function setMinimumDates() {
    const startDateInput = document.getElementById('startDate');
    const deliveryDateInput = document.getElementById('deliveryDate');

    // Bugünkü tarihi al
    const today = new Date().toISOString().split('T')[0];

    // Başlangıç tarihi minimum bugün olabilir
    if (startDateInput) {
        startDateInput.min = today;

        // Başlangıç tarihi değiştiğinde teslim tarihinin minimum değerini güncelle
        startDateInput.addEventListener('change', function() {
            if (deliveryDateInput && this.value) {
                deliveryDateInput.min = this.value;

                // Eğer teslim tarihi başlangıç tarihinden erkense, temizle
                if (deliveryDateInput.value && deliveryDateInput.value < this.value) {
                    deliveryDateInput.value = '';
                    showWarning('Teslim tarihi, başlangıç tarihinden önce olamaz!');
                }
            }
        });
    }

    // Teslim tarihi minimum bugün olabilir
    if (deliveryDateInput) {
        deliveryDateInput.min = today;
    }
}

// Uyarı mesajı göster
function showWarning(message) {
    // Bootstrap toast veya basit alert
    if (typeof bootstrap !== 'undefined') {
        // Bootstrap toast kullan
        const toastHtml = `
            <div class="toast align-items-center text-white bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        // Toast container oluştur (yoksa)
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

        // Toast ekle
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        // Toast kapandıktan sonra DOM'dan kaldır
        toastElement.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    } else {
        // Basit alert kullan
        alert(message);
    }
}