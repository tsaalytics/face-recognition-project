import { setupSubmission, setupNavigation, setupDecision, setupEvidencePreview } from './ui';
import { setupFaceEnrollment } from './face-enrollment';
import { setupFaceKiosk } from './face-kiosk';
document.querySelectorAll('[data-face-kiosk]').forEach((form) => setupFaceKiosk(form));
document.querySelectorAll('[data-face-enrollment]').forEach((form) => setupFaceEnrollment(form));
document.querySelectorAll('[data-submit-form]').forEach((form) => setupSubmission(form));
document.querySelectorAll('[data-nav-toggle]').forEach((button) => setupNavigation(button, document.getElementById(button.getAttribute('aria-controls'))));
document.querySelectorAll('[data-decision-form]').forEach((form) => setupDecision(form));
document.querySelectorAll('[data-evidence-image]').forEach((image) => setupEvidencePreview(image, image.parentElement.querySelector('[data-evidence-error]')));
document.querySelectorAll('[data-error-field]').forEach((message, index) => {
 const field = document.getElementById(message.dataset.errorField);
 if (!field || field.type === 'hidden') return;
 message.id = 'field-error-' + index;
 field.setAttribute('aria-invalid','true');
 field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'),message.id].filter(Boolean).join(' '));
});
document.querySelector('[data-validation-summary]')?.focus();
import { setupCorrectionForm } from './correction-form';
import { setupListControls } from './list-controls';

setupListControls(document);
document.querySelectorAll('[data-correction-form]').forEach((form) => setupCorrectionForm(form));

const employeeSelect = document.querySelector('[data-invitation-employee]');
if (employeeSelect) {
 const email = document.querySelector('[data-invitation-email]');
 const syncEmail = () => { email.value = employeeSelect.selectedOptions[0]?.dataset.email || ''; };
 employeeSelect.addEventListener('change',syncEmail);
 document.querySelector('[data-employee-search]').addEventListener('input',(event) => {
  const search=event.target.value.toLocaleLowerCase();
  for(const option of employeeSelect.options) option.hidden=Boolean(option.value) && !option.textContent.toLocaleLowerCase().includes(search);
 });
 syncEmail();
}

const workspace = document.querySelector('[data-workspace]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (workspace && sidebarToggle) {
    const sidebar = document.getElementById(sidebarToggle.getAttribute('aria-controls'));
    const setSidebar = (expanded) => {
        workspace.classList.toggle('sidebar-collapsed', !expanded);
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        if (sidebar) sidebar.inert = !expanded;
    };
    setSidebar(!window.matchMedia('(max-width: 600px)').matches);
    sidebarToggle.addEventListener('click', () => setSidebar(sidebarToggle.getAttribute('aria-expanded') !== 'true'));
    workspace.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebarToggle.getAttribute('aria-expanded') === 'true') {
            setSidebar(false);
            sidebarToggle.focus();
        }
    });
}
