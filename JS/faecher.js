// ===== FÄCHERVERWALTUNG MIT SQLITE UND SAUBEREM DESIGN =====
function faecherInit() {
    console.log('Initialisiere Fächerverwaltung...');
    addFaecherStyles();
    loadFaecherList();
}

function addFaecherStyles() {
    if (!document.getElementById('faecher-custom-styles')) {
        const style = document.createElement('style');
        style.id = 'faecher-custom-styles';
        style.textContent = `
            .fach-card {
                background: #FF9A56;
                border: none;
                border-radius: 20px;
                padding: 25px;
                box-shadow: 0 6px 20px rgba(255, 154, 86, 0.3);
                transition: all 0.3s ease;
                color: white;
            }
            
            .fach-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 30px rgba(255, 154, 86, 0.4);
                background: #FF8040;
            }
            
            .fach-card-title {
                font-size: 26px;
                font-weight: bold;
                color: white;
                margin-bottom: 20px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .kategorie-list {
                background: rgba(255,255,255,0.2);
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .kategorie-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                margin-bottom: 8px;
                background: rgba(255,255,255,0.25);
                border-radius: 8px;
                backdrop-filter: blur(10px);
            }
            
            .kategorie-row:last-child {
                margin-bottom: 0;
            }
            
            .kategorie-name {
                color: white;
                font-size: 15px;
                font-weight: 500;
            }
            
            .kategorie-gewicht {
                background: white;
                color: #FF9A56;
                padding: 4px 12px;
                border-radius: 15px;
                font-weight: bold;
                font-size: 14px;
                min-width: 30px;
                text-align: center;
            }
            
            .action-buttons {
                display: flex;
                gap: 10px;
                margin-top: 15px;
            }
            
            .action-btn {
                background: #4A90E2;
                border: none;
                color: white;
                padding: 10px 16px;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 14px;
                font-weight: 600;
                flex: 1;
                text-align: center;
            }
            
            .action-btn:hover {
                background: #357ABD;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            }
            
            .action-btn.delete {
                background: #dc2626;
            }
            
            .action-btn.delete:hover {
                background: #b91c1c;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            }
            
            .modal-content-styled {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                padding: 30px;
                max-width: 600px;
                animation: modalSlideIn 0.3s ease;
            }
            
            @keyframes modalSlideIn {
                from {
                    transform: translateY(-30px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .modal-header-title {
                color: #4A90E2;
                font-size: 24px;
                font-weight: bold;
                margin: 0;
            }
            
            .form-label {
                color: #4A90E2;
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 8px;
                display: block;
            }
            
            .form-input {
                width: 100%;
                padding: 12px;
                border: 2px solid #E0E0E0;
                border-radius: 10px;
                font-size: 15px;
                transition: all 0.3s;
            }
            
            .form-input:focus {
                outline: none;
                border-color: #4A90E2;
                box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            }
            
            .kategorie-input-row {
                background: #F8F9FA;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 8px;
                border: 2px solid #E0E0E0;
                transition: all 0.2s;
            }
            
            .kategorie-input-row:hover {
                border-color: #4A90E2;
                background: #F0F7FF;
            }
            
            .weight-control-btn {
                width: 30px;
                height: 30px;
                border-radius: 8px;
                background: white;
                color: #4A90E2;
                border: 2px solid #4A90E2;
                cursor: pointer;
                font-size: 18px;
                font-weight: bold;
                transition: all 0.2s;
            }
            
            .weight-control-btn:hover {
                background: #4A90E2;
                color: white;
            }
            
            .weight-display {
                font-size: 16px;
                font-weight: bold;
                color: #FF9A56;
                min-width: 30px;
                text-align: center;
                display: inline-block;
            }
            
            .btn-primary-blue {
                background: #4A90E2;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .btn-primary-blue:hover {
                background: #357ABD;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            }
            
            .btn-add-category {
                background: white;
                color: #4A90E2;
                border: 2px solid #4A90E2;
                padding: 10px 20px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                width: 100%;
            }
            
            .btn-add-category:hover {
                background: #F0F7FF;
            }
            
            .btn-remove-category {
                width: 35px;
                height: 35px;
                background: white;
                color: #FF9A56;
                border: 2px solid #FF9A56;
                border-radius: 8px;
                cursor: pointer;
                font-size: 20px;
                font-weight: bold;
                transition: all 0.2s;
            }
            
            .btn-remove-category:hover {
                background: #FF9A56;
                color: white;
            }
            
            .notification {
                padding: 14px 24px;
                border-radius: 12px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: notificationSlide 0.3s ease;
                margin-bottom: 8px;
                font-size: 15px;
            }
            
            .notification.success {
                background: #10b981;
                color: white;
            }
            
            .notification.error {
                background: #ef4444;
                color: white;
            }
            
            @keyframes notificationSlide {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function loadFaecherList() {
    const moduleContent = document.getElementById('module-content');
    if (!moduleContent) {
        console.error('module-content Container nicht gefunden');
        return;
    }
    
    moduleContent.innerHTML = `
        <div class="content-header" style="background: linear-gradient(135deg, #FFB380 0%, #FF9A56 100%); padding: 30px; border-radius: 20px; margin-bottom: 25px; color: white; box-shadow: 0 6px 20px rgba(255, 154, 86, 0.3);">
            <h2 style="font-size: 32px; margin-bottom: 10px; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                📚 Fächerverwaltung
            </h2>
            <p style="color: rgba(255,255,255,0.95); margin-bottom: 20px; font-size: 16px;">
                Verwalten Sie Ihre Unterrichtsfächer und Bewertungskategorien
            </p>
            <button class="btn-primary-blue" onclick="showAddFachModal()">
                ➕ Neues Fach anlegen
            </button>
        </div>
        
        <div id="faecher-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;">
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 48px; color: #FF9A56;">⏳</div>
                <p style="color: #666; margin-top: 10px;">Lade Fächer...</p>
            </div>
        </div>
    `;
    
    // Lade Fächer von SQLite Datenbank
    fetch('api/faecher.php?action=list')
        .then(response => {
            if (!response.ok) throw new Error('Netzwerkfehler');
            return response.json();
        })
        .then(data => {
            const faecherList = document.getElementById('faecher-list');
            
            if (!data || data.length === 0) {
                faecherList.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: linear-gradient(135deg, #FFB380 0%, #FF9A56 100%); border-radius: 20px; color: white;">
                        <div style="font-size: 72px; margin-bottom: 20px;">📚</div>
                        <p style="font-size: 24px; font-weight: bold; margin-bottom: 10px;">
                            Noch keine Fächer angelegt
                        </p>
                        <p style="font-size: 16px;">
                            Klicken Sie auf "Neues Fach anlegen" um zu beginnen.
                        </p>
                    </div>
                `;
                return;
            }
            
            faecherList.innerHTML = '';
            data.forEach(fach => {
                const card = createFachCard(fach);
                faecherList.appendChild(card);
            });
        })
        .catch(error => {
            console.error('Fehler beim Laden der Fächer:', error);
        });
}

function createFachCard(fach) {
    const card = document.createElement('div');
    card.className = 'fach-card';
    
    let kategorienHTML = '<div class="kategorie-list">';
    if (fach.kategorien && Array.isArray(fach.kategorien) && fach.kategorien.length > 0) {
        const sortedKategorien = [...fach.kategorien].sort((a, b) => a.name.localeCompare(b.name));
        
        sortedKategorien.forEach(kat => {
            kategorienHTML += `
                <div class="kategorie-row">
                    <span class="kategorie-name">📝 ${kat.name}</span>
                    <span class="kategorie-gewicht">${kat.gewichtung}</span>
                </div>
            `;
        });
    } else {
        kategorienHTML += `
            <div class="kategorie-row" style="justify-content: center;">
                <span class="kategorie-name" style="opacity: 0.8; font-style: italic;">Keine Kategorien definiert</span>
            </div>
        `;
    }
    kategorienHTML += '</div>';
    
    card.innerHTML = `
        <h3 class="fach-card-title">📚 ${fach.name}</h3>
        ${kategorienHTML}
        <div class="action-buttons">
            <button class="action-btn" onclick="editFach(${fach.id})" title="Bearbeiten">
                ✏️ Bearbeiten
            </button>
            <button class="action-btn" onclick="duplicateFach(${fach.id})" title="Kopieren">
                📋 Kopieren
            </button>
            <button class="action-btn delete" onclick="deleteFach(${fach.id})" title="Löschen">
                🗑️ Löschen
            </button>
        </div>
    `;
    
    return card;
}

function showAddFachModal() {
    console.log('Öffne Modal für neues Fach');
    openFachModal(null);
    setTimeout(() => {
        addKategorie();
    }, 100);
}

function editFach(fachId) {
    console.log('Bearbeite Fach mit ID:', fachId);
    
    // Lade Fachdaten
    fetch('api/faecher.php?action=get&id=' + fachId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Server-Fehler: ' + response.status);
            }
            return response.json();
        })
        .then(fach => {
            console.log('Geladene Fachdaten:', fach);
            
            // Öffne Modal
            openFachModal(fachId);
            
            // Fülle Daten nach kurzer Verzögerung
            setTimeout(() => {
                const nameInput = document.getElementById('fach-name');
                if (nameInput) {
                    nameInput.value = fach.name;
                }
                
                const container = document.getElementById('kategorien-container');
                if (container) {
                    container.innerHTML = '';
                    
                    if (fach.kategorien && fach.kategorien.length > 0) {
                        fach.kategorien.forEach(kat => {
                            addKategorieRow(kat.name, kat.gewichtung || 1);
                        });
                    } else {
                        addKategorieRow('', 1);
                    }
                }
            }, 100);
        })
        .catch(error => {
            console.error('Fehler beim Laden:', error);
            showNotification('Fehler beim Laden der Fachdaten', 'error');
        });
}

function openFachModal(fachId = null) {
    let modal = document.getElementById('fach-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'fach-modal';
        modal.className = 'modal';
        modal.style = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;';
        document.body.appendChild(modal);
    }
    
    const modalTitle = fachId ? '✏️ Fach bearbeiten' : '➕ Neues Fach anlegen';
    const submitButtonText = fachId ? '💾 Änderungen speichern' : '💾 Fach speichern';
    
    modal.innerHTML = `
        <div class="modal-content-styled" style="position: relative; width: 90%; max-width: 600px; margin: auto; max-height: 90vh; overflow-y: auto;">
            <button onclick="closeFachModal()" style="position: absolute; top: 15px; right: 15px; background: white; border: 2px solid #E0E0E0; color: #999; width: 35px; height: 35px; border-radius: 50%; font-size: 24px; cursor: pointer;">×</button>
            
            <div style="margin-bottom: 25px;">
                <h3 class="modal-header-title">${modalTitle}</h3>
            </div>
            
            <form id="fach-form" onsubmit="saveFach(event, ${fachId || 'null'})">
                <input type="hidden" id="edit-fach-id" value="${fachId || ''}">
                
                <div style="margin-bottom: 20px;">
                    <label class="form-label">Fachbezeichnung:</label>
                    <input type="text" id="fach-name" name="name" required class="form-input" placeholder="z.B. Mathematik, Deutsch, Englisch...">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label class="form-label">Bewertungskategorien:</label>
                    <div id="kategorien-container"></div>
                </div>
                
                <button type="button" onclick="addKategorie()" class="btn-add-category" style="margin-bottom: 20px;">
                    + Kategorie hinzufügen
                </button>
                
                <button type="submit" class="btn-primary-blue" style="width: 100%;">
                    ${submitButtonText}
                </button>
            </form>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    if (!fachId) {
        setTimeout(() => addKategorie(), 100);
    }
}

function closeFachModal() {
    const modal = document.getElementById('fach-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function addKategorie() {
    addKategorieRow('', 1);
}

function addKategorieRow(name = '', gewichtung = 1) {
    const container = document.getElementById('kategorien-container');
    if (!container) return;
    
    const row = document.createElement('div');
    row.className = 'kategorie-input-row';
    
    row.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" name="kategorie[]" placeholder="z.B. Klassenarbeit, Test, Mündlich..." value="${name}" class="form-input" style="flex: 1; padding: 10px;" required>
            
            <div class="weight-container" style="display: flex; align-items: center; gap: 8px; background: white; padding: 6px 12px; border-radius: 8px; border: 2px solid #E0E0E0;">
                <span style="color: #666; font-weight: 600; font-size: 14px;">Gewichtung:</span>
                <button type="button" class="weight-control-btn" onclick="decreaseWeight(this)">−</button>
                <span class="weight-display">${gewichtung}</span>
                <input type="hidden" name="gewichtung[]" value="${gewichtung}">
                <button type="button" class="weight-control-btn" onclick="increaseWeight(this)">+</button>
            </div>
            
            <button type="button" onclick="removeKategorie(this)" class="btn-remove-category">×</button>
        </div>
    `;
    
    container.appendChild(row);
}

function increaseWeight(btn) {
    const container = btn.closest('.weight-container');
    const weightDisplay = container.querySelector('.weight-display');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    
    if (weightDisplay && hiddenInput) {
        let value = parseInt(hiddenInput.value) || 1;
        if (value < 10) {
            value++;
            hiddenInput.value = value;
            weightDisplay.textContent = value;
        }
    }
}

function decreaseWeight(btn) {
    const container = btn.closest('.weight-container');
    const weightDisplay = container.querySelector('.weight-display');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    
    if (weightDisplay && hiddenInput) {
        let value = parseInt(hiddenInput.value) || 1;
        if (value > 1) {
            value--;
            hiddenInput.value = value;
            weightDisplay.textContent = value;
        }
    }
}

function removeKategorie(btn) {
    const rows = document.querySelectorAll('.kategorie-input-row');
    if (rows.length > 1) {
        btn.closest('.kategorie-input-row').remove();
    } else {
        showNotification('Mindestens eine Kategorie ist erforderlich!', 'error');
    }
}

function saveFach(event, fachId = null) {
    event.preventDefault();
    
    const hiddenFachId = document.getElementById('edit-fach-id');
    if (hiddenFachId && hiddenFachId.value) {
        fachId = parseInt(hiddenFachId.value) || null;
    }
    
    const formData = new FormData(event.target);
    const kategorien = [];
    const katNames = formData.getAll('kategorie[]');
    const katGewichtungen = formData.getAll('gewichtung[]');
    
    for (let i = 0; i < katNames.length; i++) {
        if (katNames[i].trim()) {
            kategorien.push({
                name: katNames[i].trim(),
                gewichtung: parseInt(katGewichtungen[i]) || 1
            });
        }
    }
    
    if (kategorien.length === 0) {
        showNotification('Bitte mindestens eine Kategorie anlegen!', 'error');
        return;
    }
    
    const fachName = formData.get('name');
    if (!fachName || !fachName.trim()) {
        showNotification('Bitte einen Fachnamen eingeben!', 'error');
        return;
    }
    
    const data = {
        name: fachName.trim(),
        kategorien: kategorien
    };
    
    const action = fachId ? 'update' : 'create';
    const url = 'api/faecher.php?action=' + action + (fachId ? '&id=' + fachId : '');
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeFachModal();
            loadFaecherList();
            const message = fachId ? '✅ Fach erfolgreich aktualisiert!' : '✅ Fach erfolgreich angelegt!';
            showNotification(message, 'success');
        } else {
            throw new Error(result.message || 'Unbekannter Fehler');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showNotification('Fehler beim Speichern: ' + error.message, 'error');
    });
}

function deleteFach(fachId) {
    if (!confirm('⚠️ Wirklich löschen?\n\nDieses Fach und alle zugehörigen Daten werden unwiderruflich gelöscht!')) {
        return;
    }
    
    fetch('api/faecher.php?action=delete&id=' + fachId, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadFaecherList();
            showNotification('✅ Fach erfolgreich gelöscht!', 'success');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showNotification('Fehler beim Löschen', 'error');
    });
}

function duplicateFach(fachId) {
    fetch('api/faecher.php?action=get&id=' + fachId)
        .then(response => response.json())
        .then(fach => {
            const newName = prompt('📋 Name für die Kopie:', fach.name + ' (Kopie)');
            if (!newName) return;
            
            const data = {
                name: newName,
                kategorien: fach.kategorien
            };
            
            return fetch('api/faecher.php?action=create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadFaecherList();
                showNotification('✅ Fach erfolgreich dupliziert!', 'success');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            showNotification('Fehler beim Duplizieren', 'error');
        });
}

function showNotification(message, type = 'info') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.className = 'notification ' + type;
    notification.textContent = message;
    notification.style.cursor = 'pointer';
    
    notification.onclick = () => notification.remove();
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Exportiere alle Funktionen global
window.faecherInit = faecherInit;
window.loadFaecherList = loadFaecherList;
window.createFachCard = createFachCard;
window.showAddFachModal = showAddFachModal;
window.editFach = editFach;
window.openFachModal = openFachModal;
window.closeFachModal = closeFachModal;
window.addKategorie = addKategorie;
window.addKategorieRow = addKategorieRow;
window.increaseWeight = increaseWeight;
window.decreaseWeight = decreaseWeight;
window.removeKategorie = removeKategorie;
window.saveFach = saveFach;
window.deleteFach = deleteFach;
window.duplicateFach = duplicateFach;
window.showNotification = showNotification;

console.log('✅ faecher.js erfolgreich geladen - Alle Funktionen verfügbar');