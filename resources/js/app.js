document.querySelectorAll('[data-submit-form]').forEach((form) => {
 form.addEventListener('submit', () => {
  const button = form.querySelector('[data-submit-button]');
  button.disabled = true;
  button.textContent = 'Memproses...';
 });
});

document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
 button.addEventListener('click', () => {
  const navigation = document.getElementById(button.getAttribute('aria-controls'));
  const expanded = button.getAttribute('aria-expanded') === 'true';
  button.setAttribute('aria-expanded', String(!expanded));
  navigation.classList.toggle('hidden', expanded);
 });
});

import { setupCorrectionForm } from './correction-form';
document.querySelectorAll('[data-correction-form]').forEach((form) => setupCorrectionForm(form));
