/* =====================================================================
   ServiceHub — Main JavaScript
   ===================================================================== */

const BASE = '/ThinkFest';

/* ── DOM Helpers ────────────────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ── Sidebar Toggle ─────────────────────────────────────────────────── */
function initSidebar() {
    const sidebar = $('#sidebar');
    const toggle = $('#sidebarToggle');
    const closeBtn = $('#sidebarClose');
    if (!sidebar) return;

    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.style.cssText =
        'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;display:none;';
    document.body.appendChild(overlay);

    function open() {
        sidebar.classList.add('open');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    toggle && toggle.addEventListener('click', open);
    closeBtn && closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', close);
}

/* ── Dropdown Menus ─────────────────────────────────────────────────── */
function initDropdowns() {
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-dropdown]');
        if (trigger) {
            e.stopPropagation();
            const target = document.getElementById(trigger.dataset.dropdown);
            if (!target) return;
            const isOpen = target.classList.contains('show');
            $$('.dropdown-menu.show, .notif-panel.show').forEach((m) =>
                m.classList.remove('show'),
            );
            if (!isOpen) target.classList.add('show');
            return;
        }
        $$('.dropdown-menu.show, .notif-panel.show').forEach((m) =>
            m.classList.remove('show'),
        );
    });
}

/* ── Toast Notifications ─────────────────────────────────────────────── */
function showToast(message, type = 'info', duration = 3500) {
    let container = $('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle',
    };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info} toast-icon"></i><span class="toast-text">${escHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = '.3s ease';
        setTimeout(() => toast.remove(), 320);
    }, duration);
}

/* ── AJAX Helper ─────────────────────────────────────────────────────── */
function ajax(data, callback) {
    const fd = new FormData();
    Object.keys(data).forEach((k) => fd.append(k, data[k]));
    fetch(`${BASE}/api/ajax.php`, { method: 'POST', body: fd })
        .then((r) => r.json())
        .then((res) => callback(null, res))
        .catch((err) => callback(err, null));
}

/* ── Escape HTML ─────────────────────────────────────────────────────── */
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

const FORM_RULES = {
    email: {
        regex: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
        message: 'Enter a valid email address.',
    },
    name: {
        regex: /^[A-Za-z][A-Za-z\s'.-]{1,79}$/,
        message: 'Enter a valid full name.',
    },
    department: {
        regex: /^[A-Za-z0-9][A-Za-z0-9&()/,.-\s]{1,79}$/,
        message: 'Enter a valid department name.',
    },
    phone: {
        regex: /^\+?[0-9][0-9\s\-()]{7,19}$/,
        message: 'Enter a valid phone number.',
    },
    'login-password': {
        regex: /^\S{8,64}$/,
        message: 'Password must be 8-64 characters.',
    },
    'strong-password': {
        regex: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,64}$/,
        message:
            'Use 8-64 chars with uppercase, lowercase, number and special character.',
    },
    'request-title': {
        regex: /^[A-Za-z0-9][A-Za-z0-9\s&()/#.,:;'"!?+\-]{4,254}$/,
        message: 'Enter a valid request title.',
    },
    location: {
        regex: /^[A-Za-z0-9][A-Za-z0-9\s#.,()/-]{1,119}$/,
        message: 'Enter a valid location.',
    },
    'text-block': {
        regex: /^[A-Za-z0-9\s.,:;!?()'"/#&@+\-]{10,2000}$/,
        message: 'Use 10-2000 valid characters.',
    },
    'text-block-short': {
        regex: /^[A-Za-z0-9\s.,:;!?()'"/#&@+\-]{2,1000}$/,
        message: 'Use 2-1000 valid characters.',
    },
};

function getFieldErrorBox(field) {
    return field.closest('.form-group')?.querySelector('.form-error') || null;
}

function setFieldError(field, message) {
    const errorBox = getFieldErrorBox(field);
    field.classList.toggle('error', !!message);
    if (errorBox) errorBox.textContent = message || '';
}

function validateFormFields(form, showToastOnError = false) {
    let firstError = '';
    let isValid = true;
    form.querySelectorAll('input, textarea, select').forEach((field) => {
        if (
            field.disabled ||
            ['button', 'submit', 'reset', 'file'].includes(field.type)
        ) {
            return;
        }

        const rawValue = field.value || '';
        const trimmedValue = rawValue.trim();
        let message = '';

        if (field.required && !trimmedValue) {
            message = 'This field is required.';
        } else if (trimmedValue && field.dataset.rule) {
            const rule = FORM_RULES[field.dataset.rule];
            if (rule && !rule.regex.test(trimmedValue)) {
                message = rule.message;
            }
        }

        if (!message && trimmedValue && !field.checkValidity()) {
            message = field.validationMessage || 'Enter a valid value.';
        }

        if (!message && field.dataset.match) {
            const target = form.querySelector(field.dataset.match);
            if (target && rawValue !== target.value) {
                message = 'Values do not match.';
            }
        }

        setFieldError(field, message);
        if (message && !firstError) firstError = message;
        if (message) isValid = false;
    });

    if (!isValid && showToastOnError) {
        showToast(
            firstError || 'Please correct the highlighted fields.',
            'error',
        );
    }
    return isValid;
}

/* ── Notification Polling ─────────────────────────────────────────────── */
function initNotifications() {
    const badge = $('#notifBadge');
    if (!badge) return;

    function fetchCount() {
        ajax({ action: 'get_notif_count' }, (err, res) => {
            if (err || !res) return;
            if (res.count > 0) {
                badge.textContent = res.count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    function loadNotifications() {
        const list = $('#notifList');
        if (!list) return;
        ajax({ action: 'get_notifications' }, (err, res) => {
            if (err || !res || !res.notifications) return;
            if (res.notifications.length === 0) {
                list.innerHTML =
                    '<div class="notif-empty"><i class="far fa-bell"></i><p>No notifications</p></div>';
                return;
            }
            list.innerHTML = res.notifications
                .map(
                    (n) => `
                <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" onclick="markRead(${n.id}, this, ${n.request_id})">
                    <div class="notif-icon ${escHtml(n.type)}"><i class="fas ${notifIcon(n.type)}"></i></div>
                    <div class="notif-text">
                        <p>${escHtml(n.message)}</p>
                        <div class="notif-time">${escHtml(n.time_ago)}</div>
                    </div>
                </div>`,
                )
                .join('');
        });
    }

    const notifBtn = $('#notifBtn');
    notifBtn && notifBtn.addEventListener('click', loadNotifications);

    fetchCount();
    setInterval(fetchCount, 60000);
}

function notifIcon(type) {
    const m = {
        status_update: 'fa-sync-alt',
        new_request: 'fa-plus-circle',
        comment: 'fa-comment',
        assignment: 'fa-user-check',
        info: 'fa-info-circle',
    };
    return m[type] || 'fa-bell';
}

function markRead(id, el, requestId) {
    ajax({ action: 'mark_notification_read', id }, (err, res) => {
        if (err || !res) return;
        el.classList.remove('unread');
        const badge = $('#notifBadge');
        if (badge) {
            const c = parseInt(badge.textContent) - 1;
            if (c <= 0) badge.style.display = 'none';
            else badge.textContent = c;
        }
        if (requestId)
            window.location = `${BASE}/view-request.php?id=${requestId}`;
    });
}

/* ── Mark All Notifications Read ─────────────────────────────────────── */
function markAllRead() {
    ajax({ action: 'mark_all_read' }, (err, res) => {
        if (err || !res) return;
        $$('.notif-item.unread').forEach((el) => el.classList.remove('unread'));
        const badge = $('#notifBadge');
        if (badge) badge.style.display = 'none';
        showToast('All notifications marked as read', 'success');
    });
}

/* ── Comment/Update Form (view-request page) ─────────────────────────── */
function initCommentForm() {
    const form = $('#commentForm');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!validateFormFields(form, true)) return;
        const comment = form.querySelector('[name="comment"]').value.trim();
        const statusEl = form.querySelector('[name="new_status"]');
        const reqId = form.querySelector('[name="request_id"]').value;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Posting…';
        ajax(
            {
                action: 'add_comment',
                comment,
                new_status: statusEl ? statusEl.value : '',
                request_id: reqId,
            },
            (err, res) => {
                btn.disabled = false;
                btn.innerHTML =
                    '<i class="fas fa-paper-plane"></i> Post Update';
                if (err || !res || !res.success) {
                    showToast(res?.message || 'Error posting comment', 'error');
                    return;
                }
                showToast('Comment posted!', 'success');
                form.reset();
                loadTimeline(reqId);
            },
        );
    });
}

function loadTimeline(reqId) {
    ajax({ action: 'get_timeline', request_id: reqId }, (err, res) => {
        if (err || !res || !res.timeline) return;
        const tl = $('#timeline');
        if (!tl) return;
        tl.innerHTML = res.timeline
            .map((t) => {
                const dotColor = statusDotColor(t.status_changed_to);
                return `<div class="timeline-item">
                <div class="timeline-dot ${dotColor}"></div>
                <div class="timeline-content">
                    <div class="timeline-meta"><strong>${escHtml(t.author)}</strong> · ${escHtml(t.time_ago)}</div>
                    <div class="timeline-text">${escHtml(t.comment)}</div>
                    ${t.status_changed_to ? `<span class="badge badge-primary timeline-status">Status → ${escHtml(t.status_changed_to.replace('_', ' '))}</span>` : ''}
                </div>
            </div>`;
            })
            .join('');
    });
}

function statusDotColor(status) {
    const m = {
        resolved: 'success',
        closed: 'gray',
        rejected: 'danger',
        in_progress: '',
        pending: 'warning',
        open: '',
    };
    return m[status] || '';
}

/* ── Delete Request ─────────────────────────────────────────────────── */
function deleteRequest(id) {
    if (!confirm('Delete this request? This action cannot be undone.')) return;
    ajax({ action: 'delete_request', id }, (err, res) => {
        if (err || !res || !res.success) {
            showToast(res?.message || 'Error', 'error');
            return;
        }
        showToast('Request deleted', 'success');
        setTimeout(() => (window.location = `${BASE}/dashboard.php`), 900);
    });
}

/* ── Admin: Update Request Status ────────────────────────────────────── */
function openStatusModal(id, currentStatus, currentAssigned) {
    const modal = $('#statusModal');
    if (!modal) return;
    $('#statusReqId').value = id;
    $('#statusSelect').value = currentStatus || 'pending';
    const assignEl = $('#assignSelect');
    if (assignEl && currentAssigned) assignEl.value = currentAssigned;
    modal.classList.add('show');
}

function submitStatusUpdate() {
    const id = $('#statusReqId').value;
    const status = $('#statusSelect').value;
    const note = $('#statusNote').value.trim();
    const assign = $('#assignSelect') ? $('#assignSelect').value : '';
    if (!note) {
        showToast('Please add a note', 'error');
        return;
    }
    ajax(
        { action: 'update_status', id, status, note, assigned_to: assign },
        (err, res) => {
            closeModal('statusModal');
            if (err || !res || !res.success) {
                showToast(res?.message || 'Error', 'error');
                return;
            }
            showToast('Request updated!', 'success');
            setTimeout(() => location.reload(), 900);
        },
    );
}

/* ── Admin: Delete User ─────────────────────────────────────────────── */
function toggleUserStatus(id, current) {
    const action = current == 1 ? 'Deactivate' : 'Activate';
    if (!confirm(`${action} this user?`)) return;
    ajax({ action: 'toggle_user', id }, (err, res) => {
        if (err || !res || !res.success) {
            showToast(res?.message || 'Error', 'error');
            return;
        }
        showToast(`User ${action.toLowerCase()}d`, 'success');
        setTimeout(() => location.reload(), 800);
    });
}

/* ── Modal Helpers ─────────────────────────────────────────────────── */
function openModal(id) {
    const m = document.getElementById(id);
    m && m.classList.add('show');
}
function closeModal(id) {
    const m = document.getElementById(id);
    m && m.classList.remove('show');
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay'))
        e.target.classList.remove('show');
    const btn = e.target.closest('[data-close-modal]');
    if (btn) closeModal(btn.dataset.closeModal);
});

/* ── Category Card Selection ─────────────────────────────────────────── */
function initCategorySelect() {
    const cards = $$('.category-card');
    const hidden = document.getElementById('selectedCategory');
    if (!cards.length || !hidden) return;
    cards.forEach((card) => {
        card.addEventListener('click', () => {
            cards.forEach((c) => c.classList.remove('selected'));
            card.classList.add('selected');
            hidden.value = card.dataset.catId;
        });
    });
}

/* ── File Upload Preview ─────────────────────────────────────────────── */
function initFileUpload() {
    const area = $('.file-upload-area');
    const input = area && area.querySelector('input[type="file"]');
    const preview = $('#filePreview');
    if (!area || !input) return;

    ['dragover', 'dragleave', 'drop'].forEach((ev) => {
        area.addEventListener(ev, (e) => {
            e.preventDefault();
            area.classList.toggle('drag-over', ev === 'dragover');
            if (ev === 'drop') input.files = e.dataTransfer.files;
            if (ev !== 'dragover') updatePreview(input.files);
        });
    });
    input.addEventListener('change', () => updatePreview(input.files));

    function updatePreview(files) {
        if (!preview || !files.length) return;
        preview.innerHTML = [...files]
            .map(
                (f) =>
                    `<span style="background:var(--primary-light);color:var(--primary);padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;"><i class="fas fa-paperclip"></i> ${escHtml(f.name)}</span>`,
            )
            .join('');
    }
}

/* ── Form Validation ─────────────────────────────────────────────────── */
function initForms() {
    $$('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!validateFormFields(form, true)) {
                e.preventDefault();
            }
        });
    });
}

/* ── Admin Request Search (live) ─────────────────────────────────────── */
function initAdminSearch() {
    const searchInput = $('#adminSearch');
    if (!searchInput) return;
    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const q = searchInput.value.trim();
            const status = $('#filterStatus') ? $('#filterStatus').value : '';
            const priority = $('#filterPriority')
                ? $('#filterPriority').value
                : '';
            const category = $('#filterCategory')
                ? $('#filterCategory').value
                : '';
            ajax(
                { action: 'search_requests', q, status, priority, category },
                (err, res) => {
                    if (err || !res || !res.html) return;
                    const tbody = $('#requestsTableBody');
                    if (tbody) tbody.innerHTML = res.html;
                },
            );
        }, 350);
    });

    ['#filterStatus', '#filterPriority', '#filterCategory'].forEach((sel) => {
        const el = $(sel);
        el &&
            el.addEventListener('change', () =>
                searchInput.dispatchEvent(new Event('input')),
            );
    });
}

/* ── Counter Animation ─────────────────────────────────────────────── */
function animateCounters() {
    $$('[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count);
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 60));
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current.toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 25);
    });
}

/* ── Charts (admin pages) ─────────────────────────────────────────────── */
function initAdminCharts() {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.plugins.legend.labels.boxWidth = 12;

    // Status donut
    const donutEl = document.getElementById('statusChart');
    if (donutEl && donutEl.dataset.values) {
        const vals = JSON.parse(donutEl.dataset.values);
        const lbls = JSON.parse(donutEl.dataset.labels);
        new Chart(donutEl, {
            type: 'doughnut',
            data: {
                labels: lbls,
                datasets: [
                    {
                        data: vals,
                        backgroundColor: [
                            '#f59e0b',
                            '#3b82f6',
                            '#6366f1',
                            '#10b981',
                            '#64748b',
                            '#ef4444',
                        ],
                        borderWidth: 0,
                        hoverOffset: 6,
                    },
                ],
            },
            options: {
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 16, font: { size: 12 } },
                    },
                },
                animation: { animateScale: true },
            },
        });
    }

    // Requests over time line chart
    const lineEl = document.getElementById('requestsChart');
    if (lineEl && lineEl.dataset.values) {
        const vals = JSON.parse(lineEl.dataset.values);
        const lbls = JSON.parse(lineEl.dataset.labels);
        new Chart(lineEl, {
            type: 'line',
            data: {
                labels: lbls,
                datasets: [
                    {
                        label: 'Requests',
                        data: vals,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.1)',
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: { precision: 0 },
                    },
                    x: { grid: { display: false } },
                },
                plugins: { legend: { display: false } },
            },
        });
    }

    // Category bar chart
    const barEl = document.getElementById('categoryChart');
    if (barEl && barEl.dataset.values) {
        const vals = JSON.parse(barEl.dataset.values);
        const lbls = JSON.parse(barEl.dataset.labels);
        new Chart(barEl, {
            type: 'bar',
            data: {
                labels: lbls,
                datasets: [
                    {
                        label: 'Requests',
                        data: vals,
                        backgroundColor: [
                            '#6366f1',
                            '#10b981',
                            '#3b82f6',
                            '#f59e0b',
                            '#8b5cf6',
                            '#ef4444',
                            '#64748b',
                            '#ec4899',
                        ],
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: { precision: 0 },
                    },
                    x: { grid: { display: false } },
                },
                plugins: { legend: { display: false } },
            },
        });
    }

    // Resolution time bar
    const resEl = document.getElementById('resolutionChart');
    if (resEl && resEl.dataset.values) {
        const vals = JSON.parse(resEl.dataset.values);
        const lbls = JSON.parse(resEl.dataset.labels);
        new Chart(resEl, {
            type: 'bar',
            data: {
                labels: lbls,
                datasets: [
                    {
                        label: 'Avg. Resolution (hrs)',
                        data: vals,
                        backgroundColor: 'rgba(16,185,129,.75)',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.05)' },
                    },
                    x: { grid: { display: false } },
                },
                plugins: { legend: { display: false } },
            },
        });
    }
}

/* ── Init All ─────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDropdowns();
    initNotifications();
    initCommentForm();
    initCategorySelect();
    initFileUpload();
    initForms();
    initAdminSearch();
    animateCounters();
    initAdminCharts();
});
