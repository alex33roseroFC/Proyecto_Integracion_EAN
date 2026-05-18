(function() {
    'use strict';

    var monthNumberMap = {
        Ene: '01',
        Feb: '02',
        Mar: '03',
        Abr: '04',
        May: '05',
        Jun: '06',
        Jul: '07',
        Ago: '08',
        Sep: '09',
        Oct: '10',
        Nov: '11',
        Dic: '12'
    };

    var state = {
        currentTrigger: null,
        modalInstance: null,
        loadedComments: {}
    };

    function splitEmployeeName(fullName) {
        var parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return { nom: '', prenom: '' };
        if (parts.length === 1) return { nom: parts[0], prenom: '' };
        if (parts.length === 2) return { nom: parts[0], prenom: parts[1] };
        return {
            nom: parts.slice(0, 2).join(' '),
            prenom: parts.slice(2).join(' ')
        };
    }

    function getSqlDate(monthColumn) {
        var parts = String(monthColumn || '').split('_');
        var month = parts[0] || '';
        var year = parts[1] || '';
        if (!month || !year || !monthNumberMap[month]) return '';
        return year + '-' + monthNumberMap[month] + '-01';
    }

    function formatDate(sqlDate) {
        var parts = String(sqlDate || '').split('-');
        if (parts.length !== 3) return sqlDate || '-';
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function buildCommentKey(numeroEmpleado, codigoAffaire, fechaSql) {
        return String(numeroEmpleado || '').trim() + '|' + String(codigoAffaire || '').trim().toUpperCase() + '|' + String(fechaSql || '').trim();
    }

    function getSelectedEmployeeContext() {
        var selectedRadio = document.querySelector('#empleados-table input[name="selected_employee_radio"]:checked');
        var selectedRow = selectedRadio ? selectedRadio.closest('tr') : null;
        var hiddenEmployee = document.getElementById('selected_employee_id');
        var matricula = selectedRow ? (selectedRow.dataset.matricula || '') : '';
        if (!matricula && hiddenEmployee) {
            matricula = String(hiddenEmployee.value || '').trim();
        }
        var fullName = selectedRow ? (selectedRow.dataset.fullName || '') : '';
        var derived = splitEmployeeName(fullName);
        return {
            numeroEmpleado: matricula,
            fullName: fullName,
            nom: selectedRow ? (selectedRow.dataset.nom || derived.nom) : derived.nom,
            prenom: selectedRow ? (selectedRow.dataset.prenom || derived.prenom) : derived.prenom
        };
    }

    function getRowProjectName(row) {
        var projectField = row ? row.querySelector('.proyecto-autocomplete') : null;
        var value = projectField ? String(projectField.value || '').trim() : '';
        if (value.indexOf('||') >= 0) {
            value = value.split('||')[0].trim();
        }
        return value;
    }

    function getCommentContext(trigger) {
        var row = trigger ? trigger.closest('.form-row') : null;
        if (!row) return null;

        var employee = getSelectedEmployeeContext();
        var projectName = getRowProjectName(row);
        var cecoInput = row.querySelector('input[name="centro_costos[]"]');
        var codigoAffaire = cecoInput ? String(cecoInput.value || '').trim() : '';
        var monthColumn = trigger.getAttribute('data-month-column') || '';
        var fechaSql = getSqlDate(monthColumn);

        var missing = [];
        if (!employee.numeroEmpleado) missing.push('colaborador');
        if (!projectName) missing.push('proyecto');
        if (!codigoAffaire) missing.push('centro de costo');
        if (!fechaSql) missing.push('mes');

        if (missing.length) {
            trigger.setAttribute('data-comment-error', missing.join(', '));
            return null;
        }

        return {
            numeroEmpleado: employee.numeroEmpleado,
            fullName: employee.fullName,
            nom: employee.nom,
            prenom: employee.prenom,
            nombreProyecto: projectName,
            codigoAffaire: codigoAffaire,
            monthColumn: monthColumn,
            fechaSql: fechaSql,
            fechaDisplay: formatDate(fechaSql),
            key: buildCommentKey(employee.numeroEmpleado, codigoAffaire, fechaSql)
        };
    }

    function setTriggerState(trigger, hasComment) {
        if (!trigger) return;
        var shell = trigger.closest('.assignment-month-input-shell');
        var existingCorner = shell ? shell.querySelector('.cell-corner-badge') : null;
        if (hasComment) {
            trigger.classList.add('has-comment');
            trigger.setAttribute('title', 'Ver o editar comentario');
            if (shell && !existingCorner) {
                var badge = document.createElement('span');
                badge.className = 'cell-corner-badge';
                shell.appendChild(badge);
            }
        } else {
            trigger.classList.remove('has-comment');
            trigger.setAttribute('title', 'Agregar comentario');
            if (existingCorner) {
                existingCorner.remove();
            }
        }
    }

    function getModalElements() {
        return {
            modal: document.getElementById('assignment-comment-modal'),
            employee: document.getElementById('assignment-comment-employee'),
            project: document.getElementById('assignment-comment-project'),
            affaire: document.getElementById('assignment-comment-affaire'),
            date: document.getElementById('assignment-comment-date'),
            text: document.getElementById('assignment-comment-text'),
            feedback: document.getElementById('assignment-comment-feedback'),
            saveBtn: document.getElementById('assignment-comment-save-btn')
        };
    }

    function ensureModalInstance(modalEl) {
        if (!modalEl) return null;
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            if (!state.modalInstance) {
                state.modalInstance = new window.bootstrap.Modal(modalEl);
            }
            return state.modalInstance;
        }
        return null;
    }

    function fillModal(ctx, comentario) {
        var els = getModalElements();
        if (!els.modal) return;
        if (els.employee) els.employee.textContent = ctx.fullName || ((ctx.nom + ' ' + ctx.prenom).trim()) || '-';
        if (els.project) els.project.textContent = ctx.nombreProyecto || '-';
        if (els.affaire) els.affaire.textContent = ctx.codigoAffaire || '-';
        if (els.date) els.date.textContent = ctx.fechaDisplay || '-';
        if (els.text) els.text.value = comentario || '';
    }

    function openAssignmentCommentModal(trigger) {
        var ctx = getCommentContext(trigger);
        if (!ctx) {
            var detail = trigger ? trigger.getAttribute('data-comment-error') : '';
            alert('Primero debe seleccionar un colaborador y un proyecto valido para registrar el comentario. Falta: ' + (detail || 'contexto incompleto'));
            return false;
        }

        var els = getModalElements();
        if (!els.modal) {
            alert('No se encontro el modal assignment-comment-modal en la pagina.');
            return false;
        }

        state.currentTrigger = trigger;
        fillModal(ctx, state.loadedComments[ctx.key] || '');
        if (els.feedback) els.feedback.textContent = 'Cargando comentario...';

        var instance = ensureModalInstance(els.modal);
        if (instance) {
            instance.show();
        } else {
            els.modal.style.display = 'block';
            els.modal.classList.add('show');
            els.modal.removeAttribute('aria-hidden');
            els.modal.setAttribute('aria-modal', 'true');
            document.body.classList.add('modal-open');
        }

        fetch('get_comentario_asignacion.php?' + new URLSearchParams({
            numero_de_empleado: ctx.numeroEmpleado,
            codigo_affaire: ctx.codigoAffaire,
            fecha: ctx.fechaSql
        }).toString(), {
            credentials: 'same-origin'
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                var comentario = data && data.success ? String(data.comentario || '') : '';
                state.loadedComments[ctx.key] = comentario;
                fillModal(ctx, comentario);
                setTriggerState(trigger, comentario.trim() !== '');
                if (els.feedback) {
                    els.feedback.textContent = comentario.trim() !== ''
                        ? 'Comentario cargado.'
                        : ((data && data.message) ? data.message : 'No hay comentario registrado para esta casilla.');
                }
            })
            .catch(function() {
                if (els.feedback) els.feedback.textContent = 'Error de conexion al consultar el comentario.';
            });

        return false;
    }

    function closeFallbackModal() {
        var els = getModalElements();
        if (!els.modal) return;
        els.modal.style.display = 'none';
        els.modal.classList.remove('show');
        els.modal.setAttribute('aria-hidden', 'true');
        els.modal.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
    }

    function saveAssignmentComment() {
        var trigger = state.currentTrigger;
        var ctx = trigger ? getCommentContext(trigger) : null;
        var els = getModalElements();
        if (!ctx || !els.text) {
            alert('No se encontro el contexto de la asignacion para guardar el comentario.');
            return;
        }

        var commentText = String(els.text.value || '');
        if (els.saveBtn) els.saveBtn.disabled = true;
        if (els.feedback) els.feedback.textContent = 'Guardando comentario...';

        fetch('save_comentario_asignacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                numero_de_empleado: ctx.numeroEmpleado,
                nom: ctx.nom,
                prenom: ctx.prenom,
                codigo_affaire: ctx.codigoAffaire,
                nombre_proyecto: ctx.nombreProyecto,
                comentario: commentText,
                fecha: ctx.fechaSql
            }).toString(),
            credentials: 'same-origin'
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    if (els.feedback) els.feedback.textContent = (data && data.message) ? data.message : 'No fue posible guardar el comentario.';
                    return;
                }
                state.loadedComments[ctx.key] = commentText;
                setTriggerState(trigger, commentText.trim() !== '');
                if (els.feedback) els.feedback.textContent = 'Comentario guardado correctamente.';
                // Cerrar el modal y recargar para ver el cambio
                setTimeout(function() {
                    var instance = state.modalInstance;
                    if (instance && typeof instance.hide === 'function') {
                        instance.hide();
                    } else if (els.modal) {
                        els.modal.style.display = 'none';
                        els.modal.classList.remove('show');
                        els.modal.setAttribute('aria-hidden', 'true');
                        els.modal.removeAttribute('aria-modal');
                        document.body.classList.remove('modal-open');
                    }
                    window.location.reload();
                }, 700);
            })
            .catch(function() {
                if (els.feedback) els.feedback.textContent = 'Error de conexion al guardar el comentario.';
            })
            .finally(function() {
                if (els.saveBtn) els.saveBtn.disabled = false;
            });
    }

    function deleteAssignmentComment() {
        var trigger = state.currentTrigger;
        var ctx = trigger ? getCommentContext(trigger) : null;
        var els = getModalElements();
        if (!ctx) {
            alert('No se encontró el contexto de la asignación para eliminar el comentario.');
            return;
        }
        if (!confirm('¿Está seguro de que desea eliminar este comentario? Esta acción no se puede deshacer.')) return;
        if (els.feedback) els.feedback.textContent = 'Eliminando comentario...';
        fetch('delete_comentario_asignacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                numero_de_empleado: ctx.numeroEmpleado,
                codigo_affaire: ctx.codigoAffaire,
                fecha: ctx.fechaSql
            }).toString(),
            credentials: 'same-origin'
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            if (!data || !data.success) {
                if (els.feedback) els.feedback.textContent = (data && data.message) ? data.message : 'No fue posible eliminar el comentario.';
                return;
            }
            state.loadedComments[ctx.key] = '';
            if (els.text) els.text.value = '';
            setTriggerState(trigger, false);
            if (els.feedback) els.feedback.textContent = 'Comentario eliminado correctamente.';
            // Cerrar el modal y refrescar el formulario
            setTimeout(function() {
                var instance = state.modalInstance;
                if (instance && typeof instance.hide === 'function') {
                    instance.hide();
                } else if (els.modal) {
                    els.modal.style.display = 'none';
                    els.modal.classList.remove('show');
                    els.modal.setAttribute('aria-hidden', 'true');
                    els.modal.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');
                }
                // Refrescar el formulario de asignación (recarga la página)
                window.location.reload();
            }, 700);
        })
        .catch(function() {
            if (els.feedback) els.feedback.textContent = 'Error de conexión al eliminar el comentario.';
        });
    }

    function bindEvents() {
        document.addEventListener('click', function(event) {
            var trigger = event.target.closest('.assignment-comment-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                openAssignmentCommentModal(trigger);
                return;
            }

            var fallbackClose = event.target.closest('#assignment-comment-modal [data-bs-dismiss="modal"]');
            if (fallbackClose && !state.modalInstance) {
                closeFallbackModal();
            }

            var deleteBtn = event.target.closest('#assignment-comment-delete-btn');
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                deleteAssignmentComment();
                return;
            }
        });

        var els = getModalElements();
        if (els.saveBtn) {
            els.saveBtn.addEventListener('click', saveAssignmentComment);
        }
        if (els.modal) {
            els.modal.addEventListener('hidden.bs.modal', function() {
                state.currentTrigger = null;
                var currentEls = getModalElements();
                if (currentEls.feedback) currentEls.feedback.textContent = '';
            });
        }
    }

    window.openAssignmentCommentModal = openAssignmentCommentModal;
    window.saveAssignmentComment = saveAssignmentComment;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
