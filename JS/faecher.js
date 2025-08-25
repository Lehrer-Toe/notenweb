// Fächer Modul
function faecherInit() {
    const moduleContent = document.getElementById('module-content');
    
    // Lade Fächer aus der Datenbank
    loadFaecher();
}

function loadFaecher() {
    const moduleContent = document.getElementById('module-content');
    
    moduleContent.innerHTML = `
        <div class="content-header">
            <h2 class="content-title">Fächerverwaltung</h2>
            <button class="btn-primary" onclick="openFachModal()">
                <span>➕</span> Neues Fach
            </button>
        </div>
        
        <div class="cards-grid" id="faecher-list">
            <!-- Fächer werden hier dynamisch geladen -->
        </div>
        
        <div class="info-box">
            <p>🎯 <strong>Erste Schritte:</strong> Legen Sie zunächst die Fächer an, die Sie unterrichten. Jedes Fach benötigt mindestens eine Bewertungskategorie (z.B. "Klassenarbeit", "Test", "Mündliche Mitarbeit").</p>
        </div>
    `;
    
    // Lade Fächer via AJAX
    fetch('api/faecher.php?action=list')
        .then(response => response.json())
        .then(data => {
            const faecherList = document.getElementById('faecher-list');
            
            if (data.length === 0) {
                faecherList.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                        <p style="font-size: 18px; margin-bottom: 10px;">Noch keine Fächer angelegt</p>
                        <p>Klicken Sie auf "Neues Fach" um zu beginnen.</p>
                    </div>
                `;
            } else {
                faecherList.innerHTML = data.map(fach => `
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">${fach.name}</h3>
                            <div class="card-actions">
                                <button class="btn-icon" onclick="editFach(${fach.id})" title="Bearbeiten">
                                    <span>✏️</span>
                                </button>
                                <button class="btn-icon" onclick="deleteFach(${fach.id})" title="Löschen">
                                    <span>🗑️</span>
                                </button>
                            </div>
                        </div>
                        <div class="card-info">
                            <p>${fach.kategorien || 'KA'}</p>
                            <p>Gewichtung: ${fach.gewichtung || 1}</p>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Fächer:', error);
            // Fallback für Demo-Zwecke
            const faecherList = document.getElementById('faecher-list');
            faecherList.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Mathe</h3>
                        <div class="card-actions">
                            <button class="btn-icon" onclick="editFach(1)" title="Bearbeiten">
                                <span>✏️</span>
                            </button>
                            <button class="btn-icon" onclick="deleteFach(1)" title="Löschen">
                                <span>🗑️</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-info">
                        <p>ka</p>
                        <p>Gewichtung: 1</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Physik</h3>
                        <div class="card-actions">
                            <button class="btn-icon" onclick="editFach(2)" title="Bearbeiten">
                                <span>✏️</span>
                            </button>
                            <button class="btn-icon" onclick="deleteFach(2)" title="Löschen">
                                <span>🗑️</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-info">
                        <p>KA</p>
                        <p>Gewichtung: 5</p>
                    </div>
                </div>
            `;
        });
}

function openFachModal(fachId = null) {
    // Erstelle oder aktualisiere Modal
    let modal = document.getElementById('fach-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'fach-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">${fachId ? 'Fach bearbeiten' : 'Neues Fach anlegen'}</h3>
                <button class="btn-close" onclick="closeFachModal()">×</button>
            </div>
            
            <form id="fach-form" onsubmit="saveFach(event, ${fachId})">
                <div class="form-group">
                    <label for="fach-name">Fachbezeichnung:</label>
                    <input type="text" id="fach-name" name="name" required placeholder="z.B. Mathematik">
                </div>
                
                <div id="kategorien-container">
                    <label>Bewertungskategorien:</label>
                    <div class="kategorie-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" name="kategorie[]" placeholder="z.B. Klassenarbeit" style="flex: 1;">
                        <label style="display: flex; align-items: center; gap: 5px;">
                            Gewichtung:
                            <input type="number" name="gewichtung[]" value="1" min="1" max="10" style="width: 60px;">
                        </label>
                        <button type="button" class="btn-icon" onclick="removeKategorie(this)" style="background: #FEE;">
                            <span>×</span>
                        </button>
                    </div>
                </div>
                
                <button type="button" onclick="addKategorie()" style="margin-top: 10px; padding: 8px 15px; background: #E3F2FD; border: 1px solid #4A90E2; color: #4A90E2; border-radius: 8px; cursor: pointer;">
                    + Kategorie hinzufügen
                </button>
                
                <button type="submit" class="btn-save" style="margin-top: 20px;">
                    Fach speichern
                </button>
            </form>
        </div>
    `;
    
    modal.classList.add('active');
    
    // Wenn Edit-Modus, lade Fachdaten
    if (fachId) {
        loadFachData(fachId);
    }
}

function closeFachModal() {
    const modal = document.getElementById('fach-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function addKategorie() {
    const container = document.getElementById('kategorien-container');
    const newRow = document.createElement('div');
    newRow.className = 'kategorie-row';
    newRow.style = 'display: flex; gap: 10px; margin-bottom: 10px;';
    newRow.innerHTML = `
        <input type="text" name="kategorie[]" placeholder="z.B. Test" style="flex: 1;">
        <label style="display: flex; align-items: center; gap: 5px;">
            Gewichtung:
            <input type="number" name="gewichtung[]" value="1" min="1" max="10" style="width: 60px;">
        </label>
        <button type="button" class="btn-icon" onclick="removeKategorie(this)" style="background: #FEE;">
            <span>×</span>
        </button>
    `;
    container.appendChild(newRow);
}

function removeKategorie(button) {
    const row = button.closest('.kategorie-row');
    if (document.querySelectorAll('.kategorie-row').length > 1) {
        row.remove();
    } else {
        alert('Es muss mindestens eine Bewertungskategorie vorhanden sein.');
    }
}

function saveFach(event, fachId = null) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        name: formData.get('name'),
        kategorien: formData.getAll('kategorie[]'),
        gewichtungen: formData.getAll('gewichtung[]')
    };
    
    // API-Aufruf zum Speichern
    const action = fachId ? 'update' : 'create';
    fetch(`api/faecher.php?action=${action}${fachId ? '&id=' + fachId : ''}`, {
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
            loadFaecher();
        } else {
            alert('Fehler beim Speichern: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        // Für Demo-Zwecke
        closeFachModal();
        loadFaecher();
    });
}

function editFach(fachId) {
    openFachModal(fachId);
}

function deleteFach(fachId) {
    if (confirm('Möchten Sie dieses Fach wirklich löschen? Alle zugehörigen Noten werden ebenfalls gelöscht.')) {
        fetch(`api/faecher.php?action=delete&id=${fachId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadFaecher();
            } else {
                alert('Fehler beim Löschen: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            // Für Demo-Zwecke
            loadFaecher();
        });
    }
}

function loadFachData(fachId) {
    // Lade Fachdaten für Bearbeitung
    fetch(`api/faecher.php?action=get&id=${fachId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('fach-name').value = data.name;
            // Füge Kategorien hinzu
            const container = document.getElementById('kategorien-container');
            // Lösche alle außer der ersten Kategorie
            const rows = container.querySelectorAll('.kategorie-row');
            for (let i = 1; i < rows.length; i++) {
                rows[i].remove();
            }
            // Fülle erste Kategorie
            if (data.kategorien && data.kategorien.length > 0) {
                rows[0].querySelector('input[name="kategorie[]"]').value = data.kategorien[0].name;
                rows[0].querySelector('input[name="gewichtung[]"]').value = data.kategorien[0].gewichtung;
                // Füge weitere Kategorien hinzu
                for (let i = 1; i < data.kategorien.length; i++) {
                    addKategorie();
                    const newRows = container.querySelectorAll('.kategorie-row');
                    const lastRow = newRows[newRows.length - 1];
                    lastRow.querySelector('input[name="kategorie[]"]').value = data.kategorien[i].name;
                    lastRow.querySelector('input[name="gewichtung[]"]').value = data.kategorien[i].gewichtung;
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Fachdaten:', error);
        });
}