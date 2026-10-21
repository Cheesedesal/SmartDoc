document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.modalOpen);
        const form = dialog?.querySelector('[data-modal-form]');
        if (!dialog || !form) return;

        form.reset();
        form.action = button.dataset.action;
        const estimateHint = form.querySelector('[data-estimate-hint]');
        const useEstimate = form.querySelector('[data-use-estimate]');
        if (estimateHint && useEstimate) {
            const estimate = button.dataset.estimate;
            const sampleSize = button.dataset.estimateCount;
            useEstimate.hidden = !estimate;
            useEstimate.dataset.estimate = estimate || '';
            estimateHint.textContent = estimate
                ? `Historical median: ${estimate} business days from ${sampleSize} completed requests. This is a recommendation, not an AI decision.`
                : 'Historical estimate will appear here after at least 3 completed requests. This is a transparent median estimate, not an AI decision.';
        }
        const method = form.querySelector('[name="_method"]');
        const formAction = form.querySelector('[name="_form_action"]');
        const formMethod = form.querySelector('[name="_form_method"]');
        const formTarget = form.querySelector('[name="_form_target"]');
        if (method) method.value = button.dataset.method || '';
        if (formAction) formAction.value = button.dataset.action;
        if (formMethod) formMethod.value = button.dataset.method || '';
        if (formTarget) formTarget.value = dialog.id;

        let values = {};
        try {
            values = JSON.parse(button.dataset.values || '{}');
        } catch {
            values = {};
        }
        Object.entries(values).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field) return;
            if (field instanceof RadioNodeList) {
                [...field].forEach((item) => {
                    if (item.type === 'checkbox') item.checked = Boolean(value);
                    else item.checked = String(item.value) === String(value);
                });

                document.querySelectorAll('[data-use-estimate]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const field = button.closest('form').elements.namedItem('processing_days');
                        if (field && button.dataset.estimate) field.value = button.dataset.estimate;
                    });
                });
            } else if (field.type === 'checkbox') {
                field.checked = value === true || value === 1 || value === '1';
            } else if (field.type !== 'password') {
                field.value = value ?? '';
            }
        });
        dialog.querySelector('[data-user-fields] [name="role"]')?.dispatchEvent(new Event('change'));
        dialog.querySelector('[data-user-fields] [name="course_id"]')?.dispatchEvent(new Event('change'));
        dialog.querySelector('[data-user-fields] [name="student_year_id"]')?.dispatchEvent(new Event('change'));
        dialog.showModal();
    });
});

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

const userForm = document.querySelector('[data-user-fields]');
if (userForm) {
    const roleSelect = userForm.querySelector('[name="role"]');
    if (roleSelect) {
        roleSelect.addEventListener('change', () => {
            const isStudent = roleSelect.value === 'student';
            userForm.querySelectorAll('.user-student-fields').forEach((field) => {
                field.hidden = !isStudent;
                field.querySelector('input, select')?.toggleAttribute('required', isStudent);
            });
            userForm.querySelectorAll('.user-worker-fields').forEach((field) => {
                field.hidden = isStudent;
                field.querySelector('input, select')?.toggleAttribute('required', !isStudent);
            });
            const position = userForm.querySelector('[name="position_id"]');
            position?.querySelectorAll('option[data-role]').forEach((option) => {
                const allowed = option.dataset.role === 'both' || option.dataset.role === roleSelect.value;
                option.hidden = !allowed;
                option.disabled = !allowed || (option.dataset.active !== '1' && option.value !== position.value);
            });
            userForm.querySelectorAll('.user-worker-fields select option[data-active="0"]').forEach((option) => {
                option.disabled = option.value !== option.parentElement.value;
            });
        });
        roleSelect.dispatchEvent(new Event('change'));
    }

    const courseSelect = userForm.querySelector('[name="course_id"]');
    const yearSelect = userForm.querySelector('[name="student_year_id"]');
    const sectionSelect = userForm.querySelector('[name="section_id"]');
    const refreshSections = () => {
        if (!yearSelect || !sectionSelect) return;
        const yearId = yearSelect.value;
        const selectedSection = sectionSelect.selectedOptions[0];
        if (selectedSection?.dataset.yearId && selectedSection.dataset.yearId !== yearId) {
            sectionSelect.value = '';
        }
        sectionSelect.querySelectorAll('option[data-year-id]').forEach((option) => {
            const allowed = Boolean(yearId) && option.dataset.yearId === yearId;
            option.hidden = !allowed;
            option.disabled = !allowed || (option.dataset.active !== '1' && option.value !== sectionSelect.value);
        });
        sectionSelect.disabled = !yearId;
    };
    if (courseSelect && yearSelect && sectionSelect) {
        courseSelect.addEventListener('change', () => {
            const courseId = courseSelect.value;
            const selectedYear = yearSelect.selectedOptions[0];
            if (selectedYear?.dataset.courseId && selectedYear.dataset.courseId !== courseId) {
                yearSelect.value = '';
            }
            yearSelect.querySelectorAll('option[data-course-id]').forEach((option) => {
                const allowed = Boolean(courseId) && option.dataset.courseId === courseId;
                option.hidden = !allowed;
                option.disabled = !allowed || (option.dataset.active !== '1' && option.value !== yearSelect.value);
            });
            courseSelect.querySelectorAll('option[data-active="0"]').forEach((option) => {
                option.disabled = option.value !== courseId;
            });
            yearSelect.disabled = !courseId;
            refreshSections();
        });
        yearSelect.addEventListener('change', refreshSections);
        courseSelect.dispatchEvent(new Event('change'));
        yearSelect.dispatchEvent(new Event('change'));
    }
}

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-select-all]').forEach((master) => {
    master.addEventListener('change', () => {
        document.querySelectorAll(`input[type="checkbox"][form="${CSS.escape(master.dataset.selectAll)}"][name="ids[]"]`)
            .forEach((checkbox) => {
                checkbox.checked = master.checked;
            });
    });
});

document.querySelectorAll('[data-profile-preview-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        const preview = document.querySelector('[data-profile-preview]');
        if (preview) {
            const previousUrl = preview.dataset.previewUrl;
            if (previousUrl) URL.revokeObjectURL(previousUrl);
            const url = URL.createObjectURL(file);
            preview.src = url;
            preview.dataset.previewUrl = url;
            return;
        }
        const initial = document.querySelector('[data-profile-initial]');
        if (initial) {
            const image = document.createElement('img');
            image.className = 'profile-photo-large';
            image.alt = 'Selected profile photo preview';
            image.dataset.profilePreview = '';
            image.src = URL.createObjectURL(file);
            image.dataset.previewUrl = image.src;
            initial.replaceWith(image);
        }
    });
});

document.querySelectorAll('.hover-row').forEach((row) => {
    const popup = row.querySelector('.hover-card');
    if (!popup) return;

    const openPopup = () => {
        popup.classList.add('is-open');
        const rowBounds = row.getBoundingClientRect();
        const popupBounds = popup.getBoundingClientRect();
        const left = Math.min(Math.max(8, rowBounds.left), window.innerWidth - popupBounds.width - 8);
        const below = rowBounds.bottom + popupBounds.height + 8 <= window.innerHeight;
        const top = below
            ? rowBounds.bottom + 4
            : Math.max(8, rowBounds.top - popupBounds.height - 4);
        popup.style.left = `${left}px`;
        popup.style.top = `${top}px`;
    };
    const closePopup = () => {
        if (row.contains(document.activeElement)) return;
        popup.classList.remove('is-open');
        popup.style.left = '';
        popup.style.top = '';
    };

    row.addEventListener('pointerenter', openPopup);
    row.addEventListener('pointerleave', closePopup);
    row.addEventListener('focusin', openPopup);
    row.addEventListener('focusout', closePopup);
});

const formTarget = document.querySelector('[data-modal-form] [name="_form_target"]')?.value;
const formWithErrors = formTarget
    ? [...document.querySelectorAll('[data-modal-form]')].find((form) => form.closest('dialog')?.id === formTarget)
    : null;
if (formWithErrors) {
    formWithErrors.action = formWithErrors.querySelector('[name="_form_action"]').value;
    formWithErrors.querySelector('[name="_method"]').value = formWithErrors.querySelector('[name="_form_method"]').value;
    formWithErrors.closest('dialog')?.showModal();
}
