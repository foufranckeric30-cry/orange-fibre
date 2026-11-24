'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });

    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files[0];

            if (!file) return;

            const allowed = ['image/jpeg', 'image/png', 'image/webp'];

            if (!allowed.includes(file.type)) {
                alert('Format accepté : JPEG, PNG ou WEBP.');
                input.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('La photo ne doit pas dépasser 5 Mo.');
                input.value = '';
            }
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', event => {
            if (!confirm(button.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
