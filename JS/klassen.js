// Klassen Modul - SQLite-basierte Verwaltung
function klassenInit() {
    const moduleContent = document.getElementById('module-content');
    loadKlassen();
}

// Globale Variablen für Klassenverwaltung
window.klassenData = {
    currentClassId: null,
    csvData: null,
    manualStudents: [],
    globalSorting: {
        primary: 'nachname',
        secondary: 'vorname',
        tertiary: 'geschlecht'
    }
};

// Lade alle Klassen
function loadKlassen() {
    const moduleContent = document.getElementById('module-content');
    
    moduleContent.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner"></div><p>Lade Klassen...</p></div>';
    
    fetch('api/klassen.php?action=list')
        .then(function(response) {
            if (!response.ok) throw new Error('Netzwerkfehler');
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                throw new Error(data.error);
            }
            displayKlassen(data.klassen || []);
        })
        .catch(function(error) {
            console.error('Fehler beim Laden der Klassen:', error);
            moduleContent.innerHTML = '<div class="error-state">Fehler beim Laden der Klassen: ' + error.message + '</div>';
        });
}

// Zeige Klassenliste an
function displayKlassen(klassen) {
    const moduleContent = document.getElementById('module-content');
    
    var html = '<div class="content-header">' +
        '<h2 class="content-title">Klassenverwaltung</h2>' +
        '<button class="btn-primary" onclick="openKlasseModal()">' +
        '<span>➕</span> Neue Klasse' +
        '</button>' +
        '</div>';
    
    // Sortierungsoptionen
    html += '<div style="background: #E3F2FD; padding: 15px; border-radius: 10px; margin-bottom: 20px;">' +
        '<p style="margin: 0 0 10px 0; color: #333; font-weight: 600;">📊 Globale Schülersortierung:</p>' +
        '<div style="display: flex; gap: 20px; flex-wrap: wrap;">' +
        '<label style="display: flex; align-items: center; gap: 5px;">' +
        '1. Sortierung: ' +
        '<select id="global-sort-1" onchange="updateGlobalSorting()" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">' +
        '<option value="nachname">Nachname</option>' +
        '<option value="vorname">Vorname</option>' +
        '<option value="geschlecht">Geschlecht</option>' +
        '<option value="keine">Keine</option>' +
        '</select>' +
        '</label>' +
        '<label style="display: flex; align-items: center; gap: 5px;">' +
        '2. Sortierung: ' +
        '<select id="global-sort-2" onchange="updateGlobalSorting()" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">' +
        '<option value="vorname">Vorname</option>' +
        '<option value="nachname">Nachname</option>' +
        '<option value="geschlecht">Geschlecht</option>' +
        '<option value="keine">Keine</option>' +
        '</select>' +
        '</label>' +
        '<label style="display: flex; align-items: center; gap: 5px;">' +
        '3. Sortierung: ' +
        '<select id="global-sort-3" onchange="updateGlobalSorting()" style="padding: 5px; border-radius: 5px; border: 1px solid #DDD;">' +
        '<option value="geschlecht">Geschlecht</option>' +
        '<option value="nachname">Nachname</option>' +
        '<option value="vorname">Vorname</option>' +
        '<option value="keine">Keine</option>' +
        '</select>' +
        '</label>' +
        '</div>' +
        '</div>';
    
    // Klassenkarten
    html += '<div class="cards-grid" id="klassen-list">';
    
    if (klassen.length === 0) {
        html += '<div style="grid-column: 1/-1; text-align: center; padding: 40px; background: #FFF; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">' +
            '<p style="font-size: 18px; margin-bottom: 10px; color: #666;">Noch keine Klassen angelegt</p>' +
            '<p style="color: #999;">Klicken Sie auf "Neue Klasse" um zu beginnen.</p>' +
            '</div>';
    } else {
        klassen.forEach(function(klasse) {
            html += createKlasseCard(klasse);
        });
    }
    
    html += '</div>';
    
    // Info-Box
    html += '<div class="info-box" style="background: #E3F2FD; border-left: 4px solid #4A90E2; padding: 15px; border-radius: 8px; margin-top: 20px;">' +
        '<p style="margin: 0;">📁 <strong>CSV-Import:</strong> Sie können Schülerlisten als CSV-Datei importieren. ' +
        'Unterstützte Formate: UTF-8, Windows-1252, ISO-8859-1. Umlaute werden automatisch erkannt.</p>' +
        '</div>';
    
    moduleContent.innerHTML = html;
    
    // Lade gespeicherte Sortierungseinstellungen
    loadSortingSettings();
}

// Erstelle Klassenkarte
function createKlasseCard(klasse) {
    return '<div class="card" style="background: #FF9A56; color: white; border: none; box-shadow: 0 4px 15px rgba(255, 154, 86, 0.3);">' +
        '<div class="card-header">' +
        '<h3 class="card-title" style="color: white;">' + escapeHtml(klasse.name) + '</h3>' +
        '<div class="card-actions">' +
        '<button class="btn-icon" onclick="viewKlasseDetails(' + klasse.id + ')" title="Details" style="background: rgba(255,255,255,0.2);">' +
        '<span>📋</span>' +
        '</button>' +
        '<button class="btn-icon" onclick="editKlasse(' + klasse.id + ')" title="Bearbeiten" style="background: rgba(255,255,255,0.2);">' +
        '<span>✏️</span>' +
        '</button>' +
        '<button class="btn-icon" onclick="deleteKlasse(' + klasse.id + ')" title="Löschen" style="background: rgba(255,255,255,0.2);">' +
        '<span>🗑️</span>' +
        '</button>' +
        '</div>' +
        '</div>' +
        '<div class="card-info" style="color: rgba(255,255,255,0.95);">' +
        '<p>👥 ' + (klasse.schueler_count || 0) + ' Schüler</p>' +
        '<p>📚 ' + (klasse.faecher_count || 0) + ' Fächer</p>' +
        '<p>📅 ' + escapeHtml(klasse.schuljahr || '2025/2026') + '</p>' +
        '</div>' +
        '</div>';
}

// Modal für neue/bearbeitete Klasse öffnen
function openKlasseModal(klasseId) {
    window.klassenData.currentClassId = klasseId || null;
    window.klassenData.manualStudents = [];
    window.klassenData.csvData = null;
    
    var modalTitle = klasseId ? 'Klasse bearbeiten' : 'Neue Klasse anlegen';
    
    var modal = document.getElementById('klasse-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'klasse-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }
    
    var modalContent = '<div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">' +
        '<div class="modal-header">' +
        '<h3 class="modal-title">' + modalTitle + '</h3>' +
        '<button class="btn-close" onclick="closeKlasseModal()">×</button>' +
        '</div>' +
        '<form id="klasse-form" onsubmit="saveKlasse(event)">' +
        '<div class="form-group">' +
        '<label for="klasse-name">Klassenbezeichnung:</label>' +
        '<input type="text" id="klasse-name" name="name" required placeholder="z.B. 7a" style="width: 100%; padding: 10px; border: 2px solid #E0E0E0; border-radius: 8px;">' +
        '</div>' +
        '<div class="form-group">' +
        '<label for="schuljahr">Schuljahr:</label>' +
        '<input type="text" id="schuljahr" name="schuljahr" value="2025/2026" required style="width: 100%; padding: 10px; border: 2px solid #E0E0E0; border-radius: 8px;">' +
        '</div>';
    
    // Fächer-Auswahl
    modalContent += '<div class="form-group">' +
        '<label>Fächer zuweisen:</label>' +
        '<div id="faecher-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; padding: 10px; background: #F5F5F5; border-radius: 8px;">' +
        '<p style="grid-column: 1/-1; color: #666; text-align: center;">Lade Fächer...</p>' +
        '</div>' +
        '</div>';
    
    // Schüler-Bereich
    modalContent += '<div class="form-group">' +
        '<label>Schüler verwalten:</label>' +
        '<div style="display: flex; gap: 10px; margin-bottom: 15px;">' +
        '<button type="button" onclick="showManualEntry()" style="flex: 1; padding: 10px; background: #4A90E2; color: white; border: none; border-radius: 8px; cursor: pointer;">' +
        '✍️ Manuell eingeben' +
        '</button>' +
        '<button type="button" onclick="showCSVImport()" style="flex: 1; padding: 10px; background: #FF9A56; color: white; border: none; border-radius: 8px; cursor: pointer;">' +
        '📁 CSV importieren' +
        '</button>' +
        '</div>';
    
    // Manuelle Eingabe
    modalContent += '<div id="schueler-entry" style="display: none;">' +
        '<table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;">' +
        '<thead style="background: #4A90E2; color: white;">' +
        '<tr>' +
        '<th style="padding: 10px; text-align: left;">Nachname</th>' +
        '<th style="padding: 10px; text-align: left;">Vorname</th>' +
        '<th style="padding: 10px; text-align: left;">Geschlecht</th>' +
        '<th style="padding: 10px; text-align: center; width: 80px;">Aktion</th>' +
        '</tr>' +
        '</thead>' +
        '<tbody id="schueler-list"></tbody>' +
        '</table>' +
        '<button type="button" onclick="addSchuelerRow()" style="margin-top: 10px; padding: 8px 15px; background: #E3F2FD; border: 1px solid #4A90E2; color: #4A90E2; border-radius: 8px; cursor: pointer;">' +
        '+ Schüler hinzufügen' +
        '</button>' +
        '</div>';
    
    // CSV Import
    modalContent += '<div id="csv-import" style="display: none;">' +
        '<div style="border: 2px dashed #4A90E2; border-radius: 8px; padding: 20px; text-align: center; background: #F8F9FA;">' +
        '<input type="file" id="csv-file" accept=".csv,.txt" onchange="handleCSVFile(event)" style="display: none;">' +
        '<label for="csv-file" style="cursor: pointer; color: #4A90E2; font-weight: 500;">' +
        '📁 CSV-Datei auswählen oder hier ablegen' +
        '</label>' +
        '<p style="color: #666; font-size: 14px; margin-top: 10px;">Format: Nachname,Vorname,Geschlecht (optional)</p>' +
        '</div>' +
        '<div id="csv-format-selection" style="display: none; margin-top: 15px;">' +
        '<label>CSV-Format:</label>' +
        '<select id="csv-format" style="width: 100%; padding: 8px; border: 1px solid #DDD; border-radius: 4px;">' +
        '<option value="nachname-vorname">Nachname, Vorname</option>' +
        '<option value="vorname-nachname">Vorname, Nachname</option>' +
        '</select>' +
        '</div>' +
        '<div id="csv-preview" style="display: none; margin-top: 15px;">' +
        '<h4>Vorschau (erste 5 Zeilen):</h4>' +
        '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">' +
        '<thead style="background: #F5F5F5;">' +
        '<tr><th style="padding: 5px; border: 1px solid #DDD;">Nachname</th><th style="padding: 5px; border: 1px solid #DDD;">Vorname</th><th style="padding: 5px; border: 1px solid #DDD;">Geschlecht</th></tr>' +
        '</thead>' +
        '<tbody id="csv-preview-body"></tbody>' +
        '</table>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    modalContent += '<button type="submit" class="btn-save" style="width: 100%; margin-top: 20px; padding: 12px; background: #4A90E2; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 500; cursor: pointer;">' +
        'Klasse speichern' +
        '</button>' +
        '</form>' +
        '</div>';
    
    modal.innerHTML = modalContent;
    modal.classList.add('active');
    
    // Bei Bearbeitung: Lade Klassendaten (das lädt auch die Fächer)
    if (klasseId) {
        loadKlasseData(klasseId);
    } else {
        // Bei neuer Klasse: Lade Fächer ohne Vorauswahl
        loadFaecherForSelection([]);
    }
}

// Schließe Modal
function closeKlasseModal() {
    var modal = document.getElementById('klasse-modal');
    if (modal) {
        modal.classList.remove('active');
        window.klassenData.currentClassId = null;
        window.klassenData.manualStudents = [];
        window.klassenData.csvData = null;
    }
}

// Zeige manuelle Eingabe
function showManualEntry() {
    document.getElementById('schueler-entry').style.display = 'block';
    document.getElementById('csv-import').style.display = 'none';
    window.klassenData.csvData = null;
    
    // Wenn noch keine Schüler, füge eine leere Zeile hinzu
    if (window.klassenData.manualStudents.length === 0) {
        addSchuelerRow();
    }
}

// Zeige CSV-Import
function showCSVImport() {
    document.getElementById('schueler-entry').style.display = 'none';
    document.getElementById('csv-import').style.display = 'block';
    window.klassenData.manualStudents = [];
}

// Füge Schülerzeile hinzu
function addSchuelerRow() {
    var student = {
        id: 'new_' + Date.now(),
        nachname: '',
        vorname: '',
        geschlecht: ''
    };
    window.klassenData.manualStudents.push(student);
    updateSchuelerTable();
}

// Aktualisiere Schülertabelle
function updateSchuelerTable() {
    var tbody = document.getElementById('schueler-list');
    if (!tbody) return;
    
    var html = '';
    window.klassenData.manualStudents.forEach(function(student, index) {
        html += '<tr>' +
            '<td style="padding: 5px;"><input type="text" value="' + escapeHtml(student.nachname) + '" onchange="updateStudent(' + index + ', \'nachname\', this.value)" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>' +
            '<td style="padding: 5px;"><input type="text" value="' + escapeHtml(student.vorname) + '" onchange="updateStudent(' + index + ', \'vorname\', this.value)" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;"></td>' +
            '<td style="padding: 5px;">' +
            '<select onchange="updateStudent(' + index + ', \'geschlecht\', this.value)" style="width: 100%; padding: 5px; border: 1px solid #DDD; border-radius: 4px;">' +
            '<option value="">Wählen</option>' +
            '<option value="m"' + (student.geschlecht === 'm' ? ' selected' : '') + '>Männlich</option>' +
            '<option value="w"' + (student.geschlecht === 'w' ? ' selected' : '') + '>Weiblich</option>' +
            '<option value="d"' + (student.geschlecht === 'd' ? ' selected' : '') + '>Divers</option>' +
            '</select>' +
            '</td>' +
            '<td style="padding: 5px; text-align: center;">' +
            '<button type="button" onclick="removeSchueler(' + index + ')" style="background: #F44336; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">🗑️</button>' +
            '</td>' +
            '</tr>';
    });
    
    tbody.innerHTML = html || '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Keine Schüler hinzugefügt</td></tr>';
}

// Aktualisiere Schülerdaten
function updateStudent(index, field, value) {
    if (window.klassenData.manualStudents[index]) {
        window.klassenData.manualStudents[index][field] = value;
        console.log('Schüler aktualisiert:', index, field, value);
    }
}

// Entferne Schüler
function removeSchueler(index) {
    window.klassenData.manualStudents.splice(index, 1);
    updateSchuelerTable();
}

// CSV-Datei verarbeiten
function handleCSVFile(event) {
    var file = event.target.files[0];
    if (!file) return;
    
    console.log('CSV-Datei ausgewählt:', file.name);
    
    var reader = new FileReader();
    reader.onload = function(e) {
        processCSVContent(e.target.result);
    };
    reader.readAsText(file, 'UTF-8');
}

// CSV-Inhalt verarbeiten
function processCSVContent(content) {
    console.log('Verarbeite CSV-Inhalt...');
    
    // Entferne BOM wenn vorhanden
    if (content.charCodeAt(0) === 0xFEFF) {
        content = content.slice(1);
    }
    
    // Teile in Zeilen
    var lines = content.split(/\r?\n/).filter(function(line) {
        return line.trim() !== '';
    });
    
    if (lines.length === 0) {
        showNotification('Die CSV-Datei ist leer', 'error');
        return;
    }
    
    // Erkenne Trennzeichen
    var separator = detectCSVSeparator(lines[0]);
    console.log('Erkanntes Trennzeichen:', separator);
    
    // Parse Zeilen
    window.klassenData.csvData = [];
    lines.forEach(function(line) {
        var cells = parseCSVLine(line, separator);
        if (cells.length >= 2) {
            window.klassenData.csvData.push(cells);
        }
    });
    
    console.log('CSV geparst:', window.klassenData.csvData.length + ' Zeilen');
    
    // Zeige Format-Auswahl und Vorschau
    document.getElementById('csv-format-selection').style.display = 'block';
    showCSVPreview();
}

// Erkenne CSV-Trennzeichen
function detectCSVSeparator(line) {
    var separators = [',', ';', '\t', '|'];
    var bestSeparator = ',';
    var maxCount = 0;
    
    separators.forEach(function(sep) {
        var count = (line.match(new RegExp('\\' + sep, 'g')) || []).length;
        if (count > maxCount) {
            maxCount = count;
            bestSeparator = sep;
        }
    });
    
    return bestSeparator;
}

// Parse CSV-Zeile
function parseCSVLine(line, separator) {
    var result = [];
    var current = '';
    var inQuotes = false;
    
    for (var i = 0; i < line.length; i++) {
        var char = line[i];
        
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === separator && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    
    result.push(current.trim());
    return result;
}

// Zeige CSV-Vorschau
function showCSVPreview() {
    if (!window.klassenData.csvData || window.klassenData.csvData.length === 0) return;
    
    var preview = document.getElementById('csv-preview');
    var tbody = document.getElementById('csv-preview-body');
    
    preview.style.display = 'block';
    
    var html = '';
    var maxRows = Math.min(5, window.klassenData.csvData.length);
    
    for (var i = 0; i < maxRows; i++) {
        var row = window.klassenData.csvData[i];
        html += '<tr>' +
            '<td style="padding: 5px; border: 1px solid #DDD;">' + escapeHtml(row[0] || '') + '</td>' +
            '<td style="padding: 5px; border: 1px solid #DDD;">' + escapeHtml(row[1] || '') + '</td>' +
            '<td style="padding: 5px; border: 1px solid #DDD;">' + escapeHtml(row[2] || '') + '</td>' +
            '</tr>';
    }
    
    tbody.innerHTML = html;
}

// Speichere Klasse
function saveKlasse(event) {
    event.preventDefault();
    
    var name = document.getElementById('klasse-name').value.trim();
    var schuljahr = document.getElementById('schuljahr').value.trim();
    
    if (!name || !schuljahr) {
        showNotification('Bitte alle Pflichtfelder ausfüllen', 'error');
        return;
    }
    
    // Sammle Fächer - KRITISCH: Müssen Integers sein!
    var faecher = [];
    var checkedBoxes = document.querySelectorAll('#faecher-checkboxes input[type="checkbox"]:checked');
    console.log('Anzahl ausgewählte Fächer:', checkedBoxes.length);
    
    checkedBoxes.forEach(function(cb) {
        // WICHTIG: parseInt und sofort als Number speichern
        var valueStr = cb.value;
        var fachId = parseInt(valueStr, 10);
        console.log('Checkbox value:', valueStr, '-> parsed:', fachId, 'type:', typeof fachId);
        
        if (!isNaN(fachId) && fachId > 0) {
            faecher.push(fachId); // Speichere als Integer
        }
    });
    
    console.log('Finale Fächer-Array:', faecher);
    console.log('Erstes Element Typ:', faecher.length > 0 ? typeof faecher[0] : 'leer');
    
    // Sammle Schüler
    var schueler = [];
    
    if (window.klassenData.csvData && window.klassenData.csvData.length > 0) {
        // CSV-Import
        var format = document.getElementById('csv-format').value;
        window.klassenData.csvData.forEach(function(row) {
            var student = {};
            if (format === 'nachname-vorname') {
                student.nachname = row[0] || '';
                student.vorname = row[1] || '';
            } else {
                student.vorname = row[0] || '';
                student.nachname = row[1] || '';
            }
            student.geschlecht = row[2] || '';
            if (student.nachname || student.vorname) {
                schueler.push(student);
            }
        });
    } else {
        // Manuelle Eingabe
        window.klassenData.manualStudents.forEach(function(student) {
            if (student.nachname || student.vorname) {
                schueler.push({
                    nachname: student.nachname,
                    vorname: student.vorname,
                    geschlecht: student.geschlecht
                });
            }
        });
    }
    
    var data = {
        name: name,
        schuljahr: schuljahr,
        faecher: faecher, // Sollte jetzt Integer-Array sein
        schueler: schueler
    };
    
    console.log('Daten die gesendet werden:', JSON.stringify(data, null, 2));
    
    var url = 'api/klassen.php?action=' + (window.klassenData.currentClassId ? 'update&id=' + window.klassenData.currentClassId : 'create');
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        console.log('Server Response Status:', response.status);
        if (!response.ok) throw new Error('Netzwerkfehler');
        return response.json();
    })
    .then(function(result) {
        console.log('Server Response:', result);
        if (result.error) {
            throw new Error(result.error);
        }
        closeKlasseModal();
        loadKlassen();
        showNotification(window.klassenData.currentClassId ? 'Klasse erfolgreich aktualisiert' : 'Klasse erfolgreich erstellt', 'success');
    })
    .catch(function(error) {
        console.error('Fehler beim Speichern:', error);
        showNotification('Fehler beim Speichern: ' + error.message, 'error');
    });
}

// Lade Klassendaten für Bearbeitung
function loadKlasseData(klasseId) {
    console.log('Lade Klassendaten für ID:', klasseId);
    
    fetch('api/klassen.php?action=get&id=' + klasseId)
        .then(function(response) {
            if (!response.ok) throw new Error('Netzwerkfehler');
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                throw new Error(data.error);
            }
            
            console.log('Klassendaten erhalten:', data);
            
            // Fülle Formular
            document.getElementById('klasse-name').value = data.name;
            document.getElementById('schuljahr').value = data.schuljahr;
            
            // Sammle Fächer-IDs als Array
            var faecherIds = [];
            if (data.faecher && Array.isArray(data.faecher)) {
                faecherIds = data.faecher.map(function(id) { 
                    return id.toString(); 
                });
            }
            
            console.log('Fächer-IDs für Vorauswahl:', faecherIds);
            
            // Lade Fächer mit Vorauswahl
            loadFaecherForSelection(faecherIds);
            
            // Lade Schüler
            if (data.schueler && data.schueler.length > 0) {
                window.klassenData.manualStudents = data.schueler;
                showManualEntry();
                updateSchuelerTable();
            }
        })
        .catch(function(error) {
            console.error('Fehler beim Laden der Klassendaten:', error);
            showNotification('Fehler beim Laden der Klassendaten: ' + error.message, 'error');
        });
}

// Lade Fächer für Auswahl
function loadFaecherForSelection(selectedFaecher) {
    console.log('Lade Fächer, ausgewählt:', selectedFaecher);
    
    fetch('api/faecher.php?action=list')
        .then(function(response) {
            if (!response.ok) throw new Error('Netzwerkfehler');
            return response.json();
        })
        .then(function(data) {
            var container = document.getElementById('faecher-checkboxes');
            if (!container) {
                console.error('Container faecher-checkboxes nicht gefunden');
                return;
            }
            
            console.log('Fächer von API erhalten:', data);
            
            // Die API gibt direkt ein Array zurück (siehe faecher.php Zeile 115)
            if (!Array.isArray(data)) {
                console.error('Unerwartetes Datenformat von API:', data);
                container.innerHTML = '<p style="grid-column: 1/-1; color: #FF0000; text-align: center;">Fehler: Unerwartetes Datenformat</p>';
                return;
            }
            
            if (data.length === 0) {
                container.innerHTML = '<p style="grid-column: 1/-1; color: #666; text-align: center;">Keine Fächer verfügbar. Bitte erst Fächer anlegen.</p>';
                return;
            }
            
            var html = '';
            data.forEach(function(fach) {
                // Konvertiere beide Werte zu Strings für den Vergleich
                var fachIdStr = (fach.id || '').toString();
                var isChecked = false;
                
                if (selectedFaecher && Array.isArray(selectedFaecher)) {
                    for (var i = 0; i < selectedFaecher.length; i++) {
                        if (selectedFaecher[i].toString() === fachIdStr) {
                            isChecked = true;
                            console.log('Fach ' + fach.name + ' (ID: ' + fachIdStr + ') wird vorausgewählt');
                            break;
                        }
                    }
                }
                
                html += '<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">' +
                    '<input type="checkbox" name="faecher[]" value="' + fach.id + '"' + 
                    (isChecked ? ' checked="checked"' : '') + '>' +
                    '<span>' + escapeHtml(fach.name || 'Unbenannt') + '</span>' +
                    '</label>';
            });
            
            container.innerHTML = html;
            console.log('Fächer-Checkboxen erstellt: ' + data.length + ' Fächer geladen');
            
            // Debug: Zeige welche Checkboxen tatsächlich ausgewählt sind
            var checkedCount = container.querySelectorAll('input[type="checkbox"]:checked').length;
            console.log('Anzahl ausgewählter Checkboxen nach dem Laden: ' + checkedCount);
        })
        .catch(function(error) {
            console.error('Fehler beim Laden der Fächer:', error);
            var container = document.getElementById('faecher-checkboxes');
            if (container) {
                container.innerHTML = '<p style="grid-column: 1/-1; color: #FF0000; text-align: center;">Fehler beim Laden der Fächer: ' + error.message + '</p>';
            }
        });
}

// Zeige Klassendetails
function viewKlasseDetails(klasseId) {
    fetch('api/klassen.php?action=details&id=' + klasseId)
        .then(function(response) {
            if (!response.ok) throw new Error('Netzwerkfehler');
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                throw new Error(data.error);
            }
            showKlasseDetailsModal(data);
        })
        .catch(function(error) {
            console.error('Fehler beim Laden der Details:', error);
            showNotification('Fehler beim Laden der Details', 'error');
        });
}

// Zeige Details-Modal
function showKlasseDetailsModal(klasse) {
    var modal = document.getElementById('details-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'details-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }
    
    var html = '<div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">' +
        '<div class="modal-header">' +
        '<h3 class="modal-title">Klasse ' + escapeHtml(klasse.name) + ' - Details</h3>' +
        '<button class="btn-close" onclick="closeDetailsModal()">×</button>' +
        '</div>' +
        '<div class="modal-body">';
    
    // Klasseninfo
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 15px; background: #F5F5F5; border-radius: 8px;">' +
        '<div><strong>Schuljahr:</strong> ' + escapeHtml(klasse.schuljahr) + '</div>' +
        '<div><strong>Schüleranzahl:</strong> ' + (klasse.schueler ? klasse.schueler.length : 0) + '</div>' +
        '<div><strong>Fächer:</strong> ' + (klasse.faecher_namen ? klasse.faecher_namen.join(', ') : 'Keine') + '</div>' +
        '</div>';
    
    // Schülerliste
    html += '<h4 style="margin-top: 20px; margin-bottom: 10px; color: #4A90E2;">Schülerliste</h4>';
    
    if (klasse.schueler && klasse.schueler.length > 0) {
        // Sortiere Schüler
        var sortedSchueler = sortSchueler(klasse.schueler);
        
        html += '<table style="width: 100%; border-collapse: collapse;">' +
            '<thead style="background: #4A90E2; color: white;">' +
            '<tr>' +
            '<th style="padding: 10px; text-align: left;">Nr.</th>' +
            '<th style="padding: 10px; text-align: left;">Nachname</th>' +
            '<th style="padding: 10px; text-align: left;">Vorname</th>' +
            '<th style="padding: 10px; text-align: left;">Geschlecht</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';
        
        sortedSchueler.forEach(function(schueler, index) {
            var geschlechtText = schueler.geschlecht === 'm' ? 'Männlich' :
                                schueler.geschlecht === 'w' ? 'Weiblich' :
                                schueler.geschlecht === 'd' ? 'Divers' : '-';
            
            html += '<tr style="border-bottom: 1px solid #E0E0E0;">' +
                '<td style="padding: 8px;">' + (index + 1) + '</td>' +
                '<td style="padding: 8px;">' + escapeHtml(schueler.nachname) + '</td>' +
                '<td style="padding: 8px;">' + escapeHtml(schueler.vorname) + '</td>' +
                '<td style="padding: 8px;">' + geschlechtText + '</td>' +
                '</tr>';
        });
        
        html += '</tbody></table>';
    } else {
        html += '<p style="color: #666; text-align: center; padding: 20px;">Keine Schüler in dieser Klasse</p>';
    }
    
    html += '</div></div>';
    
    modal.innerHTML = html;
    modal.classList.add('active');
}

// Schließe Details-Modal
function closeDetailsModal() {
    var modal = document.getElementById('details-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Bearbeite Klasse
function editKlasse(klasseId) {
    openKlasseModal(klasseId);
}

// Lösche Klasse
function deleteKlasse(klasseId) {
    if (!confirm('Möchten Sie diese Klasse wirklich löschen? Alle Schülerdaten und Noten werden ebenfalls gelöscht.')) {
        return;
    }
    
    fetch('api/klassen.php?action=delete&id=' + klasseId, {
        method: 'DELETE'
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Netzwerkfehler');
        return response.json();
    })
    .then(function(result) {
        if (result.error) {
            throw new Error(result.error);
        }
        loadKlassen();
        showNotification('Klasse erfolgreich gelöscht', 'success');
    })
    .catch(function(error) {
        console.error('Fehler beim Löschen:', error);
        showNotification('Fehler beim Löschen: ' + error.message, 'error');
    });
}

// Sortiere Schüler
function sortSchueler(schueler) {
    if (!schueler || schueler.length === 0) return [];
    
    var sorted = schueler.slice(); // Kopie erstellen
    
    sorted.sort(function(a, b) {
        // Primäre Sortierung
        var primary = compareField(a, b, window.klassenData.globalSorting.primary);
        if (primary !== 0) return primary;
        
        // Sekundäre Sortierung
        if (window.klassenData.globalSorting.secondary !== 'keine') {
            var secondary = compareField(a, b, window.klassenData.globalSorting.secondary);
            if (secondary !== 0) return secondary;
        }
        
        // Tertiäre Sortierung
        if (window.klassenData.globalSorting.tertiary !== 'keine') {
            return compareField(a, b, window.klassenData.globalSorting.tertiary);
        }
        
        return 0;
    });
    
    return sorted;
}

// Vergleiche Felder für Sortierung
function compareField(a, b, field) {
    if (field === 'keine') return 0;
    
    var valA = (a[field] || '').toString().toLowerCase();
    var valB = (b[field] || '').toString().toLowerCase();
    
    if (valA < valB) return -1;
    if (valA > valB) return 1;
    return 0;
}

// Aktualisiere globale Sortierung
function updateGlobalSorting() {
    window.klassenData.globalSorting.primary = document.getElementById('global-sort-1').value;
    window.klassenData.globalSorting.secondary = document.getElementById('global-sort-2').value;
    window.klassenData.globalSorting.tertiary = document.getElementById('global-sort-3').value;
    
    console.log('Sortierung aktualisiert:', window.klassenData.globalSorting);
    
    // Speichere in Session
    fetch('api/klassen.php?action=save-sorting', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(window.klassenData.globalSorting)
    });
}

// Lade Sortierungseinstellungen
function loadSortingSettings() {
    fetch('api/klassen.php?action=get-sorting')
        .then(function(response) {
            if (!response.ok) return;
            return response.json();
        })
        .then(function(data) {
            if (data && data.sorting) {
                window.klassenData.globalSorting = data.sorting;
                
                // Setze UI-Werte
                if (document.getElementById('global-sort-1')) {
                    document.getElementById('global-sort-1').value = data.sorting.primary || 'nachname';
                }
                if (document.getElementById('global-sort-2')) {
                    document.getElementById('global-sort-2').value = data.sorting.secondary || 'vorname';
                }
                if (document.getElementById('global-sort-3')) {
                    document.getElementById('global-sort-3').value = data.sorting.tertiary || 'geschlecht';
                }
            }
        })
        .catch(function(error) {
            console.log('Keine gespeicherten Sortierungseinstellungen');
        });
}

// Hilfsfunktion: HTML escapen
function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Zeige Benachrichtigung
function showNotification(message, type) {
    var notification = document.createElement('div');
    notification.className = 'notification ' + (type || 'info');
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: ' + 
        (type === 'error' ? '#F44336' : type === 'success' ? '#4CAF50' : '#2196F3') + 
        '; color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 10000; animation: slideIn 0.3s ease;';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// CSS für Animationen hinzufügen
if (!document.getElementById('klassen-animations')) {
    var style = document.createElement('style');
    style.id = 'klassen-animations';
    style.textContent = '@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }' +
        '@keyframes slideOut { from { transform: translateX(0); } to { transform: translateX(100%); } }' +
        '.spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #4A90E2; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto; }' +
        '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.head.appendChild(style);
}

// Exportiere Funktionen global
window.klassenInit = klassenInit;
window.loadKlassen = loadKlassen;
window.openKlasseModal = openKlasseModal;
window.closeKlasseModal = closeKlasseModal;
window.saveKlasse = saveKlasse;
window.editKlasse = editKlasse;
window.deleteKlasse = deleteKlasse;
window.viewKlasseDetails = viewKlasseDetails;
window.closeDetailsModal = closeDetailsModal;
window.showManualEntry = showManualEntry;
window.showCSVImport = showCSVImport;
window.addSchuelerRow = addSchuelerRow;
window.updateStudent = updateStudent;
window.removeSchueler = removeSchueler;
window.handleCSVFile = handleCSVFile;
window.updateGlobalSorting = updateGlobalSorting;
window.showNotification = showNotification;