import { state, dom } from './state.js';
import { setSaveStatus, updateHiddenInput } from './rest-save.js';
import { i18n, sprintfNumber } from './i18n.js';

export const handleDragStart = (event) => {
  const row = event.target.closest('tr[data-var-id]');
  if (!row) {
    return;
  }
  state.draggedRow = row;
  state.draggedIndex = parseInt(row.dataset.varIndex, 10);
  row.classList.add('acg-dragging');
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', row.dataset.varId);
};

const focusReorderButton = (variableId, direction) => {
  window.requestAnimationFrame(() => {
    const row = dom.tableBody?.querySelector(`tr[data-var-id="${variableId}"]`);
    const button = row?.querySelector(`[data-reorder-direction="${direction}"]:not(:disabled)`);
    if (button) {
      button.focus();
    }
  });
};

export const moveVariable = (variableId, direction, renderVariables) => {
  const currentIndex = state.variables.findIndex((variable) => variable.id === variableId);
  if (currentIndex < 0) {
    return;
  }

  const nextIndex = currentIndex + direction;
  if (nextIndex < 0 || nextIndex >= state.variables.length) {
    return;
  }

  const [movedVariable] = state.variables.splice(currentIndex, 1);
  state.variables.splice(nextIndex, 0, movedVariable);

  updateHiddenInput();
  renderVariables();
  setSaveStatus(sprintfNumber(i18n.variableMoved, nextIndex + 1));
  focusReorderButton(variableId, direction < 0 ? 'up' : 'down');
};

export const handleDragOver = (event) => {
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';
  const row = event.target.closest('tr[data-var-id]');
  if (row && row !== state.draggedRow) {
    dom.tableBody.querySelectorAll('tr.acg-drag-over').forEach((r) => r.classList.remove('acg-drag-over'));
    row.classList.add('acg-drag-over');
  }
};

export const handleDragLeave = (event) => {
  const row = event.target.closest('tr[data-var-id]');
  if (row) {
    row.classList.remove('acg-drag-over');
  }
};

export const handleDrop = (event, renderVariables) => {
  event.preventDefault();
  const targetRow = event.target.closest('tr[data-var-id]');
  if (!targetRow || targetRow === state.draggedRow || state.draggedIndex < 0) {
    return;
  }

  const targetIndex = parseInt(targetRow.dataset.varIndex, 10);
  if (Number.isNaN(targetIndex) || targetIndex === state.draggedIndex) {
    return;
  }

  const [movedVariable] = state.variables.splice(state.draggedIndex, 1);
  state.variables.splice(targetIndex, 0, movedVariable);

  updateHiddenInput();
  renderVariables();
  setSaveStatus(sprintfNumber(i18n.variableMoved, targetIndex + 1));
};

export const handleDragEnd = () => {
  if (state.draggedRow) {
    state.draggedRow.classList.remove('acg-dragging');
  }
  dom.tableBody.querySelectorAll('tr.acg-drag-over').forEach((r) => r.classList.remove('acg-drag-over'));
  state.draggedRow = null;
  state.draggedIndex = -1;
};

export const attachRowDragListeners = (renderVariables) => {
  dom.tableBody.querySelectorAll('tr[data-var-id]').forEach((row) => {
    row.addEventListener('dragstart', handleDragStart);
    row.addEventListener('dragover', handleDragOver);
    row.addEventListener('dragleave', handleDragLeave);
    row.addEventListener('drop', (e) => handleDrop(e, renderVariables));
    row.addEventListener('dragend', handleDragEnd);
  });
};
