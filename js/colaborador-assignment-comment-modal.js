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
        modalInstance: null
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

    function getModalElements() {
        return {
            modal: document.getElementById('colaborador-assignment-comment-modal'),
            employee: document.getElementById('colab-assignment-comment-employee'),
            project: document.getElementById('colab-assignment-comment-project'),
            affaire: document.getElementById('colab-assignment-comment-affaire'),
            date: document.getElementById('colab-assignment-comment-date'),
            text: document.getElementById('colab-assignment-comment-text'),
            feedback: document.getElementById('colab-assignment-comment-feedback'),
            saveBtn: document.getElementById('colab-assignment-comment-save-btn')
        };
    }

    function getCommentContext(trigger) {
        if (!trigger) return null;
        var fullName = trigger.getAttribute('data-full-name') || '';
        var names = splitEmployeeName(fullName);
        var numeroEmpleado = trigger.getAttribute('data-numero-empleado') || '';
        var codigoAffaire = trigger.getAttribute('data-codigo-affaire') || '';
        var nombreProyecto = trigger.getAttribute('data-project-name') || '';
        var monthColumn = trigger.getAttribute('data-month-column') || '';
        var fechaSql = getSqlDate(monthColumn);

        if (!numeroEmpleado || !codigoAffaire || !nombreProyecto || !fechaSql) {
            trigger.setAttribute('data-comment-error', 'contexto incompleto');
            return null;
        }

        return {
            numeroEmpleado: numeroEmpleado,
            nom: trigger.getAttribute('data-nom') || names.nom,
            prenom: trigger.getAttribute('data-prenom') || names.prenom,
            fullName: fullName,
            codigoAffaire: codigoAffaire,
            nombreProyecto: nombreProyecto,
            monthColumn: monthColumn,
            fechaSql: fechaSql,
            fechaDisplay: formatDate(fechaSql),
            key: buildCommentKey(numeroEmpleado, codigoAffaire, fechaSql)
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
        if (els.employee) els.employee.textContent = ctx.fullName || ((ctx.nom + ' ' + ctx.prenom).trim()) || '-';
        if (els.project) els.project.textContent = ctx.nombreProyecto || '-';
        if (els.affaire) els.affaire.textContent = ctx.codigoAffaire || '-';
        if (els.date) els.date.textContent = ctx.fechaDisplay || '-';
        if (els.text) els.text.value = comentario || '';
    }

    function setTriggerState(trigger, hasComment) {
        if (!trigger) return;
        var shell = trigger.closest('.collab-assignment-cell');
        var existingCorner = shell ? shell.querySelector('.collab-assignment-corner') : null;
        if (hasComment) {
            if (shell && !existingCorner) {
                var badge = document.createElement('span');
                badge.className = 'collab-assignment-corner';
                shell.appendChild(badge);
            }
            trigger.setAttribute('title', 'Ver o editar comentario');
        } else {
            if (existingCorner) {
                existingCorner.remove();
            }
            trigger.setAttribute('title', 'Agregar comentario');
        }
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

    function openCommentModal(trigger) {
        var ctx = getCommentContext(trigger);
        var els = getModalElements();
        if (!ctx) {
            alert('No se encontró el contexto necesario para abrir el comentario.');
            return false;
        }
        if (!els.modal) {
            alert('No se encontró el modal de comentario en la página.');
            return false;
        }

        state.currentTrigger = trigger;
        fillModal(ctx, '');
        if (els.feedback) els.feedback.textContent = 'Cargando comentario...';

        // --- SOLO LECTURA: ocultar botones y bloquear textarea ---
        if (els.saveBtn) els.saveBtn.style.display = 'none';
        var deleteBtn = document.getElementById('colab-assignment-comment-delete-btn');
        if (deleteBtn) deleteBtn.style.display = 'none';
        if (els.text) els.text.readOnly = true;

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
        }).toString(), { credentials: 'same-origin' })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                var comentario = data && data.success ? String(data.comentario || '') : '';
                fillModal(ctx, comentario);
                setTriggerState(trigger, comentario.trim() !== '');
                if (els.feedback) {
                    els.feedback.textContent = comentario.trim() !== '' ? 'Comentario cargado.' : 'No hay comentario registrado para esta casilla.';
                }
            })
            .catch(function() {
                if (els.feedback) els.feedback.textContent = 'Error de conexión al consultar el comentario.';
            });

        return false;
    }

    function saveComment() {
        var trigger = state.currentTrigger;
        var ctx = getCommentContext(trigger);
        var els = getModalElements();
        if (!ctx || !els.text) {
            alert('No se encontró el contexto de la asignación para guardar el comentario.');
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
                setTriggerState(trigger, commentText.trim() !== '');
                if (els.feedback) els.feedback.textContent = 'Comentario guardado correctamente.';
                setTimeout(function() {
                    var instance = state.modalInstance;
                    if (instance && typeof instance.hide === 'function') {
                        instance.hide();
                    } else {
                        closeFallbackModal();
                    }
                    window.location.reload();
                }, 700);
            })
            .catch(function() {
                if (els.feedback) els.feedback.textContent = 'Error de conexión al guardar el comentario.';
            })
            .finally(function() {
                if (els.saveBtn) els.saveBtn.disabled = false;
            });
    }

    function deleteComment() {
        var trigger = state.currentTrigger;
        var ctx = getCommentContext(trigger);
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
                if (els.text) els.text.value = '';
                setTriggerState(trigger, false);
                if (els.feedback) els.feedback.textContent = 'Comentario eliminado correctamente.';
                setTimeout(function() {
                    var instance = state.modalInstance;
                    if (instance && typeof instance.hide === 'function') {
                        instance.hide();
                    } else {
                        closeFallbackModal();
                    }
                    window.location.reload();
                }, 700);
            })
            .catch(function() {
                if (els.feedback) els.feedback.textContent = 'Error de conexión al eliminar el comentario.';
            });
    }

    function bindEvents() {
        document.addEventListener('click', function(event) {
            var trigger = event.target.closest('.collab-assignment-comment-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                openCommentModal(trigger);
                return;
            }

            var saveBtn = event.target.closest('#colab-assignment-comment-save-btn');
            if (saveBtn) {
                event.preventDefault();
                event.stopPropagation();
                saveComment();
                return;
            }

            var deleteBtn = event.target.closest('#colab-assignment-comment-delete-btn');
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                deleteComment();
                return;
            }

            var fallbackClose = event.target.closest('#colaborador-assignment-comment-modal [data-bs-dismiss="modal"]');
            if (fallbackClose && !state.modalInstance) {
                closeFallbackModal();
            }
        });

        var els = getModalElements();
        if (els.modal) {
            els.modal.addEventListener('hidden.bs.modal', function() {
                state.currentTrigger = null;
                if (els.feedback) els.feedback.textContent = '';
            });
        }
    }

    window.openColabAssignmentCommentModal = openCommentModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
