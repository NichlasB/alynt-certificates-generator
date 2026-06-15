import { state, dom } from './state.js';

const getStatusElement = () => {
  const builder = document.getElementById('acg-template-builder');
  if (!builder) {
    return null;
  }
  let status = builder.querySelector('[data-acg-save-status]');
  if (!status) {
    status = document.createElement('p');
    status.dataset.acgSaveStatus = 'true';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    builder.prepend(status);
  }
  return status;
};

const getBuilderElement = () => document.getElementById('acg-template-builder');

const setBuilderBusy = (isBusy) => {
  const builder = getBuilderElement();
  if (!builder) {
    return;
  }
  builder.setAttribute('aria-busy', isBusy ? 'true' : 'false');
};

export const setSaveStatus = (message, type = 'info') => {
  const status = getStatusElement();
  if (!status) {
    return;
  }
  status.className = `notice notice-${type}`;
  status.textContent = message;
};

export const scheduleRestSave = () => {
  if (!state.initialized) {
    return;
  }
  if (!window.acgAdmin || !window.acgAdmin.postId || !window.acgAdmin.restUrl) {
    return;
  }
  clearTimeout(state.saveTimeout);
  state.saveTimeout = setTimeout(() => {
    const i18n = window.acgAdmin?.i18n || {};
    setBuilderBusy(true);
    setSaveStatus(i18n.savingTemplate || 'Saving template variables...');
    fetch(`${window.acgAdmin.restUrl}/templates/${window.acgAdmin.postId}/variables`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.acgAdmin.restNonce || '',
      },
      body: JSON.stringify({ variables: state.variables }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(i18n.saveTemplateFailed || 'Template variables could not be saved.');
        }
        setSaveStatus(i18n.templateSaved || 'Template variables saved.', 'success');
      })
      .catch((error) => {
        setSaveStatus(error.message || i18n.saveTemplateFailed || 'Template variables could not be saved.', 'error');
      })
      .finally(() => {
        setBuilderBusy(false);
      });
  }, 800);
};

export const updateHiddenInput = () => {
  if (!dom.variablesInput) {
    return;
  }
  dom.variablesInput.value = JSON.stringify(state.variables);
  scheduleRestSave();
};

export const cancelPendingSave = () => {
  clearTimeout(state.saveTimeout);
  state.saveTimeout = null;
};
