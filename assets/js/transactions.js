/**
 * transactions.js - Handler untuk modal add/edit & delete transaksi
 * SakuKu - Aplikasi Manajemen Keuangan Mahasiswa
 */

(function() {
    'use strict';

    const modal = document.getElementById('transactionModal');
    const form = document.getElementById('transactionForm');
    const deleteModal = document.getElementById('deleteModal');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formId');
    const formType = document.getElementById('formType');
    const formCategory = document.getElementById('formCategory');
    const categoryOptions = formCategory.querySelectorAll('option[data-type]');

    let deleteId = null;

    // Filter kategori berdasarkan tipe
    function filterCategories(type) {
        categoryOptions.forEach(opt => {
            if (opt.value === '') return;
            opt.hidden = opt.dataset.type !== type;
        });
        
        // Reset ke first valid option
        const firstValid = formCategory.querySelector('option[data-type="' + type + '"]:not([hidden])');
        if (firstValid) {
            formCategory.value = firstValid.value;
        }
    }

    // Open add modal
    window.openAddModal = function(type) {
        form.reset();
        formAction.value = 'create';
        formId.value = '';
        document.querySelector('.modal-title').textContent = type === 'income' ? 'Tambah Pemasukan' : 'Tambah Pengeluaran';
        
        if (type) {
            formType.value = type;
            filterCategories(type);
        }
        
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(() => formType.focus(), 100);
    };

    // Open edit modal
    window.openEditModal = function(tx) {
        form.reset();
        formAction.value = 'update';
        formId.value = tx.id;
        document.querySelector('.modal-title').textContent = 'Edit Transaksi';
        
        formType.value = tx.type;
        filterCategories(tx.type);
        
        // Wait for filter then set category
        setTimeout(() => {
            formCategory.value = tx.category_id;
        }, 0);
        
        document.getElementById('formAmount').value = tx.amount;
        document.getElementById('formDate').value = tx.transaction_date;
        document.getElementById('formNote').value = tx.note || '';
        
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(() => formType.focus(), 100);
    };

    // Close modal
    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        form.reset();
    }

    // Confirm delete
    window.confirmDelete = function(id) {
        deleteId = id;
        deleteModal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    function closeDeleteModal() {
        deleteModal.hidden = true;
        document.body.style.overflow = '';
        deleteId = null;
    }

    // Submit form
    function submitForm() {
        const submitBtn = document.querySelector('#transactionModal .btn-primary');
        const originalText = submitBtn.querySelector('.btn-text').textContent;
        
        setButtonLoading(submitBtn, true);
        clearErrors();

        const formData = new FormData(form);
        formData.append('csrf_token', form.querySelector('[name="csrf_token"]').value);

        fetch('/serius/process/transaction_process.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal();
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message, 'error');
                if (data.field_errors) {
                    Object.entries(data.field_errors).forEach(([field, msg]) => {
                        setFieldError(field, msg);
                    });
                }
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Terjadi kesalahan', 'error');
        })
        .finally(() => {
            setButtonLoading(submitBtn, false);
        });
    }

    // Delete transaction
    deleteBtn.addEventListener('click', function() {
        if (!deleteId) return;
        
        const originalText = deleteBtn.textContent;
        deleteBtn.disabled = true;
        deleteBtn.textContent = 'Menghapus...';

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', deleteId);
        formData.append('csrf_token', document.querySelector('#transactionModal [name="csrf_token"]').value);

        fetch('/serius/process/transaction_process.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeDeleteModal();
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Terjadi kesalahan', 'error');
        })
        .finally(() => {
            deleteBtn.disabled = false;
            deleteBtn.textContent = originalText;
        });
    });

    // Event listeners
    formType.addEventListener('change', function() {
        filterCategories(this.value);
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        // handled above
    });

    // Close on overlay click
    [modal, deleteModal].forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) {
                if (this === modal) closeModal();
                else closeDeleteModal();
            }
        });
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!modal.hidden) closeModal();
            if (!deleteModal.hidden) closeDeleteModal();
        }
    });

    // Real-time validation
    form.querySelectorAll('input[required], select[required]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('input-error');
            } else {
                this.classList.remove('input-error');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('input-error') && this.value.trim()) {
                this.classList.remove('input-error');
                const errorEl = document.getElementById(this.id + 'Error');
                if (errorEl) errorEl.textContent = '';
            }
        });
    });

    // Amount input formatting
    document.getElementById('formAmount').addEventListener('input', function() {
        // Remove non-digits
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Helpers
    function setButtonLoading(button, loading) {
        const btnText = button.querySelector('.btn-text') || button;
        const btnLoader = button.querySelector('.btn-loader');
        
        if (loading) {
            button.disabled = true;
            if (btnText !== button) btnText.style.display = 'none';
            if (btnLoader) btnLoader.hidden = false;
        } else {
            button.disabled = false;
            if (btnText !== button) btnText.style.display = 'inline';
            if (btnLoader) btnLoader.hidden = true;
        }
    }

    function clearErrors() {
        form.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        form.querySelectorAll('input, select').forEach(el => el.classList.remove('input-error'));
    }

    function setFieldError(fieldId, message) {
        const errorEl = document.getElementById(fieldId + 'Error');
        const inputEl = document.getElementById(fieldId);
        if (errorEl) errorEl.textContent = message;
        if (inputEl) inputEl.classList.add('input-error');
    }

    function showToast(message, type = 'info') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = `
            <svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                ${type === 'success' 
                    ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                    : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
                }
            </svg>
            <span class="toast-message">${escapeHtml(message)}</span>
            <button class="toast-close" aria-label="Tutup">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
        container.appendChild(toast);

        setTimeout(() => toast.remove(), 4000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();