// Klassen Modul
function klassenInit() {
    const moduleContent = document.getElementById('module-content');
    
    // Lade Klassen aus der Datenbank
    loadKlassen();
}

function loadKlassen() {
    const moduleContent = document.getElementById('module-content');
    
    moduleContent.innerHTML = `
        <div class="content-header">
            <h2 class="content-title">Klassenverwaltung</h2>
            <button class="btn-primary" onclick="openKlasseModal()">
                <span>➕</span> Neue Klasse
            </button>
        </div>
        
        <div style="background: #E3F2FD; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <p style="margin: 0; color: #333;">
                <strong>📊 Schülersortierung (gilt für alle Klassen):</strong>
            </p>
            <div style="display: flex; gap: 20px; margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    1. Sortierung:
                    <select id="sort1" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">
                        <option value="nachname">Nachname</option>
                        <option value="vorname">Vorname</option>
                        <option value="keine">Keine</option>
                    </select>
                </label>
                <label style="display: flex; align-items: center; gap: 5px;">
                    2. Sortierung:
                    <select id="sort2" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">
                        <option value="vorname">Vorname</option>
                        <option value="nachname">Nachname</option>
                        <option value="keine">Keine</option>
                    </select>
                </label>
                <label style="display: flex; align-items: center; gap: 5px;">
                    3. Sortierung:
                    <select id="sort3" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">
                        <option value="keine">Keine</option>
                        <option value="nachname">Nachname</option>
                        <option value="vorname">Vorname</option>
                    </select>
                </label>
            </div>
        </div>
        
        <div class="cards-grid" id="klassen-list">
            <!-- Klassen werden hier dynamisch geladen -->
        </div>
        
        <div class="info-box">
            <p>📁 <strong>CSV-Import:</strong> Laden Sie Schülerlisten im CSV-Format hoch. Sie können zwischen verschiedenen Formaten wählen (Vorname/Nachname oder Nachname/Vorname).</p>
        </div>
    `;
    
    // Lade Klassen via AJAX
    fetch('api/klassen.php?action=list')
        .then(response => response.json())
        .then(data => {
            const klassenList = document.getElementById('klassen-list');
            
            if (data.length === 0) {
                klassenList.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                        <p style="font-size: 18px; margin-bottom: 10px;">Noch keine Klassen angelegt</p>
                        <p>Klicken Sie auf "Neue Klasse" um zu beginnen.</p>
                    </div>
                `;
            } else {
                klassenList.innerHTML = data.map(klasse => `
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">${klasse.name}</h3>
                            <div class="card-actions">
                                <button class="btn-icon" onclick="openKlasseDetails(${klasse.id})" title="Details">
                                    <span>📋</span>
                                </button>
                                <button class="btn-icon" onclick="editKlasse(${klasse.id})" title="Bearbeiten">
                                    <span>✏️</span>
                                </button>
                                <button class="btn-icon" onclick="deleteKlasse(${klasse.id})" title="Löschen">
                                    <span>🗑️</span>
                                </button>
                            </div>
                        </div>
                        <div class="card-info">
                            <p>👥 ${klasse.schueler_count || 0} Schüler</p>
                            <p>📚 ${klasse.faecher_count || 0} Fächer</p>
                            <p>📅 ${klasse.schuljahr || '2025/2026'}</p>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Klassen:', error);
            // Fallback für Demo-Zwecke
            const klassenList = document.getElementById('klassen-list');
            klassenList.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">7a</h3>
                        <div class="card-actions">
                            <button class="btn-icon" onclick="openKlasseDetails(1)" title="Details">
                                <span>📋</span>
                            </button>
                            <button class="btn-icon" onclick="editKlasse(1)" title="Bearbeiten">
                                <span>✏️</span>
                            </button>
                            <button class="btn-icon" onclick="deleteKlasse(1)" title="Löschen">
                                <span>🗑️</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-info">
                        <p>👥 28 Schüler</p>
                        <p>📚 0 Fächer</p>
                        <p>📅 2025/2026</p>
                    </div>
                </div>
            `;
        });
}

function openKlasseModal(klasseId = null) {
    // Erstelle oder aktualisiere Modal
    let modal = document.getElementById('klasse-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'klasse-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">${klasseId ? 'Klasse bearbeiten' : 'Neue Klasse anlegen'}</h3>
                <button class="btn-close" onclick="closeKlasseModal()">×</button>
            </div>
            
            <form id="klasse-form" onsubmit="saveKlasse(event, ${klasseId})">
                <div class="form-group">
                    <label for="klasse-name">Klassenbezeichnung:</label>
                    <input type="text" id="klasse-name" name="name" required placeholder="z.B. 7a">
                </div>
                
                <div class="form-group">
                    <label for="schuljahr">Schuljahr:</label>
                    <input type="text" id="schuljahr" name="schuljahr" value="2025/2026" required>
                </div>
                
                <div class="form-group">
                    <label>Fächer:</label>
                    <div id="faecher-checkboxes" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="faecher[]" value="1"> Mathe
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="faecher[]" value="2"> Physik
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Schüler hinzufügen:</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button type="button" onclick="showManualEntry()" style="flex: 1; padding: 10px; background: #FF6B6B; color: white; border: none; border-radius: 8px; cursor: pointer;">
                            <span>✍️</span> Manuell eingeben
                        </button>
                        <button type="button" onclick="showCSVImport()" style="flex: 1; padding: 10px; background: #FFA726; color: white; border: none; border-radius: 8px; cursor: pointer;">
                            <span>📁</span> CSV importieren
                        </button>
                    </div>
                    
                    <div id="schueler-entry" style="display: none;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #4A90E2; color: white;">
                                <tr>
                                    <th style="padding: 10px; text-align: left;">Nachname</th>
                                    <th style="padding: 10px; text-align: left;">Vorname</th>
                                    <th style="padding: 10px; text-align: left;">Geschlecht</th>
                                    <th style="padding: 10px; text-align: center;">Aktion</th>
                                </tr>
                            </thead>
                            <tbody id="schueler-list">
                                <tr>
                                    <td style="padding: 5px;"><input type="text" name="nachname[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
                                    <td style="padding: 5px;"><input type="text" name="vorname[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
                                    <td style="padding: 5px;">
                                        <select name="geschlecht[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;">
                                            <option value="">Wählen</option>
                                            <option value="m">Männlich</option>
                                            <option value="w">Weiblich</option>
                                            <option value="d">Divers</option>
                                        </select>
                                    </td>
                                    <td style="padding: 5px; text-align: center;">
                                        <button type="button" onclick="removeSchueler(this)" style="background: #F44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">🗑️</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" onclick="addSchueler()" style="margin-top: 10px; padding: 8px 15px; background: #E3F2FD; border: 1px solid #4A90E2; color: #4A90E2; border-radius: 8px; cursor: pointer;">
                            + Schüler hinzufügen
                        </button>
                    </div>
                    
                    <div id="csv-import" style="display: none;">
                        <input type="file" id="csv-file" accept=".csv" style="margin-bottom: 10px;">
                        <p style="color: #666; font-size: 14px;">Format: Nachname,Vorname oder Vorname,Nachname (eine Zeile pro Schüler)</p>
                    </div>
                </div>
                
                <button type="submit" class="btn-save" style="margin-top: 20px;">
                    Klasse aktualisieren
                </button>
            </form>
        </div>
    `;
    
    modal.classList.add('active');
    
    // Wenn Edit-Modus, lade Klassendaten
    if (klasseId) {
        loadKlasseData(klasseId);
    }
}

function closeKlasseModal() {
    const modal = document.getElementById('klasse-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function showManualEntry() {
    document.getElementById('schueler-entry').style.display = 'block';
    document.getElementById('csv-import').style.display = 'none';
}

function showCSVImport() {
    document.getElementById('schueler-entry').style.display = 'none';
    document.getElementById('csv-import').style.display = 'block';
}

function addSchueler() {
    const tbody = document.getElementById('schueler-list');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td style="padding: 5px;"><input type="text" name="nachname[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
        <td style="padding: 5px;"><input type="text" name="vorname[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
        <td style="padding: 5px;">
            <select name="geschlecht[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;">
                <option value="">Wählen</option>
                <option value="m">Männlich</option>
                <option value="w">Weiblich</option>
                <option value="d">Divers</option>
            </select>
        </td>
        <td style="padding: 5px; text-align: center;">
            <button type="button" onclick="removeSchueler(this)" style="background: #F44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">🗑️</button>
        </td>
    `;
    tbody.appendChild(newRow);
}

function removeSchueler(button) {
    const row = button.closest('tr');
    row.remove();
}

function saveKlasse(event, klasseId = null) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        name: formData.get('name'),
        schuljahr: formData.get('schuljahr'),
        faecher: formData.getAll('faecher[]'),
        schueler: []
    };
    
    // Sammle Schülerdaten
    const nachnamen = formData.getAll('nachname[]');
    const vornamen = formData.getAll('vorname[]');
    const geschlechter = formData.getAll('geschlecht[]');
    
    for (let i = 0; i < nachnamen.length; i++) {
        if (nachnamen[i] || vornamen[i]) {
            data.schueler.push({
                nachname: nachnamen[i],
                vorname: vornamen[i],
                geschlecht: geschlechter[i]
            });
        }
    }
    
    // CSV Import verarbeiten
    const csvFile = document.getElementById('csv-file').files[0];
    if (csvFile) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const csvData = e.target.result;
            const lines = csvData.split('\n');
            lines.forEach(line => {
                const parts = line.split(',');
                if (parts.length >= 2) {
                    data.schueler.push({
                        nachname: parts[0].trim(),
                        vorname: parts[1].trim(),
                        geschlecht: ''
                    });
                }
            });
            
            // Sende Daten an Server
            sendKlasseData(data, klasseId);
        };
        reader.readAsText(csvFile);
    } else {
        // Sende Daten an Server
        sendKlasseData(data, klasseId);
    }
}

function sendKlasseData(data, klasseId) {
    const action = klasseId ? 'update' : 'create';
    fetch(`api/klassen.php?action=${action}${klasseId ? '&id=' + klasseId : ''}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeKlasseModal();
            loadKlassen();
        } else {
            alert('Fehler beim Speichern: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        // Für Demo-Zwecke
        closeKlasseModal();
        loadKlassen();
    });
}

function editKlasse(klasseId) {
    openKlasseModal(klasseId);
}

function deleteKlasse(klasseId) {
    if (confirm('Möchten Sie diese Klasse wirklich löschen? Alle Schülerdaten und Noten werden ebenfalls gelöscht.')) {
        fetch(`api/klassen.php?action=delete&id=${klasseId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadKlassen();
            } else {
                alert('Fehler beim Löschen: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            // Für Demo-Zwecke
            loadKlassen();
        });
    }
}

function openKlasseDetails(klasseId) {
    // Zeige Details zur Klasse
    alert('Klassendetails für Klasse ID: ' + klasseId + '\n\nDiese Funktion zeigt eine detaillierte Ansicht der Klasse mit allen Schülern und deren Noten.');
}

function loadKlasseData(klasseId) {
    // Lade Klassendaten für Bearbeitung
    fetch(`api/klassen.php?action=get&id=${klasseId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('klasse-name').value = data.name;
            document.getElementById('schuljahr').value = data.schuljahr;
            
            // Setze Fächer-Checkboxen
            if (data.faecher) {
                data.faecher.forEach(fachId => {
                    const checkbox = document.querySelector(`input[name="faecher[]"][value="${fachId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Füge Schüler hinzu
            if (data.schueler && data.schueler.length > 0) {
                showManualEntry();
                const tbody = document.getElementById('schueler-list');
                tbody.innerHTML = ''; // Leere erst
                
                data.schueler.forEach(schueler => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="padding: 5px;"><input type="text" name="nachname[]" value="${schueler.nachname}" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
                        <td style="padding: 5px;"><input type="text" name="vorname[]" value="${schueler.vorname}" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>
                        <td style="padding: 5px;">
                            <select name="geschlecht[]" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;">
                                <option value="">Wählen</option>
                                <option value="m" ${schueler.geschlecht === 'm' ? 'selected' : ''}>Männlich</option>
                                <option value="w" ${schueler.geschlecht === 'w' ? 'selected' : ''}>Weiblich</option>
                                <option value="d" ${schueler.geschlecht === 'd' ? 'selected' : ''}>Divers</option>
                            </select>
                        </td>
                        <td style="padding: 5px; text-align: center;">
                            <button type="button" onclick="removeSchueler(this)" style="background: #F44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">🗑️</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Klassendaten:', error);
        });
}