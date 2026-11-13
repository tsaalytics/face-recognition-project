import { setupSubmission, setupNavigation, setupDecision, setupEvidencePreview } from './ui';
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
