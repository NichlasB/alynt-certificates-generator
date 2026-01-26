import { state, dom, initDom } from './state.js';
import { fetchAvailableFonts } from './fonts.js';
import { parseVariables, ensureDefaults } from './variables.js';
import { updateHiddenInput, cancelPendingSave } from './rest-save.js';
import { renderOverlay } from './overlay.js';
import { renderRow } from './row-builder.js';
import { attachRowDragListeners } from './row-drag.js';
import { initMediaPicker } from './media.js';

const init = () => {
  if (!initDom()) {
    return;
  }

  const renderVariables = () => {
    if (!dom.tableBody) {
      return;
    }
    dom.tableBody.innerHTML = '';
    state.variables.forEach((variable, index) => {
      renderRow(variable, index, renderVariables, renderOverlay);
    });

    attachRowDragListeners(renderVariables);
    renderOverlay();
  };

  const addVariable = () => {
    const next = ensureDefaults({}, state.variables.length);
    state.variables.push(next);
    updateHiddenInput();
    renderVariables();
  };

  fetchAvailableFonts(() => {
    if (state.initialized && state.variables.length > 0) {
      renderVariables();
    }
  });

  if (dom.addButton) {
    dom.addButton.addEventListener('click', () => addVariable());
  }

  initMediaPicker();

  state.variables = parseVariables(dom.variablesInput?.value).map(ensureDefaults);
  renderVariables();

  setTimeout(() => {
    state.initialized = true;
    if (state.migratedOnLoad) {
      updateHiddenInput();
      state.migratedOnLoad = false;
    }
  }, 100);

  if (dom.previewImage) {
    if (!dom.previewImage.complete) {
      dom.previewImage.addEventListener('load', () => {
        if (!state.authoritativeWidth || !state.authoritativeHeight) {
          state.authoritativeWidth = dom.previewImage.naturalWidth;
          state.authoritativeHeight = dom.previewImage.naturalHeight;
          dom.builder.dataset.imageWidth = state.authoritativeWidth;
          dom.builder.dataset.imageHeight = state.authoritativeHeight;
        }
        renderOverlay();
      });
    }
    window.addEventListener('resize', () => renderOverlay());
  }

  const form = document.getElementById('post');
  if (form) {
    form.addEventListener('submit', () => cancelPendingSave());
  }
};

init();
