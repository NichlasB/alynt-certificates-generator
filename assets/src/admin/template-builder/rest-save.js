import { state, dom } from './state.js';

export const scheduleRestSave = () => {
  if (!state.initialized) {
    return;
  }
  if (!window.acgAdmin || !window.acgAdmin.postId || !window.acgAdmin.restUrl) {
    return;
  }
  clearTimeout(state.saveTimeout);
  state.saveTimeout = setTimeout(() => {
    fetch(`${window.acgAdmin.restUrl}/templates/${window.acgAdmin.postId}/variables`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.acgAdmin.restNonce || '',
      },
      body: JSON.stringify({ variables: state.variables }),
    }).catch(() => {});
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
